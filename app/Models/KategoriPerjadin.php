<?php

namespace App\Models;

use Database\Factories\KategoriPerjadinFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Kategori perjalanan dinas sesuai daftar resmi Poltekkes Kemenkes Manado.
 *
 * Kategori menentukan kelas biaya — fullboard, fullday, halfday, dan
 * seterusnya — sedangkan Kegiatan menjelaskan maksud perjalanannya. Keduanya
 * dipilih terpisah pada formulir usulan.
 */
class KategoriPerjadin extends Model
{
    /** @use HasFactory<KategoriPerjadinFactory> */
    use HasFactory;

    protected $table = 'kategori_perjadin';

    protected $fillable = ['grup', 'nama', 'kode', 'dalam_kota', 'urutan', 'is_aktif'];

    /**
     * Perjalanan dalam kota: tidak ada tiket, penginapan, maupun kuitansi
     * yang perlu dipertanggungjawabkan — hanya surat tugas, SPD, dan
     * transport lokalnya.
     */
    public function dalamKota(): bool
    {
        return (bool) $this->dalam_kota;
    }

    public function labelWilayah(): string
    {
        return $this->dalamKota() ? 'Dalam Kota' : 'Luar Kota';
    }

    /**
     * Urutan tampil grup pada daftar pilihan.
     *
     * @var list<string>
     */
    public const URUTAN_GRUP = [
        'Perjadin Luar Kota',
        'Perjadin Dalam Kota',
        'Narasumber',
        'Diklat',
        'Auditor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_aktif' => 'boolean', 'dalam_kota' => 'boolean'];
    }

    /**
     * @return HasMany<Usulan, $this>
     */
    public function usulan(): HasMany
    {
        return $this->hasMany(Usulan::class, 'id_kategori_perjadin');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * Daftar pilihan yang sudah dikelompokkan, siap dipakai optgroup.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function terkelompok(): Collection
    {
        return self::aktif()
            ->orderBy('urutan')
            ->get()
            ->groupBy('grup')
            // Perbandingan harus eksplisit terhadap false: grup pertama
            // berindeks 0, yang bila diperlakukan sebagai boolean akan
            // terlempar ke urutan paling belakang.
            ->sortBy(function ($_, string $grup) {
                $posisi = array_search($grup, self::URUTAN_GRUP, true);

                return $posisi === false ? PHP_INT_MAX : $posisi;
            });
    }

    public function getLabelLengkapAttribute(): string
    {
        return "{$this->grup} — {$this->nama}";
    }

    /**
     * Warna badge mengikuti grupnya agar mudah dibedakan sekilas.
     */
    public function getBadgeAttribute(): string
    {
        return match ($this->grup) {
            'Perjadin Luar Kota' => 'bg-blue-100 text-blue-700',
            'Perjadin Dalam Kota' => 'bg-teal-100 text-teal-700',
            'Narasumber' => 'bg-purple-100 text-purple-700',
            'Diklat' => 'bg-amber-100 text-amber-700',
            'Auditor' => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
