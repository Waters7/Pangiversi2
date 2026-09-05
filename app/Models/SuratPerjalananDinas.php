<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SuratPerjalananDinas extends Model
{
    use HasFactory;

    protected $table = 'surat_perjalanan_dinas';

    /** Satu berkas SPD memuat paling banyak lima pelaksana. */
    public const MAKS_PELAKSANA = 5;

    /** Pengikut yang dapat dicantumkan pada satu SPD. */
    public const MAKS_PENGIKUT = 3;

    protected $fillable = [
        'id_usulan',
        'id_pembuat',
        'dikeluarkan_di',
        'tanggal_surat',
        'maksud',
        'alat_angkut',
        'tempat_berangkat',
        'tempat_tujuan',
        'tanggal_berangkat',
        'tanggal_kembali',
        'lama_hari',
        'instansi_pembebanan',
        'akun_pembebanan',
        'keterangan_lain',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_berangkat' => 'date',
            'tanggal_kembali' => 'date',
            'lama_hari' => 'integer',
        ];
    }

    /**
     * Pilihan alat angkut yang lazim dipakai.
     *
     * @return array<int, string>
     */
    public static function alatAngkutOptions(): array
    {
        return [
            'Angkutan Udara',
            'Angkutan Darat',
            'Angkutan Laut',
            'Kendaraan Dinas',
            'Kendaraan Umum',
        ];
    }

    /**
     * @return HasMany<SpdPelaksana, $this>
     */
    public function pelaksana(): HasMany
    {
        return $this->hasMany(SpdPelaksana::class, 'id_spd')->orderBy('urutan');
    }

    /**
     * @return HasMany<SpdPengikut, $this>
     */
    public function pengikut(): HasMany
    {
        return $this->hasMany(SpdPengikut::class, 'id_spd');
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
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pembuat');
    }

    /**
     * Lama perjalanan dalam hari, dihitung inklusif seperti pada SPD cetak:
     * berangkat dan kembali pada hari yang sama terhitung satu hari.
     */
    public static function hitungLamaHari(?string $berangkat, ?string $kembali): int
    {
        if (! $berangkat || ! $kembali) {
            return 1;
        }

        $mulai = Carbon::parse($berangkat)->startOfDay();
        $selesai = Carbon::parse($kembali)->startOfDay();

        if ($selesai->lessThan($mulai)) {
            return 1;
        }

        return (int) $mulai->diffInDays($selesai) + 1;
    }

    public function getPelaksanaUtamaAttribute(): ?SpdPelaksana
    {
        return $this->pelaksana->first();
    }

    /**
     * SPD ini milik pengguna tersebut — dibuatnya sendiri, atau namanya
     * tercantum sebagai pelaksana.
     */
    public function milik(User $pengguna): bool
    {
        return $this->id_pembuat === $pengguna->id
            || $this->pelaksana->contains('id_user', $pengguna->id);
    }

    /**
     * Boleh disunting atau dihapus oleh pengguna ini.
     *
     * Tim SDM berhak membaca seluruh SPD, tetapi bukan meralat surat orang
     * lain — karena itu penyuntingan tetap terbatas pada pemilik berkasnya
     * dan super administrator.
     */
    public function bolehDiubahOleh(User $pengguna): bool
    {
        return $pengguna->isAdmin() || $this->milik($pengguna);
    }

    /**
     * Rangkuman singkat untuk daftar dan judul berkas.
     */
    public function getRingkasanAttribute(): string
    {
        $nama = $this->pelaksana_utama?->nama ?? 'Tanpa pelaksana';
        $lain = max(0, $this->pelaksana->count() - 1);

        return $lain > 0 ? "{$nama} +{$lain} orang" : $nama;
    }
}
