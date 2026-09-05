<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SesiPengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kapan pengguna terakhir masuk, dan siapa yang sedang memakai aplikasi.
 *
 * Keduanya menjawab pertanyaan berbeda: "login terakhir" bertahan lama dan
 * berguna mengenali akun yang tidak pernah dipakai; "sedang aktif" dibaca
 * dari tabel sesi dan hilang sendiri saat sesinya berakhir.
 */
class KehadiranPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Kehadiran dibaca dari tabel sesi, jadi pengujiannya memakai
        // penyimpanan yang sama dengan pemasangan sungguhan.
        config(['session.driver' => 'database', 'session.table' => 'sessions']);

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /** Sesi milik seorang pengguna, dengan kegiatan terakhir sekian menit lalu. */
    private function sesiUntuk(User $pengguna, int $menitLalu = 0): void
    {
        DB::table('sessions')->insert([
            'id' => bin2hex(random_bytes(8)),
            'user_id' => $pengguna->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'pengujian',
            'payload' => '',
            'last_activity' => now()->subMinutes($menitLalu)->getTimestamp(),
        ]);
    }

    // ── Login terakhir ──

    public function test_login_tercatat_pada_akun(): void
    {
        $pengguna = User::factory()->create([
            'nip' => '199001012020121001',
            'password' => Hash::make('rahasia-uji-coba'),
        ]);

        $this->assertTrue($pengguna->belumPernahMasuk());

        $this->post('/login', [
            'nip' => $pengguna->nip,
            'password' => 'rahasia-uji-coba',
        ])->assertRedirect();

        $segar = $pengguna->fresh();

        $this->assertNotNull($segar->login_terakhir_at, 'Waktu login terakhir tidak tercatat.');
        $this->assertNotNull($segar->login_terakhir_ip, 'Alamat login tidak tercatat.');
        $this->assertFalse($segar->belumPernahMasuk());
    }

    public function test_login_yang_gagal_tidak_mencatat_apa_pun(): void
    {
        $pengguna = User::factory()->create([
            'nip' => '199001012020121002',
            'password' => Hash::make('rahasia-uji-coba'),
        ]);

        $this->post('/login', ['nip' => $pengguna->nip, 'password' => 'salah'])
            ->assertSessionHasErrors('nip');

        $this->assertNull($pengguna->fresh()->login_terakhir_at);
    }

    // ── Sedang aktif ──

    public function test_hanya_sesi_yang_masih_hidup_yang_dihitung(): void
    {
        $aktif = User::factory()->create();
        $lama = User::factory()->create();

        $this->sesiUntuk($aktif, menitLalu: 2);
        $this->sesiUntuk($lama, menitLalu: SesiPengguna::MENIT_AKTIF + 10);

        $sesi = app(SesiPengguna::class);

        $this->assertTrue($sesi->idAktif()->contains($aktif->id));
        $this->assertFalse($sesi->idAktif()->contains($lama->id));
    }

    public function test_satu_pengguna_dengan_dua_sesi_terhitung_sekali(): void
    {
        $pengguna = User::factory()->create();

        $this->sesiUntuk($pengguna, menitLalu: 1);
        $this->sesiUntuk($pengguna, menitLalu: 3);

        $this->assertSame(1, app(SesiPengguna::class)->jumlahAktif());
    }

    public function test_halaman_administrasi_menyebut_jumlah_yang_sedang_login(): void
    {
        $pengguna = User::factory()->create();
        $this->sesiUntuk($pengguna, menitLalu: 1);

        $this->actingAs($this->admin)
            ->get(route('administrasi'))
            ->assertOk()
            ->assertViewHas('jumlahAktif', 1)
            ->assertSee('Sedang login');
    }

    public function test_daftar_dapat_disaring_pada_yang_sedang_login(): void
    {
        $aktif = User::factory()->create();
        User::factory()->count(3)->create();
        $this->sesiUntuk($aktif, menitLalu: 1);

        $halaman = $this->actingAs($this->admin)
            ->get(route('administrasi', ['kehadiran' => 'aktif']))
            ->assertOk();

        $this->assertSame(1, $halaman->viewData('users')->total());
    }

    public function test_daftar_dapat_disaring_pada_yang_belum_pernah_masuk(): void
    {
        $pernah = User::factory()->create(['login_terakhir_at' => now()->subDay()]);
        User::factory()->count(2)->create(['login_terakhir_at' => null]);

        $halaman = $this->actingAs($this->admin)
            ->get(route('administrasi', ['kehadiran' => 'belum-pernah']))
            ->assertOk();

        // Dua yang dibuat tanpa login, ditambah administrator penguji sendiri.
        $this->assertSame(3, $halaman->viewData('users')->total());
        $this->assertFalse($halaman->viewData('users')->contains($pernah));
    }

    public function test_kolom_login_terakhir_tampil_pada_daftar_pengguna(): void
    {
        User::factory()->create(['login_terakhir_at' => null]);

        $this->actingAs($this->admin)
            ->get(route('administrasi'))
            ->assertOk()
            ->assertSee('Login Terakhir')
            ->assertSee('Belum pernah');
    }
}
