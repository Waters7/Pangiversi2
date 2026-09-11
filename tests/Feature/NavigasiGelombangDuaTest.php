<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pembenahan navigasi: antrean kerja di halaman muka, langkah berikutnya
 * pada daftar usulan, kotak cari, dan penyalinan usulan.
 *
 * Yang diuji di sini bukan tampilannya, melainkan bahwa halamannya
 * benar-benar membawa keterangan itu dan saringannya benar-benar menyaring.
 */
class NavigasiGelombangDuaTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(KategoriPerjadinSeeder::class);

        $this->pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);
    }

    private function usulan(array $ganti = []): Usulan
    {
        return Usulan::factory()->create($ganti + [
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'id_kategori_perjadin' => KategoriPerjadin::value('id'),
        ]);
    }

    // ── N-01 · Antrean kerja di halaman muka ──

    public function test_halaman_muka_menampilkan_antrean_peran_yang_membukanya(): void
    {
        $usulan = $this->usulan();
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan->id,
            'total_riil' => 250_000,
            'dikirim_ke_pegawai_at' => now(),
            'batas_sanggah' => today()->addDays(3),
            'disetujui_pegawai_at' => now(),
        ]);

        $ppk = User::factory()->ppk()->create();

        $this->actingAs($ppk)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Menunggu Tindakan Anda')
            ->assertSee('Daftar riil menunggu tanda tangan');
    }

    /** Pelaksana biasa tidak mengantre apa pun, jadi panelnya tidak muncul. */
    public function test_panel_antrean_tidak_muncul_bagi_yang_tidak_mengantre(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Menunggu Tindakan Anda');
    }

    public function test_dashboard_eksekutif_juga_membawa_antrean(): void
    {
        $usulan = $this->usulan();
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        $bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);

        $this->actingAs($bendahara)
            ->get(route('dashboard-eksekutif'))
            ->assertOk()
            ->assertSee('Perjadin menunggu dibayar');
    }

    // ── N-04 · Langkah berikutnya pada daftar usulan ──

    /**
     * Kolom No. / Tanggal pernah memuat tanggal mati "12 Jan 2025" sisa
     * templat; yang tercetak harus tanggal nomor perjadin itu dibuat.
     */
    public function test_daftar_usulan_mencetak_tanggal_usulan_dibuat(): void
    {
        $usulan = $this->usulan();
        $usulan->forceFill(['created_at' => '2026-03-15 08:30:00'])->save();

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('15 Mar 2026')
            ->assertDontSee('12 Jan 2025');
    }

    /**
     * Tombol kendali daftar memakai nama bakunya — Search, Filter, Export,
     * Reset — bukan terjemahan yang berbeda-beda antarhalaman.
     */
    public function test_tombol_kendali_daftar_memakai_nama_baku(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.tindak-lanjut'))
            ->assertOk()
            ->assertSee('>Filter<', false)
            ->assertDontSee('>Saring<', false);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee('Search')
            ->assertDontSee('>Cari<', false);
    }

    public function test_daftar_usulan_menyebutkan_langkah_berikutnya(): void
    {
        $this->usulan();

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('Menunggu:')
            ->assertSee('Uang muka dibayarkan');
    }

    /**
     * Pelacaknya memuat daftar nominatif lewat nomor surat tugas, bukan lewat
     * relasi — tanpa persiapan, tiap baris mencarinya sendiri.
     */
    public function test_langkah_berikutnya_tidak_menambah_kueri_per_baris(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->pelaksana)->get(route('usulan.list'))->assertOk();

            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        foreach (range(1, 2) as $i) {
            $this->usulan(['no_tugas' => 'KP.03.01/F.XXXVIII/'.$i.'/2026']);
        }

        $sedikit = $hitung();

        foreach (range(3, 9) as $i) {
            $this->usulan(['no_tugas' => 'KP.03.01/F.XXXVIII/'.$i.'/2026']);
        }

        $banyak = $hitung();

        $this->assertLessThanOrEqual(
            $sedikit,
            $banyak,
            "Daftar usulan menjalankan {$sedikit} kueri pada 2 baris dan {$banyak} pada 9.",
        );
    }

    // ── A-01 · Kotak cari ──

    public function test_kotak_cari_muncul_pada_halaman_yang_dulu_belum_punya(): void
    {
        $ppk = User::factory()->ppk()->create();

        foreach ([
            route('persetujuan.daftar-riil'),
            route('persetujuan.nominatif'),
            route('rincian-saya.daftar-riil'),
            route('rincian-saya.rincian-biaya'),
            route('dokumen'),
            route('dokumen.laporan.index'),
            route('spd.index'),
            route('laporan.nominatif'),
        ] as $alamat) {
            $this->actingAs($ppk)
                ->get($alamat)
                ->assertOk()
                ->assertSee('name="cari"', escape: false);
        }
    }

    public function test_pencarian_dokumen_menyaring_menurut_nomor_usulan(): void
    {
        $dicari = $this->usulan(['lokasi' => 'Jakarta']);
        $lain = $this->usulan(['lokasi' => 'Surabaya']);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen', ['cari' => $dicari->no_usulan]))
            ->assertOk()
            ->assertSee($dicari->no_usulan)
            ->assertDontSee($lain->no_usulan);
    }

    public function test_pencarian_spd_menyaring_menurut_tujuan(): void
    {
        $this->actingAs($this->pelaksana)->post('/spd', [
            'dikeluarkan_di' => 'Manado',
            'pelaksana' => [[
                'nomor_surat' => '77',
                'nama' => $this->pelaksana->nama,
                'nip' => '198001012010011001',
                'id_user' => $this->pelaksana->id,
            ]],
            'maksud' => 'Rapat koordinasi.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Yogyakarta',
            'tanggal_berangkat' => today()->addDays(3)->toDateString(),
            'tanggal_kembali' => today()->addDays(5)->toDateString(),
        ])->assertRedirect();

        $this->actingAs($this->pelaksana)
            ->get(route('spd.index', ['cari' => 'Yogyakarta']))
            ->assertOk()
            ->assertSee('Yogyakarta');

        $this->actingAs($this->pelaksana)
            ->get(route('spd.index', ['cari' => 'Palembang']))
            ->assertOk()
            ->assertDontSee('Yogyakarta');
    }

    // ── F-02 · Simpan draf otomatis ──

    public function test_formulir_usulan_memasang_penyimpan_draf(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('pangi-draf-usulan');
    }

    // ── N-03 · Nama menu tidak lagi bertabrakan ──

    public function test_nama_menu_sidebar_tidak_ada_yang_kembar(): void
    {
        $isi = $this->actingAs(User::factory()->create([
            'role' => PeranPengguna::SuperAdministrator->value,
        ]))->get(route('dashboard'))->assertOk()->getContent();

        foreach (['Bayar Transport Lokal', 'Periksa Transport Lokal', 'Daftar Riil Saya',
            'Arsip Daftar Riil', 'Rincian Biaya Saya', 'Input Rincian Biaya',
            'Arsip Rincian Lengkap'] as $nama) {
            $this->assertSame(
                1,
                substr_count($isi, ">\n              {$nama}\n"),
                "Nama menu \"{$nama}\" seharusnya muncul tepat sekali di sidebar.",
            );
        }
    }
}
