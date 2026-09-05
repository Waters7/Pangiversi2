<?php

namespace App\Models;

use App\Enums\StatusUsulan;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $table = 'audit_logs';

    public const AKSI_DIBUAT = 'dibuat';

    public const AKSI_DIAJUKAN = 'diajukan';

    public const AKSI_DIPERBARUI = 'diperbarui';

    public const AKSI_DIHAPUS = 'dihapus';

    public const AKSI_DISETUJUI = 'disetujui';

    public const AKSI_DITOLAK = 'ditolak';

    public const AKSI_REVISI = 'revisi';

    public const AKSI_DIBATALKAN = 'dibatalkan';

    public const AKSI_DOKUMEN = 'dokumen';

    public const AKSI_PEMBAYARAN = 'pembayaran';

    public const AKSI_BIAYA = 'biaya';

    public const AKSI_PESERTA = 'peserta';

    public const AKSI_PENGGUNA = 'pengguna';

    protected $fillable = [
        'id_usulan',
        'id_user',
        'aksi',
        'deskripsi',
        'status_lama',
        'status_baru',
        'catatan',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * Pelaku tindakan yang tercatat.
     *
     * @return BelongsTo<User, $this>
     */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeUntukUsulan(Builder $query, Usulan $usulan): void
    {
        $query->where('id_usulan', $usulan->id);
    }

    /**
     * Warna badge per jenis aksi, dipakai pada timeline dan tabel log.
     */
    public function getAksiBadgeAttribute(): string
    {
        return match ($this->aksi) {
            self::AKSI_DIBUAT, self::AKSI_DIPERBARUI => 'bg-slate-100 text-slate-700',
            self::AKSI_DIAJUKAN => 'bg-blue-100 text-blue-700',
            self::AKSI_DISETUJUI => 'bg-emerald-100 text-emerald-700',
            self::AKSI_DITOLAK, self::AKSI_DIHAPUS => 'bg-red-100 text-red-700',
            self::AKSI_REVISI, self::AKSI_DIBATALKAN => 'bg-amber-100 text-amber-700',
            self::AKSI_PEMBAYARAN, self::AKSI_BIAYA => 'bg-teal-100 text-teal-700',
            self::AKSI_DOKUMEN => 'bg-indigo-100 text-indigo-700',
            self::AKSI_PESERTA => 'bg-cyan-100 text-cyan-700',
            self::AKSI_PENGGUNA => 'bg-purple-100 text-purple-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getAksiLabelAttribute(): string
    {
        return ucfirst($this->aksi);
    }

    public function getStatusLamaLabelAttribute(): ?string
    {
        return $this->status_lama ? StatusUsulan::dari($this->status_lama)->label() : null;
    }

    public function getStatusBaruLabelAttribute(): ?string
    {
        return $this->status_baru ? StatusUsulan::dari($this->status_baru)->label() : null;
    }

    /**
     * Apakah catatan ini merekam perpindahan status.
     */
    public function adaPerubahanStatus(): bool
    {
        return $this->status_lama !== null
            && $this->status_baru !== null
            && $this->status_lama !== $this->status_baru;
    }

    /**
     * Daftar aksi untuk filter di halaman log.
     *
     * @return array<string, string>
     */
    public static function aksiOptions(): array
    {
        return [
            self::AKSI_DIBUAT => 'Dibuat',
            self::AKSI_DIAJUKAN => 'Diajukan',
            self::AKSI_DIPERBARUI => 'Diperbarui',
            self::AKSI_DIHAPUS => 'Dihapus',
            self::AKSI_DISETUJUI => 'Disetujui',
            self::AKSI_DITOLAK => 'Ditolak',
            self::AKSI_REVISI => 'Revisi',
            self::AKSI_DIBATALKAN => 'Dibatalkan',
            self::AKSI_DOKUMEN => 'Dokumen',
            self::AKSI_PEMBAYARAN => 'Pembayaran',
            self::AKSI_BIAYA => 'Biaya',
            self::AKSI_PESERTA => 'Peserta',
            self::AKSI_PENGGUNA => 'Pengguna',
        ];
    }
}
