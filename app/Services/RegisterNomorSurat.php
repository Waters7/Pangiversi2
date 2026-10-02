<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\PesertaUsulan;
use App\Models\SpdPelaksana;
use App\Models\SuratPerjalananDinas;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Register nomor SPD dan surat tugas yang tercatat di PANGI, per bulan.
 *
 * Arsiparis mencocokkannya dengan buku agenda surat keluar: tiap nomor yang
 * dipakai aplikasi semestinya tercatat di sana, dan sebaliknya.
 *
 * Satu baris mewakili SPD seorang pelaksana — tiap pelaksana memegang nomor
 * SPD-nya sendiri — lengkap dengan nomor SPD bertanda tangan yang disalin
 * pada usulannya dan nomor surat tugasnya. Usulan yang tidak berangkat dari
 * SPD aplikasi (supervisi dalam kota, misalnya) ikut dicantumkan dengan
 * nomor yang dicatat pada usulannya.
 *
 * @phpstan-type Baris array{
 *     tanggal: Carbon, sumber: string, no_spd: ?string, no_spd_ttd: ?string, no_tugas: ?string,
 *     nama: string, nip: ?string, tujuan: ?string, berangkat: ?Carbon, kembali: ?Carbon,
 *     maksud: ?string, no_usulan: ?string, status: ?string
 * }
 */
class RegisterNomorSurat
{
    public const SUMBER_SPD = 'spd';

    public const SUMBER_USULAN = 'usulan';

    private const LEBAR = [5, 12, 22, 28, 28, 28, 20, 26, 12, 12, 18, 30];

    public function __construct(private EkspresiTanggal $ekspresi) {}

    /**
     * Baris register pada satu tahun, atau satu bulannya, urut tanggal.
     *
     * @return Collection<int, Baris>
     */
    public function baris(int $tahun, ?int $bulan = null, ?string $cari = null): Collection
    {
        $semua = $this->dariSpd($tahun, $bulan)->concat($this->dariUsulan($tahun, $bulan));

        if (filled($cari)) {
            $kunci = mb_strtolower(trim((string) $cari));
            $semua = $semua->filter(fn (array $baris) => str_contains($this->teksCari($baris), $kunci));
        }

        return $semua
            ->sortBy(fn (array $baris) => $baris['tanggal']->format('Ymd').'|'.($baris['no_spd'] ?? $baris['no_tugas'] ?? ''))
            ->values();
    }

    /**
     * Tahun yang punya nomor tercatat, terbaru lebih dulu.
     *
     * @return list<int>
     */
    public function tahunTersedia(): array
    {
        $dariSpd = SuratPerjalananDinas::query()
            ->selectRaw($this->ekspresi->tahun('tanggal_surat').' as tahun')
            ->distinct()
            ->pluck('tahun');

        $dariUsulan = Usulan::query()
            ->whereNull('id_spd')
            ->selectRaw($this->ekspresi->tahun('created_at').' as tahun')
            ->distinct()
            ->pluck('tahun');

        return $dariSpd->concat($dariUsulan)
            ->map(fn ($tahun) => (int) $tahun)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Susun register sebagai berkas Excel, dikelompokkan per bulan.
     *
     * @param  Collection<int, Baris>  $baris
     */
    public function susunXlsx(Collection $baris, string $judulPeriode): PenulisXlsx
    {
        $xlsx = new PenulisXlsx(mb_substr('Nomor Surat '.$judulPeriode, 0, 31));
        $xlsx->lebarKolom(self::LEBAR);

        $xlsx->baris([['isi' => 'REGISTER NOMOR SPD DAN SURAT TUGAS', 'gaya' => 'judul']]);
        $xlsx->baris([['isi' => 'POLITEKNIK KESEHATAN KEMENKES MANADO', 'gaya' => 'subjudul']]);
        $xlsx->baris([['isi' => 'Periode '.$judulPeriode.' — untuk dicocokkan dengan buku agenda arsip', 'gaya' => 'subjudul']]);
        $xlsx->gabung('A1:L1')->gabung('A2:L2')->gabung('A3:L3');
        $xlsx->tinggiBaris(1, 20);

        $xlsx->barisKosong();
        $xlsx->baris([
            'No.', 'Tanggal', 'No. SPD (Aplikasi)', 'No. SPD Bertanda Tangan', 'No. Surat Tugas', 'Nama Pelaksana',
            'NIP', 'Tujuan', 'Berangkat', 'Kembali', 'No. Usulan', 'Keterangan',
        ], 'kepala');
        $xlsx->tinggiBaris($xlsx->jumlahBaris(), 24);
        $xlsx->bekukan($xlsx->jumlahBaris());

        if ($baris->isEmpty()) {
            $xlsx->baris([['isi' => 'Tidak ada nomor surat pada periode ini.', 'gaya' => 'teks']]);

            return $xlsx;
        }

        $urutan = 0;

        foreach ($this->perBulan($baris) as $bulan => $isi) {
            $xlsx->baris([[
                'isi' => mb_strtoupper(Carbon::parse($bulan.'-01')->translatedFormat('F Y')).' — '.$isi->count().' nomor',
                'gaya' => 'total-teks',
            ]]);
            $xlsx->gabung('A'.$xlsx->jumlahBaris().':L'.$xlsx->jumlahBaris());

            foreach ($isi->sortBy(fn (array $b) => $b['tanggal']->format('Ymd'))->values() as $item) {
                $xlsx->baris([
                    ['isi' => ++$urutan, 'gaya' => 'teks-tengah', 'angka' => true],
                    ['isi' => $item['tanggal']->format('d/m/Y'), 'gaya' => 'teks-tengah'],
                    ['isi' => $item['no_spd'] ?? '—', 'gaya' => 'teks'],
                    ['isi' => $item['no_spd_ttd'] ?? '—', 'gaya' => 'teks'],
                    ['isi' => $item['no_tugas'] ?? '—', 'gaya' => 'teks'],
                    ['isi' => $item['nama'], 'gaya' => 'teks'],
                    ['isi' => $item['nip'] ?? '—', 'gaya' => 'teks'],
                    ['isi' => $item['tujuan'] ?? '—', 'gaya' => 'teks'],
                    ['isi' => $item['berangkat']?->format('d/m/Y') ?? '—', 'gaya' => 'teks-tengah'],
                    ['isi' => $item['kembali']?->format('d/m/Y') ?? '—', 'gaya' => 'teks-tengah'],
                    ['isi' => $item['no_usulan'] ?? '—', 'gaya' => 'teks-tengah'],
                    ['isi' => $this->keterangan($item), 'gaya' => 'teks'],
                ]);
            }
        }

        $xlsx->barisKosong(2);
        $xlsx->baris([['isi' => 'REKAP PER BULAN', 'gaya' => 'tebal']]);
        $xlsx->baris(['Bulan', 'Nomor SPD', 'Surat Tugas', 'Belum Bertanda Tangan'], 'kepala');

        foreach ($this->perBulan($baris) as $bulan => $isi) {
            $xlsx->baris([
                ['isi' => Carbon::parse($bulan.'-01')->translatedFormat('F Y'), 'gaya' => 'teks'],
                ['isi' => $isi->whereNotNull('no_spd')->count(), 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $isi->pluck('no_tugas')->filter()->unique()->count(), 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $isi->where('sumber', self::SUMBER_SPD)->whereNull('no_spd_ttd')->count(), 'gaya' => 'teks-tengah', 'angka' => true],
            ]);
        }

        return $xlsx;
    }

    /**
     * Kelompokkan per bulan ("2026-10"), terbaru lebih dulu.
     *
     * @param  Collection<int, Baris>  $baris
     * @return Collection<string, Collection<int, Baris>>
     */
    public function perBulan(Collection $baris): Collection
    {
        return $baris
            ->groupBy(fn (array $item) => $item['tanggal']->format('Y-m'))
            ->sortKeysDesc();
    }

    /**
     * Keterangan singkat: asal nomor, dan status usulannya bila ada.
     *
     * @param  Baris  $baris
     */
    public function keterangan(array $baris): string
    {
        if ($baris['sumber'] === self::SUMBER_USULAN) {
            return 'Dicatat pada usulan (tanpa SPD aplikasi)'.($baris['status'] ? ' · '.$baris['status'] : '');
        }

        if ($baris['no_usulan'] === null) {
            return 'SPD belum dipakai pada usulan';
        }

        return $baris['no_spd_ttd'] === null
            ? 'Nomor SPD bertanda tangan belum dicatat · '.$baris['status']
            : (string) $baris['status'];
    }

    /**
     * @return Collection<int, Baris>
     */
    private function dariSpd(int $tahun, ?int $bulan): Collection
    {
        $spd = SuratPerjalananDinas::query()
            ->with('pelaksana')
            ->whereYear('tanggal_surat', $tahun)
            ->when($bulan, fn ($query) => $query->whereMonth('tanggal_surat', $bulan))
            ->get();

        // Usulan yang berangkat dari SPD ini — satu per pelaksana, sebab tiap
        // pelaksana mengajukan usulannya sendiri dengan nomor SPD-nya.
        $usulanPerSpd = Usulan::query()
            ->whereIn('id_spd', $spd->pluck('id'))
            ->where('status', '!=', StatusUsulan::Draft->value)
            ->get(['id', 'id_spd', 'id_user', 'no_usulan', 'no_spd', 'no_tugas', 'status'])
            ->groupBy('id_spd');

        return $spd->flatMap(function (SuratPerjalananDinas $surat) use ($usulanPerSpd): array {
            $usulan = $usulanPerSpd->get($surat->id, collect());
            $sendiri = $surat->pelaksana->count() === 1;

            return $surat->pelaksana->map(function (SpdPelaksana $orang) use ($surat, $usulan, $sendiri): array {
                $milik = $usulan->firstWhere('id_user', $orang->id_user) ?? ($sendiri ? $usulan->first() : null);

                return [
                    'tanggal' => $surat->tanggal_surat,
                    'sumber' => self::SUMBER_SPD,
                    'no_spd' => $orang->nomor_surat,
                    'no_spd_ttd' => filled($milik?->no_spd) ? $milik->no_spd : null,
                    'no_tugas' => $surat->no_tugas ?: ($milik?->no_tugas ?: null),
                    'nama' => (string) $orang->nama,
                    'nip' => $orang->nip,
                    'tujuan' => $surat->tempat_tujuan,
                    'berangkat' => $surat->tanggal_berangkat,
                    'kembali' => $surat->tanggal_kembali,
                    'maksud' => $surat->maksud,
                    'no_usulan' => $milik?->no_usulan,
                    'status' => $milik ? StatusUsulan::dari($milik->status)->label() : null,
                ];
            })->all();
        })->values();
    }

    /**
     * Usulan bernomor yang tidak berangkat dari SPD aplikasi.
     *
     * @return Collection<int, Baris>
     */
    private function dariUsulan(int $tahun, ?int $bulan): Collection
    {
        return Usulan::query()
            ->with('peserta:id,id_usulan,nama,nip', 'user:id,nama,nip')
            ->whereNull('id_spd')
            ->where('status', '!=', StatusUsulan::Draft->value)
            ->whereYear('created_at', $tahun)
            ->when($bulan, fn ($query) => $query->whereMonth('created_at', $bulan))
            ->get()
            ->map(function (Usulan $usulan): array {
                $orang = $usulan->peserta->isNotEmpty()
                    ? $usulan->peserta
                    : collect([new PesertaUsulan(['nama' => $usulan->user?->nama, 'nip' => $usulan->user?->nip])]);

                return [
                    'tanggal' => $usulan->created_at,
                    'sumber' => self::SUMBER_USULAN,
                    'no_spd' => null,
                    'no_spd_ttd' => filled($usulan->no_spd) ? $usulan->no_spd : null,
                    'no_tugas' => filled($usulan->no_tugas) ? $usulan->no_tugas : null,
                    'nama' => $orang->pluck('nama')->filter()->implode(', ') ?: '—',
                    'nip' => $orang->pluck('nip')->filter()->implode(', ') ?: null,
                    'tujuan' => collect([$usulan->lokasi, $usulan->instansi])->filter()->implode(' — ') ?: null,
                    'berangkat' => filled($usulan->tanggal_mulai) ? Carbon::parse($usulan->tanggal_mulai) : null,
                    'kembali' => filled($usulan->tanggal_selesai) ? Carbon::parse($usulan->tanggal_selesai) : null,
                    'maksud' => $usulan->uraian,
                    'no_usulan' => $usulan->no_usulan,
                    'status' => StatusUsulan::dari($usulan->status)->label(),
                ];
            })
            ->values();
    }

    /**
     * @param  Baris  $baris
     */
    private function teksCari(array $baris): string
    {
        return mb_strtolower(implode(' ', array_filter([
            $baris['no_spd'], $baris['no_spd_ttd'], $baris['no_tugas'], $baris['nama'],
            $baris['nip'], $baris['tujuan'], $baris['no_usulan'],
        ])));
    }
}
