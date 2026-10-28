<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PesanWhatsapp;
use App\Enums\StatusUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\FormatWhatsapp;
use App\Services\PenagihDokumen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Administrasi Sistem → Format WhatsApp: kalimat pesan WhatsApp yang
 * disusun aplikasi dapat diubah, dengan penanda yang diganti isinya.
 */
class FormatWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private string $kerja;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Cermin format ditulis ke folder pemantau tiruan, bukan folder aplikasi.
        $this->kerja = sys_get_temp_dir().'/pangi-format-wa-'.uniqid();
        config(['pantau.folder' => $this->kerja.'/pantau', 'app.url' => 'https://pangi.test']);

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->kerja);

        parent::tearDown();
    }

    private function simpan(PesanWhatsapp $pesan, string $isi): TestResponse
    {
        return $this->actingAs($this->admin)
            ->put(route('administrasi.format-whatsapp.simpan', $pesan->value), ['isi_'.$pesan->value => $isi]);
    }

    private function pesanPenagihan(): string
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Selesai->value,
            'lokasi' => 'Jakarta',
            'id_user' => User::factory()->create(['nama' => 'Sri Handayani', 'no_hp' => '081200001111'])->id,
        ]);

        $tautan = (string) app(PenagihDokumen::class)->tautanWhatsapp($usulan);

        return rawurldecode(substr($tautan, strpos($tautan, '?text=') + strlen('?text=')));
    }

    public function test_halaman_memuat_seluruh_format_beserta_bawaannya(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->get(route('administrasi.format-whatsapp'))
            ->assertOk()
            ->assertSee('Format WhatsApp');

        foreach (PesanWhatsapp::cases() as $pesan) {
            $halaman->assertSee($pesan->label())->assertSee('{'.array_key_first($pesan->penanda()).'}');
        }

        $halaman->assertSee('Mohon diunggah melalui aplikasi PANGI');
    }

    public function test_format_penagihan_yang_diubah_dipakai_pada_tautan_whatsapp(): void
    {
        $this->simpan(PesanWhatsapp::PenagihanBerkas, "Halo *{nama}*,\r\nberkas {no_usulan} ke {lokasi} kurang {jumlah_berkas}:\r\n{daftar_berkas}")
            ->assertRedirect(route('administrasi.format-whatsapp'))
            ->assertSessionHas('success');

        $pesan = $this->pesanPenagihan();

        // Ganti baris CRLF dari formulir tersimpan sebagai LF.
        $this->assertStringStartsWith("Halo *Sri Handayani*,\nberkas ", $pesan);
        $this->assertStringContainsString('ke Jakarta kurang 5:', $pesan);
        $this->assertStringContainsString('1. SPPD bertanda tangan', $pesan);
        $this->assertStringNotContainsString('Mohon diunggah melalui aplikasi PANGI', $pesan);
    }

    public function test_penanda_tidak_dikenal_dan_penanda_wajib_yang_hilang_ditolak(): void
    {
        $this->simpan(PesanWhatsapp::PenagihanBerkas, 'Halo {namaa}, {daftar_berkas}')
            ->assertSessionHasErrors(['isi_penagihan_berkas' => 'Penanda tidak dikenal: {namaa}. Pakai salah satu penanda yang tersedia di bawah kolom ini.']);

        $this->simpan(PesanWhatsapp::PenagihanBerkas, 'Halo {nama}, mohon lengkapi berkas.')
            ->assertSessionHasErrors('isi_penagihan_berkas');

        $this->assertFalse(app(FormatWhatsapp::class)->sudahDiubah(PesanWhatsapp::PenagihanBerkas));
    }

    public function test_format_dapat_dikembalikan_ke_bawaan(): void
    {
        $this->simpan(PesanWhatsapp::PenagihanBerkas, 'Halo {nama}: {daftar_berkas}');

        $this->actingAs($this->admin)
            ->delete(route('administrasi.format-whatsapp.bawaan', PesanWhatsapp::PenagihanBerkas->value))
            ->assertSessionHas('success');

        $this->assertStringStartsWith('Selamat pagi/siang Sri Handayani,', $this->pesanPenagihan());
    }

    /** Pesan gangguan memakai format yang diubah, meski basis data terputus. */
    public function test_format_peringatan_server_terbaca_dari_cermin_saat_basis_data_terputus(): void
    {
        $this->simpan(PesanWhatsapp::GangguanServer, "*SITUS PANGI MATI* sejak {sejak}\n{hasil_pemeriksaan}")
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->put(route('administrasi.server.peringatan'), [
            'peringatan_aktif' => '1',
            'nomor_penerima' => '081234567890',
            'wa_gateway' => 'fonnte',
            'wa_token' => 'TOKENFONNTE123456',
            'peringatan_ulang_menit' => 60,
        ]);

        Http::fake([
            'pangi.test/up' => Http::response('ok', 200),
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);

        Cache::flush();
        $semula = config('database.default');
        config([
            'database.connections.terputus' => ['driver' => 'sqlite', 'database' => $this->kerja.'/tidak-ada/pangi.sqlite', 'prefix' => ''],
            'database.default' => 'terputus',
        ]);

        try {
            foreach (range(1, 3) as $putaran) {
                $this->artisan('pangi:pantau-server')->assertFailed();
            }
        } finally {
            config(['database.default' => $semula]);
            DB::purge('terputus');
        }

        $kiriman = Http::recorded(fn ($permintaan) => str_contains($permintaan->url(), 'api.fonnte.com'))->values();
        $this->assertCount(1, $kiriman);
        $this->assertStringStartsWith('*SITUS PANGI MATI* sejak', $kiriman[0][0]['message']);
        $this->assertStringContainsString('Basis data: Tidak tersambung', $kiriman[0][0]['message']);
    }

    public function test_hanya_pemegang_hak_mengatur_format_whatsapp(): void
    {
        $timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);

        $this->actingAs($timSdm)->get(route('administrasi'))->assertOk()->assertDontSee(route('administrasi.format-whatsapp'));
        $this->actingAs($timSdm)->get(route('administrasi.format-whatsapp'))->assertForbidden();
        $this->actingAs($timSdm)
            ->put(route('administrasi.format-whatsapp.simpan', PesanWhatsapp::PenagihanBerkas->value), [
                'isi_penagihan_berkas' => 'Halo {daftar_berkas}',
            ])
            ->assertForbidden();
    }
}
