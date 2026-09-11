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
        $this->assertSame('Uang muka dibayarkan', $berikutnya['judul']);
    }

    /**
     * SPD terbit sebelum usulan ada dan persetujuan PPK melekat pada SPD yang
     * ditandatanganinya, jadi keduanya bukan tahap yang perlu dilacak.
     */
    public function test_persetujuan_ppk_dan_terbitnya_spd_bukan_tonggak(): void
    {
        $judul = $this->pelacak()->tonggak($this->usulan)->pluck('judul');

        $this->assertFalse($judul->contains('Disetujui PPK'));
        $this->assertFalse($judul->contains('Surat Perjalanan Dinas terbit'));
        $this->assertSame('Usulan dibuat', $judul->first());
        $this->assertSame('Uang muka dibayarkan', $judul->get(1));
    }

    // ── Rentang hari antar tahap ──

    public function test_tahap_pertama_tidak_punya_durasi(): void
    {
        $this->assertNull($this->tonggak('Usulan dibuat')['durasi']);
    }

    public function test_tahap_terlewati_menghitung_hari_dari_tahap_sebelumnya(): void
    {
        $this->usulan->forceFill(['created_at' => '2026-04-01 09:00:00'])->save();
        $this->usulan->keuangan->update(['tanggal_transfer' => '2026-04-04']);

        $langkah = $this->tonggak('Uang muka dibayarkan');

        $this->assertSame(3, $langkah['durasi']);
        $this->assertSame('3 hari dari tahap sebelumnya', $langkah['durasi_label']);
    }

    public function test_tahap_di_hari_yang_sama_disebut_demikian(): void
    {
        $this->usulan->forceFill(['created_at' => '2026-04-04 09:00:00'])->save();
        $this->usulan->keuangan->update(['tanggal_transfer' => '2026-04-04']);

        $this->assertSame('di hari yang sama dari tahap sebelumnya', $this->tonggak('Uang muka dibayarkan')['durasi_label']);
    }

    /** Tahap yang sedang menunggu dihitung sampai hari ini. */
    public function test_tahap_yang_menunggu_menghitung_lamanya_tertahan(): void
    {
        $this->usulan->forceFill(['created_at' => today()->subDays(6)->setTime(9, 0)])->save();

        $menunggu = $this->tonggak('Uang muka dibayarkan');
        $sesudahnya = $this->tonggak('Pelunasan dibayarkan');

        $this->assertSame(6, $menunggu['durasi']);
        $this->assertSame('Sudah menunggu 6 hari', $menunggu['durasi_label']);
        // Tahap yang menunggu di belakangnya belum bisa diukur.
        $this->assertNull($sesudahnya['durasi_label']);
    }

    /** Dokumen bisa diunggah sebelum uang muka cair; selisih mundur tidak jadi angka minus. */
    public function test_tahap_yang_terlewati_lebih_dulu_tidak_berdurasi_negatif(): void
    {
        $this->usulan->forceFill(['created_at' => '2026-04-01 09:00:00'])->save();
        $this->usulan->keuangan->update(['tanggal_transfer' => '2026-04-10']);
        $this->usulan->dokumen()->create(['surat_tugas' => 'dokumen/st.pdf']);
        $this->usulan->dokumen()->latest('id')->first()->forceFill(['updated_at' => '2026-04-05 09:00:00'])->save();

        $this->assertSame(0, $this->tonggak('Dokumen pertanggungjawaban diunggah')['durasi']);
    }

    public function test_lama_berjalan_dihitung_dari_tahap_pertama(): void
    {
        $this->usulan->forceFill(['created_at' => today()->subDays(10)->setTime(9, 0)])->save();

        $this->assertSame(10, $this->pelacak()->lamaBerjalan($this->usulan->fresh()));
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
        $this->tandatanganiBerkas($this->usulan, $this->ppk);

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

    /**
     * Daftar nominatif terbit per surat tugas begitu satu pelaksana tuntas.
     * Bagi kawan seperjalanan yang berkasnya belum ditandatangani PPK,
     * daftar itu belum memuat dirinya — tonggaknya belum terlewati.
     */
    public function test_tonggak_nominatif_belum_terlewati_bila_usulan_ini_belum_tercantum(): void
    {
        DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'tanggal_tugas' => now()->toDateString(),
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);

        $this->assertFalse($this->tonggak('Daftar nominatif diverifikasi PPK')['selesai']);
        $this->assertFalse($this->tonggak('Nominatif diterima tim keuangan')['selesai']);

        $this->tandatanganiBerkas($this->usulan, $this->ppk);

        $this->assertTrue($this->tonggak('Daftar nominatif diverifikasi PPK')['selesai']);
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

    public function test_detail_usulan_menampilkan_rentang_hari_tiap_tahap(): void
    {
        $this->usulan->forceFill(['created_at' => today()->subDays(4)->setTime(9, 0)])->save();
        $this->usulan->keuangan->update(['tanggal_transfer' => today()->subDays(2)->toDateString()]);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('2 hari dari tahap sebelumnya')
            ->assertSee('Sudah menunggu 2 hari')
            ->assertSee('berjalan 4 hari')
            ->assertDontSee('Disetujui PPK')
            ->assertDontSee('Surat Perjalanan Dinas terbit');
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
