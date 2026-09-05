<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Usulan;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat jejak audit setiap tindakan penting pengguna (FR-14).
 *
 * Catatan bersifat append-only: tidak ada metode ubah atau hapus di sini,
 * sehingga riwayat tindakan tetap utuh untuk penelusuran.
 */
class AuditService
{
    /**
     * Catat satu tindakan ke dalam jejak audit.
     *
     * @param  array{usulan?: ?Usulan, status_lama?: ?string, status_baru?: ?string, catatan?: ?string}  $opsi
     */
    public function catat(string $aksi, string $deskripsi, array $opsi = []): AuditLog
    {
        $usulan = $opsi['usulan'] ?? null;
        $request = request();

        return AuditLog::create([
            'id_usulan' => $usulan?->id,
            'id_user' => Auth::id(),
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
            'status_lama' => $opsi['status_lama'] ?? null,
            'status_baru' => $opsi['status_baru'] ?? null,
            'catatan' => $opsi['catatan'] ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * Catat perubahan status usulan lengkap dengan status sebelum dan sesudah.
     */
    public function catatPerubahanStatus(
        Usulan $usulan,
        string $aksi,
        string $deskripsi,
        ?string $statusLama,
        ?string $catatan = null,
    ): AuditLog {
        return $this->catat($aksi, $deskripsi, [
            'usulan' => $usulan,
            'status_lama' => $statusLama,
            'status_baru' => $usulan->status,
            'catatan' => $catatan,
        ]);
    }
}
