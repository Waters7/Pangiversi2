<?php

namespace Tests\Feature;

use App\Enums\DokumenCetak;
use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PengaturanDokumen;
use App\Services\PenyusunNominatif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dokumen Output pada Administrasi Sistem: super administrator mengatur
 * ukuran kertas, ukuran huruf, elemen yang ikut tercetak, dan teks baku
 * keempat dokumen cetak. Pemasangan yang belum pernah mengaturnya tetap
 * mencetak persis seperti bawaan.
 */
class PengaturanDokumenTest extends TestCase
{
    use RefreshDatabase;

    /** Folio menurut dompdf: 8,5 × 13 inci = 612 × 936 pt; A4 = 595 × 842 pt. */
    private const FOLIO = '/MediaBox [0.000 0.000 612.000 936.000]';

    private const A4 = '/MediaBox [0.000 0.000 595.280 841.890]';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    private function atur(): PengaturanDokumen
    {
        return app(PengaturanDokumen::class);
    }

    // ── Bawaan ──

    public function test_tanpa_pengaturan_dokumen_memakai_tampilan_bawaan(): void
    {
        foreach (DokumenCetak::cases() as $dokumen) {
            $atur = $this->atur()->untuk($dokumen);

            $this->assertSame('folio', $atur->kertas(), $dokumen->value);
            $this->assertSame($dokumen->hurufBawaan(), $atur->huruf());
            $this->assertFalse($this->atur()->sudahDiatur($dokumen));

            foreach (array_keys($dokumen->elemen()) as $kode) {
                $this->assertTrue($atur->tampil($kode), "{$dokumen->value}.{$kode}");
            }
        }

        $this->assertSame('SURAT PERJALANAN DINAS (SPD)', $this->atur()->untuk(DokumenCetak::Perjadin)->teks('judul'));
    }

    public function test_penanda_teks_diganti_nilainya(): void
    {
        $judul = $this->atur()->untuk(DokumenCetak::DaftarNominatif)->teks('judul', ['tahun' => 2026]);

        $this->assertSame('NOMINATIF PERJADIN POLTEKKES KEMENKES MANADO TA 2026', $judul);
        $this->assertStringNotContainsString(':tahun', $judul);
    }

    // ── Halaman pengaturan ──

    public function test_halaman_dokumen_output_hanya_untuk_yang_berhak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('administrasi.dokumen'))
            ->assertOk()
            ->assertSee('Dokumen Output')
            ->assertSee('Dokumen Perjadin (SPD)')
            ->assertSee('Daftar Nominatif')
            ->assertSee('Tabel pengikut')
            ->assertSee('Tampilan bawaan');

        $this->actingAs($this->admin)
            ->get(route('administrasi.dokumen', ['dokumen' => 'daftar-riil']))
            ->assertOk()
            ->assertSee('Paragraf pernyataan');

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('administrasi.dokumen'))
            ->assertForbidden();
    }

    public function test_dokumen_yang_tidak_dikenal_tidak_ditemukan(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrasi.dokumen.simpan', 'surat-kaleng'), ['kertas' => 'a4', 'huruf' => 10])
            ->assertNotFound();
    }

    // ── Menyimpan dan mengembalikan ──

    public function test_pengaturan_tersimpan_dan_tercatat_pada_jejak_audit(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrasi.dokumen.simpan', DokumenCetak::Perjadin->value), [
                'kertas' => 'a4',
                'huruf' => 12,
                'lebar_kop' => 70,
                'elemen' => ['kop' => '1', 'kode_nomor' => '1', 'tingkat_biaya' => '1', 'keterangan_lain' => '1', 'catatan_coret' => '1', 'kaki_gratifikasi' => '1'],
                'teks' => ['judul' => 'SURAT TUGAS PERJALANAN DINAS'],
            ])
            ->assertRedirect(route('administrasi.dokumen', ['dokumen' => 'perjadin']))
            ->assertSessionHas('success');

        $atur = $this->atur()->untuk(DokumenCetak::Perjadin);
        $this->assertSame('a4', $atur->kertas());
        $this->assertSame(12.0, $atur->huruf());
        $this->assertSame(70, $atur->lebarKop());
        $this->assertSame('SURAT TUGAS PERJALANAN DINAS', $atur->teks('judul'));

        // Elemen yang tidak dikirim berarti dimatikan.
        $this->assertFalse($atur->tampil('pengikut'));
        $this->assertTrue($atur->tampil('kop'));
        $this->assertTrue($this->atur()->sudahDiatur(DokumenCetak::Perjadin));

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'pengguna',
            'deskripsi' => 'Tampilan dokumen "Dokumen Perjadin (SPD)" disimpan — elemen disembunyikan: Tabel pengikut.',
        ]);

        // Dokumen lain tidak ikut berubah.
        $this->assertSame('folio', $this->atur()->untuk(DokumenCetak::DaftarRiil)->kertas());
    }

    public function test_teks_yang_dikosongkan_kembali_ke_bawaan_dan_nilai_di_luar_batas_dijepit(): void
    {
        $this->actingAs($this->admin)->put(route('administrasi.dokumen.simpan', DokumenCetak::DaftarRiil->value), [
            'kertas' => 'folio',
            'huruf' => 10.5,
            'teks' => ['judul' => '   ', 'pernyataan' => 'Saya menyatakan biaya ini benar.'],
            'elemen' => ['kop' => '1'],
        ])->assertSessionHasNoErrors();

        $atur = $this->atur()->untuk(DokumenCetak::DaftarRiil);
        $this->assertSame('Daftar Pengeluaran Riil', $atur->teks('judul'));
        $this->assertSame('Saya menyatakan biaya ini benar.', $atur->teks('pernyataan'));

        $this->actingAs($this->admin)
            ->from(route('administrasi.dokumen'))
            ->put(route('administrasi.dokumen.simpan', DokumenCetak::DaftarRiil->value), ['kertas' => 'folio', 'huruf' => 99])
            ->assertSessionHasErrors('huruf');
    }

    public function test_pengaturan_dapat_dikembalikan_ke_bawaan(): void
    {
        $this->actingAs($this->admin)->put(route('administrasi.dokumen.simpan', DokumenCetak::RincianBiaya->value), [
            'kertas' => 'legal', 'huruf' => 9, 'elemen' => [], 'teks' => ['judul' => 'Rincian'],
        ]);
        $this->assertSame('legal', $this->atur()->untuk(DokumenCetak::RincianBiaya)->kertas());

        $this->actingAs($this->admin)
            ->delete(route('administrasi.dokumen.bawaan', DokumenCetak::RincianBiaya->value))
            ->assertRedirect(route('administrasi.dokumen', ['dokumen' => 'rincian-biaya']));

        $atur = $this->atur()->untuk(DokumenCetak::RincianBiaya);
        $this->assertSame('folio', $atur->kertas());
        $this->assertSame('Rincian Biaya Perjalanan Dinas', $atur->teks('judul'));
        $this->assertTrue($atur->tampil('terbilang'));
        $this->assertFalse($this->atur()->sudahDiatur(DokumenCetak::RincianBiaya));
    }

    // ── Pengaruhnya pada dokumen yang tercetak ──

    public function test_ukuran_kertas_dokumen_mengikuti_pengaturan(): void
    {
        [$usulan, $peserta] = $this->usulanDenganDaftarRiil();

        $this->actingAs($this->admin)
            ->get(route('daftar-riil.cetak', [$usulan->no_usulan, $peserta->id]))
            ->assertOk()
            ->assertSee(self::FOLIO, false);

        $this->atur()->simpan(DokumenCetak::DaftarRiil, ['kertas' => 'a4', 'huruf' => 10.5]);

        $this->actingAs($this->admin)
            ->get(route('daftar-riil.cetak', [$usulan->no_usulan, $peserta->id]))
            ->assertOk()
            ->assertSee(self::A4, false);
    }

    public function test_elemen_yang_dimatikan_hilang_dari_dokumen_yang_dirender(): void
    {
        [$usulan, $peserta, $daftar] = $this->usulanDenganDaftarRiil();

        $isi = fn () => view('daftar-riil.cetak', [
            'usulan' => $usulan->fresh(), 'peserta' => $peserta, 'daftar' => $daftar->fresh(),
            'qr' => null, 'qrPelaksana' => null,
        ])->render();

        $awal = $isi();
        $this->assertStringContainsString('Daftar Pengeluaran Riil', $awal);
        $this->assertStringContainsString('kop-surat-poltekkes', $awal);
        $this->assertStringContainsString('menyetorkan', $awal);
        $this->assertStringContainsString('Nomor Usulan:', $awal);

        $this->atur()->simpan(DokumenCetak::DaftarRiil, [
            'kertas' => 'folio',
            'huruf' => 12,
            'elemen' => ['identitas' => '1', 'qr' => '1', 'catatan_qr' => '1'],
            'teks' => ['judul' => 'Daftar Pengeluaran Riil Pelaksana'],
        ]);
        app(PengaturanDokumen::class)->lupakan();

        $sesudah = $isi();
        $this->assertStringContainsString('Daftar Pengeluaran Riil Pelaksana', $sesudah);
        $this->assertStringNotContainsString('kop-surat-poltekkes', $sesudah);
        $this->assertStringNotContainsString('menyetorkan', $sesudah);
        $this->assertStringNotContainsString('Nomor Usulan:', $sesudah);
        $this->assertStringContainsString('font-size: 12pt', $sesudah);
        // Identitas tetap dinyalakan, jadi masih tercetak.
        $this->assertStringContainsString('Tujuan Perjalanan', $sesudah);
    }

    public function test_judul_dan_subjudul_nominatif_mengikuti_pengaturan(): void
    {
        $nominatif = DaftarNominatif::create([
            'no_tugas' => 'KP.03.01/F.XXXVIII/90/2026',
            'tanggal_tugas' => '2026-05-04',
        ]);

        $this->atur()->simpan(DokumenCetak::DaftarNominatif, [
            'kertas' => 'folio',
            'huruf' => 8,
            'elemen' => ['baris_total' => '1', 'ttd' => '1'],
            'teks' => ['judul' => 'DAFTAR NOMINATIF TAHUN :tahun', 'subjudul' => 'Surat Tugas :nomor'],
        ]);
        app(PengaturanDokumen::class)->lupakan();

        $penyusun = app(PenyusunNominatif::class);
        $baris = $penyusun->baris($nominatif->no_tugas);

        $isi = view('laporan.cetak-nominatif', [
            'nominatif' => $nominatif, 'baris' => $baris, 'total' => $penyusun->total($baris),
            'ppk' => null, 'qr' => null,
        ])->render();

        $this->assertStringContainsString('DAFTAR NOMINATIF TAHUN 2026', $isi);
        $this->assertStringNotContainsString('NOMINATIF PERJADIN POLTEKKES', $isi);
        // Subjudul dimatikan, jadi nomor surat tugasnya tidak tercetak di situ.
        $this->assertStringNotContainsString('Surat Tugas KP.03.01', $isi);
        $this->assertStringNotContainsString('Kategori Pembiayaan', $isi);
    }

    // ── Hak akses ──

    public function test_kemampuan_mengatur_dokumen_hanya_dimiliki_super_administrator(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $this->assertSame(
                $peran === PeranPengguna::SuperAdministrator,
                $peran->punya(Kemampuan::MengaturDokumenCetak),
                $peran->value,
            );
        }
    }

    /**
     * @return array{0: Usulan, 1: PesertaUsulan, 2: DaftarRiil}
     */
    private function usulanDenganDaftarRiil(): array
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.03.01/F.XXXVIII/77/2026',
        ]);

        $peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $usulan->id,
            'id_user' => $this->admin->id,
            'nama' => $this->admin->nama,
            'peran' => 'ketua',
        ]);

        $daftar = DaftarRiil::create([
            'id_usulan' => $usulan->id,
            'id_peserta' => $peserta->id,
            'total_riil' => 250000,
        ]);

        return [$usulan, $peserta, $daftar];
    }
}
