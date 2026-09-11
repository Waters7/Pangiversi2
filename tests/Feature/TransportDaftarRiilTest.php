<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\Notifikasi;
use App\Models\PesertaUsulan;
use App\Models\RincianDaftarRiil;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Biaya transport lokal dipertanggungjawabkan lewat Daftar Pengeluaran Riil
 * (Lampiran IX PMK 113/2012), bukan lewat rincian biaya — ia dinyatakan
 * sendiri oleh pelaksana, bukan ditagihkan dengan kuitansi resmi.
 */
class TransportDaftarRiilTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $timKeuangan;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama_bank' => 'Bank Mandiri',
            'nomor_rekening' => '1520001234567',
            'nama_rekening' => 'Rahmatullah Ade',
        ]);

        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
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

    /**
     * @param  array<int, float>  $nominal
     */
    private function isiNota(array $nominal): void
    {
        foreach ($nominal as $urutan => $angka) {
            $this->usulan->notaTransport()->updateOrCreate(['urutan' => $urutan], ['nominal' => $angka]);
        }

        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
    }

    private function daftar(): ?DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id);
    }

    /**
     * Tim keuangan menyatakan seluruh nominal sudah diperiksa.
     * Tanpa ini berkasnya tertahan dan belum boleh dikirim ke pelaksana.
     */
    private function validasiNominal(): void
    {
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);
    }

    // ── Transport masuk daftar riil ──

    public function test_nota_transport_tidak_masuk_rincian_biaya(): void
    {
        $this->isiNota([1 => 75_000, 2 => 150_000]);

        $this->assertSame(0, $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->count());
    }

    public function test_nota_transport_masuk_daftar_riil_per_ruas(): void
    {
        $this->isiNota([1 => 75_000, 2 => 150_000]);

        $rincian = $this->daftar()->rincian;

        $this->assertCount(2, $rincian);
        $this->assertSame('Rumah ke bandara', $rincian->first()->uraian);
        $this->assertSame(75_000.0, $rincian->first()->nominal);
        $this->assertTrue($rincian->first()->dariDokumen());
    }

    public function test_total_daftar_riil_mengikuti_jumlah_barisnya(): void
    {
        $this->isiNota([1 => 75_000, 2 => 150_000, 3 => 150_000, 4 => 75_000]);

        $this->assertSame(450_000.0, $this->daftar()->total_riil);
    }

    public function test_nota_yang_dikosongkan_dicabut_dari_daftar_riil(): void
    {
        $this->isiNota([1 => 75_000, 2 => 150_000]);
        $this->assertCount(2, $this->daftar()->rincian);

        $this->usulan->notaTransport()->where('urutan', 2)->update(['nominal' => null]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertCount(1, $this->daftar()->fresh()->rincian);
        $this->assertSame(75_000.0, $this->daftar()->fresh()->total_riil);
    }

    /**
     * Daftar yang sudah ditandatangani PPK tidak boleh berubah diam-diam saat
     * pelaksana menyunting notanya.
     */
    public function test_daftar_bertanda_tangan_tidak_ikut_berubah(): void
    {
        $this->isiNota([1 => 75_000]);

        $daftar = $this->daftar();
        $daftar->update(['ditandatangani_at' => now(), 'id_ppk' => User::factory()->ppk()->create()->id]);

        $this->usulan->notaTransport()->where('urutan', 1)->update(['nominal' => 900_000]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertSame(75_000.0, $daftar->fresh()->total_riil);
    }

    // ── Pengiriman ke pelaksana oleh tim keuangan ──

    /**
     * Tim keuangan memeriksa nominalnya lebih dulu, lalu mengirimkan
     * kedua dokumen kepada pelaksana. PPK menandatangani belakangan.
     */
    public function test_tim_keuangan_yang_mengirim_berkas_ke_pelaksana(): void
    {
        $this->isiNota([1 => 75_000]);
        $this->validasiNominal();

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNotNull($this->daftar()->dikirim_ke_pegawai_at);
    }

    public function test_ppk_tidak_mengirim_berkas_ke_pelaksana(): void
    {
        $this->isiNota([1 => 75_000]);

        $this->actingAs(User::factory()->ppk()->create())
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertForbidden();

        $this->assertNull($this->daftar()->dikirim_ke_pegawai_at);
    }

    // ── Rekening pelaksana ──

    public function test_detail_keuangan_menampilkan_rekening_pelaksana(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('Rekening Pelaksana')
            ->assertSee('Bank Mandiri')
            ->assertSee('1520001234567');
    }

    public function test_menu_pembayaran_menampilkan_rekening_pelaksana(): void
    {
        $this->usulan->keuangan->update(['status' => Keuangan::STATUS_BELUM]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_BENDAHARA]))
            ->get(route('pembayaran', ['tahap' => 'disetujui']))
            ->assertOk()
            ->assertSee('Bank Mandiri')
            ->assertSee('1520001234567');
    }

    public function test_rekening_yang_belum_lengkap_ditandai(): void
    {
        $this->pelaksana->update(['nomor_rekening' => null]);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('Belum dilengkapi');
    }

    // ── Layanan ──

    public function test_layanan_melewati_usulan_tanpa_peserta(): void
    {
        $this->peserta->delete();

        // Tidak boleh melempar galat: usulan lama bisa saja belum berpeserta.
        app(SinkronBiayaDokumen::class)->selaraskanDaftarRiil($this->usulan->fresh());

        $this->assertNull($this->daftar());
    }

    /**
     * Daftar riil terbit hanya untuk transport lokal. Komponen lain sudah
     * dipertanggungjawabkan pada rincian biaya, jadi tiket dan bill hotel
     * tidak boleh ikut masuk sekalipun keduanya terisi.
     */
    public function test_hanya_transport_lokal_yang_masuk_daftar_riil(): void
    {
        $this->usulan->tiket()->create([
            'arah' => ArahTiket::Pergi->value,
            'kota_asal' => 'Manado',
            'kota_tujuan' => 'Jakarta',
            'nomor_tiket' => 'GA-602',
            'kode_booking' => 'XY7QW2',
            'harga' => 2_500_000,
            'boarding_pass' => 'dokumen/bp.pdf',
        ]);

        Dokumen::updateOrCreate(
            ['id_usulan' => $this->usulan->id],
            ['bill_hotel_no_transaksi' => 'TRX-9', 'bill_hotel_nominal' => 800_000],
        );

        $this->isiNota([1 => 75_000]);

        $daftar = $this->daftar();

        $this->assertCount(1, $daftar->rincian);
        $this->assertSame(75_000.0, $daftar->total_riil);

        // Tiket dan penginapan tetap tercatat, tetapi pada rincian biaya.
        $kategori = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya
            ->pluck('kategori')->map->value->sort()->values()->all();

        $this->assertSame(['penginapan', 'transport'], $kategori);
    }

    public function test_seluruh_baris_bersumber_dari_nota_pelaksana(): void
    {
        $this->isiNota([1 => 75_000, 2 => 150_000]);

        $sumber = $this->daftar()->rincian->pluck('sumber')->unique()->values()->all();

        $this->assertSame([RincianDaftarRiil::SUMBER_DOKUMEN], $sumber);
    }

    // ── Pengajuan ke PPK ──

    /**
     * Begitu pelaksana mengisi notanya, berkasnya berjalan ke tim
     * keuangan — merekalah yang memeriksa nominal dan memisahkan
     * transport lokal sebelum dikirim kembali kepada pelaksana.
     */
    public function test_berkas_diajukan_ke_tim_keuangan(): void
    {
        $this->isiNota([1 => 75_000]);

        $this->assertNotNull($this->daftar()->diajukan_at);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Berkas pertanggungjawaban menunggu verifikasi',
        ]);
    }

    /**
     * Pemberitahuan dikirim sekali saja; tanpa penjagaan ini tim keuangan
     * menerima pesan yang sama tiap kali pelaksana menyunting notanya.
     */
    public function test_tim_keuangan_tidak_diberi_tahu_berulang_kali(): void
    {
        $this->isiNota([1 => 75_000]);
        $this->isiNota([1 => 90_000]);

        $this->assertSame(
            1,
            Notifikasi::where('judul', 'Berkas pertanggungjawaban menunggu verifikasi')->count()
        );
    }

    // ── Meja kerja PPK ──

    /**
     * Berkas yang belum divalidasi dan dikirim tim keuangan belum menjadi
     * urusan PPK: ia tampak sebagai berkas berjalan, bukan antrian kerja.
     */
    public function test_menu_persetujuan_memisahkan_yang_masih_di_keuangan(): void
    {
        $this->isiNota([1 => 75_000]);

        $ppk = User::factory()->ppk()->create();

        $this->actingAs($ppk)
            ->get(route('persetujuan.daftar-riil'))
            ->assertOk()
            ->assertSee('Verifikasi Daftar Riil Transportasi Pelaksana')
            ->assertDontSee($this->usulan->no_usulan);

        $this->actingAs($ppk)
            ->get(route('persetujuan.daftar-riil', ['status' => 'menunggu-keuangan']))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan);
    }

    public function test_verifikasi_daftar_riil_dikelompokkan_per_bulan(): void
    {
        $this->usulan->update(['tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-06']);
        $this->isiNota([1 => 75_000]);

        $ppk = User::factory()->ppk()->create();

        $this->actingAs($ppk)
            ->get(route('persetujuan.daftar-riil', ['status' => 'menunggu-keuangan']))
            ->assertOk()
            ->assertSee('Mei 2026')
            ->assertViewHas('daftar', fn ($daftar) => $daftar->keys()->first() === 'Mei 2026')
            ->assertViewHas('jumlahBulan', fn ($bulan) => ($bulan[5] ?? 0) === 1);

        // Saringan periode mempertahankan tab yang sedang dibuka.
        $this->actingAs($ppk)
            ->get(route('persetujuan.daftar-riil', ['status' => 'menunggu-keuangan', 'tahun' => 2026, 'bulan' => 6]))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());
    }

    public function test_menu_persetujuan_tertutup_bagi_peran_lain(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('persetujuan.daftar-riil'))
            ->assertForbidden();
    }

    public function test_menu_persetujuan_mengelompokkan_menurut_tahapnya(): void
    {
        $this->isiNota([1 => 75_000]);

        $this->actingAs(User::factory()->ppk()->create())
            ->get(route('persetujuan.daftar-riil', ['status' => 'selesai']))
            ->assertOk()
            ->assertSee('Tidak ada berkas pada kelompok ini');
    }
}
