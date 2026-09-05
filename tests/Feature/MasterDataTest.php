<?php

namespace Tests\Feature;

use App\Models\KomponenBiaya;
use App\Models\LokasiTujuan;
use App\Models\TahunAnggaran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->administrator()->create();
    }

    // ── Kontrol akses ──

    public function test_halaman_master_data_menolak_pegawai_biasa(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        foreach (['master.unit-kerja', 'master.lokasi', 'master.komponen-biaya', 'master.tahun-anggaran'] as $rute) {
            $this->actingAs($pegawai)->get(route($rute))->assertForbidden();
        }
    }

    public function test_administrator_dapat_membuka_semua_halaman_master_data(): void
    {
        $admin = $this->administrator();

        foreach (['master.unit-kerja', 'master.lokasi', 'master.komponen-biaya', 'master.tahun-anggaran'] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
        }
    }

    // ── Unit kerja ──

    public function test_administrator_dapat_menambah_unit_kerja(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('master.unit-kerja.store'), [
                'kode' => 'adum',
                'nama' => 'Bagian Administrasi Umum',
                'keterangan' => 'Kepegawaian dan keuangan',
                'is_aktif' => '1',
            ])
            ->assertRedirect(route('master.unit-kerja'));

        // Kode selalu disimpan dalam huruf kapital.
        $this->assertDatabaseHas('unit_kerja', [
            'kode' => 'ADUM',
            'nama' => 'Bagian Administrasi Umum',
            'is_aktif' => true,
        ]);
    }

    public function test_kode_unit_kerja_harus_unik(): void
    {
        UnitKerja::factory()->create(['kode' => 'ADUM']);

        $this->actingAs($this->administrator())
            ->post(route('master.unit-kerja.store'), ['kode' => 'ADUM', 'nama' => 'Duplikat'])
            ->assertSessionHasErrors('kode');
    }

    public function test_unit_kerja_yang_masih_punya_pegawai_tidak_dapat_dihapus(): void
    {
        $unit = UnitKerja::factory()->create();
        User::factory()->create(['id_unit' => $unit->id]);

        $this->actingAs($this->administrator())
            ->delete(route('master.unit-kerja.destroy', $unit))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id]);
    }

    public function test_unit_kerja_tanpa_pegawai_dapat_dihapus(): void
    {
        // Administrator dibuat lebih dulu agar tidak ikut ditempatkan di unit ini.
        $admin = $this->administrator();
        $unit = UnitKerja::factory()->create();

        $this->actingAs($admin)
            ->delete(route('master.unit-kerja.destroy', $unit))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('unit_kerja', ['id' => $unit->id]);
    }

    // ── Lokasi tujuan ──

    public function test_administrator_dapat_menambah_lokasi_tujuan(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('master.lokasi.store'), [
                'nama' => 'Manado',
                'provinsi' => 'Sulawesi Utara',
                'jenis' => 'dalam_kota',
            ])
            ->assertRedirect(route('master.lokasi'));

        $this->assertDatabaseHas('lokasi_tujuan', ['nama' => 'Manado', 'jenis' => 'dalam_kota']);
    }

    public function test_jenis_lokasi_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('master.lokasi.store'), ['nama' => 'Manado', 'jenis' => 'luar_angkasa'])
            ->assertSessionHasErrors('jenis');
    }

    public function test_lokasi_yang_dipakai_usulan_tidak_dapat_dihapus(): void
    {
        $lokasi = LokasiTujuan::factory()->create();
        User::factory()->create();
        Usulan::factory()->create(['id_lokasi' => $lokasi->id]);

        $this->actingAs($this->administrator())
            ->delete(route('master.lokasi.destroy', $lokasi))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('lokasi_tujuan', ['id' => $lokasi->id]);
    }

    // ── Komponen biaya ──

    public function test_administrator_dapat_menambah_komponen_biaya(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('master.komponen-biaya.store'), [
                'nama' => 'Uang Harian',
                'satuan' => 'OH',
                'harga_satuan' => 430000,
                'jenis' => 'sbm',
            ])
            ->assertRedirect(route('master.komponen-biaya'));

        $this->assertDatabaseHas('komponen_biaya', ['nama' => 'Uang Harian', 'harga_satuan' => 430000]);
    }

    public function test_harga_satuan_tidak_boleh_negatif(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('master.komponen-biaya.store'), [
                'nama' => 'Uang Harian',
                'satuan' => 'OH',
                'harga_satuan' => -1,
                'jenis' => 'sbm',
            ])
            ->assertSessionHasErrors('harga_satuan');
    }

    public function test_komponen_biaya_dapat_diperbarui_tanpa_bentrok_nama_sendiri(): void
    {
        $komponen = KomponenBiaya::factory()->create(['nama' => 'Uang Harian']);

        $this->actingAs($this->administrator())
            ->put(route('master.komponen-biaya.update', $komponen), [
                'nama' => 'Uang Harian',
                'satuan' => 'OK',
                'harga_satuan' => 500000,
                'jenis' => 'at_cost',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('komponen_biaya', [
            'id' => $komponen->id,
            'satuan' => 'OK',
            'jenis' => 'at_cost',
        ]);
    }

    // ── Tahun anggaran ──

    public function test_mengaktifkan_tahun_anggaran_menonaktifkan_tahun_lain(): void
    {
        $lama = TahunAnggaran::factory()->aktif()->create(['tahun' => 2025]);
        $baru = TahunAnggaran::factory()->create(['tahun' => 2026]);

        $this->actingAs($this->administrator())
            ->put(route('master.tahun-anggaran.aktifkan', $baru))
            ->assertSessionHas('success');

        $this->assertFalse($lama->fresh()->is_aktif);
        $this->assertTrue($baru->fresh()->is_aktif);
    }

    public function test_tahun_anggaran_hanya_boleh_satu_per_tahun(): void
    {
        TahunAnggaran::factory()->create(['tahun' => 2026]);

        $this->actingAs($this->administrator())
            ->post(route('master.tahun-anggaran.store'), ['tahun' => 2026, 'pagu' => 1000])
            ->assertSessionHasErrors('tahun');
    }

    public function test_realisasi_tahun_anggaran_dihitung_dari_total_keuangan(): void
    {
        $tahun = TahunAnggaran::factory()->create(['tahun' => 2026, 'pagu' => 10_000_000]);
        User::factory()->create();

        $usulan = Usulan::factory()->create(['id_tahun_anggaran' => $tahun->id]);
        $usulan->keuangan()->create(['total' => 4_000_000, 'uang_muka' => 0, 'sisa' => 0]);

        $this->assertSame(4_000_000.0, $tahun->fresh()->realisasi);
        $this->assertSame(6_000_000.0, $tahun->fresh()->sisa_pagu);
    }
}
