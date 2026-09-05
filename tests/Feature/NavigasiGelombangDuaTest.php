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

    public function test_daftar_usulan_menyebutkan_langkah_berikutnya(): void
    {
        $this->usulan();

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('Menunggu:')
            ->assertSee('Disetujui PPK');
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

    // ── F-03 · Salin dari usulan sebelumnya ──

    public function test_formulir_terisi_dari_usulan_yang_disalin(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $asal = $this->usulan([
            'lokasi' => 'Bandung',
            'instansi' => 'Kementerian Kesehatan',
            'uraian' => 'Rapat penyusunan kurikulum.',
        ]);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['salin' => $asal->no_usulan]))
            ->assertOk()
            ->assertSee('Isian disalin dari usulan')
            ->assertSee($asal->no_usulan)
            ->assertSee('Bandung')
            ->assertSee('Kementerian Kesehatan')
            ->assertSee('Rapat penyusunan kurikulum.');
    }

    /** Tanggal dan SPD harus baru; menyalinnya justru menyesatkan. */
    public function test_tanggal_perjalanan_tidak_ikut_disalin(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $asal = $this->usulan([
            'tanggal_mulai' => today()->subDays(30)->toDateString(),
            'tanggal_selesai' => today()->subDays(28)->toDateString(),
        ]);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['salin' => $asal->no_usulan]))
            ->assertOk()
            ->assertDontSee('value="'.today()->subDays(30)->toDateString().'"', escape: false);
    }

    public function test_usulan_orang_lain_tidak_dapat_disalin(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $oranglain = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        $asal = Usulan::factory()->create([
            'id_user' => $oranglain->id,
            'lokasi' => 'Rahasia Denpasar',
            'id_kategori_perjadin' => KategoriPerjadin::value('id'),
        ]);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['salin' => $asal->no_usulan]))
            ->assertOk()
            ->assertDontSee('Isian disalin dari usulan')
            ->assertDontSee('Rahasia Denpasar');
    }

    public function test_nomor_salinan_yang_tidak_ada_diabaikan(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['salin' => 'TIDAK/ADA/2026']))
            ->assertOk()
            ->assertDontSee('Isian disalin dari usulan');
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

    /** Draf tidak boleh menimpa isian yang sedang disalin dari usulan lama. */
    public function test_draf_tidak_dipulihkan_saat_menyalin(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $asal = $this->usulan();

        $isi = $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['salin' => $asal->no_usulan]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('bolehPulihkan: false', $isi);
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
