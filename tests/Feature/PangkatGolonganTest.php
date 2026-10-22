<?php

namespace Tests\Feature;

use App\Models\PangkatGolongan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pangkat dan golongan sebagai master data: daftar PNS, PPPK, dan
 * outsourcing sudah terisi, dan satuan kerja menambah pilihan lain sendiri.
 */
class PangkatGolonganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
    }

    public function test_daftar_bawaan_memuat_pns_pppk_dan_outsourcing(): void
    {
        $this->assertSame(17 + 17 + 1, PangkatGolongan::count());
        $this->assertSame('Penata Muda (III/a)', PangkatGolongan::tulisanUntuk('III/a'));
        $this->assertSame('Golongan IX (PPPK)', PangkatGolongan::tulisanUntuk('IX'));
        $this->assertSame('Outsourcing', PangkatGolongan::tulisanUntuk('OS'));

        $this->actingAs($this->admin)
            ->get(route('master.pangkat-golongan'))
            ->assertOk()
            ->assertSee('Outsourcing')
            ->assertSee('Penata Muda (III/a)');
    }

    public function test_pilihan_tambahan_ditawarkan_pada_formulir_pengguna_dan_spd(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.pangkat-golongan.store'), [
                'kode' => 'HON',
                'tulisan' => 'Tenaga Honorer',
                'kelompok' => 'Non-ASN',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('administrasi'))
            ->assertOk()
            ->assertSee('<option value="HON">Tenaga Honorer</option>', false);

        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'golongan' => null]);

        $this->actingAs($this->admin)
            ->put(route('administrasi.update', $pegawai), [
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'email' => $pegawai->email,
                'role' => $pegawai->role,
                'golongan' => 'HON',
            ])
            ->assertSessionHas('success');

        $this->assertSame('HON', $pegawai->fresh()->golongan);

        // Tulisannya mengisi sendiri kolom Pangkat dan Golongan pada SPD.
        $this->actingAs($pegawai->fresh())
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('<option value="Tenaga Honorer">Tenaga Honorer</option>', false)
            // Data pelaksana dikirim ke Alpine lewat @js, yang menyandikan tanda petik.
            ->assertSee(str_replace('"', '\\u0022', '"pangkat_golongan":"Tenaga Honorer"'), false);
    }

    public function test_kode_wajib_unik_tanpa_spasi_dan_paling_panjang_delapan_karakter(): void
    {
        $isi = ['tulisan' => 'Pilihan Baru', 'kelompok' => 'Non-ASN'];

        $this->actingAs($this->admin)->post(route('master.pangkat-golongan.store'), $isi + ['kode' => 'OS'])->assertSessionHasErrors('kode');
        $this->actingAs($this->admin)->post(route('master.pangkat-golongan.store'), $isi + ['kode' => 'TERLALUPANJANG'])->assertSessionHasErrors('kode');
        $this->actingAs($this->admin)->post(route('master.pangkat-golongan.store'), $isi + ['kode' => 'A B'])->assertSessionHasErrors('kode');

        $this->assertFalse(PangkatGolongan::where('tulisan', 'Pilihan Baru')->exists());
    }

    public function test_kode_yang_diganti_ikut_diganti_pada_pegawai(): void
    {
        $outsourcing = PangkatGolongan::firstWhere('kode', 'OS');
        $pegawai = User::factory()->create(['golongan' => 'OS']);

        $this->actingAs($this->admin)
            ->put(route('master.pangkat-golongan.update', $outsourcing), [
                'kode' => 'ALIH',
                'tulisan' => 'Tenaga Alih Daya',
                'kelompok' => 'Non-ASN',
            ])
            ->assertSessionHas('success');

        $this->assertSame('ALIH', $pegawai->fresh()->golongan);
        $this->assertSame('Tenaga Alih Daya', PangkatGolongan::tulisanUntuk('ALIH'));
    }

    public function test_pilihan_yang_dipakai_pegawai_tidak_dapat_dihapus(): void
    {
        $outsourcing = PangkatGolongan::firstWhere('kode', 'OS');
        $pegawai = User::factory()->create(['golongan' => 'OS']);

        $this->actingAs($this->admin)
            ->delete(route('master.pangkat-golongan.destroy', $outsourcing))
            ->assertSessionHas('error');
        $this->assertModelExists($outsourcing);

        $pegawai->update(['golongan' => null]);

        $this->actingAs($this->admin)
            ->delete(route('master.pangkat-golongan.destroy', $outsourcing))
            ->assertSessionHas('success');
        $this->assertModelMissing($outsourcing);
    }

    public function test_tanpa_hak_master_data_halamannya_tertutup(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($pegawai)->get(route('master.pangkat-golongan'))->assertForbidden();
        $this->actingAs($pegawai)
            ->post(route('master.pangkat-golongan.store'), ['kode' => 'HON', 'tulisan' => 'Tenaga Honorer', 'kelompok' => 'Non-ASN'])
            ->assertForbidden();
    }

    public function test_tulisan_bebas_pada_impor_dikenali(): void
    {
        $this->assertSame('III/a', PangkatGolongan::dari('Penata Muda (III/a)')?->kode);
        $this->assertSame('IX', PangkatGolongan::dari('Golongan IX (PPPK)')?->kode);
        $this->assertSame('OS', PangkatGolongan::dari('outsourcing')?->kode);
        $this->assertSame('OS', PangkatGolongan::dari('os')?->kode);
        $this->assertNull(PangkatGolongan::dari('Tidak Dikenal'));
    }
}
