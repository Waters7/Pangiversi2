<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\LokasiTujuan;
use App\Models\RincianBiaya;
use App\Models\RiwayatPembayaran;
use App\Models\SpdPelaksana;
use App\Models\SuratPerjalananDinas;
use App\Models\TahunAnggaran;
use App\Models\TindakLanjut;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder demo harus menghasilkan satu berkas untuk tiap fitur yang perlu
 * dicoba, dan harus aman dijalankan berulang saat menyiapkan sesi uji coba.
 */
class DemoPangiSeederTest extends TestCase
{
    use RefreshDatabase;

    private const NIP_UJI = '199310182025061003';

    protected function setUp(): void
    {
        parent::setUp();

        $unit = UnitKerja::factory()->create(['kode' => 'DIR']);

        User::factory()->create([
            'nama' => 'OCTAVIANUS ELRICTH WATERS MODAMI, S.Kom',
            'nip' => self::NIP_UJI,
            'role' => User::ROLE_DOSEN_TENDIK,
            'id_unit' => $unit->id,
        ]);

        User::factory()->create(['role' => User::ROLE_PPK]);
        User::factory()->create(['role' => User::ROLE_BENDAHARA]);
        User::factory()->count(6)->create(['role' => User::ROLE_DOSEN_TENDIK, 'id_unit' => $unit->id]);

        KategoriPerjadin::factory()->count(3)->create();
        LokasiTujuan::factory()->create(['nama' => 'Jakarta']);
        TahunAnggaran::factory()->create(['tahun' => (int) now()->format('Y')]);
    }

    private function jalankan(): void
    {
        $this->artisan('db:seed', ['--class' => 'DemoPangiSeeder', '--force' => true])
            ->assertSuccessful();
    }

    public function test_menghasilkan_usulan_untuk_setiap_status(): void
    {
        $this->jalankan();

        foreach ([
            StatusUsulan::Draft,
            StatusUsulan::Ditolak,
            StatusUsulan::Disetujui,
            StatusUsulan::Selesai,
        ] as $status) {
            $this->assertTrue(
                Usulan::where('status', $status->value)->exists(),
                "Tidak ada usulan berstatus {$status->value} untuk diuji coba."
            );
        }
    }

    /**
     * Sejak penugasan disahkan lewat SPD, tidak ada lagi tahap validasi
     * usulan oleh PPK. Berkas berstatus menunggu_ppk atau perlu_revisi
     * hanya akan mandek tanpa menu yang dapat membereskannya, jadi seeder
     * tidak boleh menghasilkannya.
     */
    public function test_tidak_menghasilkan_status_yang_tidak_dapat_dibereskan(): void
    {
        $this->jalankan();

        foreach ([StatusUsulan::MenungguPpk, StatusUsulan::PerluRevisi] as $status) {
            $this->assertFalse(
                Usulan::where('status', $status->value)->exists(),
                "Seeder menghasilkan usulan berstatus {$status->value} yang tidak dapat dibereskan."
            );
        }
    }

    public function test_akun_uji_coba_memiliki_usulan_pada_berbagai_tahap(): void
    {
        $this->jalankan();

        $octa = User::firstWhere('nip', self::NIP_UJI);
        $milikOcta = Usulan::where('id_user', $octa->id)->get();

        $this->assertGreaterThanOrEqual(6, $milikOcta->count());
        $this->assertGreaterThanOrEqual(3, $milikOcta->pluck('status')->unique()->count());
    }

    public function test_ketiga_jenis_qr_terbit(): void
    {
        $this->jalankan();

        $this->assertTrue(DaftarRiil::whereNotNull('kode_verifikasi')->exists(), 'QR tanda tangan PPK belum ada.');
        $this->assertTrue(DaftarRiil::whereNotNull('kode_konfirmasi')->exists(), 'QR konfirmasi pelaksana belum ada.');
        $this->assertTrue(Keuangan::whereNotNull('kode_konfirmasi_bayar')->exists(), 'QR konfirmasi bendahara belum ada.');
    }

    public function test_ketiga_tahap_pembayaran_terwakili(): void
    {
        $this->jalankan();

        foreach ([Keuangan::STATUS_BELUM, Keuangan::STATUS_SEBAGIAN, Keuangan::STATUS_LUNAS] as $status) {
            $this->assertTrue(
                Keuangan::where('status', $status)->exists(),
                "Tidak ada pembayaran berstatus \"{$status}\" untuk diuji coba."
            );
        }
    }

    public function test_daftar_riil_mencakup_menunggu_tanggapan_dan_sanggahan(): void
    {
        $this->jalankan();

        $this->assertTrue(
            DaftarRiil::whereNotNull('dikirim_ke_pegawai_at')
                ->whereNull('disetujui_pegawai_at')
                ->whereNull('sanggahan')
                ->exists(),
            'Tidak ada daftar riil yang menunggu tanggapan pelaksana.'
        );

        // Sanggahan demo memperlihatkan pemisahan dokumennya: daftar riil
        // diterima pelaksana, rincian biayanya yang dipersoalkan.
        $this->assertTrue(
            DaftarRiil::whereNotNull('rincian_sanggahan')
                ->whereNotNull('disetujui_pegawai_at')
                ->exists(),
            'Tidak ada berkas yang menyanggah satu dokumen sambil menyetujui yang lain.'
        );
    }

    public function test_rombongan_menyediakan_ketiga_keadaan_konfirmasi(): void
    {
        $this->jalankan();

        foreach ([
            Usulan::KONFIRMASI_MENUNGGU,
            Usulan::KONFIRMASI_DIKONFIRMASI,
            Usulan::KONFIRMASI_DIBATALKAN,
        ] as $konfirmasi) {
            $this->assertTrue(
                Usulan::where('konfirmasi', $konfirmasi)->exists(),
                "Tidak ada usulan berkonfirmasi \"{$konfirmasi}\"."
            );
        }

        $octa = User::firstWhere('nip', self::NIP_UJI);
        $this->assertTrue(
            Usulan::whereColumn('id_pembuat', '!=', 'id_user')->where('id_pembuat', $octa->id)->exists(),
            'Akun uji coba belum punya usulan yang dibuatkan untuk rekannya.'
        );
    }

    public function test_ada_berkas_lengkap_dan_berkas_yang_masih_kurang(): void
    {
        $this->jalankan();

        $lengkap = Usulan::whereHas('dokumen', fn ($q) => $q->whereNotNull('laporan_hasil')->whereNotNull('nota_transportasi'))->exists();
        $kurang = Usulan::whereHas('dokumen', fn ($q) => $q->whereNull('laporan_hasil'))->exists();

        $this->assertTrue($lengkap, 'Tidak ada usulan dengan berkas lengkap.');
        $this->assertTrue($kurang, 'Tidak ada usulan dengan berkas kurang untuk menguji pengingat.');
    }

    public function test_dapat_dijalankan_berulang_tanpa_menumpuk(): void
    {
        $this->jalankan();
        $pertama = Usulan::count();

        $this->jalankan();

        $this->assertSame($pertama, Usulan::count());
    }

    public function test_tidak_menyentuh_usulan_di_luar_data_demo(): void
    {
        $milikOrangLain = Usulan::factory()->create(['kode_rombongan' => 'NYATA-2026-001']);

        $this->jalankan();
        $this->jalankan();

        $this->assertDatabaseHas('usulan', ['id' => $milikOrangLain->id]);
    }

    public function test_berhenti_dengan_pesan_jelas_bila_akun_uji_belum_ada(): void
    {
        User::where('nip', self::NIP_UJI)->delete();

        $this->artisan('db:seed', ['--class' => 'DemoPangiSeeder', '--force' => true])
            ->expectsOutputToContain('Akun uji coba tidak ditemukan')
            ->assertSuccessful();

        $this->assertDatabaseCount('usulan', 0);
    }

    // ── Fitur yang lahir belakangan ──

    public function test_spd_terbit_dengan_pola_nomor_resmi(): void
    {
        $this->jalankan();

        $this->assertTrue(SuratPerjalananDinas::exists(), 'Tidak ada SPD untuk diuji coba.');

        $nomor = SpdPelaksana::value('nomor_surat');

        $this->assertStringStartsWith(SpdPelaksana::AWALAN_NOMOR, (string) $nomor);
        $this->assertStringEndsWith('/'.now()->format('Y'), (string) $nomor);
    }

    public function test_dokumen_transport_terisi_pergi_pulang_dan_empat_ruas(): void
    {
        $this->jalankan();

        $usulan = Usulan::whereHas('tiket')->with('tiket', 'notaTransport')->first();

        $this->assertNotNull($usulan, 'Tidak ada usulan bertiket untuk diuji coba.');
        $this->assertCount(2, $usulan->tiket);
        $this->assertCount(4, $usulan->notaTransport);
    }

    public function test_laporan_perjadin_lengkap_dengan_tindak_lanjut(): void
    {
        $this->jalankan();

        $laporan = LaporanPerjadin::whereNotNull('diselesaikan_at')->with('kegiatan', 'tindakLanjut')->first();

        $this->assertNotNull($laporan, 'Tidak ada laporan perjadin yang sudah diselesaikan.');
        $this->assertNotEmpty($laporan->kegiatan, 'Laporan belum punya uraian kegiatan harian.');

        // Ketiga status tindak lanjut perlu terwakili agar menu Daftar
        // Tindak Lanjut dapat dicoba pada semua keadaannya.
        $status = TindakLanjut::pluck('status');

        foreach (StatusTindakLanjut::cases() as $kasus) {
            $this->assertTrue($status->contains($kasus), "Tidak ada tindak lanjut berstatus {$kasus->value}.");
        }
    }

    public function test_daftar_riil_mewakili_seluruh_tahap_kerja_ppk(): void
    {
        $this->jalankan();

        $daftar = DaftarRiil::all();

        $this->assertTrue(
            $daftar->contains(fn (DaftarRiil $d) => $d->diajukan_at && ! $d->sudahDikirimKePegawai()),
            'Tidak ada daftar riil yang menunggu verifikasi PPK.'
        );

        $this->assertTrue(
            $daftar->contains(fn (DaftarRiil $d) => $d->sudah_ditandatangani),
            'Tidak ada daftar riil yang sudah ditandatangani kedua belah pihak.'
        );
    }

    public function test_nominatif_mewakili_ketiga_tahapnya(): void
    {
        $this->jalankan();

        $this->assertTrue(
            DaftarNominatif::whereNull('ditandatangani_at')->exists(),
            'Tidak ada daftar nominatif yang menunggu tanda tangan PPK.'
        );

        $this->assertTrue(
            DaftarNominatif::whereNotNull('ditandatangani_at')->whereNull('dikirim_at')->exists(),
            'Tidak ada daftar nominatif yang sudah ditandatangani tapi belum dikirim.'
        );

        $this->assertTrue(
            DaftarNominatif::whereNotNull('dikirim_at')->exists(),
            'Tidak ada daftar nominatif yang sudah sampai ke Tim SDM.'
        );
    }

    /**
     * Baris nominatif harus terisi pada seluruh kolomnya — kalau ada yang
     * nol, formatnya tidak dapat dinilai saat uji coba.
     */
    public function test_baris_nominatif_terisi_seluruh_kolomnya(): void
    {
        $this->jalankan();

        $nominatif = DaftarNominatif::firstOrFail();
        $baris = app(PenyusunNominatif::class)->baris($nominatif->no_tugas);

        $this->assertNotEmpty($baris);

        foreach (['tiket', 'transport', 'harian_jumlah', 'inap_jumlah', 'jumlah'] as $kolom) {
            $this->assertGreaterThan(0, $baris->first()[$kolom], "Kolom {$kolom} kosong pada daftar nominatif.");
        }
    }
    // ── Fitur yang baru ──

    public function test_jurnal_pembayaran_terisi_untuk_kedua_jenisnya(): void
    {
        $this->jalankan();

        foreach ([RiwayatPembayaran::JENIS_UANG_MUKA, RiwayatPembayaran::JENIS_PELUNASAN] as $jenis) {
            $this->assertTrue(
                RiwayatPembayaran::where('jenis', $jenis)->exists(),
                "Jurnal pembayaran tidak memuat {$jenis}."
            );
        }

        // Tiap baris jurnal menyebut siapa yang mencatatnya.
        $this->assertFalse(
            RiwayatPembayaran::whereNull('id_pencatat')->exists(),
            'Ada baris jurnal tanpa pencatat.'
        );
    }

    public function test_kedua_dokumen_punya_jalur_persetujuan_sendiri(): void
    {
        $this->jalankan();

        // Ada berkas yang daftar riilnya diterima tapi rincian biayanya disanggah.
        $this->assertTrue(
            DaftarRiil::whereNotNull('disetujui_pegawai_at')
                ->whereNotNull('rincian_sanggahan')
                ->exists(),
            'Tidak ada berkas yang menyikapi kedua dokumennya berbeda.'
        );

        // Ada berkas yang kedua dokumennya sudah ditandatangani PPK.
        $this->assertTrue(
            DaftarRiil::whereNotNull('ditandatangani_at')
                ->whereNotNull('rincian_ditandatangani_at')
                ->exists(),
            'Tidak ada berkas yang kedua dokumennya ditandatangani PPK.'
        );
    }

    public function test_ada_berkas_yang_dikembalikan_ppk(): void
    {
        $this->jalankan();

        $this->assertTrue(
            DaftarRiil::whereNotNull('dikembalikan_at')->whereNotNull('alasan_kembali')->exists(),
            'Tidak ada berkas yang dikembalikan PPK ke tim keuangan.'
        );
    }

    public function test_penginapan_dihitung_per_hari(): void
    {
        $this->jalankan();

        $baris = RincianBiaya::where('kategori', KategoriBiaya::Penginapan->value)
            ->where('volume', '>', 1)
            ->first();

        $this->assertNotNull($baris, 'Tidak ada komponen penginapan yang bervolume.');
        $this->assertSame('hari', $baris->satuan, 'Satuan penginapan seharusnya hari, bukan malam.');
    }

    public function test_pembebanan_nominatif_ditetapkan_pada_yang_sudah_diterima(): void
    {
        $this->jalankan();

        $this->assertTrue(
            DaftarNominatif::whereNotNull('dikirim_at')
                ->whereNotNull('id_akun_pembiayaan')
                ->whereNotNull('id_kategori_pembiayaan')
                ->exists(),
            'Tidak ada daftar nominatif yang sudah ditetapkan pembebanannya.'
        );
    }

    public function test_berkas_tersebar_pada_beberapa_bulan(): void
    {
        $this->jalankan();

        $bulan = Usulan::whereNotNull('tanggal_mulai')
            ->get()
            ->map(fn (Usulan $item) => Carbon::parse($item->tanggal_mulai)->format('Y-m'))
            ->unique();

        $this->assertGreaterThanOrEqual(
            3,
            $bulan->count(),
            'Data demo terlalu menumpuk pada satu bulan untuk menguji pengelompokan periode.'
        );
    }
}
