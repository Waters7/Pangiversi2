<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Siapa yang boleh mengambil data lewat API Mappalus.
 *
 * Tokennya dibuat PANGI dan diserahkan kepada Mappalus. Yang disimpan hanya
 * sidiknya, jadi basis data yang bocor pun tidak membuka API ini, dan tokennya
 * tampil sekali saat dibuat. Tokennya terpisah dari token API dashboard, supaya
 * yang satu tidak membuka data yang lain.
 */
class AksesMappalus
{
    /** API terbuka hanya bila sudah ada token yang berlaku. */
    public function terbuka(): bool
    {
        return $this->sidikToken() !== '';
    }

    public function tokenCocok(?string $token): bool
    {
        // Sidiknya dibandingkan dengan hash_equals supaya lama pemeriksaan
        // tidak membocorkan seberapa dekat tebakannya.
        return filled($token) && $this->terbuka() && hash_equals($this->sidikToken(), $this->sidik($token));
    }

    /**
     * Bila daftar alamat diisi, hanya alamat itu yang boleh memanggil API.
     * Daftar kosong berarti alamat pemanggil tidak dibatasi.
     */
    public function alamatDiizinkan(string $ip): bool
    {
        $daftar = $this->daftarAlamat();

        return $daftar === [] || IpUtils::checkIp($ip, $daftar);
    }

    /**
     * Terbitkan token baru; token lama seketika tidak berlaku.
     *
     * @return string Tokennya, satu-satunya kesempatan membacanya.
     */
    public function buatToken(): string
    {
        $token = Str::random(64);

        Pengaturan::simpan([
            Pengaturan::MAPPALUS_TOKEN_SIDIK => $this->sidik($token),
            Pengaturan::MAPPALUS_TOKEN_UJUNG => mb_substr($token, -4),
            Pengaturan::MAPPALUS_TOKEN_DIBUAT => now()->toIso8601String(),
        ]);

        return $token;
    }

    public function cabutToken(): void
    {
        Pengaturan::simpan([
            Pengaturan::MAPPALUS_TOKEN_SIDIK => '',
            Pengaturan::MAPPALUS_TOKEN_UJUNG => '',
            Pengaturan::MAPPALUS_TOKEN_DIBUAT => '',
        ]);
    }

    /**
     * @param  list<string>  $alamat
     */
    public function simpanDaftarAlamat(array $alamat): void
    {
        Pengaturan::simpan([Pengaturan::MAPPALUS_ALAMAT_IP => implode("\n", $alamat)]);
    }

    /**
     * @return list<string>
     */
    public function daftarAlamat(): array
    {
        return array_values(array_filter(explode("\n", Pengaturan::ambil(Pengaturan::MAPPALUS_ALAMAT_IP))));
    }

    /**
     * @return array{terbuka: bool, ujung: string, dibuat: string}
     */
    public function keadaanToken(): array
    {
        return [
            'terbuka' => $this->terbuka(),
            'ujung' => Pengaturan::ambil(Pengaturan::MAPPALUS_TOKEN_UJUNG),
            'dibuat' => Pengaturan::ambil(Pengaturan::MAPPALUS_TOKEN_DIBUAT),
        ];
    }

    private function sidikToken(): string
    {
        return Pengaturan::ambil(Pengaturan::MAPPALUS_TOKEN_SIDIK);
    }

    private function sidik(string $token): string
    {
        return hash('sha256', $token);
    }
}
