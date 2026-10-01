<?php

namespace App\Services;

use App\Enums\BerkasLpj;
use App\Enums\JenisPerjadin;
use App\Models\Pengaturan;
use App\Models\Usulan;

/**
 * Berkas pertanggungjawaban yang wajib untuk tiap jalur pengajuan.
 *
 * Disimpan sebagai daftar kode pada tabel pengaturan — satu baris per jalur
 * — dan dibaca sekali per permintaan. Jalur yang belum pernah diatur memakai
 * susunan bawaan, jadi pemasangan lama menagih berkas yang sama persis
 * seperti sebelum pengaturan ini ada.
 */
class PengaturanBerkasLpj
{
    /** @var array<string, list<BerkasLpj>> */
    private array $hafalan = [];

    public static function kunci(JenisPerjadin $jenis): string
    {
        return 'berkas_lpj_'.$jenis->value;
    }

    /**
     * @return list<BerkasLpj>
     */
    public function untuk(JenisPerjadin $jenis): array
    {
        return $this->hafalan[$jenis->value] ??= $this->baca($jenis);
    }

    /**
     * @return list<BerkasLpj>
     */
    public function untukUsulan(Usulan $usulan): array
    {
        return $this->untuk($usulan->jalur());
    }

    public function wajib(Usulan $usulan, BerkasLpj $berkas): bool
    {
        return in_array($berkas, $this->untukUsulan($usulan), true);
    }

    /**
     * @param  array<int|string, mixed>  $kode
     */
    public function simpan(JenisPerjadin $jenis, array $kode): void
    {
        $terpilih = collect(BerkasLpj::cases())
            ->filter(fn (BerkasLpj $berkas) => in_array($berkas->value, $kode, true) || ($kode[$berkas->value] ?? false))
            ->map(fn (BerkasLpj $berkas) => $berkas->value)
            ->values()
            ->all();

        Pengaturan::simpan([self::kunci($jenis) => json_encode($terpilih)]);

        unset($this->hafalan[$jenis->value]);
    }

    public function kembalikanBawaan(JenisPerjadin $jenis): void
    {
        Pengaturan::simpan([self::kunci($jenis) => '']);

        unset($this->hafalan[$jenis->value]);
    }

    public function sudahDiatur(JenisPerjadin $jenis): bool
    {
        return Pengaturan::ambil(self::kunci($jenis), '') !== '';
    }

    public function lupakan(): void
    {
        $this->hafalan = [];
    }

    /**
     * @return list<BerkasLpj>
     */
    private function baca(JenisPerjadin $jenis): array
    {
        $tersimpan = json_decode(Pengaturan::ambil(self::kunci($jenis), ''), true);

        if (! is_array($tersimpan)) {
            return BerkasLpj::bawaanUntuk($jenis);
        }

        // Urutannya mengikuti enum, bukan urutan tersimpan, supaya checklist
        // selalu tampil dengan susunan yang sama.
        return collect(BerkasLpj::cases())
            ->filter(fn (BerkasLpj $berkas) => in_array($berkas->value, $tersimpan, true))
            ->values()
            ->all();
    }
}
