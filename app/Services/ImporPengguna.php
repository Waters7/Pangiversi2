<?php

namespace App\Services;

use App\Enums\Golongan;
use App\Enums\PeranPengguna;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat dan memperbarui akun pengguna secara massal dari sebuah
 * SumberDataPegawai, dengan NIP sebagai kunci identitas.
 */
class ImporPengguna
{
    /**
     * Ringkasan hasil impor.
     *
     * @var array{dibuat: int, diperbarui: int, dilewati: list<string>}
     */
    private array $ringkasan = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => []];

    /**
     * Bila hanya melengkapi, akun yang sudah ada cuma diisi pada kolom yang
     * masih kosong — nama, jabatan, nomor HP, dan sebagainya yang sudah
     * terisi dibiarkan. Mode ini dipakai seeder data bawaan supaya aman
     * dijalankan ulang di produksi; impor CSV oleh administrator tetap
     * menimpa dengan isi berkasnya.
     */
    private bool $hanyaMelengkapi = false;

    /**
     * @return array{dibuat: int, diperbarui: int, dilewati: list<string>}
     */
    public function jalankan(SumberDataPegawai $sumber, bool $hanyaMelengkapi = false): array
    {
        $this->hanyaMelengkapi = $hanyaMelengkapi;
        $baris = $sumber->ambil();

        // Referensi dimuat sekali agar impor ratusan baris tidak memicu N+1.
        $unit = UnitKerja::pluck('id', 'kode');
        $idPerNip = User::pluck('id', 'nip');

        DB::transaction(function () use ($baris, $unit, $idPerNip): void {
            foreach ($baris as $nomor => $data) {
                $this->prosesBaris($nomor + 2, $data, $unit, $idPerNip);
            }
        });

        return $this->ringkasan;
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  Collection<string, int>  $unit
     * @param  Collection<string, int>  $idPerNip
     */
    private function prosesBaris(int $nomorBaris, array $data, $unit, $idPerNip): void
    {
        if (blank($data['nip']) || blank($data['nama'])) {
            $this->ringkasan['dilewati'][] = "Baris {$nomorBaris}: nama dan NIP wajib diisi.";

            return;
        }

        $peran = PeranPengguna::tryFrom((string) $data['role']);

        if ($data['role'] !== null && $peran === null) {
            $this->ringkasan['dilewati'][] = "Baris {$nomorBaris}: peran \"{$data['role']}\" tidak dikenali.";

            return;
        }

        // Sebagian berkas kepegawaian mencantumkan kotak surat bersama pada
        // beberapa orang. Surel yang sudah dipakai NIP lain dilewati agar
        // satu baris bermasalah tidak menggagalkan seluruh impor.
        $email = $data['email'];

        if (filled($email)) {
            $pemilik = User::where('email', $email)->value('nip');

            if ($pemilik !== null && $pemilik !== $data['nip']) {
                $this->ringkasan['dilewati'][] = "Baris {$nomorBaris}: surel \"{$email}\" sudah dipakai NIP {$pemilik}, kolom surel dikosongkan.";
                $email = null;
            }
        }

        $atribut = [
            'nama' => $data['nama'],
            'email' => $email,
            'no_hp' => $data['no_hp'] ?? null,
            'jabatan' => $data['jabatan'],
            // Golongan dibakukan lewat enum, sehingga "iii/a" maupun
            // "Penata Muda (III/a)" tersimpan seragam sebagai III/a.
            'golongan' => Golongan::dari($data['golongan'] ?? null)?->value,
            'id_unit' => $data['unit_kode'] ? $unit->get($data['unit_kode']) : null,
            'id_atasan' => $data['atasan_nip'] ? $idPerNip->get($data['atasan_nip']) : null,
            'nama_bank' => $data['nama_bank'],
            'nomor_rekening' => $data['nomor_rekening'],
            'nama_rekening' => $data['nama_rekening'],
        ];

        $pengguna = User::firstWhere('nip', $data['nip']);

        if ($pengguna) {
            $pengguna->update($this->perubahanUntuk($pengguna, $atribut, $peran));

            $this->ringkasan['diperbarui']++;

            return;
        }

        $baru = User::create($atribut + [
            'nip' => $data['nip'],
            'role' => ($peran ?? PeranPengguna::DosenTendik)->value,
            // Kata sandi awal boleh ditentukan berkas impor. Bila kosong,
            // akun dibuat dengan sandi acak dan harus direset admin dulu.
            'password' => Hash::make(
                filled($data['password'] ?? null) ? $data['password'] : Str::random(32)
            ),
        ]);

        $idPerNip->put($baru->nip, $baru->id);

        $this->ringkasan['dibuat']++;
    }

    /**
     * Kolom yang boleh ditulis ke akun yang sudah ada.
     *
     * Sel yang kosong berarti "tidak ada data", bukan "kosongkan": surel,
     * nomor HP, dan rekening yang sudah diisi pengguna lewat Profil tidak
     * terhapus. Peran hanya ditimpa bila kolomnya diisi. Dalam mode hanya
     * melengkapi, kolom yang sudah terisi pada akun pun dibiarkan.
     *
     * @param  array<string, mixed>  $atribut
     * @return array<string, mixed>
     */
    private function perubahanUntuk(User $pengguna, array $atribut, ?PeranPengguna $peran): array
    {
        $terisi = array_filter($atribut, fn ($nilai) => $nilai !== null && $nilai !== '');

        if ($this->hanyaMelengkapi) {
            return array_filter($terisi, fn ($nilai, $kolom) => blank($pengguna->{$kolom}), ARRAY_FILTER_USE_BOTH);
        }

        return $peran ? $terisi + ['role' => $peran->value] : $terisi;
    }
}
