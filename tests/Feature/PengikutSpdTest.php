<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kolom pengikut pada SPD adalah kewenangan pimpinan.
 *
 * Pengikut ikut berangkat tanpa mengajukan usulan sendiri dan biayanya
 * menempel pada pelaksana, jadi yang memutuskan siapa boleh ikut adalah
 * pimpinan — bukan pelaksana yang menyusun suratnya.
 *
 * Berkas ini sekaligus menjaga kabar terbitnya SPD: pelaksana yang baru
 * ditambahkan lewat penyuntingan harus ikut diberi tahu, sementara yang sudah
 * tercantum sejak awal tidak dikabari dua kali.
 */
class PengikutSpdTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Pelaksana Pertama',
            'nip' => '199310182025061003',
        ]);

        $this->pimpinan = User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Dr. Direktur Poltekkes',
            'nip' => '197104041994031002',
            'jabatan' => 'Direktur',
        ]);

        User::factory()->create([
            'role' => User::ROLE_PPK,
            'nama' => 'Pejabat Pembuat Komitmen',
            'nip' => '198609262008122003',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isian(array $ubah = []): array
    {
        return array_replace_recursive([
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => '2026-09-01',
            'pelaksana' => [
                [
                    'id_user' => $this->pelaksana->id,
                    'nama' => $this->pelaksana->nama,
                    'nip' => $this->pelaksana->nip,
                    'pangkat_golongan' => 'Penata Muda (III/a)',
                    'jabatan_instansi' => 'Pranata Komputer Ahli Pertama',
                    'tingkat_biaya' => 'Tingkat C',
                ],
            ],
            'maksud' => 'Konsultasi teknis pengembangan sistem informasi.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => '2026-09-10',
            'tanggal_kembali' => '2026-09-12',
            'instansi_pembebanan' => 'Politeknik Kesehatan Kemenkes Manado',
            'akun_pembebanan' => '524111',
            'keterangan_lain' => '',
        ], $ubah);
    }

    /**
     * @return array<string, mixed>
     */
    private function denganPengikut(string $nama = 'Ikut Satu'): array
    {
        return $this->isian([
            'pengikut' => [
                ['nama' => $nama, 'tanggal_lahir' => '2015-05-05', 'keterangan' => 'Anak'],
            ],
        ]);
    }

    // ── Tampilan formulir ──

    public function test_formulir_menawarkan_pengikut_kepada_pimpinan(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('pengikut[0][nama]', false);
    }

    public function test_formulir_menawarkan_pengikut_kepada_super_administrator(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('pengikut[0][nama]', false);
    }

    public function test_formulir_menyembunyikan_pengikut_dari_pelaksana(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertDontSee('pengikut[0][nama]', false);
    }

    public function test_formulir_penyuntingan_menyembunyikan_pengikut_dari_pelaksana(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->denganPengikut());
        $spd = SuratPerjalananDinas::firstOrFail();

        $this->actingAs($this->pelaksana)
            ->get(route('spd.edit', $spd))
            ->assertOk()
            ->assertDontSee('pengikut[0][nama]', false);
    }

    // ── Penyimpanan ──

    public function test_pengikut_kiriman_pimpinan_tersimpan(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->denganPengikut());

        $this->assertSame('Ikut Satu', SuratPerjalananDinas::firstOrFail()->pengikut->first()->nama);
    }

    public function test_pengikut_kiriman_pelaksana_diabaikan(): void
    {
        $this->actingAs($this->pelaksana)->post(route('spd.store'), $this->denganPengikut());

        $this->assertCount(0, SuratPerjalananDinas::firstOrFail()->pengikut);
    }

    /**
     * Formulir pelaksana tidak memuat bagian pengikut, sehingga kirimannya
     * selalu kosong. Tanpa penjagaan ini kiriman itu terbaca sebagai perintah
     * mengosongkan, dan pengikut yang dipasang pimpinan lenyap begitu
     * pelaksana menyunting suratnya sendiri.
     */
    public function test_penyuntingan_pelaksana_tidak_menghapus_pengikut_pimpinan(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->denganPengikut());
        $spd = SuratPerjalananDinas::firstOrFail();

        $this->actingAs($this->pelaksana)
            ->put(route('spd.update', $spd), $this->isian(['maksud' => 'Maksud diperbarui.']))
            ->assertRedirect();

        $this->assertSame('Ikut Satu', $spd->fresh()->pengikut->first()->nama);
    }

    public function test_pimpinan_dapat_mengganti_pengikut_lewat_penyuntingan(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->denganPengikut());
        $spd = SuratPerjalananDinas::firstOrFail();

        $this->actingAs($this->pimpinan)
            ->put(route('spd.update', $spd), $this->denganPengikut('Ikut Dua'))
            ->assertRedirect();

        $pengikut = $spd->fresh()->pengikut;

        $this->assertCount(1, $pengikut);
        $this->assertSame('Ikut Dua', $pengikut->first()->nama);
    }

    // ── Kabar terbitnya SPD ──

    public function test_pelaksana_baru_dikabari_setelah_penyuntingan(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->isian());
        $spd = SuratPerjalananDinas::firstOrFail();

        $kedua = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Pelaksana Kedua',
            'nip' => '199501012025061004',
        ]);

        $isian = $this->isian();
        $isian['pelaksana'][] = [
            'id_user' => $kedua->id,
            'nama' => $kedua->nama,
            'nip' => $kedua->nip,
            'pangkat_golongan' => 'Penata Muda (III/a)',
            'jabatan_instansi' => 'Dosen',
            'tingkat_biaya' => 'Tingkat C',
        ];

        $this->actingAs($this->pimpinan)->put(route('spd.update', $spd), $isian)->assertRedirect();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $kedua->id,
            'judul' => 'Surat Perjalanan Dinas terbit',
        ]);
    }

    /** Yang sudah dikabari saat SPD terbit tidak diberi tahu ulang. */
    public function test_pelaksana_lama_tidak_dikabari_dua_kali(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->isian());
        $spd = SuratPerjalananDinas::firstOrFail();

        $this->actingAs($this->pimpinan)
            ->put(route('spd.update', $spd), $this->isian(['maksud' => 'Maksud diperbarui.']))
            ->assertRedirect();

        $this->assertSame(1, Notifikasi::where('id_user', $this->pelaksana->id)
            ->where('judul', 'Surat Perjalanan Dinas terbit')
            ->count());
    }

    /** SPD terbit langsung tampil pada daftar milik pelaksananya. */
    public function test_spd_tampil_pada_daftar_pelaksana(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->isian());

        $this->actingAs($this->pelaksana)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Jakarta');
    }

    /**
     * SPD yang dibuatkan orang lain tetap terbawa ke formulir usulan
     * pelaksananya, sehingga ia dapat langsung mengajukan tanpa menunggu
     * dikirimi nomornya.
     */
    public function test_spd_terbawa_ke_formulir_usulan_pelaksana(): void
    {
        $this->actingAs($this->pimpinan)->post(route('spd.store'), $this->isian());

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertViewHas('spdTerkait', fn (array $daftar) => count($daftar) === 1
                && $daftar[0]['tempat_tujuan'] === 'Jakarta');
    }
}
