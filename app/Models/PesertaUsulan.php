<?php

namespace App\Models;

use Database\Factories\PesertaUsulanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesertaUsulan extends Model
{
    /** @use HasFactory<PesertaUsulanFactory> */
    use HasFactory;

    protected $table = 'peserta_usulan';

    protected $fillable = [
        'id_usulan',
        'id_user',
        'nama',
        'nip',
        'jabatan',
        'peran',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * @return array<string, string>
     */
    public static function peranOptions(): array
    {
        return [
            'ketua' => 'Ketua Tim',
            'anggota' => 'Anggota',
        ];
    }

    public function getPeranLabelAttribute(): string
    {
        return self::peranOptions()[$this->peran] ?? '-';
    }
}
