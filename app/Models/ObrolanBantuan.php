<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu laporan kendala dari pengguna kepada administrator, beserta
 * tanya jawab yang mengikutinya.
 */
class ObrolanBantuan extends Model
{
    protected $table = 'obrolan_bantuan';

    /** Baru dilaporkan, administrator belum menjawab. */
    public const STATUS_TERBUKA = 'terbuka';

    /** Sudah dijawab administrator, menunggu tanggapan pelapor. */
    public const STATUS_DIJAWAB = 'dijawab';

    /** Kendalanya sudah beres. */
    public const STATUS_SELESAI = 'selesai';

    /**
     * @var array<string, string>
     */
    public const LABEL = [
        self::STATUS_TERBUKA => 'Perlu Dijawab',
        self::STATUS_DIJAWAB => 'Sudah Dijawab',
        self::STATUS_SELESAI => 'Selesai',
    ];

    protected $fillable = [
        'id_pelapor',
        'judul',
        'status',
        'id_usulan',
        'dibalas_at',
        'diselesaikan_at',
        'id_penyelesai',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dibalas_at' => 'datetime',
            'diselesaikan_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pelapor');
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penyelesai');
    }

    /**
     * @return HasMany<PesanBantuan, $this>
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(PesanBantuan::class, 'id_obrolan')->orderBy('id');
    }

    public function sudahSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::LABEL[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_TERBUKA => 'bg-amber-100 text-amber-700',
            self::STATUS_DIJAWAB => 'bg-blue-100 text-blue-700',
            default => 'bg-emerald-100 text-emerald-700',
        };
    }

    /**
     * Obrolan yang masih menunggu tindakan, terbaru di atas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBelumSelesai(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_SELESAI);
    }

    /**
     * Catat satu pesan baru, lalu geser statusnya menurut siapa yang bicara.
     *
     * Pesan dari administrator menandai obrolan sudah dijawab; pesan dari
     * pelapor mengembalikannya ke antrian — termasuk membuka kembali obrolan
     * yang sempat dinyatakan selesai, karena kendalanya ternyata belum beres.
     */
    public function balas(User $pengirim, string $isi, ?string $lampiran = null): PesanBantuan
    {
        $pesan = $this->pesan()->create([
            'id_pengirim' => $pengirim->id,
            'isi' => $isi,
            'lampiran' => $lampiran,
        ]);

        $dariAdmin = $pengirim->isAdmin();

        $this->update([
            'status' => $dariAdmin ? self::STATUS_DIJAWAB : self::STATUS_TERBUKA,
            'dibalas_at' => $dariAdmin ? now() : $this->dibalas_at,
            'diselesaikan_at' => null,
            'id_penyelesai' => null,
        ]);

        return $pesan;
    }

    public function selesaikan(User $oleh): void
    {
        $this->update([
            'status' => self::STATUS_SELESAI,
            'diselesaikan_at' => now(),
            'id_penyelesai' => $oleh->id,
        ]);
    }

    /**
     * Tandai pesan lawan bicara sebagai sudah terbaca.
     */
    public function tandaiTerbaca(User $pembaca): void
    {
        $this->pesan()
            ->whereNull('dibaca_at')
            ->where('id_pengirim', '!=', $pembaca->id)
            ->update(['dibaca_at' => now()]);
    }

    /**
     * Berapa pesan lawan bicara yang belum dibaca oleh pengguna ini.
     */
    public function belumDibaca(User $pembaca): int
    {
        return $this->pesan()
            ->whereNull('dibaca_at')
            ->where('id_pengirim', '!=', $pembaca->id)
            ->count();
    }
}
