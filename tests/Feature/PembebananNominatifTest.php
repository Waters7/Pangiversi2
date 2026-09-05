<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\KategoriPembiayaan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pembebanan daftar nominatif dinyatakan dua hal yang berbeda: kategori
 * pembiayaan menyebut sumber dananya (RM, BLU, LN), akun pembiayaan menyebut
 * mata anggaran yang dibebani. Satu sumber dana dapat membebani beberapa
 * akun, jadi keduanya dipilih terpisah.
 */
class PembebananNominatifTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/91/2026';

    private User $timKeuangan;

    /** Master data hanya dikelola super administrator. */
    private User $admin;

    private DaftarNominatif $nominatif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->nominatif = DaftarNominatif::create([
            'no_tugas' => self::NO_TUGAS,
            'id_ppk' => User::factory()->ppk()->create()->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);
    }

    // ── Master data ──

    public function test_kategori_pembiayaan_terisi_sejak_pemasangan(): void
    {
        $this->assertSame(
            ['BLU', 'LN', 'RM'],
            KategoriPembiayaan::orderBy('kode')->pluck('kode')->all(),
        );
    }

    public function test_halaman_master_kategori_terbuka(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master.kategori-pembiayaan'))
            ->assertOk()
            ->assertSee('Kategori Pembiayaan')
            ->assertSee('Rupiah Murni');
    }

    public function test_kategori_baru_dapat_ditambahkan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.kategori-pembiayaan.store'), [
                'kode' => 'PNBP',
                'nama' => 'Penerimaan Negara Bukan Pajak',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('kategori_pembiayaan', ['kode' => 'PNBP']);
    }

    /**
     * Kategori yang sudah membiayai daftar nominatif tidak boleh dihapus:
     * daftar lama akan kehilangan sumber dananya.
     */
    public function test_kategori_yang_terpakai_tidak_dapat_dihapus(): void
    {
        $kategori = KategoriPembiayaan::firstWhere('kode', 'RM');
        $this->nominatif->update(['id_kategori_pembiayaan' => $kategori->id]);

        $this->actingAs($this->admin)
            ->delete(route('master.kategori-pembiayaan.destroy', $kategori))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kategori_pembiayaan', ['id' => $kategori->id]);
    }

    // ── Penetapan pada nominatif ──

    public function test_kedua_pilihan_tampil_pada_daftar_nominatif(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee('Kategori Pembiayaan')
            ->assertSee('Akun Pembiayaan')
            ->assertSee('RM — Rupiah Murni')
            ->assertSee('524111 — Belanja Perjalanan Dinas Biasa');
    }

    public function test_kategori_dan_akun_ditetapkan_sekaligus(): void
    {
        $kategori = KategoriPembiayaan::firstWhere('kode', 'BLU');
        $akun = AkunPembiayaan::firstWhere('kode', '524111');

        $this->actingAs($this->timKeuangan)
            ->put(route('laporan.nominatif.akun', $this->nominatif), [
                'id_kategori_pembiayaan' => $kategori->id,
                'id_akun_pembiayaan' => $akun->id,
            ])
            ->assertSessionHas('success');

        $this->nominatif->refresh();

        $this->assertSame($kategori->id, $this->nominatif->id_kategori_pembiayaan);
        $this->assertSame($akun->id, $this->nominatif->id_akun_pembiayaan);
    }

    public function test_pembebanan_dapat_dikosongkan_kembali(): void
    {
        $this->nominatif->update([
            'id_kategori_pembiayaan' => KategoriPembiayaan::value('id'),
            'id_akun_pembiayaan' => AkunPembiayaan::value('id'),
        ]);

        $this->actingAs($this->timKeuangan)
            ->put(route('laporan.nominatif.akun', $this->nominatif), [
                'id_kategori_pembiayaan' => null,
                'id_akun_pembiayaan' => null,
            ])
            ->assertSessionHas('success');

        $this->nominatif->refresh();

        $this->assertNull($this->nominatif->id_kategori_pembiayaan);
        $this->assertNull($this->nominatif->id_akun_pembiayaan);
    }

    public function test_kategori_asing_ditolak(): void
    {
        $this->actingAs($this->timKeuangan)
            ->put(route('laporan.nominatif.akun', $this->nominatif), [
                'id_kategori_pembiayaan' => 9999,
            ])
            ->assertSessionHasErrors('id_kategori_pembiayaan');
    }

    public function test_daftar_tanpa_pembebanan_ditandai(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee('Pembebanan belum ditetapkan');
    }

    public function test_kode_pembebanan_tampil_setelah_ditetapkan(): void
    {
        $this->nominatif->update([
            'id_kategori_pembiayaan' => KategoriPembiayaan::firstWhere('kode', 'LN')->id,
            'id_akun_pembiayaan' => AkunPembiayaan::firstWhere('kode', '524113')->id,
        ]);

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertDontSee('Pembebanan belum ditetapkan');
    }

    // ── Rentang tanggal pada cetakan ──

    /**
     * Kolom lamanya perjalanan menyebut jumlah harinya sekaligus rentang
     * tanggalnya, supaya pemeriksa dapat menakar kewajarannya tanpa membuka
     * SPD satu per satu.
     */
    public function test_baris_nominatif_menyebut_rentang_tanggal_perjalanan(): void
    {
        $pelaksana = User::factory()->create();

        $usulan = Usulan::factory()->create([
            'id_user' => $pelaksana->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-04-06',
            'tanggal_selesai' => '2026-04-08',
        ]);

        $baris = app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->first();

        $this->assertNotNull($baris, 'Baris nominatif tidak tersusun.');
        $this->assertSame('2026-04-06', $baris['berangkat']?->toDateString());
        $this->assertSame('2026-04-08', $baris['kembali']?->toDateString());
        $this->assertNotNull($usulan->no_tugas);
    }
    // ── Pengelompokan per periode ──

    /** Daftar nominatif lain, diterima tim keuangan pada tanggal tertentu. */
    private function nominatifDiterima(string $noTugas, string $tanggal): DaftarNominatif
    {
        return DaftarNominatif::create([
            'no_tugas' => $noTugas,
            'id_ppk' => User::factory()->ppk()->create()->id,
            'ditandatangani_at' => $tanggal,
            'dikirim_at' => $tanggal,
        ]);
    }

    /** Jumlah daftar yang benar-benar tampil pada halaman. */
    private function jumlahTampil(TestResponse $halaman): int
    {
        return $halaman->viewData('daftar')->flatten(1)->count();
    }

    public function test_daftar_dikelompokkan_per_bulan_diterima(): void
    {
        $this->nominatif->update(['dikirim_at' => '2026-03-11']);
        $this->nominatifDiterima('KP.03.01/F.XXXVIII/92/2026', '2026-07-22');

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            // Terbaru di atas, mengikuti urutan penerimaannya.
            ->assertSeeInOrder(['Juli 2026', 'Maret 2026']);
    }

    public function test_saringan_bulan_membatasi_daftarnya(): void
    {
        $this->nominatif->update(['dikirim_at' => '2026-03-11']);
        $this->nominatifDiterima('KP.03.01/F.XXXVIII/92/2026', '2026-07-22');

        $this->assertSame(1, $this->jumlahTampil(
            $this->actingAs($this->timKeuangan)->get(route('laporan.nominatif', ['bulan' => 3]))->assertOk()
        ));

        $this->assertSame(0, $this->jumlahTampil(
            $this->actingAs($this->timKeuangan)->get(route('laporan.nominatif', ['bulan' => 5]))->assertOk()
        ));
    }

    public function test_saringan_tahun_membatasi_daftarnya(): void
    {
        $this->nominatif->update(['dikirim_at' => '2026-03-11']);
        $this->nominatifDiterima('KP.03.01/F.XXXVIII/92/2025', '2025-11-04');

        $this->assertSame(1, $this->jumlahTampil(
            $this->actingAs($this->timKeuangan)->get(route('laporan.nominatif', ['tahun' => 2025]))->assertOk()
        ));

        $this->assertSame(2, $this->jumlahTampil(
            $this->actingAs($this->timKeuangan)->get(route('laporan.nominatif'))->assertOk()
        ));
    }

    public function test_hanya_tahun_yang_berisi_yang_ditawarkan(): void
    {
        $this->nominatif->update(['dikirim_at' => '2026-03-11']);

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertViewHas('tahunTersedia', fn ($tahun) => $tahun->all() === [2026]);
    }

    public function test_saringan_periode_mempertahankan_akun_yang_dipilih(): void
    {
        $akun = AkunPembiayaan::first();
        $this->nominatif->update(['dikirim_at' => '2026-03-11', 'id_akun_pembiayaan' => $akun->id]);

        // Memilih bulan tidak boleh melepaskan saringan akunnya.
        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif', ['akun' => $akun->id]))
            ->assertOk()
            ->assertSee('akun='.$akun->id.'&amp;bulan=3', false);
    }
    // ── Cetakan ──

    public function test_cetakan_memuat_kedua_pembebanan(): void
    {
        $this->nominatif->update([
            'id_kategori_pembiayaan' => KategoriPembiayaan::firstWhere('kode', 'RM')->id,
            'id_akun_pembiayaan' => AkunPembiayaan::firstWhere('kode', '524111')->id,
        ]);

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif.cetak', $this->nominatif))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
