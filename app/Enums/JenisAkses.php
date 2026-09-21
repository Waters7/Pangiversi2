<?php

namespace App\Enums;

/**
 * Sifat sebuah kemampuan pada menunya: sekadar melihat, mengubah, atau
 * menghapus. Dipakai untuk menyusun kolom pada matriks hak akses peran.
 */
enum JenisAkses: string
{
    case Lihat = 'lihat';

    case Ubah = 'ubah';

    case Hapus = 'hapus';

    public function label(): string
    {
        return match ($this) {
            self::Lihat => 'Lihat',
            self::Ubah => 'Ubah',
            self::Hapus => 'Hapus',
        };
    }
}
