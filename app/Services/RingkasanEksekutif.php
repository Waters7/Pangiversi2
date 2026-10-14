<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\Keuangan;
use App\Models\PaguAkun;
use App\Models\PesertaUsulan;
use App\Models\TahunAnggaran;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Angka-angka dashboard eksekutif: realisasi anggaran dan pergerakan pegawai
 * selama satu tahun anggaran.
 *
 * Dipisahkan dari controller supaya halaman web dan API membaca hitungan yang
 * sama persis. Bila keduanya menghitung sendiri-sendiri, angka di layar cepat
 * atau lambat akan berbeda dari angka yang diambil aplikasi lain.
 */
class RingkasanEksekutif
{
    /**
     * Nama bulan singkat sebagai sumbu grafik.
     *
     * @var list<string>
     */
    public const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __construct(private EkspresiTanggal $tanggal) {}

    /**
     * Tahun anggaran yang dipakai bila permintaan tidak menyebutkannya.
     */
    public function tahunBawaan(): int
    {
        return TahunAnggaran::aktif()?->tahun ?? now()->year;
    }

    /**
     * @return Collection<int, int>
     */
    public function tahunTersedia(): Collection
    {
        return TahunAnggaran::orderByDesc('tahun')->pluck('tahun');
    }

    /**
     * Realisasi biaya per bulan keberangkatan, dipecah per kategori perjadin.
     *
     * @return Collection<string, list<float>>
     */
    public function realisasiPerKategori(int $tahun): Collection
    {
        $baris = Keuangan::query()
            ->join('usulan', 'usulan.id', '=', 'keuangan.id_usulan')
            ->leftJoin('kategori_perjadin', 'kategori_perjadin.id', '=', 'usulan.id_kategori_perjadin')
            ->whereIn('usulan.status', $this->statusBerlaku())
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->selectRaw('COALESCE(kategori_perjadin.nama, ?) as kategori', ['Tanpa Kategori'])
            ->selectRaw($this->tanggal->bulan('usulan.tanggal_mulai').' as bulan')
            ->selectRaw('SUM(keuangan.total) as jumlah')
            ->groupBy('kategori', 'bulan')
            ->get();

        // Semua kategori yang pernah dipakai tetap muncul agar grafik konsisten.
        $kategori = $baris->pluck('kategori')->unique()->sort()->values();

        return $kategori->mapWithKeys(function (string $nama) use ($baris) {
            $perBulan = array_fill(0, 12, 0.0);

            foreach ($baris->where('kategori', $nama) as $item) {
                $perBulan[(int) $item->bulan - 1] = (float) $item->jumlah;
            }

            return [$nama => $perBulan];
        });
    }

    /**
     * @param  Collection<string, list<float>>  $perKategori
     * @return list<float>
     */
    public function jumlahkanPerBulan(Collection $perKategori): array
    {
        $total = array_fill(0, 12, 0.0);

        foreach ($perKategori as $nilaiBulanan) {
            foreach ($nilaiBulanan as $indeks => $nilai) {
                $total[$indeks] += $nilai;
            }
        }

        return $total;
    }

    public function totalRealisasi(int $tahun): float
    {
        return (float) Keuangan::query()
            ->join('usulan', 'usulan.id', '=', 'keuangan.id_usulan')
            ->whereIn('usulan.status', $this->statusBerlaku())
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->sum('keuangan.total');
    }

    /**
     * Pagu dan realisasi tiap kode akun, beserta rekapnya per sumber dana.
     *
     * Realisasi sebuah perjalanan dinas dibebankan ke akun yang ditetapkan
     * pada daftar nominatif surat tugasnya. Yang belum berakun dikumpulkan
     * tersendiri, supaya jumlah seluruhnya tetap sama dengan total realisasi.
     * Akun tanpa pagu tetap tampil bila sudah ada realisasinya.
     *
     * @return array{
     *     pagu: float,
     *     akun: Collection<int, array{kode: string, nama: string, sumber: ?string, sumber_nama: ?string, pagu: float, realisasi: float, sisa: float, persen: ?float, melebihi: bool}>,
     *     sumber: Collection<int, array{kode: ?string, nama: string, pagu: float, realisasi: float, sisa: float, persen: ?float, porsi: ?float}>,
     *     belum_berakun: float
     * }
     */
    public function anggaranPerAkun(int $tahun): array
    {
        $realisasi = Keuangan::query()
            ->join('usulan', 'usulan.id', '=', 'keuangan.id_usulan')
            ->leftJoin('daftar_nominatif', 'daftar_nominatif.no_tugas', '=', 'usulan.no_tugas')
            ->whereIn('usulan.status', $this->statusBerlaku())
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->groupBy('daftar_nominatif.id_akun_pembiayaan')
            ->selectRaw('daftar_nominatif.id_akun_pembiayaan as id_akun, SUM(keuangan.total) as jumlah')
            ->get()
            ->mapWithKeys(fn ($baris) => [(int) $baris->id_akun => (float) $baris->jumlah]);

        $pagu = PaguAkun::with('sumberDana')
            ->whereHas('tahunAnggaran', fn ($query) => $query->where('tahun', $tahun))
            ->get()
            ->keyBy('id_akun_pembiayaan');

        $akun = AkunPembiayaan::whereIn('id', $pagu->keys()->merge($realisasi->keys()->filter()))
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get()
            ->map(function (AkunPembiayaan $akun) use ($pagu, $realisasi): array {
                $baris = $pagu->get($akun->id);
                $nilaiPagu = (float) ($baris?->pagu ?? 0);
                $nilaiRealisasi = (float) ($realisasi[$akun->id] ?? 0);

                return [
                    'kode' => $akun->kode,
                    'nama' => $akun->nama,
                    'sumber' => $baris?->sumberDana?->kode,
                    'sumber_nama' => $baris?->sumberDana?->nama,
                    'pagu' => $nilaiPagu,
                    'realisasi' => $nilaiRealisasi,
                    'sisa' => $nilaiPagu - $nilaiRealisasi,
                    'persen' => $nilaiPagu > 0 ? round($nilaiRealisasi / $nilaiPagu * 100, 2) : null,
                    'melebihi' => $nilaiRealisasi > $nilaiPagu,
                ];
            })
            ->values();

        $totalPagu = (float) $akun->sum('pagu');

        $sumber = $akun->groupBy(fn (array $baris) => $baris['sumber'] ?? '')
            ->map(function (Collection $baris, string $kode) use ($totalPagu): array {
                $nilaiPagu = (float) $baris->sum('pagu');
                $nilaiRealisasi = (float) $baris->sum('realisasi');

                return [
                    'kode' => $kode !== '' ? $kode : null,
                    'nama' => $baris->first()['sumber_nama'] ?? 'Tanpa sumber dana',
                    'pagu' => $nilaiPagu,
                    'realisasi' => $nilaiRealisasi,
                    'sisa' => $nilaiPagu - $nilaiRealisasi,
                    'persen' => $nilaiPagu > 0 ? round($nilaiRealisasi / $nilaiPagu * 100, 2) : null,
                    'porsi' => $totalPagu > 0 ? round($nilaiPagu / $totalPagu * 100, 2) : null,
                ];
            })
            ->sortByDesc('pagu')
            ->values();

        return [
            'pagu' => $totalPagu,
            'akun' => $akun,
            'sumber' => $sumber,
            'belum_berakun' => (float) ($realisasi[0] ?? 0),
        ];
    }

    /**
     * Jumlah pegawai yang berangkat tiap bulan — satu orang dihitung sekali
     * per perjalanan yang diikutinya.
     *
     * @return list<int>
     */
    public function pegawaiPerBulan(int $tahun): array
    {
        $baris = PesertaUsulan::query()
            ->join('usulan', 'usulan.id', '=', 'peserta_usulan.id_usulan')
            ->whereIn('usulan.status', $this->statusBerlaku())
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->selectRaw($this->tanggal->bulan('usulan.tanggal_mulai').' as bulan')
            ->selectRaw('COUNT(peserta_usulan.id) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan');

        $perBulan = array_fill(0, 12, 0);

        foreach ($baris as $bulan => $jumlah) {
            $perBulan[(int) $bulan - 1] = (int) $jumlah;
        }

        return $perBulan;
    }

    /**
     * Rekap keberangkatan per unit kerja: jumlah orang, jumlah perjalanan,
     * dan sebarannya tiap bulan.
     *
     * @return Collection<int, array{unit: string, orang: int, perjalanan: int, perBulan: list<int>, biaya: float}>
     */
    public function pegawaiPerUnit(int $tahun): Collection
    {
        $baris = PesertaUsulan::query()
            ->join('usulan', 'usulan.id', '=', 'peserta_usulan.id_usulan')
            ->leftJoin('users', 'users.id', '=', 'peserta_usulan.id_user')
            ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'users.id_unit')
            ->leftJoin('keuangan', 'keuangan.id_usulan', '=', 'usulan.id')
            ->whereIn('usulan.status', $this->statusBerlaku())
            ->whereYear('usulan.tanggal_mulai', $tahun)
            ->selectRaw('COALESCE(unit_kerja.nama, ?) as unit', ['Tanpa Unit'])
            ->selectRaw($this->tanggal->bulan('usulan.tanggal_mulai').' as bulan')
            ->selectRaw('COUNT(peserta_usulan.id) as orang')
            ->selectRaw('COUNT(DISTINCT usulan.id) as perjalanan')
            ->selectRaw('COALESCE(SUM(keuangan.total), 0) as biaya')
            ->groupBy('unit', 'bulan')
            ->get();

        return $baris
            ->groupBy('unit')
            ->map(function ($rows, string $unit) {
                $perBulan = array_fill(0, 12, 0);

                foreach ($rows as $row) {
                    $perBulan[(int) $row->bulan - 1] = (int) $row->orang;
                }

                return [
                    'unit' => $unit,
                    'orang' => (int) $rows->sum('orang'),
                    'perjalanan' => (int) $rows->sum('perjalanan'),
                    'perBulan' => $perBulan,
                    'biaya' => (float) $rows->sum('biaya'),
                ];
            })
            ->sortByDesc('orang')
            ->values();
    }

    /**
     * Pegawai yang perjalanannya sudah berlaku dan belum berangkat.
     */
    public function akanBerangkat(): int
    {
        return PesertaUsulan::whereHas('usulan', function ($query): void {
            $query->where('status', StatusUsulan::Disetujui->value)
                ->whereDate('tanggal_mulai', '>', today());
        })->count();
    }

    /**
     * Pegawai yang sedang dalam masa perjalanan dinas hari ini.
     */
    public function sedangBerjalan(): int
    {
        return PesertaUsulan::whereHas('usulan', function ($query): void {
            $query->where('status', StatusUsulan::Disetujui->value)
                ->whereDate('tanggal_mulai', '<=', today())
                ->whereDate('tanggal_selesai', '>=', today());
        })->count();
    }

    /**
     * Perjalanan yang sudah berakhir namun dokumen pertanggungjawabannya
     * belum lengkap sehingga usulannya belum bisa ditutup.
     */
    public function belumMelapor(): int
    {
        $wajib = Usulan::DOKUMEN_LPJ_WAJIB;

        return Usulan::where('status', StatusUsulan::Disetujui->value)
            ->whereDate('tanggal_selesai', '<', today())
            ->where(function ($query) use ($wajib): void {
                $query->whereDoesntHave('dokumen');

                foreach ($wajib as $kolom) {
                    $query->orWhereHas('dokumen', fn ($q) => $q->whereNull($kolom)->orWhere($kolom, ''));
                }
            })
            ->count();
    }

    /**
     * Perjalanan yang sudah pasti berlangsung — hanya inilah yang dihitung
     * sebagai realisasi, agar draf dan usulan yang batal tidak ikut terbaca.
     *
     * @return list<string>
     */
    private function statusBerlaku(): array
    {
        return [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value];
    }
}
