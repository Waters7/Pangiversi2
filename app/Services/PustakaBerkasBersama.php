<?php

namespace App\Services;

use App\Models\Dokumen;
use App\Models\TiketPerjadin;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas pertanggungjawaban yang dipakai bersama beberapa pelaksana.
 *
 * Satu invoice tiket, satu bill hotel, atau satu invoice biaya penyelenggaraan
 * kerap mencakup seluruh rombongan. Yang mengisi belakangan cukup menyebut
 * nomornya: berkas yang sudah diunggah rekannya disalin. Harga dan nominal
 * tidak ikut — dibagi menurut jumlah orang dalam satu invoice — begitu pula
 * boarding pass, yang dimiliki tiap penumpang sendiri.
 */
class PustakaBerkasBersama
{
    public const JENIS_TIKET = 'tiket';

    public const JENIS_HOTEL = 'hotel';

    public const JENIS_PENYELENGGARAAN = 'penyelenggaraan';

    /**
     * Tiket bernomor sama milik usulan lain yang invoicenya tersimpan,
     * yang paling dulu dicatat.
     */
    public function tiket(?string $nomor, ?int $kecualiUsulan = null): ?TiketPerjadin
    {
        $kunci = PustakaSuratTugas::kunci($nomor);

        if ($kunci === '') {
            return null;
        }

        return TiketPerjadin::query()
            ->whereRaw(PustakaSuratTugas::kunciSql('nomor_tiket').' = ?', [$kunci])
            ->whereNotNull('invoice')
            ->where('invoice', '!=', '')
            ->when($kecualiUsulan, fn ($query) => $query->where('id_usulan', '!=', $kecualiUsulan))
            ->with('usulan.user')
            ->oldest('id')
            ->get()
            ->first(fn (TiketPerjadin $tiket) => $this->ada($tiket->invoice));
    }

    /**
     * Dokumen usulan lain yang bill hotel atau kuitansinya tersimpan untuk
     * nomor transaksi hotel yang sama.
     */
    public function hotel(?string $nomorTransaksi, ?int $kecualiUsulan = null): ?Dokumen
    {
        return $this->dokumen('bill_hotel_no_transaksi', $nomorTransaksi, ['bill_hotel', 'kwintasi'], $kecualiUsulan);
    }

    /** Dokumen usulan lain yang bukti bayarnya tersimpan untuk nomor invoice penyelenggaraan yang sama. */
    public function penyelenggaraan(?string $nomorInvoice, ?int $kecualiUsulan = null): ?Dokumen
    {
        return $this->dokumen('penyelenggaraan_invoice', $nomorInvoice, ['penyelenggaraan_bukti'], $kecualiUsulan);
    }

    /**
     * Salin berkas ke folder unggahan, supaya mengganti atau menghapus berkas
     * pelaksana yang satu tidak ikut menghapus milik rekannya.
     */
    public function salin(?string $asal, string $folder): ?string
    {
        if (! $this->ada($asal)) {
            return null;
        }

        $tujuan = $folder.'/'.Str::uuid().'.'.pathinfo((string) $asal, PATHINFO_EXTENSION);

        return Storage::disk('public')->copy($asal, $tujuan) ? $tujuan : null;
    }

    /**
     * Ringkasan hasil pemeriksaan untuk tombol Cek pada formulir.
     *
     * @return array{ditemukan: bool, pesan: string, berkas?: list<array{label: string, tautan: string}>, data?: array<string, string>}
     */
    public function ringkas(string $jenis, ?string $nomor, ?int $kecualiUsulan = null): array
    {
        $sumber = match ($jenis) {
            self::JENIS_TIKET => $this->tiket($nomor, $kecualiUsulan),
            self::JENIS_HOTEL => $this->hotel($nomor, $kecualiUsulan),
            self::JENIS_PENYELENGGARAAN => $this->penyelenggaraan($nomor, $kecualiUsulan),
        };

        $namaNomor = match ($jenis) {
            self::JENIS_TIKET => 'nomor tiket',
            self::JENIS_HOTEL => 'nomor transaksi hotel',
            self::JENIS_PENYELENGGARAAN => 'nomor invoice',
        };

        if (! $sumber) {
            return [
                'ditemukan' => false,
                'pesan' => 'Berkas dengan '.$namaNomor.' ini belum pernah diunggah. Unggah berkasnya.',
            ];
        }

        $usulan = $sumber->usulan;
        $oleh = $usulan?->user?->nama ? ' oleh '.$usulan->user->nama : '';
        $rujukan = $usulan ? ' (usulan '.$usulan->no_usulan.')' : '';

        return match ($jenis) {
            self::JENIS_TIKET => [
                'ditemukan' => true,
                'pesan' => 'Tiket bernomor ini sudah dicatat'.$oleh.$rujukan.'. Invoice-nya dipakai otomatis, dan kota asal, kota tujuan, '
                    .'serta kode booking sudah diisikan. Harga tiket dan boarding pass tetap diisi masing-masing.',
                'berkas' => [['label' => 'Invoice tiket', 'tautan' => route('berkas.lihat', $sumber->invoice)]],
                'data' => [
                    'kota_asal' => (string) $sumber->kota_asal,
                    'kota_tujuan' => (string) $sumber->kota_tujuan,
                    'kode_booking' => (string) $sumber->kode_booking,
                ],
            ],
            self::JENIS_HOTEL => [
                'ditemukan' => true,
                'pesan' => 'Bill hotel atau kuitansi bernomor transaksi ini sudah diunggah'.$oleh.$rujukan.'. Berkasnya dipakai otomatis; '
                    .'nominal tetap diisi masing-masing.',
                'berkas' => array_values(array_filter([
                    $this->ada($sumber->bill_hotel) ? ['label' => 'Bill hotel', 'tautan' => route('berkas.lihat', $sumber->bill_hotel)] : null,
                    $this->ada($sumber->kwintasi) ? ['label' => 'Kuitansi penyelenggara / hotel', 'tautan' => route('berkas.lihat', $sumber->kwintasi)] : null,
                ])),
            ],
            self::JENIS_PENYELENGGARAAN => [
                'ditemukan' => true,
                'pesan' => 'Bukti bayar bernomor invoice ini sudah diunggah'.$oleh.$rujukan.'. Berkasnya dipakai otomatis; '
                    .'nominal tetap diisi masing-masing.',
                'berkas' => [['label' => 'Bukti bayar biaya penyelenggaraan', 'tautan' => route('berkas.lihat', $sumber->penyelenggaraan_bukti)]],
            ],
        };
    }

    /**
     * @param  list<string>  $kolomBerkas
     */
    private function dokumen(string $kolomNomor, ?string $nomor, array $kolomBerkas, ?int $kecualiUsulan): ?Dokumen
    {
        $kunci = PustakaSuratTugas::kunci($nomor);

        if ($kunci === '') {
            return null;
        }

        return Dokumen::query()
            ->whereRaw(PustakaSuratTugas::kunciSql($kolomNomor).' = ?', [$kunci])
            ->where(function ($query) use ($kolomBerkas) {
                foreach ($kolomBerkas as $kolom) {
                    $query->orWhere(fn ($query) => $query->whereNotNull($kolom)->where($kolom, '!=', ''));
                }
            })
            ->when($kecualiUsulan, fn ($query) => $query->where('id_usulan', '!=', $kecualiUsulan))
            ->with('usulan.user')
            ->oldest('id')
            ->get()
            ->first(fn (Dokumen $dokumen) => collect($kolomBerkas)->contains(fn (string $kolom) => $this->ada($dokumen->{$kolom})));
    }

    public function ada(?string $berkas): bool
    {
        return filled($berkas) && Storage::disk('public')->exists($berkas);
    }
}
