<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
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
 * Kategori perjalanan dinas menentukan kelas biayanya — fullboard, fullday,
 * halfday, dan seterusnya — terpisah dari jenis kegiatan yang menjelaskan
 * maksud perjalanannya.
 */
class KategoriPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(KategoriPerjadinSeeder::class);

        User::factory()->ppk()->create();
        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        // Usulan perjadin baru terbuka setelah SPD terbit.
        $this->terbitkanSpd($this->pengusul);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function dataUsulan(array $ubahan = []): array
    {
        return array_merge([
            'id_spd' => $this->spdMilik($this->pengusul)->id,
            'id_kegiatan' => Kegiatan::factory()->create()->id,
            'id_kategori_perjadin' => KategoriPerjadin::firstWhere('kode', 'LK-FB')->id,
            'no_tugas' => 'TGS-2026-001',
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-03',
            'jenis_pengajuan' => 'personal',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    // ── Daftar kategori ──

    public function test_seluruh_grup_kategori_tersedia(): void
    {
        $grup = KategoriPerjadin::terkelompok();

        $this->assertSame(
            ['Perjadin Luar Kota', 'Perjadin Dalam Kota', 'Narasumber', 'Diklat'],
            $grup->keys()->all()
        );
    }

    public function test_kategori_luar_kota_sesuai_daftar_resmi(): void
    {
        $nama = KategoriPerjadin::where('grup', 'Perjadin Luar Kota')->orderBy('urutan')->pluck('nama');

        $this->assertSame(
            ['Luar Kota NonFullBoard', 'Luar Kota FullBoard', 'Luar Negeri'],
            $nama->all()
        );
    }

    public function test_kategori_dalam_kota_sesuai_daftar_resmi(): void
    {
        $nama = KategoriPerjadin::where('grup', 'Perjadin Dalam Kota')->orderBy('urutan')->pluck('nama');

        $this->assertSame([
            'Dalam Kota FullBoard',
            'Dalam Kota FullDay',
            'Dalam Kota Lebih dari 8 Jam',
            'Dalam Kota HalfDay Pagi',
            'Dalam Kota HalfDay Sore',
            'Transport Lokal',
        ], $nama->all());
    }

    public function test_kategori_narasumber_dan_diklat_tersedia(): void
    {
        $this->assertSame(4, KategoriPerjadin::where('grup', 'Narasumber')->count());
        $this->assertSame(3, KategoriPerjadin::where('grup', 'Diklat')->count());
        $this->assertNotNull(KategoriPerjadin::firstWhere('nama', 'Narasumber (Daring)'));
    }

    public function test_kategori_nonaktif_tidak_ditawarkan(): void
    {
        KategoriPerjadin::firstWhere('kode', 'LN')->update(['is_aktif' => false]);

        $semua = KategoriPerjadin::terkelompok()->flatten()->pluck('nama');

        $this->assertNotContains('Luar Negeri', $semua);
    }

    // ── Formulir usulan ──

    public function test_formulir_menampilkan_pilihan_berkelompok(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Kategori Perjalanan Dinas')
            ->assertSee('<optgroup label="Perjadin Luar Kota">', false)
            ->assertSee('<optgroup label="Narasumber">', false)
            ->assertSee('Dalam Kota HalfDay Pagi');
    }

    public function test_kategori_tersimpan_saat_usulan_dibuat(): void
    {
        $kategori = KategoriPerjadin::firstWhere('kode', 'DK-HDP');

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_kategori_perjadin' => $kategori->id]));

        $this->assertSame($kategori->id, Usulan::first()->id_kategori_perjadin);
    }

    public function test_kategori_wajib_dipilih(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_kategori_perjadin' => null]))
            ->assertSessionHasErrors('id_kategori_perjadin');

        $this->assertSame(0, Usulan::count());
    }

    public function test_kategori_asing_ditolak(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_kategori_perjadin' => 9999]))
            ->assertSessionHasErrors('id_kategori_perjadin');
    }

    // ── Tampilan ──

    public function test_kategori_tampil_pada_detail_usulan(): void
    {
        $kategori = KategoriPerjadin::firstWhere('kode', 'NS-DAR');

        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'id_kategori_perjadin' => $kategori->id,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $usulan))
            ->assertOk()
            ->assertSee('Kategori Perjadin')
            ->assertSee('Narasumber (Daring)')
            ->assertSee('Narasumber');
    }

    public function test_formulir_edit_memilih_kategori_yang_tersimpan(): void
    {
        $kategori = KategoriPerjadin::firstWhere('kode', 'DL-DK');

        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
            'id_kategori_perjadin' => $kategori->id,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.edit', $usulan))
            ->assertOk()
            ->assertSee('value="'.$kategori->id.'"'."\n".'                        selected', false);
    }

    public function test_label_lengkap_menggabungkan_grup_dan_nama(): void
    {
        $kategori = KategoriPerjadin::firstWhere('kode', 'TL');

        $this->assertSame('Perjadin Dalam Kota — Transport Lokal', $kategori->label_lengkap);
    }
}
