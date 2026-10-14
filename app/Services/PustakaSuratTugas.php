<?php

namespace App\Services;

use App\Models\Dokumen;
use App\Models\SuratPerjalananDinas;
use App\Models\Usulan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas surat tugas yang sudah diunggah, dicari menurut nomornya.
 *
 * Satu surat tugas kerap dipakai beberapa pelaksana sekaligus. Yang datang
 * belakangan cukup mengetik nomornya: berkas yang sudah diunggah rekannya
 * — pada usulan maupun SPD — disalin, bukan diunggah ulang. Nomor
 * dibandingkan tanpa membedakan huruf besar-kecil dan spasi.
 *
 * @phpstan-type Berkas array{
 *     path: string, no_tugas: string, pengunggah: ?string, waktu: ?Carbon,
 *     sumber: string, rujukan: ?string, tautan: ?string
 * }
 */
class PustakaSuratTugas
{
    public const SUMBER_USULAN = 'usulan';

    public const SUMBER_SPD = 'spd';

    /** Ekspresi SQL kunci pembanding nomor, selaras dengan kunci(). */
    private const KUNCI_SQL = "REPLACE(LOWER(TRIM(%s)), ' ', '')";

    /** Kunci pembanding nomor surat tugas: huruf kecil tanpa spasi. */
    public static function kunci(?string $nomor): string
    {
        return str_replace(' ', '', mb_strtolower(trim((string) $nomor)));
    }

    /** Ekspresi SQL yang menghasilkan kunci() dari sebuah kolom. */
    public static function kunciSql(string $kolom): string
    {
        return sprintf(self::KUNCI_SQL, $kolom);
    }

    /**
     * Berkas surat tugas yang tercatat untuk satu nomor, terlama lebih dulu.
     * Hanya berkas yang benar-benar ada di penyimpanan.
     *
     * @return Collection<int, Berkas>
     */
    public function berkas(?string $nomor, ?int $kecualiUsulan = null, ?int $kecualiSpd = null): Collection
    {
        $kunci = self::kunci($nomor);

        if ($kunci === '') {
            return collect();
        }

        $dariUsulan = Dokumen::query()
            ->whereNotNull('surat_tugas')
            ->where('surat_tugas', '!=', '')
            ->whereHas('usulan', fn ($query) => $query
                ->whereRaw(sprintf(self::KUNCI_SQL, 'usulan.no_tugas').' = ?', [$kunci])
                ->when($kecualiUsulan, fn ($query) => $query->where('usulan.id', '!=', $kecualiUsulan)))
            ->with('usulan.user')
            ->get()
            ->map(fn (Dokumen $dokumen) => $this->dariDokumen($dokumen));

        $dariSpd = SuratPerjalananDinas::query()
            ->whereNotNull('surat_tugas')
            ->where('surat_tugas', '!=', '')
            ->whereRaw(sprintf(self::KUNCI_SQL, 'no_tugas').' = ?', [$kunci])
            ->when($kecualiSpd, fn ($query) => $query->where('id', '!=', $kecualiSpd))
            ->with('pembuat')
            ->get()
            ->map(fn (SuratPerjalananDinas $spd) => $this->dariSpd($spd));

        $disk = Storage::disk('public');

        return $dariUsulan->concat($dariSpd)
            ->filter(fn (array $berkas) => $disk->exists($berkas['path']))
            // Berkas asli lebih dulu, baru salinannya.
            ->sortBy(fn (array $berkas) => [PustakaBerkasBersama::salinan($berkas['path']) ? 1 : 0, $berkas['waktu']?->getTimestamp() ?? 0])
            ->values();
    }

    /**
     * Berkas asal — yang paling dulu diunggah — untuk satu nomor, beserta
     * banyaknya berkas lain bernomor sama.
     *
     * @return (Berkas&array{jumlah: int})|null
     */
    public function temukan(?string $nomor, ?int $kecualiUsulan = null, ?int $kecualiSpd = null): ?array
    {
        $berkas = $this->berkas($nomor, $kecualiUsulan, $kecualiSpd);

        return $berkas->isEmpty() ? null : $berkas->first() + ['jumlah' => $berkas->count()];
    }

    /**
     * Salin berkas surat tugas yang sudah ada untuk pemakai baru, supaya
     * menghapus usulan atau SPD yang satu tidak ikut menghapus berkas yang
     * lain. Null bila nomornya belum pernah diunggah.
     */
    public function salinUntuk(?string $nomor, ?int $kecualiUsulan = null, ?int $kecualiSpd = null): ?string
    {
        $asal = $this->temukan($nomor, $kecualiUsulan, $kecualiSpd);

        if (! $asal) {
            return null;
        }

        $disk = Storage::disk('public');
        $tujuan = 'dokumen/surat-tugas/'.PustakaBerkasBersama::AWALAN_SALINAN.Str::uuid().'.'.pathinfo($asal['path'], PATHINFO_EXTENSION);

        return $disk->copy($asal['path'], $tujuan) ? $tujuan : null;
    }

    /**
     * Seluruh nomor surat tugas yang tercatat — pada usulan maupun SPD —
     * dikelompokkan per nomor, terbaru lebih dulu, untuk Jejak Audit.
     *
     * @return Collection<int, array{
     *     no_tugas: string, pertama: ?Carbon, pengunggah: ?string, dokumen: ?Berkas,
     *     jumlah_berkas: int, usulan: Collection<int, Usulan>, spd: Collection<int, SuratPerjalananDinas>
     * }>
     */
    public function daftar(?string $cari = null, ?int $tahun = null): Collection
    {
        $disk = Storage::disk('public');

        $usulan = Usulan::query()
            ->whereNotNull('no_tugas')
            ->where('no_tugas', '!=', '')
            ->with('user', 'dokumen')
            ->get();

        $spd = SuratPerjalananDinas::query()
            ->whereNotNull('no_tugas')
            ->where('no_tugas', '!=', '')
            ->with('pembuat')
            ->get();

        $kunci = $usulan->map(fn (Usulan $item) => self::kunci($item->no_tugas))
            ->concat($spd->map(fn (SuratPerjalananDinas $item) => self::kunci($item->no_tugas)))
            ->unique();

        $usulanPerKunci = $usulan->groupBy(fn (Usulan $item) => self::kunci($item->no_tugas));
        $spdPerKunci = $spd->groupBy(fn (SuratPerjalananDinas $item) => self::kunci($item->no_tugas));

        $daftar = $kunci->map(function (string $kunci) use ($usulanPerKunci, $spdPerKunci, $disk): array {
            $pemakaiUsulan = ($usulanPerKunci[$kunci] ?? collect())->sortBy('created_at')->values();
            $pemakaiSpd = ($spdPerKunci[$kunci] ?? collect())->sortBy('created_at')->values();

            $berkas = $pemakaiUsulan
                ->flatMap(fn (Usulan $item) => $item->dokumen
                    ->filter(fn (Dokumen $dokumen) => filled($dokumen->surat_tugas))
                    ->map(fn (Dokumen $dokumen) => $this->dariDokumen($dokumen->setRelation('usulan', $item))))
                ->concat($pemakaiSpd->filter(fn (SuratPerjalananDinas $item) => filled($item->surat_tugas))
                    ->map(fn (SuratPerjalananDinas $item) => $this->dariSpd($item)))
                ->filter(fn (array $berkas) => $disk->exists($berkas['path']))
                ->sortBy(fn (array $berkas) => $berkas['waktu']?->getTimestamp() ?? 0)
                ->values();

            $pertama = $pemakaiUsulan->pluck('created_at')->concat($pemakaiSpd->pluck('created_at'))->filter()->min();

            return [
                'no_tugas' => $pemakaiUsulan->first()?->no_tugas ?? $pemakaiSpd->first()?->no_tugas,
                'pertama' => $pertama ? Carbon::parse($pertama) : null,
                'pengunggah' => $berkas->first()['pengunggah'] ?? null,
                'dokumen' => $berkas->first(),
                'jumlah_berkas' => $berkas->count(),
                'usulan' => $pemakaiUsulan,
                'spd' => $pemakaiSpd,
            ];
        });

        if (filled($cari)) {
            $teks = mb_strtolower(trim((string) $cari));
            $daftar = $daftar->filter(fn (array $baris) => str_contains(mb_strtolower($baris['no_tugas']), $teks)
                || $baris['usulan']->contains(fn (Usulan $item) => str_contains(mb_strtolower($item->user?->nama ?? ''), $teks)
                    || str_contains(mb_strtolower($item->no_usulan), $teks)));
        }

        if ($tahun) {
            $daftar = $daftar->filter(fn (array $baris) => $baris['pertama']?->year === $tahun);
        }

        return $daftar->sortByDesc(fn (array $baris) => $baris['pertama']?->getTimestamp() ?? 0)->values();
    }

    /**
     * @return Berkas
     */
    private function dariDokumen(Dokumen $dokumen): array
    {
        return [
            'path' => $dokumen->surat_tugas,
            'no_tugas' => (string) $dokumen->usulan?->no_tugas,
            'pengunggah' => $dokumen->usulan?->user?->nama,
            'waktu' => $dokumen->created_at,
            'sumber' => self::SUMBER_USULAN,
            'rujukan' => $dokumen->usulan?->no_usulan,
            'tautan' => route('berkas.lihat', $dokumen->surat_tugas),
        ];
    }

    /**
     * @return Berkas
     */
    private function dariSpd(SuratPerjalananDinas $spd): array
    {
        return [
            'path' => $spd->surat_tugas,
            'no_tugas' => (string) $spd->no_tugas,
            'pengunggah' => $spd->pembuat?->nama,
            'waktu' => $spd->created_at,
            'sumber' => self::SUMBER_SPD,
            'rujukan' => null,
            'tautan' => route('berkas.lihat', $spd->surat_tugas),
        ];
    }
}
