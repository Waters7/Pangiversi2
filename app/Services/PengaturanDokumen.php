<?php

namespace App\Services;

use App\Enums\DokumenCetak;
use App\Models\Pengaturan;

/**
 * Membaca dan menyimpan pengaturan tampilan dokumen cetak.
 *
 * Nilainya disimpan sebagai satu baris JSON per dokumen pada tabel
 * pengaturan, sehingga tidak perlu tabel baru dan tetap ikut cache yang
 * sudah ada. Dokumen yang belum pernah diatur memakai bawaannya, jadi
 * pemasangan lama tetap mencetak persis seperti sebelumnya.
 */
class PengaturanDokumen
{
    /** @var array<string, TampilanDokumen> */
    private array $hafalan = [];

    public static function kunci(DokumenCetak $dokumen): string
    {
        return 'dokumen_cetak_'.$dokumen->value;
    }

    public function untuk(DokumenCetak $dokumen): TampilanDokumen
    {
        return $this->hafalan[$dokumen->value] ??= new TampilanDokumen($dokumen, $this->baca($dokumen));
    }

    /**
     * Simpan pengaturan baru; nilai yang tidak dikirim tetap memakai bawaan.
     *
     * @param  array{kertas?: string, huruf?: float|string, lebar_kop?: int|string, elemen?: array<string, mixed>, teks?: array<string, string|null>}  $isi
     */
    public function simpan(DokumenCetak $dokumen, array $isi): void
    {
        Pengaturan::simpan([
            self::kunci($dokumen) => json_encode($this->rapikan($dokumen, $isi), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        unset($this->hafalan[$dokumen->value]);
    }

    /**
     * Bersihkan kiriman formulir menjadi bentuk yang disimpan: nilai di luar
     * batas dijepit, teks kosong kembali ke bawaan, elemen yang tidak
     * dicentang berarti dimatikan.
     *
     * @param  array<string, mixed>  $isi
     * @return array{kertas: string, huruf: float, lebar_kop: int, elemen: array<string, bool>, teks: array<string, string>}
     */
    private function rapikan(DokumenCetak $dokumen, array $isi): array
    {
        $bawaan = $dokumen->bawaan();

        $elemen = [];
        foreach (array_keys($dokumen->elemen()) as $kode) {
            $elemen[$kode] = (bool) ($isi['elemen'][$kode] ?? false);
        }

        $teks = [];
        foreach ($dokumen->teks() as $kode => $tentang) {
            $nilai = trim((string) ($isi['teks'][$kode] ?? ''));
            $teks[$kode] = $nilai !== '' ? $nilai : $tentang['bawaan'];
        }

        $kertas = (string) ($isi['kertas'] ?? $bawaan['kertas']);

        return [
            'kertas' => array_key_exists($kertas, KertasCetak::PILIHAN) ? $kertas : $bawaan['kertas'],
            'huruf' => max(6, min(16, (float) ($isi['huruf'] ?? $bawaan['huruf']))),
            'lebar_kop' => max(40, min(100, (int) ($isi['lebar_kop'] ?? $bawaan['lebar_kop']))),
            'elemen' => $elemen,
            'teks' => $teks,
        ];
    }

    /**
     * Pasang setelan hanya untuk permintaan ini — dipakai pratinjau agar
     * perubahan yang belum disimpan dapat dilihat lebih dahulu.
     *
     * @param  array<string, mixed>  $isi
     */
    public function sementara(DokumenCetak $dokumen, array $isi): void
    {
        $this->hafalan[$dokumen->value] = new TampilanDokumen($dokumen, $this->rapikan($dokumen, $isi));
    }

    /** Kembalikan satu dokumen ke tampilan bawaannya. */
    public function kembalikanBawaan(DokumenCetak $dokumen): void
    {
        Pengaturan::simpan([self::kunci($dokumen) => '']);

        unset($this->hafalan[$dokumen->value]);
    }

    public function sudahDiatur(DokumenCetak $dokumen): bool
    {
        return Pengaturan::ambil(self::kunci($dokumen), '') !== '';
    }

    public function lupakan(): void
    {
        $this->hafalan = [];
    }

    /**
     * @return array{kertas: string, huruf: float, lebar_kop: int, elemen: array<string, bool>, teks: array<string, string>}
     */
    private function baca(DokumenCetak $dokumen): array
    {
        $bawaan = $dokumen->bawaan();
        $tersimpan = json_decode(Pengaturan::ambil(self::kunci($dokumen), ''), true);

        if (! is_array($tersimpan)) {
            return $bawaan;
        }

        // Elemen dan teks digabung per kunci supaya elemen baru yang muncul
        // pada pembaruan aplikasi langsung memakai bawaannya, bukan hilang.
        return [
            'kertas' => is_string($tersimpan['kertas'] ?? null) ? $tersimpan['kertas'] : $bawaan['kertas'],
            'huruf' => (float) ($tersimpan['huruf'] ?? $bawaan['huruf']),
            'lebar_kop' => (int) ($tersimpan['lebar_kop'] ?? $bawaan['lebar_kop']),
            'elemen' => array_map(
                fn ($kode) => (bool) ($tersimpan['elemen'][$kode] ?? $bawaan['elemen'][$kode]),
                array_combine(array_keys($bawaan['elemen']), array_keys($bawaan['elemen'])),
            ),
            'teks' => array_map(
                fn ($kode) => (string) ($tersimpan['teks'][$kode] ?? $bawaan['teks'][$kode]),
                array_combine(array_keys($bawaan['teks']), array_keys($bawaan['teks'])),
            ),
        ];
    }
}
