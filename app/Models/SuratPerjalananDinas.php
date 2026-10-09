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
        'no_tugas',
        'surat_tugas',
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
     * SPD ini berangkutan udara atau laut — perjalanan yang pasti ke luar
     * kota, jadi usulannya tidak boleh berkategori dalam kota.
     */
    public function lewatUdaraAtauLaut(): bool
    {
        $alat = mb_strtolower((string) $this->alat_angkut);

        return str_contains($alat, 'udara') || str_contains($alat, 'laut')
            || str_contains($alat, 'pesawat') || str_contains($alat, 'kapal');
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
     * Nomor SPD bagi pengguna tertentu.
     *
     * Nomor melekat pada tiap pelaksana, jadi orang kedua pada satu SPD
     * punya nomornya sendiri — bukan nomor orang pertama. Bila pengguna
     * bukan pelaksana (misalnya Tim SDM yang membuatkannya), nomor
     * pelaksana pertama yang dipakai.
     */
    /** Surat tugas sudah dilampirkan sejak SPD dibuat. */
    public function punyaSuratTugas(): bool
    {
        return filled($this->surat_tugas);
    }

    public function nomorUntuk(User $pengguna): ?string
    {
        return $this->pelaksana->firstWhere('id_user', $pengguna->id)?->nomor_surat
            ?? $this->pelaksana_utama?->nomor_surat;
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
     * Boleh dihapus utuh oleh pengguna ini.
     *
     * SPD bersama milik semua pelaksananya: pelaksana yang bukan pembuatnya
     * hanya menghapus dirinya sendiri dari daftar pelaksana, supaya surat
     * rekannya tidak ikut hilang. Pembuat dan super administrator tetap
     * dapat menghapus seluruhnya.
     */
    public function bolehDihapusUtuhOleh(User $pengguna): bool
    {
        return $pengguna->isAdmin()
            || $this->id_pembuat === $pengguna->id
            || ($this->milik($pengguna) && $this->pelaksana->count() <= 1);
    }

    /**
     * Pelaksana itu boleh dihapus dari SPD ini oleh pengguna tersebut,
     * sementara pelaksana lainnya tetap.
     *
     * Hanya bila masih ada pelaksana lain — SPD tanpa pelaksana dihapus
     * utuh saja. Pembuat dan super administrator menghapus siapa pun;
     * pelaksana lain hanya dirinya sendiri.
     */
    public function bolehMenghapusPelaksana(User $pengguna, SpdPelaksana $orang): bool
    {
        return $orang->id_spd === $this->id
            && $this->pelaksana->count() > 1
            && ($pengguna->isAdmin() || $this->id_pembuat === $pengguna->id || $orang->id_user === $pengguna->id);
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
