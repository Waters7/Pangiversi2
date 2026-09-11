<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tindakan pembayaran yang dilakukan bendahara.
 *
 * Barisnya hanya ditambah, tidak pernah disunting: pembatalan dicatat
 * sebagai baris tersendiri supaya jejaknya utuh dan dapat diperiksa.
 */
class RiwayatPembayaran extends Model
{
    protected $table = 'riwayat_pembayaran';

    public const JENIS_UANG_MUKA = 'uang_muka';

    public const JENIS_PELUNASAN = 'pelunasan';

    /** Penggantian transport lokal, ditransfer terpisah dari pelunasan. */
    public const JENIS_TRANSPORT_LOKAL = 'transport_lokal';

    public const JENIS_BATAL_UANG_MUKA = 'batal_uang_muka';

    public const JENIS_BATAL_PELUNASAN = 'batal_pelunasan';

    public const JENIS_BATAL_TRANSPORT_LOKAL = 'batal_transport_lokal';

    protected $fillable = [
        'id_keuangan',
        'id_usulan',
        'jenis',
        'nominal',
        'tanggal',
        'id_pencatat',
        'bukti',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominal' => 'float',
            'tanggal' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Keuangan, $this>
     */
    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(Keuangan::class, 'id_keuangan');
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * Bendahara yang mencatat pembayaran ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pencatat');
    }

    /**
     * Pembatalan mengurangi kembali nilai yang sudah tercatat, jadi ia
     * ditampilkan sebagai angka negatif pada rekap.
     */
    public function pembatalan(): bool
    {
        return in_array($this->jenis, [
            self::JENIS_BATAL_UANG_MUKA,
            self::JENIS_BATAL_PELUNASAN,
            self::JENIS_BATAL_TRANSPORT_LOKAL,
        ], true);
    }

    public function nilaiArus(): float
    {
        return $this->pembatalan() ? -$this->nominal : $this->nominal;
    }

    public function getJenisLabelAttribute(): string
    {
        return match ($this->jenis) {
            self::JENIS_UANG_MUKA => 'Uang Muka',
            self::JENIS_PELUNASAN => 'Pelunasan',
            self::JENIS_TRANSPORT_LOKAL => 'Transport Lokal',
            self::JENIS_BATAL_UANG_MUKA => 'Pembatalan Uang Muka',
            self::JENIS_BATAL_PELUNASAN => 'Pembatalan Pelunasan',
            self::JENIS_BATAL_TRANSPORT_LOKAL => 'Pembatalan Transport Lokal',
            default => $this->jenis,
        };
    }

    public function getJenisBadgeAttribute(): string
    {
        return match ($this->jenis) {
            self::JENIS_UANG_MUKA => 'bg-blue-100 text-blue-700',
            self::JENIS_PELUNASAN => 'bg-emerald-100 text-emerald-700',
            self::JENIS_TRANSPORT_LOKAL => 'bg-violet-100 text-violet-700',
            default => 'bg-red-100 text-red-700',
        };
    }

    /**
     * Catat satu tindakan pembayaran.
     */
    public static function catat(
        Keuangan $keuangan,
        string $jenis,
        float $nominal,
        string $tanggal,
        ?User $pencatat,
        ?string $bukti = null,
        ?string $catatan = null,
    ): self {
        return self::create([
            'id_keuangan' => $keuangan->id,
            'id_usulan' => $keuangan->id_usulan,
            'jenis' => $jenis,
            'nominal' => $nominal,
            'tanggal' => $tanggal,
            'id_pencatat' => $pencatat?->id,
            'bukti' => $bukti,
            'catatan' => $catatan,
        ]);
    }
}
