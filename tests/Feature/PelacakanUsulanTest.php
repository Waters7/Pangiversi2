<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PelacakUsulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pelacakan tonggak sebuah perjalanan dinas.
 *
 * Halaman detail usulan menjawab satu pertanyaan: berkas ini sudah sampai
 * mana, dan kapan tiap tahapnya terlewati. Tahap yang belum terjadi tetap
 * ditampilkan supaya terlihat berkasnya sedang menunggu apa.
 */
class PelacakanUsulanTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $timKeuangan;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'no_tugas' => 'KP.03.01/F.XXXVIII/77/2026',
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
    }

    private function pelacak(): PelacakUsulan
    {
        return app(PelacakUsulan::class);
    }

    private function tonggak(string $judul): array
    {
        return $this->pelacak()->tonggak($this->usulan->fresh())->firstWhere('judul', $judul);
    }

    // ── Susunan tonggak ──

    public function test_usulan_baru_hanya_melewati_tahap_pertama(): void
    {
        $tonggak = $this->pelacak()->tonggak($this->usulan);

        $this->assertTrue($tonggak->firstWhere('judul', 'Usulan dibuat')['selesai']);
        $this->assertFalse($tonggak->firstWhere('judul', 'Pelunasan dibayarkan')['selesai']);
    }

    public function test_langkah_berikutnya_menunjuk_tahap_yang_menghambat(): void
    {
        $berikutnya = $this->pelacak()->langkahBerikutnya($this->usulan);

        $this->assertNotNull($berikutnya);
        $this->assertFalse($berikutnya['selesai']);
        $this->assertSame('Disetujui PPK', $berikutnya['judul']);
    }

    public function test_pembayaran_uang_muka_menutup_tahapnya(): void
    {
        $this->usulan->keuangan->update([
            'tanggal_transfer' => '2026-04-04',
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $langkah = $this->tonggak('Uang muka dibayarkan');

        $this->assertTrue($langkah['selesai']);
        $this->assertSame('2026-04-04', $langkah['waktu']->toDateString());
    }

    /**
     * Kedua dokumen ditandatangani sendiri-sendiri, jadi tahapnya baru
     * terlewati saat keduanya sudah disikapi — bukan salah satunya.
     */
    public function test_tanda_tangan_baru_terhitung_setelah_kedua_dokumen(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $riil = DaftarRiil::firstWhere('id_usulan', $this->usulan->id);
        $riil->kirimKePegawai();

        $riil->fresh()->jalur()->setujui();
        $this->assertFalse($this->tonggak('Ditandatangani pelaksana')['selesai']);

        $riil->fresh()->jalurRincian()->setujui();
        $this->assertTrue($this->tonggak('Ditandatangani pelaksana')['selesai']);

        $riil->fresh()->jalur()->tandaTangani($this->ppk);
        $this->assertFalse($this->tonggak('Ditandatangani PPK')['selesai']);

        $riil->fresh()->jalurRincian()->tandaTangani($this->ppk);
        $this->assertTrue($this->tonggak('Ditandatangani PPK')['selesai']);
    }

    public function test_tonggak_nominatif_mengikuti_pengesahan_dan_pengirimannya(): void
    {
        $nominatif = DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'tanggal_tugas' => now()->toDateString(),
        ]);

        $this->assertFalse($this->tonggak('Daftar nominatif diverifikasi PPK')['selesai']);

        $nominatif->update(['id_ppk' => $this->ppk->id, 'ditandatangani_at' => now()]);

        $this->assertTrue($this->tonggak('Daftar nominatif diverifikasi PPK')['selesai']);
        $this->assertFalse($this->tonggak('Nominatif diterima tim keuangan')['selesai']);

        $nominatif->update(['dikirim_at' => now()]);

        $this->assertTrue($this->tonggak('Nominatif diterima tim keuangan')['selesai']);
    }

    public function test_kemajuan_dihitung_dari_tahap_yang_terlewati(): void
    {
        $awal = $this->pelacak()->kemajuan($this->usulan);

        $this->usulan->keuangan->update(['tanggal_transfer' => '2026-04-04']);

        $sesudah = $this->pelacak()->kemajuan($this->usulan->fresh());

        $this->assertSame($awal['selesai'] + 1, $sesudah['selesai']);
        $this->assertGreaterThan($awal['persen'], $sesudah['persen']);
        $this->assertLessThanOrEqual(100, $sesudah['persen']);
    }

    // ── Tampilan ──

    public function test_detail_usulan_menampilkan_pelacakan_berkas(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Pelacakan Berkas')
            ->assertSee('Usulan dibuat')
            ->assertSee('Daftar nominatif diverifikasi PPK')
            ->assertSee('Nominatif diterima tim keuangan')
            ->assertSee('Pelunasan dibayarkan');
    }

    public function test_tahap_yang_belum_terjadi_ditandai_belum(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Sedang menunggu')
            ->assertSee('Belum');
    }
}
