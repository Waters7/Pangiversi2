<?php

namespace App\Http\Controllers;

use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use Illuminate\View\View;

/**
 * Halaman publik yang dituju QR code pada dokumen perjalanan dinas.
 *
 * Sengaja terbuka tanpa autentikasi agar pemeriksa dokumen fisik dapat
 * memastikan keabsahan tanda tangan, namun hanya menampilkan data legalitas
 * seperlunya: nomor pengajuan perjalanan dinas, kodenya, dan tanggalnya.
 *
 * Ada tiga jenis kode yang dikenali:
 * PPK-  tanda tangan Pejabat Pembuat Komitmen pada daftar pengeluaran riil,
 * PLK-  konfirmasi pelaksana atas nominal yang dibayarkan,
 * BND-  konfirmasi bendahara bahwa pembayaran sudah lunas,
 * LPK-  tanda tangan pelaksana pada laporan perjalanan dinas,
 * PIM-  konfirmasi pimpinan atas laporan perjalanan dinas.
 */
class VerifikasiController extends Controller
{
    public function __invoke(string $kode): View
    {
        // Laporan perjalanan dinas punya halaman sendiri: isinya berbeda dari
        // dokumen keuangan — nomor surat, tanggal perjalanan, dan siapa yang
        // menandatangani di tiap sisi.
        if ($laporan = $this->laporanPerjadin($kode)) {
            return view('verifikasi.laporan', [
                'laporan' => $laporan,
                'usulan' => $laporan->usulan,
                'sisi' => $laporan->kode_pimpinan === $kode ? 'pimpinan' : 'pelaksana',
                'kode' => $kode,
            ]);
        }

        if ($nominatif = $this->tandaTanganNominatif($kode)) {
            return view('verifikasi.tampil', [
                'daftar' => null,
                'keuangan' => null,
                'nominatif' => $nominatif,
                'jenis' => 'nominatif',
                'kode' => $kode,
            ]);
        }

        foreach (['ppk', 'pelaksana', 'bendahara'] as $jenis) {
            $daftar = match ($jenis) {
                'ppk' => $this->tandaTanganPpk($kode),
                'pelaksana' => $this->tandaTanganPelaksana($kode),
                default => null,
            };

            if ($daftar) {
                return view('verifikasi.tampil', [
                    'daftar' => $daftar,
                    'keuangan' => null,
                    'nominatif' => null,
                    'jenis' => $jenis,
                    'kode' => $kode,
                ]);
            }

            if ($jenis === 'bendahara' && $keuangan = $this->konfirmasiBendahara($kode)) {
                return view('verifikasi.tampil', [
                    'daftar' => null,
                    'keuangan' => $keuangan,
                    'nominatif' => null,
                    'jenis' => 'bendahara',
                    'kode' => $kode,
                ]);
            }
        }

        return view('verifikasi.tampil', [
            'daftar' => null,
            'keuangan' => null,
            'nominatif' => null,
            'jenis' => null,
            'kode' => $kode,
        ]);
    }

    /**
     * Tanda tangan PPK pada daftar nominatif. Berbeda dari yang lain,
     * daftar ini menaungi beberapa usulan sekaligus — seluruhnya yang
     * berbagi satu nomor surat tugas.
     */
    private function tandaTanganNominatif(string $kode): ?DaftarNominatif
    {
        return DaftarNominatif::with('ppk:id,nama,nip')
            ->where('kode_verifikasi', $kode)
            ->whereNotNull('ditandatangani_at')
            ->first();
    }

    private function tandaTanganPpk(string $kode): ?DaftarRiil
    {
        return DaftarRiil::with(['usulan:id,no_usulan,created_at', 'ppk:id,nama,nip'])
            ->where('kode_verifikasi', $kode)
            ->sudahDitandatangani()
            ->first();
    }

    private function tandaTanganPelaksana(string $kode): ?DaftarRiil
    {
        return DaftarRiil::with(['usulan:id,no_usulan,created_at', 'peserta:id,nama,nip'])
            ->where('kode_konfirmasi', $kode)
            ->whereNotNull('disetujui_pegawai_at')
            ->first();
    }

    /**
     * Tanda tangan pada laporan perjalanan dinas: kode pelaksana hanya sah
     * selama laporannya masih di meja pimpinan atau sudah dikonfirmasi,
     * kode pimpinan hanya sah selama konfirmasinya belum dicabut.
     */
    private function laporanPerjadin(string $kode): ?LaporanPerjadin
    {
        return LaporanPerjadin::with(['usulan.user:id,nama,nip', 'usulan.spd', 'pimpinan:id,nama,nip,jabatan'])
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('kode_pelaksana', $kode)->whereNotNull('dikirim_at'))
                ->orWhere(fn ($w) => $w->where('kode_pimpinan', $kode)->whereNotNull('dikonfirmasi_at')))
            ->first();
    }

    private function konfirmasiBendahara(string $kode): ?Keuangan
    {
        return Keuangan::with(['usulan:id,no_usulan,created_at'])
            ->where('kode_konfirmasi_bayar', $kode)
            ->lunas()
            ->first();
    }
}
