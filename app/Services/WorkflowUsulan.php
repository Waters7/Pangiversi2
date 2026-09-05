<?php

namespace App\Services;

use App\Enums\LevelPersetujuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\Persetujuan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengatur alur validasi usulan perjalanan dinas (FR-07).
 *
 * Validasi cukup satu tahap di PPK karena surat tugas yang diajukan sudah
 * sepengetahuan atasan langsung dan Kasubbag Umum. Struktur bertahap tetap
 * dipertahankan agar tahap tambahan dapat dipasang kembali tanpa mengubah
 * pemanggilnya, dan tahap tanpa approver dilewati otomatis supaya usulan
 * tidak tertahan tanpa ada yang bisa memutuskan.
 */
class WorkflowUsulan
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    /**
     * Catat usulan sebagai berlaku, tanpa menunggu validasi.
     *
     * Penugasannya sudah disahkan lewat Surat Perjalanan Dinas — SPD terbit
     * lebih dulu dan usulan perjadin baru boleh dibuat sesudahnya — sehingga
     * validasi PPK tidak diulang di dalam aplikasi. Yang tersisa bagi
     * pengusul adalah melaksanakan perjalanan, melengkapi berkas
     * pertanggungjawaban, lalu menunggu pembayaran dari bendahara.
     */
    public function ajukan(Usulan $usulan): void
    {
        $statusLama = $usulan->status;

        $usulan->update(['status' => StatusUsulan::Disetujui->value, 'catatan' => null]);

        $this->audit->catatPerubahanStatus(
            $usulan,
            AuditLog::AKSI_DIAJUKAN,
            "Usulan {$usulan->no_usulan} diajukan dan langsung berlaku karena penugasannya sudah disahkan lewat SPD.",
            $statusLama,
        );

        $this->beritahuPengusul(
            $usulan,
            'Usulan perjalanan dinas Anda tercatat',
            "Usulan {$usulan->no_usulan} berlaku sejak sekarang. Setelah perjalanan selesai, "
                .'lengkapi berkas pertanggungjawaban agar pembayaran dapat diproses bendahara.',
            Notifikasi::TIPE_SUKSES,
        );
    }

    /**
     * Setujui tahap yang sedang berjalan lalu teruskan ke tahap berikutnya.
     */
    public function setujui(Usulan $usulan, User $approver, ?string $catatan = null): void
    {
        $level = $this->levelBerjalan($usulan);
        $statusLama = $usulan->status;

        DB::transaction(function () use ($usulan, $approver, $catatan, $level, $statusLama): void {
            $this->rekamKeputusan($usulan, $approver, $level, Persetujuan::KEPUTUSAN_SETUJU, $catatan);

            $levelBerikut = $level
                ? $this->levelTersediaMulai($usulan, $level->berikutnya())
                : null;

            if (! $levelBerikut) {
                $this->finalkan(
                    $usulan,
                    $statusLama,
                    "Usulan {$usulan->no_usulan} disetujui pada tahap akhir oleh {$approver->nama}.",
                    $catatan,
                );

                return;
            }

            $usulan->update(['status' => $levelBerikut->statusMenunggu()->value]);

            $this->audit->catatPerubahanStatus(
                $usulan,
                AuditLog::AKSI_DISETUJUI,
                "Tahap {$level?->label()} disetujui oleh {$approver->nama}. Diteruskan ke {$levelBerikut->label()}.",
                $statusLama,
                $catatan,
            );

            $this->beritahuApprover($usulan, $levelBerikut);
            $this->beritahuPengusul(
                $usulan,
                'Usulan Anda maju ke tahap berikutnya',
                "Usulan {$usulan->no_usulan} telah disetujui {$level?->label()} dan kini menunggu {$levelBerikut->label()}.",
                Notifikasi::TIPE_INFO,
            );
        });
    }

    /**
     * Tolak usulan pada tahap yang sedang berjalan.
     */
    public function tolak(Usulan $usulan, User $approver, ?string $catatan = null): void
    {
        $level = $this->levelBerjalan($usulan);
        $statusLama = $usulan->status;

        DB::transaction(function () use ($usulan, $approver, $catatan, $level, $statusLama): void {
            $this->rekamKeputusan($usulan, $approver, $level, Persetujuan::KEPUTUSAN_TOLAK, $catatan);

            $usulan->update([
                'status' => StatusUsulan::Ditolak->value,
                'catatan' => $catatan,
            ]);

            $this->audit->catatPerubahanStatus(
                $usulan,
                AuditLog::AKSI_DITOLAK,
                "Usulan {$usulan->no_usulan} ditolak pada tahap ".($level?->label() ?? 'persetujuan')." oleh {$approver->nama}.",
                $statusLama,
                $catatan,
            );

            $this->beritahuPengusul(
                $usulan,
                'Usulan Anda ditolak',
                $catatan
                    ? "Usulan {$usulan->no_usulan} ditolak. Alasan: {$catatan}"
                    : "Usulan {$usulan->no_usulan} ditolak.",
                Notifikasi::TIPE_BAHAYA,
            );
        });
    }

    /**
     * Kembalikan usulan kepada pengusul untuk diperbaiki.
     */
    public function mintaRevisi(Usulan $usulan, User $approver, string $catatan): void
    {
        $level = $this->levelBerjalan($usulan);
        $statusLama = $usulan->status;

        DB::transaction(function () use ($usulan, $approver, $catatan, $level, $statusLama): void {
            $this->rekamKeputusan($usulan, $approver, $level, Persetujuan::KEPUTUSAN_REVISI, $catatan);

            $usulan->update([
                'status' => StatusUsulan::PerluRevisi->value,
                'catatan' => $catatan,
            ]);

            $this->audit->catatPerubahanStatus(
                $usulan,
                AuditLog::AKSI_REVISI,
                "Usulan {$usulan->no_usulan} dikembalikan untuk revisi oleh {$approver->nama} pada tahap ".($level?->label() ?? 'persetujuan').'.',
                $statusLama,
                $catatan,
            );

            $this->beritahuPengusul(
                $usulan,
                'Usulan perlu diperbaiki',
                "Usulan {$usulan->no_usulan} dikembalikan untuk revisi. Catatan: {$catatan}",
                Notifikasi::TIPE_PERINGATAN,
            );
        });
    }

    /**
     * Tahap persetujuan yang sedang menunggu keputusan.
     */
    public function levelBerjalan(Usulan $usulan): ?LevelPersetujuan
    {
        return StatusUsulan::dari($usulan->status)->level();
    }

    /**
     * Pengguna yang berwenang memutuskan tahap tertentu.
     *
     * @return Collection<int, User>
     */
    public function approverUntuk(Usulan $usulan, LevelPersetujuan $level): Collection
    {
        return User::where('role', $level->peran()?->value)->get();
    }

    /**
     * Apakah pengguna ini boleh memutuskan usulan pada kondisinya saat ini.
     */
    public function bolehMemutuskan(Usulan $usulan, User $user): bool
    {
        $level = $this->levelBerjalan($usulan);

        if (! $level) {
            return false;
        }

        // Administrator berperan sebagai jaring pengaman bila approver berhalangan.
        if ($user->isAdmin()) {
            return true;
        }

        return $this->approverUntuk($usulan, $level)->contains('id', $user->id);
    }

    /**
     * Daftar usulan yang menunggu keputusan pengguna ini.
     *
     * @return Collection<int, Usulan>
     */
    public function antrianUntuk(User $user): Collection
    {
        return Usulan::with('user')
            ->whereIn('status', StatusUsulan::nilaiMenunggu())
            ->get()
            ->filter(fn (Usulan $usulan) => $this->bolehMemutuskan($usulan, $user))
            ->values();
    }

    /**
     * Tahap pertama sejak $mulai yang benar-benar memiliki approver.
     */
    private function levelTersediaMulai(Usulan $usulan, ?LevelPersetujuan $mulai): ?LevelPersetujuan
    {
        $level = $mulai;

        while ($level !== null) {
            if ($this->approverUntuk($usulan, $level)->isNotEmpty()) {
                return $level;
            }

            $level = $level->berikutnya();
        }

        return null;
    }

    private function finalkan(Usulan $usulan, string $statusLama, string $deskripsi, ?string $catatan = null): void
    {
        $usulan->update(['status' => StatusUsulan::Disetujui->value]);

        $this->audit->catatPerubahanStatus(
            $usulan,
            AuditLog::AKSI_DISETUJUI,
            $deskripsi,
            $statusLama,
            $catatan,
        );

        $this->beritahuPengusul(
            $usulan,
            'Usulan Anda disetujui',
            "Usulan {$usulan->no_usulan} telah disetujui seluruh tahapan. Dokumen penugasan dapat diproses.",
            Notifikasi::TIPE_SUKSES,
        );
    }

    private function rekamKeputusan(
        Usulan $usulan,
        User $approver,
        ?LevelPersetujuan $level,
        string $keputusan,
        ?string $catatan,
    ): void {
        $level ??= LevelPersetujuan::pertama();

        $usulan->persetujuan()->create([
            'id_approver' => $approver->id,
            'level' => $level,
            'peran' => $level->peran()?->value ?? PeranPengguna::Ppk->value,
            'keputusan' => $keputusan,
            'catatan' => $catatan,
            'waktu_keputusan' => now(),
        ]);
    }

    private function beritahuApprover(Usulan $usulan, LevelPersetujuan $level): void
    {
        $this->notifikasi->kirimKeBanyak(
            $this->approverUntuk($usulan, $level),
            'Usulan menunggu keputusan Anda',
            "Usulan {$usulan->no_usulan} dari {$usulan->user?->nama} menunggu keputusan pada tahap {$level->label()}.",
            [
                'usulan' => $usulan,
                'tipe' => Notifikasi::TIPE_PERINGATAN,
                'url' => route('persetujuan.detail', $usulan->no_usulan),
            ],
        );
    }

    private function beritahuPengusul(Usulan $usulan, string $judul, string $pesan, string $tipe): void
    {
        if (! $usulan->user) {
            return;
        }

        $this->notifikasi->kirim($usulan->user, $judul, $pesan, [
            'usulan' => $usulan,
            'tipe' => $tipe,
        ]);
    }
}
