<?php

namespace Tests\Feature;

use App\Enums\Golongan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Golongan kepegawaian mengikuti daftar jenis pangkat yang berlaku, dan
 * dipakai menyusun kolom "Pangkat dan Golongan" pada Surat Perjalanan Dinas.
 */
class GolonganTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seluruh pasangan pangkat dan golongan sesuai daftar resmi.
     */
    public function test_nama_pangkat_sesuai_daftar_resmi(): void
    {
        $daftar = [
            'IV/e' => 'Pembina Utama',
            'IV/d' => 'Pembina Utama Madya',
            'IV/c' => 'Pembina Utama Muda',
            'IV/b' => 'Pembina Tingkat I',
            'IV/a' => 'Pembina',
            'III/d' => 'Penata Tingkat I',
            'III/c' => 'Penata',
            'III/b' => 'Penata Muda Tingkat I',
            'III/a' => 'Penata Muda',
            'II/d' => 'Pengatur Tingkat I',
            'II/c' => 'Pengatur',
            'II/b' => 'Pengatur Muda Tingkat I',
            'II/a' => 'Pengatur Muda',
            'I/d' => 'Juru Tingkat I',
            'I/c' => 'Juru',
            'I/b' => 'Juru Muda Tingkat I',
            'I/a' => 'Juru Muda',
        ];

        $this->assertCount(count($daftar), Golongan::cases());

        foreach ($daftar as $golongan => $pangkat) {
            $this->assertSame($pangkat, Golongan::from($golongan)->pangkat());
        }
    }

    public function test_tulisan_lengkap_seperti_pada_spd(): void
    {
        $this->assertSame('Penata Muda (III/a)', Golongan::IIIa->lengkap());
        $this->assertSame('Pembina Utama Muda (IV/c)', Golongan::IVc->lengkap());
    }

    public function test_golongan_terbaca_dari_tulisan_bebas(): void
    {
        $this->assertSame(Golongan::IIIa, Golongan::dari('III/a'));
        $this->assertSame(Golongan::IIIa, Golongan::dari('iii/a'));
        $this->assertSame(Golongan::IIIa, Golongan::dari('III / a'));
        $this->assertSame(Golongan::IIIa, Golongan::dari('Penata Muda (III/a)'));
        $this->assertSame(Golongan::IVb, Golongan::dari('IV/b'));
    }

    public function test_tulisan_yang_tidak_dikenali_menghasilkan_null(): void
    {
        $this->assertNull(Golongan::dari(null));
        $this->assertNull(Golongan::dari(''));
        $this->assertNull(Golongan::dari('V/z'));
    }

    public function test_pilihan_dikelompokkan_menurut_golongan(): void
    {
        $terkelompok = Golongan::terkelompok();

        $this->assertSame([
            'Golongan IV (Pembina)',
            'Golongan III (Penata)',
            'Golongan II (Pengatur)',
            'Golongan I (Juru)',
        ], array_keys($terkelompok));

        $this->assertCount(5, $terkelompok['Golongan IV (Pembina)']);
        $this->assertCount(4, $terkelompok['Golongan III (Penata)']);
    }

    // ── Data pegawai ──

    public function test_golongan_tersimpan_pada_pegawai(): void
    {
        $pegawai = User::factory()->create(['golongan' => 'III/a']);

        $this->assertSame('III/a', $pegawai->fresh()->golongan);
        $this->assertSame('Penata Muda (III/a)', Golongan::dari($pegawai->golongan)->lengkap());
    }

    public function test_berkas_pegawai_memuat_kolom_golongan(): void
    {
        $berkas = database_path('data/pegawai-poltekkes.csv');
        $this->assertFileExists($berkas);

        $handle = fopen($berkas, 'r');
        $kepala = fgetcsv($handle, escape: '\\');
        fclose($handle);

        $kepala[0] = preg_replace('/^\xEF\xBB\xBF/', '', $kepala[0]);

        $this->assertContains('golongan', $kepala);
    }

    public function test_seluruh_golongan_pada_berkas_pegawai_dikenali(): void
    {
        $handle = fopen(database_path('data/pegawai-poltekkes.csv'), 'r');
        $kepala = fgetcsv($handle, escape: '\\');
        $kepala[0] = preg_replace('/^\xEF\xBB\xBF/', '', $kepala[0]);
        $indeks = array_search('golongan', $kepala, true);

        $terisi = 0;

        while (($baris = fgetcsv($handle, escape: '\\')) !== false) {
            $nilai = trim($baris[$indeks] ?? '');

            if ($nilai === '') {
                continue;
            }

            $terisi++;
            $this->assertNotNull(Golongan::dari($nilai), "Golongan \"{$nilai}\" tidak dikenali.");
        }

        fclose($handle);

        // Sebagian besar pegawai sudah punya golongan pada berkas DUK.
        $this->assertGreaterThan(150, $terisi);
    }
}
