<?php

namespace Tests\Feature;

use App\Enums\JenisAkses;
use App\Enums\Kemampuan;
use App\Enums\MenuAplikasi;
use App\Enums\PeranPengguna;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Peran & Hak Akses pada Administrasi Sistem: super administrator menambah
 * peran dan mengatur menu yang boleh dilihat, diubah, dan dihapus tiap
 * peran. Peran yang belum pernah diatur tetap memakai bawaan enumnya.
 */
class PeranHakAksesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** @return list<string> */
    private function nilai(array $kemampuan): array
    {
        return array_map(fn (Kemampuan $k) => $k->value, $kemampuan);
    }

    // ── Matriks menu ──

    public function test_setiap_kemampuan_punya_tepat_satu_menu(): void
    {
        $tercakup = MenuAplikasi::seluruhKemampuan();

        $this->assertCount(count(Kemampuan::cases()), $tercakup);
        $this->assertEqualsCanonicalizing(Kemampuan::cases(), $tercakup);
    }

    public function test_matriks_menyusun_kemampuan_menurut_kolom_lihat_ubah_hapus(): void
    {
        $this->assertSame([Kemampuan::MelihatKeuangan], MenuAplikasi::Keuangan->kemampuanJenis(JenisAkses::Lihat));
        $this->assertSame([Kemampuan::MengelolaBiaya, Kemampuan::MemvalidasiBiaya], MenuAplikasi::Keuangan->kemampuanJenis(JenisAkses::Ubah));
        $this->assertSame([Kemampuan::MenghapusMasterData], MenuAplikasi::MasterData->kemampuanJenis(JenisAkses::Hapus));
        $this->assertSame([], MenuAplikasi::JejakAudit->kemampuanJenis(JenisAkses::Hapus));
    }

    // ── Akses halaman ──

    public function test_halaman_peran_hanya_untuk_super_administrator(): void
    {
        $this->actingAs($this->admin)
            ->get(route('administrasi.peran'))
            ->assertOk()
            ->assertSee('Peran &amp; Hak Akses', false)
            ->assertSee('Tim SDM')
            ->assertSee('Mahasiswa')
            ->assertSee('Tambah Peran');

        $this->actingAs($this->admin)
            ->get(route('administrasi.peran', ['peran' => 'tim_sdm']))
            ->assertOk()
            ->assertSee('Centang semua')
            ->assertSee('Simpan Hak Akses');

        $this->actingAs($this->pengguna(User::ROLE_TIM_SDM))
            ->get(route('administrasi.peran'))
            ->assertForbidden();
    }

    public function test_membuka_halaman_menyalin_peran_bawaan_beserta_hak_akses_enumnya(): void
    {
        $this->assertSame(0, Peran::count());

        $this->actingAs($this->admin)->get(route('administrasi.peran'));

        $this->assertCount(count(PeranPengguna::cases()), Peran::all());

        $timSdm = Peran::where('kode', PeranPengguna::TimSdm->value)->firstOrFail();
        $this->assertTrue($timSdm->bawaan);
        $this->assertEqualsCanonicalizing(PeranPengguna::TimSdm->kemampuan(), $timSdm->kemampuan());
    }

    // ── Sebelum diatur: bawaan enum berlaku ──

    public function test_tanpa_pengaturan_hak_akses_bawaan_enum_tetap_berlaku(): void
    {
        $timSdm = $this->pengguna(User::ROLE_TIM_SDM);

        $this->assertTrue($timSdm->punyaKemampuan(Kemampuan::MengelolaPengguna));
        $this->assertTrue($timSdm->punyaKemampuan(Kemampuan::MenghapusPengguna));
        $this->assertFalse($timSdm->punyaKemampuan(Kemampuan::MengelolaPeran));
        $this->assertSame('Tim SDM', $timSdm->role_label);
    }

    // ── Mengubah hak akses peran bawaan ──

    public function test_mencabut_hak_akses_peran_bawaan_langsung_berlaku(): void
    {
        $timSdm = $this->pengguna(User::ROLE_TIM_SDM);
        $this->actingAs($timSdm)->get(route('jadwal-perjalanan'))->assertOk();

        $this->actingAs($this->admin)->get(route('administrasi.peran'));
        $peran = Peran::where('kode', PeranPengguna::TimSdm->value)->firstOrFail();

        $tanpaJadwal = array_values(array_filter(
            PeranPengguna::TimSdm->kemampuan(),
            fn (Kemampuan $k) => $k !== Kemampuan::MelihatJadwalPerjalanan,
        ));

        $this->actingAs($this->admin)
            ->put(route('administrasi.peran.update', $peran), [
                'nama' => 'Tim SDM',
                'keterangan' => $peran->keterangan,
                'kemampuan' => $this->nilai($tanpaJadwal),
            ])
            ->assertRedirect(route('administrasi.peran', ['peran' => 'tim_sdm']))
            ->assertSessionHas('success');

        $this->actingAs($timSdm)->get(route('jadwal-perjalanan'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('dashboard'))->assertOk()->assertDontSee(route('jadwal-perjalanan'));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'pengguna', 'deskripsi' => 'Hak akses peran "Tim SDM" disimpan — dicabut: Melihat jadwal keberangkatan.']);
    }

    public function test_hak_hapus_dapat_dicabut_tanpa_mencabut_hak_mengelola(): void
    {
        $timSdm = $this->pengguna(User::ROLE_TIM_SDM);
        $korban = $this->pengguna(User::ROLE_DOSEN_TENDIK);

        $this->actingAs($this->admin)->get(route('administrasi.peran'));
        $peran = Peran::where('kode', PeranPengguna::TimSdm->value)->firstOrFail();
        $tanpaHapus = array_values(array_filter(PeranPengguna::TimSdm->kemampuan(), fn (Kemampuan $k) => $k !== Kemampuan::MenghapusPengguna));

        $this->actingAs($this->admin)->put(route('administrasi.peran.update', $peran), [
            'nama' => 'Tim SDM', 'kemampuan' => $this->nilai($tanpaHapus),
        ]);

        $this->actingAs($timSdm)->get(route('administrasi'))->assertOk();
        $this->actingAs($timSdm)->delete(route('administrasi.destroy', $korban))->assertForbidden();
        $this->assertModelExists($korban);
    }

    public function test_super_administrator_tidak_dapat_dikurangi(): void
    {
        $this->actingAs($this->admin)->get(route('administrasi.peran'));
        $peran = Peran::where('kode', PeranPengguna::SuperAdministrator->value)->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('administrasi.peran.update', $peran), ['nama' => 'Super Administrator', 'kemampuan' => []])
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->punyaKemampuan(Kemampuan::MengelolaPeran));
        $this->actingAs($this->admin)
            ->get(route('administrasi.peran', ['peran' => 'super_administrator']))
            ->assertOk()
            ->assertSee('tidak dapat diubah');
    }

    // ── Peran buatan ──

    public function test_menambah_peran_baru_lalu_memberinya_akses_menu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('administrasi.peran.store'), ['nama' => 'Auditor Internal', 'keterangan' => 'Membaca laporan dan jejak audit'])
            ->assertRedirect(route('administrasi.peran', ['peran' => 'auditor_internal']));

        $peran = Peran::where('kode', 'auditor_internal')->firstOrFail();
        $this->assertFalse($peran->bawaan);
        $this->assertSame([Kemampuan::MengajukanUsulan], $peran->kemampuan());
        $this->assertArrayHasKey('auditor_internal', User::roleOptions());
        $this->assertSame('Auditor Internal', User::roleOptions()['auditor_internal']);

        $auditor = $this->pengguna('auditor_internal');
        $this->assertSame('Auditor Internal', $auditor->role_label);
        $this->actingAs($auditor)->get(route('dashboard'))->assertOk();
        $this->actingAs($auditor)->get(route('audit-log'))->assertForbidden();

        $this->actingAs($this->admin)->put(route('administrasi.peran.update', $peran), [
            'nama' => 'Auditor Internal',
            'kemampuan' => $this->nilai([Kemampuan::MengajukanUsulan, Kemampuan::MelihatJejakAudit, Kemampuan::MelihatLaporan]),
        ]);

        $this->actingAs($auditor)->get(route('audit-log'))->assertOk();
        $this->actingAs($auditor)->get(route('laporan'))->assertOk();
        $this->actingAs($auditor)->get(route('keuangan'))->assertForbidden();
    }

    public function test_nama_peran_yang_kodenya_sudah_ada_ditolak(): void
    {
        $this->actingAs($this->admin)->get(route('administrasi.peran'));

        $this->actingAs($this->admin)
            ->from(route('administrasi.peran'))
            ->post(route('administrasi.peran.store'), ['nama' => 'Tim SDM'])
            ->assertRedirect(route('administrasi.peran'))
            ->assertSessionHasErrors('nama');

        $this->assertSame(1, Peran::where('kode', 'tim_sdm')->count());
    }

    public function test_peran_buatan_dihapus_hanya_bila_tidak_dipakai_dan_peran_bawaan_tidak_pernah(): void
    {
        $this->actingAs($this->admin)->post(route('administrasi.peran.store'), ['nama' => 'Peninjau']);
        $peran = Peran::where('kode', 'peninjau')->firstOrFail();
        $pemakai = $this->pengguna('peninjau');

        $this->actingAs($this->admin)
            ->from(route('administrasi.peran', ['peran' => 'peninjau']))
            ->delete(route('administrasi.peran.destroy', $peran))
            ->assertSessionHasErrors('peran');
        $this->assertModelExists($peran);

        $pemakai->delete();
        $this->actingAs($this->admin)
            ->delete(route('administrasi.peran.destroy', $peran))
            ->assertRedirect(route('administrasi.peran'));
        $this->assertModelMissing($peran);
        $this->assertSame(0, $peran->hakAkses()->count());

        $this->actingAs($this->admin)->get(route('administrasi.peran'));
        $bawaan = Peran::where('kode', PeranPengguna::TimSdm->value)->firstOrFail();
        $this->actingAs($this->admin)->delete(route('administrasi.peran.destroy', $bawaan))->assertForbidden();
    }

    // ── Kemampuan hapus master data ──

    public function test_menghapus_master_data_membutuhkan_kemampuan_tersendiri(): void
    {
        // Unit tanpa pegawai, supaya penghapusannya hanya bergantung pada hak akses.
        $unit = UnitKerja::create(['kode' => 'UJI', 'nama' => 'Unit Uji']);
        User::query()->update(['id_unit' => null]);

        $this->actingAs($this->admin)->get(route('administrasi.peran'));

        // Peran buatan dengan hak mengelola master data tetapi tanpa hak menghapus.
        $this->actingAs($this->admin)->post(route('administrasi.peran.store'), ['nama' => 'Pengelola Referensi']);
        $peran = Peran::where('kode', 'pengelola_referensi')->firstOrFail();
        $this->actingAs($this->admin)->put(route('administrasi.peran.update', $peran), [
            'nama' => 'Pengelola Referensi',
            'kemampuan' => $this->nilai([Kemampuan::MengajukanUsulan, Kemampuan::MelihatLaporan, Kemampuan::MengelolaMasterData]),
        ]);
        $pengelola = $this->pengguna('pengelola_referensi');

        $this->actingAs($pengelola)->get(route('master.unit-kerja'))->assertOk();
        $this->actingAs($pengelola)->delete(route('master.unit-kerja.destroy', $unit))->assertForbidden();
        $this->assertModelExists($unit);

        // Pengguna yang dibuat pabrik boleh saja ditempatkan di unit ini; kosongkan lagi.
        User::query()->update(['id_unit' => null]);
        $this->actingAs($this->admin)->delete(route('master.unit-kerja.destroy', $unit))->assertRedirect();
        $this->assertModelMissing($unit);
    }
}
