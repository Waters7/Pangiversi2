<?php

namespace App\Services;

use App\Enums\PesanWhatsapp;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Format pesan WhatsApp yang dapat diubah dari Administrasi Sistem.
 *
 * Format tersimpan pada tabel pengaturan, dan dicerminkan ke sebuah berkas:
 * pesan pemantau server justru dikirim saat basis data mungkin tidak dapat
 * dihubungi, jadi formatnya harus tetap terbaca tanpanya. Bila keduanya
 * tidak ada, format bawaan yang dipakai.
 */
class FormatWhatsapp
{
    /** Berkas cermin format, di folder pemantau server bersama cermin pengaturannya. */
    public const BERKAS_CERMIN = 'format-whatsapp.json';

    public function templat(PesanWhatsapp $pesan): string
    {
        try {
            $tersimpan = Pengaturan::ambil($pesan->kunciPengaturan(), '');
        } catch (Throwable) {
            $tersimpan = (string) ($this->cermin()[$pesan->value] ?? '');
        }

        return $tersimpan !== '' ? $tersimpan : $pesan->bawaan();
    }

    public function sudahDiubah(PesanWhatsapp $pesan): bool
    {
        return Pengaturan::ambil($pesan->kunciPengaturan(), '') !== '';
    }

    /**
     * Susun pesan: tiap penanda {kunci} diganti nilainya.
     *
     * @param  array<string, string>  $nilai
     */
    public function susun(PesanWhatsapp $pesan, array $nilai): string
    {
        $ganti = [];

        foreach ($nilai as $kunci => $isi) {
            $ganti['{'.$kunci.'}'] = $isi;
        }

        return strtr($this->templat($pesan), $ganti);
    }

    public function simpan(PesanWhatsapp $pesan, string $isi): void
    {
        // Ganti baris dari formulir selalu CRLF; pesan WhatsApp memakai LF.
        $isi = trim(str_replace(["\r\n", "\r"], "\n", $isi));

        // Format yang sama dengan bawaan disimpan kosong, supaya kelak ikut
        // berubah bila bawaannya diperbarui.
        Pengaturan::simpan([$pesan->kunciPengaturan() => $isi === $pesan->bawaan() ? '' : $isi]);
        $this->tulisCermin();
    }

    public function kembalikanBawaan(PesanWhatsapp $pesan): void
    {
        Pengaturan::simpan([$pesan->kunciPengaturan() => '']);
        $this->tulisCermin();
    }

    /**
     * Penanda dalam format yang tidak dikenal pada pesan ini.
     *
     * @return list<string>
     */
    public function penandaAsing(PesanWhatsapp $pesan, string $isi): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $isi, $cocok);

        return array_values(array_unique(array_diff($cocok[1], array_keys($pesan->penanda()))));
    }

    /**
     * Penanda wajib yang tidak ada pada format.
     *
     * @return list<string>
     */
    public function penandaHilang(PesanWhatsapp $pesan, string $isi): array
    {
        return array_values(array_filter(
            $pesan->penandaWajib(),
            fn (string $kunci) => ! str_contains($isi, '{'.$kunci.'}'),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function cermin(): array
    {
        $berkas = $this->berkasCermin();

        if (! is_file($berkas)) {
            return [];
        }

        $isi = json_decode((string) file_get_contents($berkas), true);

        return is_array($isi) ? $isi : [];
    }

    private function tulisCermin(): void
    {
        $isi = [];

        foreach (PesanWhatsapp::cases() as $pesan) {
            $isi[$pesan->value] = Pengaturan::ambil($pesan->kunciPengaturan(), '');
        }

        File::ensureDirectoryExists(dirname($this->berkasCermin()));
        file_put_contents($this->berkasCermin(), json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkasCermin(): string
    {
        return rtrim((string) config('pantau.folder'), '/\\').DIRECTORY_SEPARATOR.self::BERKAS_CERMIN;
    }
}
