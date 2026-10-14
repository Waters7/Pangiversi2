<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Satu surat tugas kerap dipakai beberapa pelaksana. Yang datang belakangan
 * cukup mengetik nomornya: berkas yang sudah diunggah rekannya dipakai,
 * dan Jejak Audit mendaftar tiap nomor beserta berkasnya.
 */
class SuratTugasBersamaTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = 'KP.01.02/F.XXX/1557/2026';

    private const ISI_BERKAS = '%PDF-1.4 surat tugas bersama';

    private User $perintis;

    private User $rekan;

    private Usulan $usulanPerintis;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(KategoriPerjadinSeeder::class);
        Kegiatan::firstOrCreate(['nama' => 'Mengikuti rapat, seminar, lokakarya, atau studi banding']);

        $this->perintis = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rina Pongoh']);
        $this->rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        Storage::disk('public')->put('dokumen/surat-tugas/asal.pdf', self::ISI_BERKAS);
        $this->usulanPerintis = Usulan::factory()->create([
            'id_user' => $this->perintis->id,
            'no_tugas' => self::NOMOR,
            'status' => StatusUsulan::Disetujui->value,
        ]);
        Dokumen::create(['id_usulan' => $this->usulanPerintis->id, 'surat_tugas' => 'dokumen/surat-tugas/asal.pdf']);
    }

    /**
     * Isian usulan perjalanan dalam kota — tanpa SPD, cukup surat tugas.
     *
     * @return array<string, mixed>
     */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'jenis' => JenisPerjadin::DalamKota->value,
            'id_kategori_perjadin' => KategoriPerjadin::where('kode', 'DK-FD')->firstOrFail()->id,
            'id_kegiatan' => Kegiatan::first()->id,
            'no_tugas' => self::NOMOR,
            'lokasi' => 'Manado',
            'instansi' => 'Dinas Kesehatan Kota Manado',
            'tanggal_mulai' => today()->addDays(7)->toDateString(),
            'tanggal_selesai' => today()->addDays(7)->toDateString(),
            'uraian' => 'Kegiatan contoh.',
        ], $ubah);
    }

    public function test_cek_menemukan_surat_tugas_yang_sudah_diunggah_rekan(): void
    {
        $this->actingAs($this->rekan)
            // Huruf besar-kecil dan spasi tidak membedakan nomor.
            ->getJson(route('surat-tugas.cek', ['no_tugas' => '  kp.01.02/F.XXX/1557/ 2026 ']))
            ->assertOk()
            ->assertJson([
                'ditemukan' => true,
                'no_tugas' => self::NOMOR,
                'pengunggah' => 'Rina Pongoh',
                'tautan' => route('berkas.lihat', 'dokumen/surat-tugas/asal.pdf'),
            ]);

        $this->actingAs($this->rekan)
            ->getJson(route('surat-tugas.cek', ['no_tugas' => 'KP.01.02/F.XXX/9999/2026']))
            ->assertOk()
            ->assertJson(['ditemukan' => false]);
    }

    /** Usulan yang sedang disunting tidak menemukan berkasnya sendiri. */
    public function test_cek_mengabaikan_usulan_yang_sedang_disunting(): void
    {
        $this->actingAs($this->perintis)
            ->getJson(route('surat-tugas.cek', ['no_tugas' => self::NOMOR, 'kecuali_usulan' => $this->usulanPerintis->id]))
            ->assertOk()
            ->assertJson(['ditemukan' => false]);
    }

    public function test_usulan_memakai_berkas_surat_tugas_bernomor_sama_tanpa_unggah_ulang(): void
    {
        $this->actingAs($this->rekan)
            ->post(route('usulan.store'), $this->isian())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('usulan.saya'));

        $berkas = Usulan::where('id_user', $this->rekan->id)->sole()->dokumen()->sole()->surat_tugas;

        // Disalin, bukan dipakai bersama: menghapus usulan yang satu tidak
        // ikut menghapus berkas yang lain.
        $this->assertNotSame('dokumen/surat-tugas/asal.pdf', $berkas);
        $this->assertSame(self::ISI_BERKAS, Storage::disk('public')->get($berkas));
    }

    /** Selama nomornya sama, berkas rombongan terkunci: unggahan lain diabaikan. */
    public function test_unggahan_surat_tugas_diabaikan_bila_nomornya_sudah_ada(): void
    {
        $this->actingAs($this->rekan)
            ->post(route('usulan.store'), $this->isian([
                'surat_tugas' => UploadedFile::fake()->createWithContent('lain.pdf', '%PDF-1.4 berkas lain'),
            ]))
            ->assertSessionHasNoErrors();

        $berkas = Usulan::where('id_user', $this->rekan->id)->sole()->dokumen()->sole()->surat_tugas;

        $this->assertSame(self::ISI_BERKAS, Storage::disk('public')->get($berkas));
        $this->assertStringStartsWith('dokumen/surat-tugas/bersama-', $berkas);
    }

    /** Pengunggah asli tetap dapat mengganti berkasnya sendiri; berkas rekan tidak berubah. */
    public function test_pengunggah_asli_tetap_dapat_mengganti_surat_tugasnya(): void
    {
        Storage::disk('public')->put('dokumen/surat-tugas/spd-asal.pdf', self::ISI_BERKAS);
        $spd = $this->terbitkanSpd($this->perintis);
        $spd->update(['no_tugas' => self::NOMOR, 'surat_tugas' => 'dokumen/surat-tugas/spd-asal.pdf']);

        $this->actingAs($this->perintis)
            ->put(route('spd.update', $spd), $this->isianSpd($this->perintis) + [
                'surat_tugas' => UploadedFile::fake()->createWithContent('baru.pdf', '%PDF-1.4 surat tugas perbaikan'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('%PDF-1.4 surat tugas perbaikan', Storage::disk('public')->get($spd->fresh()->surat_tugas));
        $this->assertSame(self::ISI_BERKAS, Storage::disk('public')->get('dokumen/surat-tugas/asal.pdf'));
    }

    /**
     * Isian SPD pokok untuk penyuntingan.
     *
     * @return array<string, mixed>
     */
    private function isianSpd(User $pelaksana): array
    {
        return [
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => today()->toDateString(),
            'pelaksana' => [[
                'nomor_surat' => '123',
                'id_user' => $pelaksana->id,
                'nama' => $pelaksana->nama,
                'nip' => $pelaksana->nip,
                'pangkat_golongan' => 'Penata Muda (III/a)',
                'jabatan_instansi' => 'Pranata Komputer Ahli Pertama',
                'tingkat_biaya' => 'Tingkat C',
            ]],
            'maksud' => 'Penugasan kedinasan.',
            'alat_angkut' => 'Angkutan Darat',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Tomohon',
            'tanggal_berangkat' => today()->addWeek()->toDateString(),
            'tanggal_kembali' => today()->addWeek()->toDateString(),
            'no_tugas' => self::NOMOR,
        ];
    }

    public function test_nomor_surat_tugas_baru_tetap_wajib_diunggah(): void
    {
        $this->actingAs($this->rekan)
            ->post(route('usulan.store'), $this->isian(['no_tugas' => 'KP.01.02/F.XXX/9999/2026']))
            ->assertSessionHasErrors('surat_tugas');

        $this->actingAs($this->rekan)
            ->post(route('usulan.store'), $this->isian([
                'no_tugas' => 'KP.01.02/F.XXX/9999/2026',
                'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_spd_tanpa_berkas_memakai_surat_tugas_bernomor_sama(): void
    {
        $spd = $this->terbitkanSpd($this->rekan);
        $spd->update(['no_tugas' => self::NOMOR]);

        $this->actingAs($this->rekan)
            ->put(route('spd.update', $spd), [
                'dikeluarkan_di' => 'Manado',
                'tanggal_surat' => today()->toDateString(),
                'pelaksana' => [[
                    'nomor_surat' => '123',
                    'id_user' => $this->rekan->id,
                    'nama' => $this->rekan->nama,
                    'nip' => $this->rekan->nip,
                    'pangkat_golongan' => 'Penata Muda (III/a)',
                    'jabatan_instansi' => 'Pranata Komputer Ahli Pertama',
                    'tingkat_biaya' => 'Tingkat C',
                ]],
                'maksud' => 'Penugasan kedinasan.',
                'alat_angkut' => 'Angkutan Darat',
                'tempat_berangkat' => 'Manado',
                'tempat_tujuan' => 'Tomohon',
                'tanggal_berangkat' => today()->addWeek()->toDateString(),
                'tanggal_kembali' => today()->addWeek()->toDateString(),
                'no_tugas' => self::NOMOR,
            ])
            ->assertSessionHasNoErrors();

        $berkas = SuratPerjalananDinas::find($spd->id)->surat_tugas;
        $this->assertNotNull($berkas);
        $this->assertSame(self::ISI_BERKAS, Storage::disk('public')->get($berkas));
    }

    public function test_jejak_audit_mendaftar_nomor_surat_tugas_beserta_dokumennya(): void
    {
        $this->actingAs($this->rekan)->post(route('usulan.store'), $this->isian());
        $usulanRekan = Usulan::where('id_user', $this->rekan->id)->sole();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('audit-log.surat-tugas'))
            ->assertOk()
            ->assertSee('Surat Tugas &amp; Dokumen', false)
            ->assertSee('KP.01.02/<wbr>F.XXX/<wbr>1557/<wbr>2026', false)
            ->assertSee(route('berkas.lihat', 'dokumen/surat-tugas/asal.pdf'))
            ->assertSee('2 salinan pada usulan')
            ->assertSee('Rina Pongoh')
            ->assertSee($this->usulanPerintis->no_usulan)
            ->assertSee($usulanRekan->no_usulan);
    }

    public function test_jejak_audit_surat_tugas_tertutup_tanpa_hak(): void
    {
        $this->actingAs($this->rekan)->get(route('audit-log.surat-tugas'))->assertForbidden();
    }
}
