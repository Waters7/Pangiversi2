<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\AsistenAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kunci API Anthropic untuk asisten AI dashboard dipasang super administrator
 * dari Administrasi Sistem — tersimpan terenkripsi — tanpa menyentuh .env.
 */
class KunciAnthropicAdministrasiTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'sk-ant-api03-uji-coba-kunci-yang-cukup-panjang-untuk-dipakai-ABC123';

    private const KUNCI_ENV = 'sk-ant-api03-kunci-dari-env-yang-cukup-panjang-untuk-dipakai-XYZ';

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    public function test_memasang_kunci_menghidupkan_asisten_ai_dan_tersimpan_terenkripsi(): void
    {
        config(['ai.api_key' => null]);

        $this->assertFalse(app(AsistenAi::class)->tersedia());

        $this->actingAs($this->admin())
            ->put(route('administrasi.kunci-anthropic.simpan'), ['kunci_anthropic' => self::KUNCI])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(app(AsistenAi::class)->tersedia());
        $this->assertSame(['kunci' => self::KUNCI, 'sumber' => 'pengaturan'], Pengaturan::kunciAnthropic());

        // Yang tersimpan di basis data bukan kuncinya apa adanya.
        $mentah = Pengaturan::query()->where('kunci', Pengaturan::KUNCI_ANTHROPIC)->value('nilai');
        $this->assertNotSame(self::KUNCI, $mentah);
        $this->assertStringNotContainsString('sk-ant-', (string) $mentah);

        $this->assertDatabaseHas('audit_logs', [
            'deskripsi' => 'Kunci API Anthropic untuk asisten AI dashboard dipasang.',
        ]);

        // Halaman hanya memperlihatkan ujung-ujung kuncinya.
        $this->actingAs($this->admin())
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertSee('Kunci API Anthropic')
            ->assertSee('sk-ant-api0••••••••••••••••C123')
            ->assertDontSee(self::KUNCI)
            ->assertSee('Ganti Kunci')
            ->assertSee('Hapus Kunci');
    }

    public function test_kunci_yang_bukan_format_anthropic_ditolak(): void
    {
        config(['ai.api_key' => null]);

        $this->actingAs($this->admin())
            ->from(route('administrasi.pengaturan'))
            ->put(route('administrasi.kunci-anthropic.simpan'), ['kunci_anthropic' => 'bukan kunci yang sah karena ada spasi dan tanpa awalan'])
            ->assertRedirect(route('administrasi.pengaturan'))
            ->assertSessionHasErrors('kunci_anthropic');

        $this->assertFalse(app(AsistenAi::class)->tersedia());
    }

    public function test_kunci_env_berlaku_selama_pengaturan_kosong_dan_dapat_digantikan(): void
    {
        config(['ai.api_key' => self::KUNCI_ENV]);

        $this->actingAs($this->admin())
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertSee('Aktif · dari berkas .env')
            ->assertSee('Ganti Kunci')
            ->assertDontSee('Hapus Kunci');

        $this->assertSame('env', Pengaturan::kunciAnthropic()['sumber']);

        $this->actingAs($this->admin())
            ->put(route('administrasi.kunci-anthropic.simpan'), ['kunci_anthropic' => self::KUNCI]);

        $this->assertSame(['kunci' => self::KUNCI, 'sumber' => 'pengaturan'], Pengaturan::kunciAnthropic());
    }

    public function test_menghapus_kunci_mengembalikan_ke_env_atau_menonaktifkan_ai(): void
    {
        config(['ai.api_key' => self::KUNCI_ENV]);
        Pengaturan::simpanRahasia(Pengaturan::KUNCI_ANTHROPIC, self::KUNCI);

        $this->actingAs($this->admin())
            ->delete(route('administrasi.kunci-anthropic.hapus'))
            ->assertSessionHas('success', 'Kunci API Anthropic dihapus. Asisten AI kembali memakai kunci dari berkas .env server.');

        $this->assertSame(['kunci' => self::KUNCI_ENV, 'sumber' => 'env'], Pengaturan::kunciAnthropic());

        config(['ai.api_key' => null]);
        Pengaturan::simpanRahasia(Pengaturan::KUNCI_ANTHROPIC, self::KUNCI);

        $this->actingAs($this->admin())
            ->delete(route('administrasi.kunci-anthropic.hapus'))
            ->assertSessionHas('success', 'Kunci API Anthropic dihapus. Fitur AI dashboard nonaktif sampai kunci baru dipasang.');

        $this->assertFalse(app(AsistenAi::class)->tersedia());
    }

    public function test_nilai_yang_tidak_dapat_didekripsi_dianggap_kosong(): void
    {
        config(['ai.api_key' => null]);
        Pengaturan::simpan([Pengaturan::KUNCI_ANTHROPIC => 'bukan-hasil-enkripsi']);

        $this->assertSame(['kunci' => '', 'sumber' => null], Pengaturan::kunciAnthropic());
        $this->assertFalse(app(AsistenAi::class)->tersedia());
    }

    public function test_hanya_super_administrator_yang_melihat_dan_mengatur_kunci(): void
    {
        config(['ai.api_key' => self::KUNCI_ENV]);
        $timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);

        $this->actingAs($timSdm)
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertDontSee('Kunci API Anthropic');

        $this->actingAs($timSdm)
            ->put(route('administrasi.kunci-anthropic.simpan'), ['kunci_anthropic' => self::KUNCI])
            ->assertForbidden();
        $this->actingAs($timSdm)->delete(route('administrasi.kunci-anthropic.hapus'))->assertForbidden();

        $this->assertSame('env', Pengaturan::kunciAnthropic()['sumber']);
    }
}
