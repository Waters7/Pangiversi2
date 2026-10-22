<?php

namespace App\Models;

use App\Enums\JenisReaksi;
use Illuminate\Database\Eloquent\Model;

/**
 * Reaksi seorang pengguna pada kartu news feed dashboard eksekutif.
 */
class ReaksiKabar extends Model
{
    protected $table = 'kabar_reaksi';

    protected $fillable = ['jenis', 'id_kabar', 'id_user', 'reaksi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['id_kabar' => 'integer', 'reaksi' => JenisReaksi::class];
    }
}
