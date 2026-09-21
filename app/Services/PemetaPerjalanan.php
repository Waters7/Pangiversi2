<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menyusun peta kota tujuan perjalanan dinas: tiap kota beserta jumlah
 * perjalanan, pegawai yang berangkat, dan koordinatnya.
 *
 * "Dalam kota dan sekitarnya" berarti tujuan di Sulawesi Utara: Manado
 * beserta kota dan kabupaten di sekelilingnya — dikenali dari master Lokasi
 * Tujuan (klasifikasi dalam kota atau provinsinya) atau daftar kota bawaan.
 * Tujuan yang tidak dikenal keduanya mengikuti kategori perjadin usulannya.
 */
class PemetaPerjalanan
{
    public const DALAM_KOTA = 'dalam-kota';

    public const LUAR_KOTA = 'luar-kota';

    /** @var list<string> */
    public const JENIS = [self::DALAM_KOTA, self::LUAR_KOTA];

    public function __construct(private KoordinatKota $koordinat) {}

    /**
     * @return array{
     *     kota: list<array<string, mixed>>,
     *     tanpaKoordinat: list<array{nama: string, perjalanan: int}>,
     *     ringkasan: array{kota: int, perjalanan: int, pegawai: int},
     * }
     */
    public function susun(string $jenis, ?int $tahun = null): array
    {
        $usulan = $this->usulanTerpilih($jenis, $tahun);

        $kelompok = $usulan->groupBy(fn (Usulan $u) => $this->kunciKota($u));

        $kota = [];
        $tanpaKoordinat = [];

        foreach ($kelompok as $kelompokUsulan) {
            /** @var Usulan $pertama */
            $pertama = $kelompokUsulan->first();
            $lokasi = $pertama->lokasiTujuan;
            $nama = $lokasi?->nama ?? Str::of($pertama->lokasi)->before(',')->squish()->title()->toString();
            $titik = $this->titik($lokasi?->lintang, $lokasi?->bujur, $nama);

            $pegawai = $kelompokUsulan
                ->flatMap(fn (Usulan $u) => $u->peserta->map(fn ($p) => $p->id_user ?: mb_strtolower($p->nama)))
                ->unique();

            if ($titik === null) {
                $tanpaKoordinat[] = ['nama' => $nama, 'perjalanan' => $kelompokUsulan->count()];

                continue;
            }

            $kota[] = [
                'nama' => $nama,
                'provinsi' => $lokasi?->provinsi,
                'lintang' => $titik[0],
                'bujur' => $titik[1],
                'perjalanan' => $kelompokUsulan->count(),
                'pegawai' => $pegawai->count(),
                'terakhir' => $this->tanggal($kelompokUsulan->max('tanggal_mulai'))?->translatedFormat('d M Y'),
                'daftar' => $kelompokUsulan
                    ->sortByDesc('tanggal_mulai')
                    ->take(8)
                    ->map(fn (Usulan $u) => [
                        'no_usulan' => $u->no_usulan,
                        'pegawai' => $u->peserta->pluck('nama')->implode(', ') ?: ($u->user?->nama ?? '—'),
                        'tanggal' => $this->rentang($u),
                        'kegiatan' => $u->kegiatan?->nama ?? $u->instansi,
                        'status' => StatusUsulan::dari($u->status)->label(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($kota, fn ($a, $b) => [$b['perjalanan'], $a['nama']] <=> [$a['perjalanan'], $b['nama']]);
        usort($tanpaKoordinat, fn ($a, $b) => [$b['perjalanan'], $a['nama']] <=> [$a['perjalanan'], $b['nama']]);

        return [
            'kota' => $kota,
            'tanpaKoordinat' => $tanpaKoordinat,
            'ringkasan' => [
                'kota' => count($kota) + count($tanpaKoordinat),
                'perjalanan' => $usulan->count(),
                'pegawai' => $usulan
                    ->flatMap(fn (Usulan $u) => $u->peserta->map(fn ($p) => $p->id_user ?: mb_strtolower($p->nama)))
                    ->unique()
                    ->count(),
            ],
        ];
    }

    /**
     * Tahun-tahun yang punya perjalanan, terbaru lebih dulu.
     *
     * @return list<int>
     */
    public function tahunTersedia(): array
    {
        return Usulan::query()
            ->whereIn('status', $this->statusTampil())
            ->whereNotNull('tanggal_mulai')
            ->pluck('tanggal_mulai')
            ->map(fn ($tanggal) => (int) $this->tanggal($tanggal)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Usulan>
     */
    private function usulanTerpilih(string $jenis, ?int $tahun): Collection
    {
        return Usulan::query()
            ->with(['lokasiTujuan', 'kategoriPerjadin', 'kegiatan', 'user:id,nama', 'peserta:id,id_usulan,id_user,nama'])
            ->whereIn('status', $this->statusTampil())
            ->where(fn ($q) => $q->whereNotNull('lokasi')->where('lokasi', '!=', ''))
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->get()
            ->filter(fn (Usulan $u) => $this->dalamKota($u) === ($jenis === self::DALAM_KOTA))
            ->values();
    }

    /**
     * Status yang berarti perjalanannya nyata atau sedang diproses; draf dan
     * yang ditolak tidak dipetakan.
     *
     * @return list<string>
     */
    private function statusTampil(): array
    {
        return [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value, ...StatusUsulan::nilaiMenunggu()];
    }

    private function dalamKota(Usulan $usulan): bool
    {
        $lokasi = $usulan->lokasiTujuan;
        $nama = $lokasi?->nama ?? $usulan->lokasi;

        if ($lokasi?->jenis === 'dalam_kota' || Str::contains(mb_strtolower((string) $lokasi?->provinsi), 'sulawesi utara')) {
            return true;
        }

        if ($lokasi?->provinsi || $this->koordinat->cari($nama) !== null) {
            return $this->koordinat->sekitarManado($nama);
        }

        // Kota yang tidak dikenal: percaya kategori perjadin usulannya.
        return $usulan->kategoriPerjadin?->dalamKota() ?? false;
    }

    private function kunciKota(Usulan $usulan): string
    {
        $nama = $usulan->lokasiTujuan?->nama ?? $usulan->lokasi;

        return Str::of($nama)->before(',')->lower()->squish()->toString();
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function titik(?float $lintang, ?float $bujur, string $nama): ?array
    {
        if ($lintang !== null && $bujur !== null) {
            return [(float) $lintang, (float) $bujur];
        }

        return $this->koordinat->cari($nama);
    }

    /** Kolom tanggal usulan tersimpan sebagai teks; dibaca sebagai Carbon di sini. */
    private function tanggal(mixed $nilai): ?Carbon
    {
        return blank($nilai) ? null : Carbon::parse($nilai);
    }

    private function rentang(Usulan $usulan): string
    {
        $mulai = $this->tanggal($usulan->tanggal_mulai);
        $selesai = $this->tanggal($usulan->tanggal_selesai);

        if (! $mulai) {
            return '—';
        }

        if (! $selesai || $selesai->isSameDay($mulai)) {
            return $mulai->translatedFormat('d M Y');
        }

        // Bulan yang sama cukup disebut sekali: "01 – 02 Nov 2026".
        $awal = $mulai->isSameMonth($selesai) ? $mulai->format('d') : $mulai->translatedFormat('d M');

        return $awal.' – '.$selesai->translatedFormat('d M Y');
    }
}
