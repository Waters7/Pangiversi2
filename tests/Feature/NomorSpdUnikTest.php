<?php

namespace Tests\Feature;

use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Satu nomor SPD untuk satu usulan: nomor yang sudah dipakai usulan lain —
 * milik siapa pun — ditolak saat mengajukan maupun menyunting, supaya tidak
 * ada usulan ganda atas SPD yang sama.
 */
class NomorSpdUnikTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private const NOMOR = 'AR.05.02/F.XXX/2372/2026';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        Kegiatan::create(['nama' => 'Rapat Koordinasi']);
        KategoriPerjadin::factory()->create();
    }

    /** @return array<string, mixed> */
    private function isian(array $ubahan = []): array
    {
        return array_merge([
            'id_kegiatan' => Kegiatan::first()->id,
            'id_kategori_perjadin' => KategoriPerjadin::first()->id,
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-07',
            'surat_tugas' => UploadedFile::fake()->create('st.pdf', 100, 'application/pdf'),
            'no_spd' => self::NOMOR,
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    public function test_nomor_spd_yang_sudah_dipakai_usulan_lain_ditolak(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->isian())->assertSessionHasNoErrors();
        $pertama = Usulan::sole();

        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $balasan = $this->actingAs($orangLain)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), $this->isian())
            ->assertRedirect(route('usulan.create'))
            ->assertSessionHasErrors('no_spd');

        // Pesannya menyebut usulan mana yang sudah memakai nomor itu.
        $pesan = $balasan->baseResponse->getSession()->get('errors')->first('no_spd');
        $this->assertStringContainsString($pertama->no_usulan, $pesan);
        $this->assertStringContainsString($this->pengusul->nama, $pesan);
        $this->assertStringContainsString('Satu nomor SPD hanya untuk satu usulan', $pesan);

        $this->assertSame(1, Usulan::count());
    }

    /** Beda huruf besar-kecil atau spasi tepi tetap nomor yang sama. */
    public function test_perbedaan_huruf_dan_spasi_tidak_meloloskan_duplikat(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->isian(['no_spd' => '  '.self::NOMOR.' ']));
        $this->assertSame(self::NOMOR, Usulan::sole()->no_spd);

        $this->actingAs($this->pengusul)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), $this->isian(['no_spd' => mb_strtolower(self::NOMOR)]))
            ->assertSessionHasErrors('no_spd');

        $this->assertSame(1, Usulan::count());
    }

    public function test_nomor_berbeda_tetap_diterima(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->isian());
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->isian(['no_spd' => 'AR.05.02/F.XXX/2373/2026']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Usulan::count());
    }

    /** Menyunting draf tidak boleh tersandung nomornya sendiri, tetapi tetap ditolak bila memakai nomor usulan lain. */
    public function test_menyunting_usulan_mengabaikan_nomornya_sendiri(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->isian(['action' => 'draft']));
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->isian(['no_spd' => 'AR.05.02/F.XXX/2373/2026', 'action' => 'draft']));

        [$pertama, $kedua] = Usulan::orderBy('id')->get();

        $this->actingAs($this->pengusul)
            ->put(route('usulan.update', $pertama), $this->isian(['action' => 'draft', 'lokasi' => 'Makassar']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Makassar', $pertama->fresh()->lokasi);

        $this->actingAs($this->pengusul)
            ->from(route('usulan.edit', $pertama))
            ->put(route('usulan.update', $pertama), $this->isian(['action' => 'draft', 'no_spd' => $kedua->no_spd]))
            ->assertSessionHasErrors('no_spd');
        $this->assertSame(self::NOMOR, $pertama->fresh()->no_spd);
    }
}
