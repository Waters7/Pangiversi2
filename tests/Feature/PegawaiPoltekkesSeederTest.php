<?php

namespace Tests\Feature;

use App\Enums\Golongan;
use App\Enums\PeranPengguna;
use App\Models\User;
use Database\Seeders\PegawaiPoltekkesSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Seeder pegawai dipakai dua kali: saat pemasangan awal dan — di produksi —
 * saat menambah pegawai baru ke data bawaan. Jalan keduanya tidak boleh
 * merusak isian yang sudah ada.
 */
class PegawaiPoltekkesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const NIP_DIREKTUR = '197104041994031002';

    private const NIP_WADIR = '197906082002122001';

    public function test_memuat_wakil_direktur_dengan_atasan_direktur(): void
    {
        $this->seed([UnitKerjaSeeder::class, PegawaiPoltekkesSeeder::class]);

        $wadir = User::firstWhere('nip', self::NIP_WADIR);
        $direktur = User::firstWhere('nip', self::NIP_DIREKTUR);

        $this->assertNotNull($wadir);
        $this->assertSame(PeranPengguna::Pimpinan->value, $wadir->role);
        $this->assertSame('Wakil Direktur II', $wadir->jabatan);
        $this->assertSame('III/c', $wadir->golongan);
        $this->assertSame('Penata', Golongan::from($wadir->golongan)->pangkat());
        $this->assertSame('DIR', $wadir->unit->kode);
        $this->assertTrue($wadir->id_atasan === $direktur->id);
        $this->assertNull($direktur->id_atasan);
        $this->assertTrue(Hash::check(self::NIP_WADIR, $wadir->password));
    }

    public function test_dijalankan_ulang_tidak_menimpa_isian_dan_atasan_yang_sudah_diubah(): void
    {
        $this->seed([UnitKerjaSeeder::class, PegawaiPoltekkesSeeder::class]);

        $wadir = User::firstWhere('nip', self::NIP_WADIR);
        $staf = User::where('role', PeranPengguna::DosenTendik->value)->first();
        $jumlah = User::count();

        // Administrator memindahkan garis atasan dan pengguna mengganti nomor
        // WhatsApp serta rekening lewat Profil — semuanya harus selamat dari
        // seeder ulang, meski berkasnya memuat nilai lama.
        $staf->update([
            'id_atasan' => $wadir->id,
            'no_hp' => '081200000000',
            'email' => 'sudah.diganti@contoh.test',
            'nama_bank' => 'Bank BRI',
            'nomor_rekening' => '0001-01-000000-00-0',
            'nama_rekening' => 'Nama Rekening',
        ]);
        $staf->update(['password' => Hash::make('sandi-baru')]);

        $this->seed(PegawaiPoltekkesSeeder::class);

        $staf->refresh();

        $this->assertSame($jumlah, User::count());
        $this->assertSame($wadir->id, $staf->id_atasan);
        $this->assertSame('081200000000', $staf->no_hp);
        $this->assertSame('sudah.diganti@contoh.test', $staf->email);
        $this->assertSame('0001-01-000000-00-0', $staf->nomor_rekening);
        $this->assertTrue(Hash::check('sandi-baru', $staf->password));
    }
}
