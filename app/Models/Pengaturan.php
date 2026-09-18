<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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
     * Kunci API Anthropic untuk wawasan dan agen AI dashboard eksekutif.
     *
     * Dipasang super administrator lewat Administrasi Sistem dan disimpan
     * terenkripsi dengan APP_KEY — rahasia pihak ketiga tidak boleh
     * terbaca apa adanya pada salinan basis data. Bila kosong, kunci dari
     * ANTHROPIC_API_KEY pada .env (kalau ada) yang berlaku.
     */
    public const KUNCI_ANTHROPIC = 'kunci_api_anthropic';

    /**
     * Pengiriman data dashboard eksekutif terjadwal ke aplikasi tujuan
     * (menu Integrasi Data): sakelar, alamat tujuan, token yang dibawa
     * (rahasia, terenkripsi), dan jadwalnya.
     */
    public const INTEGRASI_AKTIF = 'integrasi_aktif';

    public const INTEGRASI_URL = 'integrasi_url';

    public const INTEGRASI_TOKEN_TUJUAN = 'integrasi_token_tujuan';

    /** tiap_jam | harian | mingguan */
    public const INTEGRASI_JADWAL = 'integrasi_jadwal';

    /** Jam kirim (HH:MM) untuk jadwal harian dan mingguan. */
    public const INTEGRASI_JAM = 'integrasi_jam';

    /** Hari kirim untuk jadwal mingguan: 1 = Senin … 7 = Minggu. */
    public const INTEGRASI_HARI = 'integrasi_hari';

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
        self::INTEGRASI_AKTIF => '0',
        self::INTEGRASI_JADWAL => 'harian',
        self::INTEGRASI_JAM => '06:00',
        self::INTEGRASI_HARI => '1',
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
     * Kunci API Anthropic yang sedang berlaku beserta asalnya.
     *
     * Nilai yang tidak dapat didekripsi — misalnya karena APP_KEY diganti —
     * diperlakukan sebagai kosong supaya fitur AI menonaktifkan diri alih-alih
     * memanggil API dengan kunci rusak.
     *
     * @return array{kunci: string, sumber: 'pengaturan'|'env'|null}
     */
    public static function kunciAnthropic(): array
    {
        $tersimpan = self::rahasia(self::KUNCI_ANTHROPIC);

        if ($tersimpan !== '') {
            return ['kunci' => $tersimpan, 'sumber' => 'pengaturan'];
        }

        $env = (string) config('ai.api_key');

        return ['kunci' => $env, 'sumber' => $env !== '' ? 'env' : null];
    }

    /**
     * Simpan nilai rahasia terenkripsi; string kosong menghapusnya.
     */
    public static function simpanRahasia(string $kunci, string $nilai): void
    {
        self::simpan([$kunci => $nilai === '' ? '' : Crypt::encryptString($nilai)]);
    }

    public static function rahasia(string $kunci): string
    {
        $terenkripsi = self::ambil($kunci);

        if ($terenkripsi === '') {
            return '';
        }

        try {
            return Crypt::decryptString($terenkripsi);
        } catch (DecryptException) {
            return '';
        }
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
