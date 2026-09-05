<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\TahunAnggaran;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Merangkum data perjalanan dinas menjadi angka-angka ringkas.
 *
 * Dipakai bersama oleh wawasan AI dan alat yang dipanggil agen, sehingga
 * keduanya membaca kenyataan yang sama dan tidak ada perhitungan ganda.
 */
class RingkasanDataPerjadin
{
    /**
     * @var list<string>
     */
    private const STATUS_TERPAKAI = [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value];

    public function __construct(private EkspresiTanggal $tanggal) {}

    /**
     * Potret menyeluruh satu tahun anggaran.
     *
     * @return array<string, mixed>
     */
    public function potret(int $tahun): array
    {
        $anggaran = TahunAnggaran::firstWhere('tahun', $tahun);

        return [
            'tahun' => $tahun,
            'pagu' => (float) ($anggaran?->pagu ?? 0),
            'realisasi' => $this->realisasi($tahun),
            'realisasi_per_bulan' => $this->realisasiPerBulan($tahun),
            'realisasi_per_kategori' => $this->realisasiPerKategori($tahun),
            'perjalanan_per_bulan' => $this->perjalananPerBulan($tahun),
            'unit_teraktif' => $this->unitTeraktif($tahun),
            'tujuan_terbanyak' => $this->tujuanTerbanyak($tahun),
            'jumlah_usulan' => $this->jumlahPerStatus($tahun),
            'belum_melapor' => $this->belumMelapor(),
            'menunggu_validasi' => Usulan::menungguKeputusan()->count(),
            'rata_biaya_per_perjalanan' => $this->rataBiaya($tahun),
        ];
    }

    public function realisasi(int $tahun): float
    {
        return (float) $this->keuanganTahun($tahun)->sum('keuangan.total');
    }

    /**
     * @return array<string, float>
     */
    public function realisasiPerBulan(int $tahun): array
    {
        $baris = $this->keuanganTahun($tahun)
            ->selectRaw($this->tanggal->bulan('usulan.tanggal_mulai').' as bulan')
            ->selectRaw('SUM(keuangan.total) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan');

        return $this->petakanBulan($baris);
    }

    /**
     * @return array<string, float>
     */
    public function realisasiPerKategori(int $tahun): array
    {
        return $this->keuanganTahun($tahun)
            ->leftJoin('kategori_perjadin', 'kategori_perjadin.id', '=', 'usulan.id_kategori_perjadin')
            ->selectRaw('COALESCE(kategori_perjadin.nama, ?) as kategori', ['Tanpa Kategori'])
            ->selectRaw('SUM(keuangan.total) as jumlah')
            ->groupBy('kategori')
            ->orderByDesc('jumlah')
            ->pluck('jumlah', 'kategori')
            ->map(fn ($nilai) => (float) $nilai)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function perjalananPerBulan(int $tahun): array
    {
        $baris = PesertaUsulan::query()
            ->join('usulan', 'usulan.id', '=', 'peserta_usulan.id_usulan')
            ->whereIn('usulan.status', self::STATUS_TERPAKAI)
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->selectRaw($this->tanggal->bulan('usulan.tanggal_mulai').' as bulan')
            ->selectRaw('COUNT(peserta_usulan.id) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan');

        return array_map('intval', $this->petakanBulan($baris));
    }

    /**
     * @return array<string, int>
     */
    public function unitTeraktif(int $tahun, int $batas = 5): array
    {
        return Usulan::query()
            ->join('users', 'users.id', '=', 'usulan.id_user')
            ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'users.id_unit')
            ->whereIn('usulan.status', self::STATUS_TERPAKAI)
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->selectRaw('COALESCE(unit_kerja.nama, ?) as unit', ['Tanpa Unit'])
            ->selectRaw('COUNT(usulan.id) as jumlah')
            ->groupBy('unit')
            ->orderByDesc('jumlah')
            ->limit($batas)
            ->pluck('jumlah', 'unit')
            ->map(fn ($nilai) => (int) $nilai)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function tujuanTerbanyak(int $tahun, int $batas = 5): array
    {
        return Usulan::whereIn('status', self::STATUS_TERPAKAI)
            ->whereYear('tanggal_mulai', $tahun)
            ->selectRaw('lokasi')
            ->selectRaw('COUNT(id) as jumlah')
            ->groupBy('lokasi')
            ->orderByDesc('jumlah')
            ->limit($batas)
            ->pluck('jumlah', 'lokasi')
            ->map(fn ($nilai) => (int) $nilai)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function jumlahPerStatus(int $tahun): array
    {
        $jumlah = Usulan::whereYear('tanggal_mulai', $tahun)
            ->selectRaw('status')
            ->selectRaw('COUNT(id) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $hasil = [];

        foreach (StatusUsulan::cases() as $status) {
            $hasil[$status->label()] = (int) ($jumlah[$status->value] ?? 0);
        }

        return $hasil;
    }

    /**
     * Perjalanan yang sudah berakhir namun berkas pertanggungjawabannya
     * belum lengkap.
     *
     * @return list<array{no_usulan: string, pegawai: string, tujuan: string, selesai: string, hari_terlambat: int}>
     */
    public function belumMelapor(int $batas = 20): array
    {
        return Usulan::with('user:id,nama')
            ->where('status', StatusUsulan::Disetujui->value)
            ->whereDate('tanggal_selesai', '<', today())
            ->where(function ($query): void {
                $query->whereDoesntHave('dokumen');

                foreach (Usulan::DOKUMEN_LPJ_WAJIB as $kolom) {
                    $query->orWhereHas('dokumen', fn ($q) => $q->whereNull($kolom)->orWhere($kolom, ''));
                }
            })
            ->orderBy('tanggal_selesai')
            ->limit($batas)
            ->get()
            ->map(fn (Usulan $usulan) => [
                'no_usulan' => $usulan->no_usulan,
                'pegawai' => $usulan->user?->nama ?? '—',
                'tujuan' => $usulan->lokasi,
                'selesai' => $usulan->tanggal_selesai,
                // Selisih dihitung dari tanggal selesai ke hari ini agar bernilai positif.
                'hari_terlambat' => (int) Carbon::parse($usulan->tanggal_selesai)->diffInDays(today()),
            ])
            ->all();
    }

    public function rataBiaya(int $tahun): float
    {
        $baris = $this->keuanganTahun($tahun)->selectRaw('AVG(keuangan.total) as rata')->value('rata');

        return (float) ($baris ?? 0);
    }

    /**
     * @return Builder<Keuangan>
     */
    private function keuanganTahun(int $tahun)
    {
        return Keuangan::query()
            ->join('usulan', 'usulan.id', '=', 'keuangan.id_usulan')
            ->whereIn('usulan.status', self::STATUS_TERPAKAI)
            ->whereYear('usulan.tanggal_mulai', $tahun);
    }

    /**
     * @param  Collection<int, mixed>  $baris
     * @return array<string, float>
     */
    private function petakanBulan($baris): array
    {
        $nama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $hasil = [];

        foreach ($nama as $indeks => $bulan) {
            $hasil[$bulan] = (float) ($baris[$indeks + 1] ?? 0);
        }

        return $hasil;
    }
}
