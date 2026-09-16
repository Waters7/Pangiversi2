<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi yang boleh diubah Super Administrator lewat menu
 * Administrasi Sistem, disimpan sebagai pasangan kunci–nilai.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai'];

    /** Berapa hari setelah perjalanan selesai pengingat pertama dikirim. */
    public const PENGINGAT_HARI = 'pengingat_dokumen_hari';

    /** Jeda antar pengingat berikutnya selama dokumen belum lengkap. */
    public const PENGINGAT_ULANG = 'pengingat_dokumen_ulang';

    /** Batas jumlah pengingat agar notifikasi tidak menumpuk tanpa henti. */
    public const PENGINGAT_MAKS = 'pengingat_dokumen_maksimal';

    /** Sakelar utama pengingat. */
    public const PENGINGAT_AKTIF = 'pengingat_dokumen_aktif';

    /**
     * Tanggal dikeluarkan SPD terbuka bagi seluruh peran.
     *
     * Bawaannya terkunci: hanya pimpinan dan administrator yang boleh
     * menyesuaikan tanggal terbit. Super administrator membukanya sementara
     * bila ada SPD yang harus diterbitkan dengan tanggal mundur (backdate),
     * lalu menguncinya kembali.
     */
    public const TANGGAL_SPD_TERBUKA = 'tanggal_spd_terbuka';

    /**
     * Token bersama untuk API dashboard eksekutif.
     *
     * Dibuat dan dicabut super administrator lewat Administrasi Sistem
     * sehingga tidak perlu menyunting .env di server. Bila kosong, token
     * dari PANGI_API_TOKEN pada .env (kalau ada) yang berlaku.
     */
    public const TOKEN_API = 'token_api';

    /**
     * Nilai bawaan bila belum pernah diatur.
     *
     * @var array<string, string>
     */
    public const BAWAAN = [
        self::PENGINGAT_AKTIF => '1',
        self::PENGINGAT_HARI => '3',
        self::PENGINGAT_ULANG => '7',
        self::PENGINGAT_MAKS => '4',
        self::TANGGAL_SPD_TERBUKA => '0',
    ];

    private const CACHE = 'pengaturan.semua';

    /**
     * @return array<string, string>
     */
    public static function semua(): array
    {
        // Nilai tersimpan harus berada di kiri: operator + pada array PHP
        // mempertahankan nilai operand kiri untuk kunci yang sama.
        return Cache::rememberForever(
            self::CACHE,
            fn () => self::query()->pluck('nilai', 'kunci')->all() + self::BAWAAN
        );
    }

    /**
     * Nilai satu pengaturan, dibaca dari peta yang sudah disimpan semua().
     *
     * Sebelumnya tiap pemanggilan menjalankan kuerinya sendiri. Pemanggilnya
     * ada di dalam perulangan — tenggang laporan dihitung untuk tiap
     * perjalanan pada dashboard — sehingga satu halaman bisa menanyakan
     * pengaturan yang sama puluhan kali.
     */
    public static function ambil(string $kunci, ?string $bawaan = null): string
    {
        $tersimpan = self::semua()[$kunci] ?? null;

        return $tersimpan ?? $bawaan ?? self::BAWAAN[$kunci] ?? '';
    }

    public static function angka(string $kunci): int
    {
        return (int) self::ambil($kunci);
    }

    public static function aktif(string $kunci): bool
    {
        return self::ambil($kunci) === '1';
    }

    /**
     * Token API yang sedang berlaku beserta asalnya.
     *
     * Token dari Administrasi Sistem didahulukan atas .env supaya token
     * yang dibuat administrator langsung berlaku tanpa menyentuh server.
     *
     * @return array{token: string, sumber: 'pengaturan'|'env'|null}
     */
    public static function tokenApi(): array
    {
        $tersimpan = self::ambil(self::TOKEN_API);

        if ($tersimpan !== '') {
            return ['token' => $tersimpan, 'sumber' => 'pengaturan'];
        }

        $env = (string) config('api.token');

        return ['token' => $env, 'sumber' => $env !== '' ? 'env' : null];
    }

    /**
     * @param  array<string, string|int|bool|null>  $nilai
     */
    public static function simpan(array $nilai): void
    {
        foreach ($nilai as $kunci => $isi) {
            self::updateOrCreate(['kunci' => $kunci], ['nilai' => (string) $isi]);
        }

        Cache::forget(self::CACHE);
    }
}
