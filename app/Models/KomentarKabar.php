<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komentar pada kartu news feed dashboard eksekutif — perjalanan dinas
 * atau tindak lanjut.
 */
class KomentarKabar extends Model
{
    protected $table = 'kabar_komentar';

    protected $fillable = ['jenis', 'id_kabar', 'id_user', 'isi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['id_kabar' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /** Penulisnya sendiri dan super administrator yang boleh menghapus. */
    public function bolehDihapusOleh(User $pengguna): bool
    {
        return $this->id_user === $pengguna->id || $pengguna->isAdmin();
    }
}
