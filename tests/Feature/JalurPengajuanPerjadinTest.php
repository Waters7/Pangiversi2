<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sebelum formulir usulan dibuka, pengusul ditanya jalurnya: perjalanan
 * dalam kota, luar kota, atau supervisi kerja praktek / magang. Jalur itu
 * menyaring kategori perjadin dari master data dan menentukan berkas yang
 * diminta — supervisi berdasar surat tugas, ditambah SPD hanya bila
 * kategorinya ke luar kota.
 */
class JalurPengajuanPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->pengguna = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->seed(KategoriPerjadinSeeder::class);
        Kegiatan::firstOrCreate(['nama' => 'Mengikuti rapat, seminar, lokakarya, atau studi banding']);
    }

    private function kategori(string $kode): KategoriPerjadin
    {
        return KategoriPerjadin::where('kode', $kode)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'jenis' => JenisPerjadin::LuarKota->value,
            'id_kategori_perjadin' => $this->kategori('LK-NFB')->id,
            'id_kegiatan' => Kegiatan::first()->id,
            'no_spd' => 'AR.05.02/F.XXX/77/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
            'no_tugas' => 'KP.01.02/F.XXX/77/2026',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => today()->addDays(7)->toDateString(),
            'tanggal_selesai' => today()->addDays(9)->toDateString(),
            'uraian' => 'Kegiatan contoh.',
        ], $ubah);
    }

    // ── Langkah pertama: memilih jalur ──

    public function test_formulir_dibuka_dengan_pertanyaan_jalur(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Perjalanan dinas seperti apa yang akan diajukan?')
            ->assertSeeInOrder([
                'Perjalanan Dalam Kota Tidak Menggunakan SPD',
                'Perjalanan Luar Kota Dalam Satu Provinsi',
                'Perjalanan Luar Kota Lintas Pulau',
                'Supervisi Kerja Praktek / Magang',
            ])
            ->assertSee(route('usulan.create', ['jenis' => 'luar-kota-provinsi']))
            ->assertSee(route('usulan.create', ['jenis' => 'supervisi']))
            // Formulirnya sendiri belum muncul sebelum jalurnya dipilih.
            ->assertDontSee('Kategori Perjalanan Dinas');
    }

    public function test_jalur_yang_tidak_dikenal_kembali_ke_pertanyaan(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create', ['jenis' => 'keliling-dunia']))
            ->assertOk()
            ->assertSee('Perjalanan dinas seperti apa yang akan diajukan?');
    }

    // ── Kategori disaring menurut jalur ──

    public function test_kategori_perjadin_disaring_menurut_jalur(): void
    {
        $dalam = $this->actingAs($this->pengguna)->get(route('usulan.create', ['jenis' => 'dalam-kota']))->assertOk();
        $dalam->assertSee('Dalam Kota FullBoard')
            ->assertSee('Narasumber Dalam Kota Fullday')
            ->assertDontSee('Luar Kota NonFullBoard')
            ->assertDontSee('Supervisi Dalam Kota');

        $luar = $this->actingAs($this->pengguna)->get(route('usulan.create', ['jenis' => 'luar-kota']))->assertOk();
        $luar->assertSee('Luar Kota NonFullBoard')
            ->assertSee('Luar Negeri')
            ->assertDontSee('Dalam Kota FullBoard')
            ->assertDontSee('Supervisi Luar Kota');

        // Dalam satu provinsi: kategori luar kota yang sama, tanpa luar negeri.
        $provinsi = $this->actingAs($this->pengguna)->get(route('usulan.create', ['jenis' => 'luar-kota-provinsi']))->assertOk();
        $provinsi->assertSee('Perjalanan Luar Kota Dalam Satu Provinsi')
            ->assertSee('Luar Kota NonFullBoard')
            ->assertSee('Narasumber Luar Kota FullBoard')
            ->assertDontSee('Luar Negeri')
            ->assertDontSee('Dalam Kota FullBoard');

        $supervisi = $this->actingAs($this->pengguna)->get(route('usulan.create', ['jenis' => 'supervisi']))->assertOk();
        $supervisi->assertSee('Supervisi Dalam Kota')
            ->assertSee('Supervisi Luar Kota')
            ->assertDontSee('Luar Kota NonFullBoard')
            ->assertDontSee('Dalam Kota FullBoard');
    }

    public function test_kategori_di_luar_jalur_ditolak_saat_dikirim(): void
    {
        $this->actingAs($this->pengguna)
            ->from(route('usulan.create', ['jenis' => 'dalam-kota']))
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::DalamKota->value,
                'id_kategori_perjadin' => $this->kategori('LK-NFB')->id,
            ]))
            ->assertSessionHasErrors('id_kategori_perjadin');

        $this->assertDatabaseCount('usulan', 0);
    }

    // ── Jalur supervisi: surat tugas selalu, SPD hanya untuk luar kota ──

    public function test_formulir_supervisi_menjelaskan_spd_mengikuti_kategorinya(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create', ['jenis' => 'supervisi']))
            ->assertOk()
            ->assertSee('di dalam kota tidak memakai SPD')
            ->assertSee('tetap menerbitkan SPD')
            ->assertSee(JenisPerjadin::KEGIATAN_SUPERVISI)
            // Jenis kegiatannya tidak dapat dipilih sendiri.
            ->assertDontSee('— Pilih jenis kegiatan —')
            ->assertSee('name="surat_tugas"', false)
            // Isian SPD ikut dikirim, tetapi baru diwajibkan setelah kategori
            // luar kota dipilih — penjagaannya dipegang Alpine.
            ->assertSee('name="spd_ditandatangani"', false)
            ->assertSee('x-bind:required="butuhSpd"', false);
    }

    public function test_hanya_kategori_supervisi_luar_kota_yang_menerbitkan_spd(): void
    {
        $this->assertSame(
            [(string) $this->kategori('SV-LK')->id],
            JenisPerjadin::Supervisi->idKategoriBerSpd(),
        );

        $this->assertTrue(JenisPerjadin::Supervisi->butuhSpd($this->kategori('SV-LK')));
        $this->assertFalse(JenisPerjadin::Supervisi->butuhSpd($this->kategori('SV-DK')));

        // Jalur lain tidak bergantung kategorinya sama sekali: dalam kota
        // tanpa SPD, luar kota selalu dengan SPD.
        $this->assertFalse(JenisPerjadin::DalamKota->butuhSpd());
        $this->assertTrue(JenisPerjadin::LuarKotaDalamProvinsi->butuhSpd());
        $this->assertTrue(JenisPerjadin::LuarKota->butuhSpd());
    }

    public function test_usulan_supervisi_dalam_kota_tersimpan_tanpa_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::Supervisi->value,
                'id_kategori_perjadin' => $this->kategori('SV-DK')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
            ]))
            ->assertRedirect(route('usulan.list'))
            ->assertSessionHasNoErrors();

        $usulan = Usulan::sole();
        $this->assertSame(JenisPerjadin::Supervisi, $usulan->jalur());
        $this->assertFalse($usulan->butuhSpd());
        $this->assertNull($usulan->no_spd);

        // Jenis kegiatannya terisi sendiri, tidak mengikuti kiriman formulir.
        $this->assertSame(JenisPerjadin::KEGIATAN_SUPERVISI, $usulan->kegiatan->nama);

        // Surat tugas tetap tersimpan sebagai dasar penugasannya.
        $dokumen = $usulan->dokumen()->first();
        $this->assertNotNull($dokumen->surat_tugas);
        $this->assertNull($dokumen->spd_ditandatangani);
        Storage::disk('public')->assertExists($dokumen->surat_tugas);
    }

    public function test_surat_tugas_tetap_wajib_pada_jalur_supervisi(): void
    {
        $this->actingAs($this->pengguna)
            ->from(route('usulan.create', ['jenis' => 'supervisi']))
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::Supervisi->value,
                'id_kategori_perjadin' => $this->kategori('SV-DK')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
                'surat_tugas' => null,
            ]))
            ->assertSessionHasErrors('surat_tugas');

        $this->assertDatabaseCount('usulan', 0);
    }

    public function test_supervisi_ke_luar_kota_tetap_menuntut_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->from(route('usulan.create', ['jenis' => 'supervisi']))
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::Supervisi->value,
                'id_kategori_perjadin' => $this->kategori('SV-LK')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
            ]))
            ->assertSessionHasErrors(['no_spd', 'spd_ditandatangani']);

        $this->assertDatabaseCount('usulan', 0);
    }

    public function test_usulan_supervisi_luar_kota_tersimpan_beserta_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::Supervisi->value,
                'id_kategori_perjadin' => $this->kategori('SV-LK')->id,
            ]))
            ->assertRedirect(route('usulan.list'))
            ->assertSessionHasNoErrors();

        $usulan = Usulan::sole();
        $this->assertSame(JenisPerjadin::Supervisi, $usulan->jalur());
        $this->assertTrue($usulan->butuhSpd());
        $this->assertSame('AR.05.02/F.XXX/77/2026', $usulan->no_spd);
        $this->assertNotNull($usulan->dokumen()->first()->spd_ditandatangani);
    }

    /**
     * Kategori yang baru dikirim yang menentukan, bukan yang masih
     * tersimpan: supervisi dalam kota yang diubah menjadi luar kota
     * langsung dimintai SPD.
     */
    public function test_sunting_supervisi_menuntut_spd_setelah_kategorinya_jadi_luar_kota(): void
    {
        $this->actingAs($this->pengguna)->post(route('usulan.store'), $this->isian([
            'jenis' => JenisPerjadin::Supervisi->value,
            'id_kategori_perjadin' => $this->kategori('SV-DK')->id,
            'no_spd' => null,
            'spd_ditandatangani' => null,
            'action' => 'draft',
        ]));

        $usulan = Usulan::sole();

        $this->actingAs($this->pengguna)
            ->from(route('usulan.edit', $usulan))
            ->put(route('usulan.update', $usulan), [
                'id_kegiatan' => $usulan->id_kegiatan,
                'id_kategori_perjadin' => $this->kategori('SV-LK')->id,
                'no_tugas' => $usulan->no_tugas,
                'lokasi' => 'Jakarta',
                'instansi' => 'Kementerian Kesehatan',
                'tanggal_mulai' => today()->addDays(7)->toDateString(),
                'tanggal_selesai' => today()->addDays(9)->toDateString(),
            ])
            ->assertSessionHasErrors(['no_spd', 'spd_ditandatangani']);
    }

    public function test_jalur_berdasar_spd_tetap_menuntut_spd(): void
    {
        foreach ([JenisPerjadin::LuarKota, JenisPerjadin::LuarKotaDalamProvinsi] as $jalur) {
            $this->actingAs($this->pengguna)
                ->from(route('usulan.create', ['jenis' => $jalur->value]))
                ->post(route('usulan.store'), $this->isian(['jenis' => $jalur->value, 'no_spd' => null, 'spd_ditandatangani' => null]))
                ->assertSessionHasErrors(['no_spd', 'spd_ditandatangani']);
        }

        $this->assertDatabaseCount('usulan', 0);
    }

    public function test_usulan_luar_kota_dalam_provinsi_tersimpan_dengan_jalurnya(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isian(['jenis' => JenisPerjadin::LuarKotaDalamProvinsi->value]))
            ->assertSessionHasNoErrors();

        $usulan = Usulan::sole();
        $this->assertSame(JenisPerjadin::LuarKotaDalamProvinsi, $usulan->jalur());
        $this->assertTrue($usulan->butuhSpd());

        $this->actingAs($this->pengguna)
            ->from(route('usulan.create', ['jenis' => 'luar-kota-provinsi']))
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::LuarKotaDalamProvinsi->value,
                'id_kategori_perjadin' => $this->kategori('LN')->id,
                'no_spd' => 'AR.05.02/F.XXX/78/2026',
            ]))
            ->assertSessionHasErrors('id_kategori_perjadin');
    }

    // ── Jalur dalam kota: surat tugas saja, tanpa SPD ──

    public function test_formulir_dalam_kota_tidak_meminta_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create', ['jenis' => 'dalam-kota']))
            ->assertOk()
            ->assertSee('Perjalanan Dalam Kota Tidak Menggunakan SPD')
            ->assertDontSee('name="no_spd"', false)
            ->assertDontSee('name="spd_ditandatangani"', false)
            ->assertDontSee('name="id_spd"', false)
            ->assertSee('name="surat_tugas"', false);
    }

    public function test_usulan_dalam_kota_tersimpan_tanpa_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::DalamKota->value,
                'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
            ]))
            ->assertRedirect(route('usulan.list'))
            ->assertSessionHasNoErrors();

        $usulan = Usulan::sole();
        $this->assertFalse($usulan->butuhSpd());
        $this->assertNull($usulan->no_spd);
        $this->assertNotNull($usulan->dokumen()->first()->surat_tugas);
    }

    /** Peran yang melihat usulan semua pegawai kembali ke Usulan Perjadin Saya. */
    public function test_pengusul_yang_melihat_seluruh_usulan_kembali_ke_usulan_saya(): void
    {
        $pimpinan = User::factory()->create(['role' => User::ROLE_PIMPINAN]);

        $this->actingAs($pimpinan)
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::DalamKota->value,
                'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
            ]))
            ->assertRedirect(route('usulan.saya'));
    }

    public function test_surat_tugas_tetap_wajib_pada_jalur_dalam_kota(): void
    {
        $this->actingAs($this->pengguna)
            ->from(route('usulan.create', ['jenis' => 'dalam-kota']))
            ->post(route('usulan.store'), $this->isian([
                'jenis' => JenisPerjadin::DalamKota->value,
                'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
                'no_spd' => null,
                'spd_ditandatangani' => null,
                'surat_tugas' => null,
            ]))
            ->assertSessionHasErrors('surat_tugas');

        $this->assertDatabaseCount('usulan', 0);
    }

    /** Usulan dalam kota lama yang sudah bernomor SPD tidak kehilangan nomornya saat disunting. */
    public function test_menyunting_usulan_dalam_kota_lama_mempertahankan_nomor_spd(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengguna->id,
            'status' => 'draft',
            'jenis_perjadin' => JenisPerjadin::DalamKota->value,
            'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
            'id_kegiatan' => Kegiatan::first()->id,
            'no_spd' => 'AR.05.02/F.XXX/12/2026',
        ]);
        $usulan->dokumen()->create(['surat_tugas' => 'dokumen/surat-tugas/lama.pdf']);

        $this->actingAs($this->pengguna)
            ->put(route('usulan.update', $usulan), [
                'action' => 'draft',
                'id_kegiatan' => Kegiatan::first()->id,
                'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
                'no_tugas' => 'KP.01.02/F.XXX/80/2026',
                'lokasi' => 'Manado',
                'instansi' => 'Dinas Kesehatan',
                'tanggal_mulai' => today()->addDays(3)->toDateString(),
                'tanggal_selesai' => today()->addDays(3)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('AR.05.02/F.XXX/12/2026', $usulan->fresh()->no_spd);
    }

    // ── Jalur tersimpan dan terbaca kembali ──

    public function test_jalur_tersimpan_pada_usulan_dan_disimpulkan_untuk_usulan_lama(): void
    {
        $this->actingAs($this->pengguna)->post(route('usulan.store'), $this->isian([
            'jenis' => JenisPerjadin::DalamKota->value,
            'id_kategori_perjadin' => $this->kategori('DK-FD')->id,
        ]));

        $usulan = Usulan::sole();
        $this->assertSame('dalam-kota', $usulan->jenis_perjadin);
        $this->assertSame(JenisPerjadin::DalamKota, $usulan->jalur());

        // Usulan lama belum menyimpan jalurnya; kategorinya yang menyimpulkan.
        $usulan->update(['jenis_perjadin' => null, 'id_kategori_perjadin' => $this->kategori('SV-DK')->id]);
        $this->assertSame(JenisPerjadin::Supervisi, $usulan->fresh()->jalur());

        $usulan->update(['id_kategori_perjadin' => $this->kategori('LN')->id]);
        $this->assertSame(JenisPerjadin::LuarKota, $usulan->fresh()->jalur());
    }

    /**
     * Pemasangan yang belum menjalankan ulang seeder pun tetap dapat memakai
     * jalur supervisi: master datanya disiapkan aplikasi saat jalur itu
     * pertama kali dibuka.
     */
    public function test_master_data_supervisi_disiapkan_saat_jalurnya_dibuka(): void
    {
        KategoriPerjadin::where('grup', JenisPerjadin::GRUP_SUPERVISI)->delete();
        $this->assertDatabaseMissing('kegiatan', ['nama' => JenisPerjadin::KEGIATAN_SUPERVISI]);

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create', ['jenis' => 'supervisi']))
            ->assertOk()
            ->assertSee('Supervisi Dalam Kota')
            ->assertSee(JenisPerjadin::KEGIATAN_SUPERVISI);

        $this->assertDatabaseHas('kategori_perjadin', [
            'kode' => 'SV-DK',
            'grup' => JenisPerjadin::GRUP_SUPERVISI,
            'dalam_kota' => true,
        ]);
        $this->assertDatabaseHas('kategori_perjadin', ['kode' => 'SV-LK', 'dalam_kota' => false]);
        $this->assertDatabaseHas('kegiatan', ['nama' => JenisPerjadin::KEGIATAN_SUPERVISI]);
    }
}
