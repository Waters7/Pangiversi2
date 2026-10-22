<?php

namespace App\Models;

use App\Enums\Golongan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pilihan "Pangkat dan Golongan" — master data, bukan enum, supaya satuan
 * kerja dapat menambahkannya sendiri lewat menu Master Data.
 *
 * Kodenya disimpan pada kolom golongan pengguna; tulisannya tercetak pada
 * Surat Perjalanan Dinas. Daftar bawaannya — PNS, PPPK, dan outsourcing —
 * diisi migrasi.
 */
class PangkatGolongan extends Model
{
    protected $table = 'pangkat_golongan';

    protected $fillable = ['kode', 'tulisan', 'kelompok', 'urutan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'golongan', 'kode');
    }

    /**
     * Pilihan untuk kotak pilih, dikelompokkan dan diurutkan.
     *
     * @return array<string, array<string, string>>
     */
    public static function terkelompok(): array
    {
        $hasil = [];

        foreach (self::orderBy('urutan')->orderBy('tulisan')->get() as $baris) {
            $hasil[$baris->kelompok][$baris->kode] = $baris->tulisan;
        }

        return $hasil;
    }

    /**
     * Tulisan yang tercetak pada SPD untuk kode golongan seorang pengguna.
     */
    public static function tulisanUntuk(?string $kode): ?string
    {
        return blank($kode) ? null : self::where('kode', $kode)->value('tulisan');
    }

    /**
     * Baca pilihan dari tulisan bebas, misalnya pada berkas impor.
     *
     * Golongan PNS dan PPPK dikenali dalam berbagai tulisan ("iii/a",
     * "Penata Muda (III/a)", "Golongan IX (PPPK)"); pilihan tambahan
     * dikenali dari kode atau tulisannya persis.
     */
    public static function dari(?string $teks): ?self
    {
        if (blank($teks)) {
            return null;
        }

        $baku = Golongan::dari($teks);
        $baris = $baku ? self::firstWhere('kode', $baku->value) : null;

        if ($baris) {
            return $baris;
        }

        $teks = mb_strtolower(trim($teks));

        return self::whereRaw('LOWER(kode) = ?', [$teks])
            ->orWhereRaw('LOWER(tulisan) = ?', [$teks])
            ->first();
    }
}
