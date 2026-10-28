<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menu Pembayaran adalah ruang kerja bendahara: memilah perjalanan dinas
 * menurut tahap pembayarannya.
 */
class MenuPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private function bendahara(): User
    {
        return User::factory()->create(['role' => PeranPengguna::Bendahara->value]);
    }

    /**
     * @param  array<string, mixed>  $usulan
     * @param  'belum'|'uang-muka'|'lunas'|null  $tahap
     */
    private function perjadin(string $nama, array $usulan = [], ?string $tahap = null): Usulan
    {
        $item = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => $nama,
            'tanggal_mulai' => today()->addMonth(),
            'tanggal_selesai' => today()->addMonth()->addDays(2),
            ...$usulan,
        ]);

        if ($tahap !== null) {
            $factory = Keuangan::factory();

            $factory = match ($tahap) {
                'uang-muka' => $factory->uangMuka(),
                'lunas' => $factory->lunas(),
                default => $factory->belumBayar(),
            };

            $factory->create([
                'id_usulan' => $item->id,
                'total' => 10_000_000,
                'uang_muka' => 8_000_000,
                'sisa' => 2_000_000,
            ]);
        }

        return $item;
    }

    private function buka(string $tahap): TestResponse
    {
        return $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => $tahap]))
            ->assertOk();
    }

    // ── Hak akses ──

    public function test_bendahara_dapat_membuka_menu_pembayaran(): void
    {
        $this->actingAs($this->bendahara())
            ->get(route('pembayaran'))
            ->assertOk()
            ->assertSee('Pembayaran Perjalanan Dinas');
    }

    public function test_peran_selain_bendahara_tidak_dapat_membukanya(): void
    {
        foreach ([
            PeranPengguna::TimKeuangan,
            PeranPengguna::Ppk,
            PeranPengguna::Pimpinan,
            PeranPengguna::TimSdm,
            PeranPengguna::DosenTendik,
        ] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('pembayaran'))
                ->assertForbidden();
        }
    }

    public function test_menu_hanya_muncul_di_sidebar_bendahara(): void
    {
        $this->actingAs($this->bendahara())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('pembayaran'), false);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('pembayaran'), false);
    }

    // ── Pemilahan tahap ──

    public function test_tahap_menunggu_pembayaran_memuat_yang_belum_ditransfer(): void
    {
        $this->perjadin('Jakarta', tahap: 'belum');
        $this->perjadin('Surabaya', tahap: 'uang-muka');

        $this->buka('disetujui')
            ->assertSee('Jakarta')
            ->assertDontSee('Surabaya');
    }

    public function test_tahap_sedang_berjalan_memuat_perjalanan_hari_ini(): void
    {
        $this->perjadin('Denpasar', [
            'tanggal_mulai' => today()->subDay(),
            'tanggal_selesai' => today()->addDay(),
        ], 'uang-muka');

        $this->perjadin('Makassar', [
            'tanggal_mulai' => today()->addMonth(),
            'tanggal_selesai' => today()->addMonth()->addDay(),
        ], 'uang-muka');

        $this->buka('berjalan')
            ->assertSee('Denpasar')
            ->assertDontSee('Makassar');
    }

    public function test_tahap_uang_muka_memuat_yang_sudah_transfer_belum_lunas(): void
    {
        $this->perjadin('Palu', tahap: 'uang-muka');
        $this->perjadin('Bitung', tahap: 'lunas');

        $this->buka('uang-muka')
            ->assertSee('Palu')
            ->assertDontSee('Bitung');
    }

    public function test_tahap_lunas_memuat_yang_sudah_dibayar_penuh(): void
    {
        $this->perjadin('Gorontalo', tahap: 'lunas');
        $this->perjadin('Kendari', tahap: 'uang-muka');

        $this->buka('lunas')
            ->assertSee('Gorontalo')
            ->assertDontSee('Kendari');
    }

    public function test_tahap_yang_tidak_dikenali_kembali_ke_tahap_awal(): void
    {
        $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => 'ngawur']))
            ->assertOk()
            ->assertViewHas('tahap', 'disetujui');
    }

    // ── Ringkasan nilai ──

    public function test_ringkasan_menghitung_yang_sudah_dan_belum_dibayarkan(): void
    {
        $this->perjadin('Jakarta', tahap: 'uang-muka');
        $this->perjadin('Bandung', tahap: 'lunas');

        $this->buka('disetujui')
            ->assertViewHas('nilai', function (array $nilai) {
                // Dua perjadin @10 jt: satu baru uang muka (8 jt), satu lunas (10 jt).
                return $nilai['anggaran'] === 20_000_000.0
                    && $nilai['terbayar'] === 18_000_000.0
                    && $nilai['sisa'] === 2_000_000.0;
            });
    }

    public function test_jumlah_tiap_tahap_ditampilkan_pada_tab(): void
    {
        $this->perjadin('Jakarta', tahap: 'belum');

        $this->buka('disetujui')
            ->assertViewHas('jumlah', fn (array $jumlah) => $jumlah['disetujui'] === 1 && $jumlah['lunas'] === 0);
    }

    // ── Pencarian ──

    public function test_daftar_dapat_dicari(): void
    {
        $this->perjadin('Jakarta', tahap: 'belum');
        $this->perjadin('Surabaya', tahap: 'belum');

        $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => 'disetujui', 'search' => 'Jakarta']))
            ->assertOk()
            ->assertSee('Jakarta')
            ->assertDontSee('Surabaya');
    }

    public function test_usulan_yang_belum_disetujui_tidak_muncul(): void
    {
        $this->perjadin('Ternate', ['status' => StatusUsulan::MenungguPpk->value], 'belum');

        $this->buka('disetujui')->assertDontSee('Ternate');
    }

    // ── Saringan dan urutan ──

    public function test_daftar_pembayaran_disaring_menurut_status_pelaksana_perjalanan_dan_anggaran(): void
    {
        $maya = User::factory()->create(['nama' => 'Maya Sari Wulandari']);
        $ternate = $this->perjadin('Ternate', ['id_user' => $maya->id], 'uang-muka');
        $tidore = $this->perjadin('Tidore', [], 'lunas');
        Keuangan::where('id_usulan', $tidore->id)->update(['total' => 4_000_000]);

        $id = fn (array $saring) => $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => 'uang-muka'] + $saring))
            ->assertOk()
            ->viewData('usulan')
            ->pluck('id')
            ->all();
        $semua = fn (array $saring) => $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => 'lunas'] + $saring))
            ->assertOk()
            ->viewData('usulan')
            ->pluck('id')
            ->all();

        $this->buka('uang-muka')->assertSee('data-saringan-pembayaran', false);

        $this->assertSame([$ternate->id], $id(['pelaksana' => 'maya']));
        $this->assertSame([$ternate->id], $id(['perjalanan' => 'terna']));
        $this->assertSame([$ternate->id], $id(['status' => 'sebagian']));
        $this->assertSame([], $id(['status' => 'lunas']));
        $this->assertSame([$tidore->id], $semua(['status' => 'lunas', 'anggaran_maks' => 5_000_000]));
        $this->assertSame([], $semua(['anggaran_min' => 5_000_000]));
    }

    public function test_daftar_pembayaran_dapat_diurutkan(): void
    {
        $budi = User::factory()->create(['nama' => 'Budi']);
        $ani = User::factory()->create(['nama' => 'Ani']);
        $besar = $this->perjadin('Ternate', ['id_user' => $budi->id, 'tanggal_mulai' => today()->addMonths(2), 'tanggal_selesai' => today()->addMonths(2)->addDay()], 'belum');
        $kecil = $this->perjadin('Tidore', ['id_user' => $ani->id], 'belum');
        Keuangan::where('id_usulan', $kecil->id)->update(['total' => 1_000_000]);

        $urutan = fn (string $urut) => $this->actingAs($this->bendahara())
            ->get(route('pembayaran', ['tahap' => 'disetujui', 'urut' => $urut]))
            ->assertOk()
            ->viewData('usulan')
            ->pluck('id')
            ->all();

        $this->assertSame([$kecil->id, $besar->id], $urutan('berangkat-terlama'));
        $this->assertSame([$besar->id, $kecil->id], $urutan('berangkat-terbaru'));
        $this->assertSame([$kecil->id, $besar->id], $urutan('nama-az'));
        $this->assertSame([$besar->id, $kecil->id], $urutan('anggaran-besar'));
        $this->assertSame([$kecil->id, $besar->id], $urutan('anggaran-kecil'));

        // Judul kolom ikut menjadi tombol urut.
        $this->buka('disetujui')->assertSee('urut=anggaran-besar', false);
    }
}
