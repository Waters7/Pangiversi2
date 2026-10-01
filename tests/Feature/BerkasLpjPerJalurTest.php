<?php

namespace Tests\Feature;

use App\Enums\BerkasLpj;
use App\Enums\JenisPerjadin;
use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\PengaturanBerkasLpj;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas pertanggungjawaban yang ditagih diatur per jalur pengajuan lewat
 * Administrasi Sistem. Jalur yang belum pernah diatur menagih berkas yang
 * sama persis seperti sebelum pengaturan ini ada.
 */
class BerkasLpjPerJalurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $this->seed(KategoriPerjadinSeeder::class);
    }

    private function atur(): PengaturanBerkasLpj
    {
        return app(PengaturanBerkasLpj::class);
    }

    private function usulan(string $kodeKategori, ?string $jalur = null): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'jenis_perjadin' => $jalur,
            'id_kategori_perjadin' => KategoriPerjadin::where('kode', $kodeKategori)->value('id'),
        ]);

        PesertaUsulan::factory()->create(['id_usulan' => $usulan->id, 'id_user' => $this->admin->id]);

        return $usulan->fresh();
    }

    /** @return list<string> */
    private function labelChecklist(Usulan $usulan): array
    {
        return array_column(app(PenagihDokumen::class)->checklist($usulan), 'label');
    }

    // ── Bawaan ──

    public function test_bawaan_tiap_jalur_sama_dengan_aturan_sebelumnya(): void
    {
        $this->assertSame(BerkasLpj::cases(), $this->atur()->untuk(JenisPerjadin::LuarKota));

        $this->assertSame(
            [BerkasLpj::Sppd, BerkasLpj::NotaTransport, BerkasLpj::Penyelenggaraan, BerkasLpj::Laporan],
            $this->atur()->untuk(JenisPerjadin::DalamKota),
        );

        // Supervisi menagih SPPD hanya pada usulan yang menerbitkannya.
        $this->assertSame(
            [BerkasLpj::Sppd, BerkasLpj::NotaTransport, BerkasLpj::Penyelenggaraan, BerkasLpj::Laporan],
            $this->atur()->untuk(JenisPerjadin::Supervisi),
        );

        foreach (JenisPerjadin::cases() as $jalur) {
            $this->assertFalse($this->atur()->sudahDiatur($jalur));
        }
    }

    public function test_checklist_mengikuti_bawaan_jalurnya(): void
    {
        $luar = $this->labelChecklist($this->usulan('LK-NFB', 'luar-kota'));
        $this->assertContains('Tiket Pergi', $luar);
        $this->assertContains('Bill Hotel', $luar);
        $this->assertContains('Kuitansi penyelenggara / hotel', $luar);

        $dalam = $this->labelChecklist($this->usulan('DK-FD', 'dalam-kota'));
        $this->assertContains('SPPD Bertanda Tangan', $dalam);
        $this->assertNotContains('Tiket Pergi', $dalam);
        $this->assertNotContains('Bill Hotel', $dalam);

        // Supervisi ke luar kota menerbitkan SPD, jadi SPPD-nya ikut ditagih.
        $supervisiLuar = $this->labelChecklist($this->usulan('SV-LK', 'supervisi'));
        $this->assertContains('SPPD Bertanda Tangan', $supervisiLuar);
        $this->assertContains('Nota Transportasi Lokal', $supervisiLuar);
        $this->assertContains('Laporan Perjalanan Dinas', $supervisiLuar);

        // Yang di dalam kota tidak memakai SPD sama sekali.
        $supervisiDalam = $this->labelChecklist($this->usulan('SV-DK', 'supervisi'));
        $this->assertNotContains('SPPD Bertanda Tangan', $supervisiDalam);
        $this->assertContains('Nota Transportasi Lokal', $supervisiDalam);
        $this->assertContains('Laporan Perjalanan Dinas', $supervisiDalam);
    }

    public function test_formulir_dokumen_supervisi_dalam_kota_tanpa_seksi_sppd(): void
    {
        $luar = $this->usulan('SV-LK', 'supervisi');
        $dalam = $this->usulan('SV-DK', 'supervisi');

        $this->actingAs($this->admin)
            ->get(route('dokumen.show', $luar->no_usulan))
            ->assertOk()
            ->assertSee('name="sppd"', false);

        $this->actingAs($this->admin)
            ->get(route('dokumen.show', $dalam->no_usulan))
            ->assertOk()
            ->assertDontSee('name="sppd"', false);
    }

    // ── Halaman pengaturan ──

    public function test_halaman_berkas_pertanggungjawaban_hanya_untuk_yang_berhak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('administrasi.berkas-lpj'))
            ->assertOk()
            ->assertSee('Berkas Pertanggungjawaban')
            ->assertSee('Perjalanan Dalam Kota')
            ->assertSee('Supervisi Kerja Praktek / Magang')
            ->assertSee('Bill hotel')
            ->assertSee('Kuitansi penyelenggara / hotel');

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('administrasi.berkas-lpj'))
            ->assertForbidden();
    }

    public function test_jalur_yang_tidak_dikenal_tidak_ditemukan(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrasi.berkas-lpj.simpan', 'keliling-dunia'), ['berkas' => []])
            ->assertNotFound();
    }

    // ── Mengubah daftar berkas ──

    public function test_berkas_yang_dicabut_tidak_lagi_ditagih(): void
    {
        $usulan = $this->usulan('LK-NFB', 'luar-kota');
        $this->assertContains('Bill Hotel', $this->labelChecklist($usulan));

        $this->actingAs($this->admin)
            ->put(route('administrasi.berkas-lpj.simpan', JenisPerjadin::LuarKota->value), [
                'berkas' => ['sppd' => 'sppd', 'nota_transport' => 'nota_transport', 'laporan' => 'laporan'],
            ])
            ->assertRedirect(route('administrasi.berkas-lpj'))
            ->assertSessionHas('success');

        app(PengaturanBerkasLpj::class)->lupakan();

        $label = $this->labelChecklist($usulan->fresh());
        $this->assertContains('SPPD Bertanda Tangan', $label);
        $this->assertNotContains('Bill Hotel', $label);
        $this->assertNotContains('Tiket Pergi', $label);
        $this->assertNotContains('Kuitansi penyelenggara / hotel', $label);

        // Penagihan memakai daftar yang sama dengan checklist.
        $kurang = app(PenagihDokumen::class)->berkasKurang($usulan->fresh());
        $this->assertNotContains('Bill hotel', $kurang);
        $this->assertNotContains('Kuitansi penyelenggara / hotel', $kurang);

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'pengguna',
            'deskripsi' => 'Berkas pertanggungjawaban jalur "Perjalanan Luar Kota" disimpan — tidak lagi ditagih: Tiket pergi dan pulang, Bill hotel, Kuitansi penyelenggara / hotel, Bukti biaya penyelenggaraan.',
        ]);

        // Jalur lain tidak ikut berubah.
        $this->assertSame(
            [BerkasLpj::Sppd, BerkasLpj::NotaTransport, BerkasLpj::Penyelenggaraan, BerkasLpj::Laporan],
            $this->atur()->untuk(JenisPerjadin::DalamKota),
        );
    }

    public function test_berkas_dapat_ditambahkan_pada_jalur_supervisi(): void
    {
        $usulan = $this->usulan('SV-LK', 'supervisi');
        $this->assertNotContains('Tiket Pergi', $this->labelChecklist($usulan));

        $this->actingAs($this->admin)->put(route('administrasi.berkas-lpj.simpan', JenisPerjadin::Supervisi->value), [
            'berkas' => ['tiket' => 'tiket', 'nota_transport' => 'nota_transport', 'laporan' => 'laporan'],
        ]);
        app(PengaturanBerkasLpj::class)->lupakan();

        $label = $this->labelChecklist($usulan->fresh());
        $this->assertContains('Tiket Pergi', $label);
        $this->assertContains('Tiket Pulang', $label);
        $this->assertTrue($this->atur()->sudahDiatur(JenisPerjadin::Supervisi));
    }

    public function test_pengaturan_dapat_dikembalikan_ke_bawaan(): void
    {
        $this->actingAs($this->admin)->put(route('administrasi.berkas-lpj.simpan', JenisPerjadin::DalamKota->value), [
            'berkas' => ['laporan' => 'laporan'],
        ]);
        app(PengaturanBerkasLpj::class)->lupakan();
        $this->assertSame([BerkasLpj::Laporan], $this->atur()->untuk(JenisPerjadin::DalamKota));

        $this->actingAs($this->admin)
            ->delete(route('administrasi.berkas-lpj.bawaan', JenisPerjadin::DalamKota->value))
            ->assertRedirect(route('administrasi.berkas-lpj'));

        app(PengaturanBerkasLpj::class)->lupakan();
        $this->assertSame(
            [BerkasLpj::Sppd, BerkasLpj::NotaTransport, BerkasLpj::Penyelenggaraan, BerkasLpj::Laporan],
            $this->atur()->untuk(JenisPerjadin::DalamKota),
        );
        $this->assertFalse($this->atur()->sudahDiatur(JenisPerjadin::DalamKota));
    }

    // ── Formulir dokumen ikut menyesuaikan ──

    public function test_formulir_dokumen_hanya_memuat_berkas_yang_diminta(): void
    {
        $usulan = $this->usulan('LK-NFB', 'luar-kota');

        $this->actingAs($this->admin)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertSee('Tiket Pergi')
            ->assertSee('name="bill_hotel"', false);

        $this->atur()->simpan(JenisPerjadin::LuarKota, ['sppd', 'nota_transport', 'laporan']);
        app(PengaturanBerkasLpj::class)->lupakan();

        $this->actingAs($this->admin)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertDontSee('name="bill_hotel"', false)
            ->assertDontSee('name="kwintasi"', false)
            ->assertDontSee('Simpan Tiket Pergi');
    }

    public function test_kemampuan_mengatur_berkas_hanya_dimiliki_super_administrator(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $this->assertSame(
                $peran === PeranPengguna::SuperAdministrator,
                $peran->punya(Kemampuan::MengaturBerkasLpj),
                $peran->value,
            );
        }
    }
}
