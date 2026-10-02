<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\PesertaUsulan;
use App\Models\TindakLanjut;
use App\Models\User;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Kabar untuk news feed dashboard eksekutif: siapa yang akan dan sedang
 * melakukan perjalanan dinas, serta tindak lanjut hasil perjalanan yang
 * dijadwalkan.
 *
 * Tiap kabar diberi kunci hari supaya halamannya dapat menyusunnya seperti
 * lini masa — hari ini, besok, lalu tanggal-tanggal berikutnya. Tindak
 * lanjut yang sudah melewati tenggatnya ditaruh paling atas, karena itulah
 * yang paling perlu dilihat pimpinan.
 */
class KabarPerjalanan
{
    public const JENIS_PERJALANAN = 'perjalanan';

    public const JENIS_TINDAK_LANJUT = 'tindak-lanjut';

    public const KELOMPOK_TERLAMBAT = 'terlambat';

    /** Pilihan rentang hari ke depan yang ditawarkan halaman. */
    public const RENTANG = [7, 30, 90];

    public const RENTANG_BAWAAN = 30;

    /** Batas kabar per jenis, supaya halaman tetap ringan dibuka. */
    private const BATAS = 60;

    /**
     * Kabar dalam rentang hari ke depan, sudah berurutan untuk lini masa.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function kabar(int $hari, ?string $jenis = null): Collection
    {
        $kabar = collect();

        if ($jenis !== self::JENIS_TINDAK_LANJUT) {
            $kabar = $kabar->concat($this->perjalanan($hari));
        }

        if ($jenis !== self::JENIS_PERJALANAN) {
            $kabar = $kabar->concat($this->tindakLanjut($hari));
        }

        return $kabar->sortBy('urut')->values();
    }

    /**
     * Angka ringkas untuk panel samping. Tidak terpengaruh saringan jenis,
     * supaya pimpinan tetap melihat gambaran utuhnya.
     *
     * @return array{sedang_bertugas: int, akan_berangkat: int, tindak_lanjut: int, terlambat: int, tujuan: Collection<string, int>}
     */
    public function ringkasan(int $hari): array
    {
        $perjalanan = $this->queryPerjalanan($hari)
            ->withCount('peserta')
            ->get(['id', 'lokasi', 'tanggal_mulai']);

        // Usulan lama tanpa baris peserta tetap dihitung satu orang.
        $orang = fn (Collection $daftar) => (int) $daftar->sum(fn (Usulan $u) => max(1, (int) $u->peserta_count));
        [$berlangsung, $mendatang] = $perjalanan->partition(
            fn (Usulan $u) => Carbon::parse($u->tanggal_mulai)->startOfDay()->lte(today())
        );

        return [
            'sedang_bertugas' => $orang($berlangsung),
            'akan_berangkat' => $orang($mendatang),
            'tindak_lanjut' => $this->queryTindakLanjut($hari)->whereDate('target_selesai', '>=', today())->count(),
            'terlambat' => $this->queryTindakLanjut($hari)->whereDate('target_selesai', '<', today())->count(),
            'tujuan' => $perjalanan->pluck('lokasi')->filter()->countBy()->sortDesc()->take(5),
        ];
    }

    /**
     * Label kelompok hari pada lini masa.
     */
    public static function labelKelompok(string $kelompok): string
    {
        if ($kelompok === self::KELOMPOK_TERLAMBAT) {
            return 'Lewat tenggat';
        }

        $tanggal = Carbon::parse($kelompok)->startOfDay();

        return match (true) {
            $tanggal->isSameDay(today()) => 'Hari ini',
            $tanggal->isSameDay(today()->addDay()) => 'Besok',
            default => $tanggal->translatedFormat('l, d F Y'),
        };
    }

    /**
     * Perjalanan yang sudah berlaku atau masih diajukan, yang sedang atau
     * akan berlangsung dalam rentangnya — sama seperti Jadwal Perjalanan.
     *
     * @return Builder<Usulan>
     */
    private function queryPerjalanan(int $hari): Builder
    {
        return Usulan::query()
            ->whereIn('status', [StatusUsulan::Disetujui->value, ...StatusUsulan::nilaiMenunggu()])
            ->whereDate('tanggal_selesai', '>=', today())
            ->whereDate('tanggal_mulai', '<=', today()->addDays($hari));
    }

    /**
     * Tindak lanjut bertenggat yang belum selesai, termasuk yang sudah lewat.
     *
     * @return Builder<TindakLanjut>
     */
    private function queryTindakLanjut(int $hari): Builder
    {
        return TindakLanjut::query()
            ->belumSelesai()
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '<=', today()->addDays($hari))
            ->whereHas('laporan.usulan');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function perjalanan(int $hari): Collection
    {
        return $this->queryPerjalanan($hari)
            ->with(['peserta.user.unit', 'user.unit', 'kategoriPerjadin', 'kegiatan'])
            ->orderBy('tanggal_mulai')
            ->limit(self::BATAS)
            ->get()
            ->map(function (Usulan $usulan): array {
                $mulai = Carbon::parse($usulan->tanggal_mulai)->startOfDay();
                $selesai = Carbon::parse($usulan->tanggal_selesai)->startOfDay();
                $berlangsung = $mulai->lte(today());
                $peserta = $this->urutkanPeserta($usulan);
                $hariKe = (int) $mulai->diffInDays(today()) + 1;
                $lama = (int) $mulai->diffInDays($selesai) + 1;
                $menuju = (int) today()->diffInDays($mulai);

                return [
                    'jenis' => self::JENIS_PERJALANAN,
                    'kelompok' => ($berlangsung ? today() : $mulai)->toDateString(),
                    'urut' => ($berlangsung ? today() : $mulai)->format('Ymd').'-1-'.$mulai->format('Ymd').'-'.$usulan->id,
                    'usulan' => $usulan,
                    'penulis' => $this->penulis($peserta->first(), $usulan->user),
                    'peserta' => $peserta,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'lama' => $lama,
                    'berlangsung' => $berlangsung,
                    'pasti' => StatusUsulan::dari($usulan->status) === StatusUsulan::Disetujui,
                    'waktu' => match (true) {
                        $berlangsung => "Hari ke-{$hariKe} dari {$lama}",
                        $menuju === 0 => 'Berangkat hari ini',
                        $menuju === 1 => 'Berangkat besok',
                        default => "Berangkat {$menuju} hari lagi",
                    },
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tindakLanjut(int $hari): Collection
    {
        return $this->queryTindakLanjut($hari)
            ->with(['laporan.usulan.peserta.user.unit', 'laporan.usulan.user.unit'])
            ->orderBy('target_selesai')
            ->limit(self::BATAS)
            ->get()
            ->map(function (TindakLanjut $tindak): array {
                $usulan = $tindak->laporan->usulan;
                $target = $tindak->target_selesai->copy()->startOfDay();
                $terlambat = $target->lt(today());
                $selisih = (int) today()->diffInDays($target, true);

                return [
                    'jenis' => self::JENIS_TINDAK_LANJUT,
                    'kelompok' => $terlambat ? self::KELOMPOK_TERLAMBAT : $target->toDateString(),
                    'urut' => ($terlambat ? '00000000' : $target->format('Ymd')).'-2-'.$target->format('Ymd').'-'.$tindak->id,
                    'tindak' => $tindak,
                    'usulan' => $usulan,
                    'penulis' => $this->penulis($this->urutkanPeserta($usulan)->first(), $usulan->user),
                    'target' => $target,
                    'terlambat' => $terlambat,
                    'waktu' => match (true) {
                        $terlambat => "Terlambat {$selisih} hari",
                        $selisih === 0 => 'Jatuh tempo hari ini',
                        $selisih === 1 => 'Jatuh tempo besok',
                        default => "Jatuh tempo {$selisih} hari lagi",
                    },
                ];
            });
    }

    /**
     * Ketua rombongan lebih dulu, supaya dialah yang tampil sebagai penulis kabar.
     *
     * @return Collection<int, PesertaUsulan>
     */
    private function urutkanPeserta(Usulan $usulan): Collection
    {
        return $usulan->peserta
            ->sortBy(fn (PesertaUsulan $orang) => $orang->peran === 'ketua' ? 0 : 1)
            ->values();
    }

    /**
     * @return array{nama: string, foto: ?string, keterangan: ?string}
     */
    private function penulis(?PesertaUsulan $peserta, ?User $pengusul): array
    {
        $akun = $peserta?->user ?? $pengusul;

        return [
            'nama' => $peserta?->nama ?? $pengusul?->nama ?? 'Pelaksana',
            'foto' => $akun?->url_foto,
            'keterangan' => $peserta?->jabatan ?: ($akun?->jabatan ?: $akun?->unit?->nama),
        ];
    }
}
