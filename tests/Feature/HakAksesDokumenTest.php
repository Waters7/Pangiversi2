<?php

namespace Tests\Feature;

use App\Enums\Kemampuan;
use App\Enums\MenuAplikasi;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\Peran;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hak akses menu Dokumen: melihat dokumen pertanggungjawaban seluruh
 * perjalanan dinas (Dokumen → Lihat) dan membantu mengunggahnya atas nama
 * pelaksana (Dokumen → Ubah).
 */
class HakAksesDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Maya Sari Wulandari']);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);
    }

    /**
     * @param  list<Kemampuan>  $kemampuan
     */
    private function penggunaDengan(array $kemampuan): User
    {
        Peran::sinkronBawaan();
        Peran::where('kode', PeranPengguna::TimSdm->value)->sole()->aturHakAkses($kemampuan);

        return User::factory()->create(['role' => PeranPengguna::TimSdm->value, 'nama' => 'Operator Unit']);
    }

    private function unggahAkomodasi(User $pengunggah): TestResponse
    {
        return $this->actingAs($pengunggah)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi',
            'bill_hotel' => UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
            'bill_hotel_no_transaksi' => 'TRX-88192',
            'bill_hotel_nominal' => 1_200_000,
            'kwintasi' => UploadedFile::fake()->create('kwitansi.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_kedua_hak_berada_pada_menu_dokumen_di_matriks(): void
    {
        $this->assertSame(MenuAplikasi::Dokumen, MenuAplikasi::untuk(Kemampuan::MelihatSemuaDokumen));
        $this->assertSame(MenuAplikasi::Dokumen, MenuAplikasi::untuk(Kemampuan::MembantuUnggahDokumen));
        $this->assertTrue(PeranPengguna::SuperAdministrator->punya(Kemampuan::MembantuUnggahDokumen));
        $this->assertFalse(PeranPengguna::DosenTendik->punya(Kemampuan::MelihatSemuaDokumen));
    }

    public function test_hak_melihat_membuka_dokumen_seluruh_perjadin_tanpa_mengunggah(): void
    {
        $pemantau = $this->penggunaDengan([Kemampuan::MengajukanUsulan, Kemampuan::MelihatSemuaDokumen]);

        $this->actingAs($pemantau)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('Maya Sari Wulandari');

        $this->actingAs($pemantau)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Mode lihat saja')
            ->assertSee('data-pelaksana-dokumen', false)
            ->assertDontSee('Simpan Akomodasi')
            ->assertSee(route('dokumen.laporan.show', $this->usulan->no_usulan))
            ->assertDontSee('href="'.route('dokumen.laporan.edit', $this->usulan->no_usulan).'"', false);

        $this->actingAs($pemantau)->get(route('dokumen.format-laporan', $this->usulan))->assertOk();

        $this->actingAs($pemantau)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('data-pelaksana-laporan', false)
            ->assertDontSee('href="'.route('dokumen.laporan.edit', $this->usulan->no_usulan).'"', false);
        $this->actingAs($pemantau)->get(route('dokumen.laporan.show', $this->usulan))->assertOk();
        $this->actingAs($pemantau)->get(route('dokumen.laporan.edit', $this->usulan))->assertForbidden();

        $this->unggahAkomodasi($pemantau)->assertForbidden();
        $this->assertTrue($this->usulan->dokumen()->doesntExist());
    }

    public function test_hak_membantu_unggah_menyimpan_berkas_atas_nama_pelaksana(): void
    {
        $operator = $this->penggunaDengan([Kemampuan::MembantuUnggahDokumen]);

        // Yang membantu mengunggah dengan sendirinya boleh membuka daftarnya.
        $this->actingAs($operator)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan);

        $this->actingAs($operator)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Membantu mengunggah atas nama pelaksana')
            ->assertSee('Simpan Akomodasi');

        $this->unggahAkomodasi($operator)->assertRedirect(route('dokumen.show', $this->usulan->no_usulan));

        $this->assertSame('TRX-88192', $this->usulan->dokumen()->sole()->bill_hotel_no_transaksi);
        $this->assertDatabaseHas(Notifikasi::class, [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Dokumen Anda diunggahkan',
        ]);
        $this->assertTrue(AuditLog::where('aksi', AuditLog::AKSI_DOKUMEN)
            ->where('id_user', $operator->id)
            ->where('deskripsi', 'like', '%oleh Operator Unit atas nama Maya Sari Wulandari%')
            ->exists());

        // Isi laporan perjalanan dinas tetap milik pelaksana.
        $this->actingAs($operator)->get(route('dokumen.laporan.edit', $this->usulan))->assertForbidden();
    }

    public function test_pelaksana_yang_mengunggah_sendiri_tidak_diberi_tahu(): void
    {
        $this->unggahAkomodasi($this->pelaksana)->assertRedirect();

        $this->assertDatabaseMissing(Notifikasi::class, ['judul' => 'Dokumen Anda diunggahkan']);
        $this->assertFalse(AuditLog::where('aksi', AuditLog::AKSI_DOKUMEN)->where('deskripsi', 'like', '%atas nama%')->exists());
    }

    public function test_tanpa_hak_dokumen_pegawai_lain_tertutup(): void
    {
        $rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($rekan)->get(route('dokumen'))->assertOk()->assertDontSee($this->usulan->no_usulan);
        $this->actingAs($rekan)->get(route('dokumen.show', $this->usulan))->assertForbidden();
        $this->actingAs($rekan)->get(route('dokumen.format-laporan', $this->usulan))->assertForbidden();
        $this->actingAs($rekan)->get(route('dokumen.laporan.index'))->assertOk()->assertDontSee($this->usulan->no_usulan);
        $this->actingAs($rekan)->get(route('dokumen.laporan.show', $this->usulan))->assertForbidden();

        $this->unggahAkomodasi($rekan)->assertForbidden();
        $this->assertTrue($this->usulan->dokumen()->doesntExist());
    }
}
