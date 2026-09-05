<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar yang panjang dinavigasi lewat pengelompokan, bukan digulung.
 *
 * SPD disaring per periode, usulan dan berkas persetujuan dikelompokkan
 * menurut statusnya, dokumen menurut bulan dan tanggal keberangkatan, dan
 * jejak audit menurut peran pelakunya.
 */
class NavigasiDanPengelompokanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $this->ppk = User::factory()->ppk()->create();
    }

    // ── Daftar SPD per periode ──

    private function spdPada(string $tanggal): SuratPerjalananDinas
    {
        return SuratPerjalananDinas::create([
            'id_pembuat' => $this->admin->id,
            'tanggal_surat' => $tanggal,
            'dikeluarkan_di' => 'Manado',
            'maksud' => 'Kegiatan '.$tanggal,
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tanggal_berangkat' => $tanggal,
            'tanggal_kembali' => $tanggal,
            'tempat_tujuan' => 'Jakarta',
            'lama_hari' => 3,
        ]);
    }

    public function test_daftar_spd_menawarkan_tahun_yang_berisi_saja(): void
    {
        $this->spdPada('2026-03-10');
        $this->spdPada('2025-08-02');

        $this->actingAs($this->admin)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('2026')
            ->assertSee('2025')
            ->assertDontSee('2019');
    }

    public function test_daftar_spd_disaring_menurut_bulan(): void
    {
        $maret = $this->spdPada('2026-03-10');
        $agustus = $this->spdPada('2026-08-14');

        $halaman = $this->actingAs($this->admin)
            ->get(route('spd.index', ['tahun' => 2026, 'bulan' => 3]))
            ->assertOk();

        // Kolom Berangkat memuat tanggalnya; itu yang membedakan kedua baris.
        $halaman->assertSee('10 Mar 2026');
        $halaman->assertDontSee('14 Agu 2026');
    }

    public function test_saringan_tahun_membatasi_hasilnya(): void
    {
        $this->spdPada('2026-03-10');
        $lama = $this->spdPada('2025-03-10');

        $this->actingAs($this->admin)
            ->get(route('spd.index', ['tahun' => 2026]))
            ->assertOk()
            ->assertDontSee($lama->maksud);
    }

    // ── Usulan per status ──

    /**
     * Hanya tiga keadaan yang benar-benar dihasilkan alur sekarang.
     * Menawarkan tab untuk status yang tidak dapat dicapai hanya
     * menyesatkan pembacanya.
     */
    public function test_daftar_usulan_menampilkan_tiga_status_yang_hidup(): void
    {
        Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Draft->value]);
        Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Selesai->value]);

        $this->actingAs($this->admin)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('Draf')
            ->assertSee('Konfirmasi')
            ->assertSee('Selesai')
            ->assertDontSee('Menunggu Validasi PPK')
            ->assertDontSee('Perlu Revisi');
    }

    /**
     * Berkas lama yang terlanjur ditolak tidak boleh tersembunyi, jadi
     * tabnya muncul hanya bila memang ada isinya.
     */
    public function test_tab_ditolak_muncul_hanya_bila_ada_isinya(): void
    {
        Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Draft->value]);

        $this->actingAs($this->admin)->get(route('usulan.list'))->assertOk()->assertDontSee('Ditolak');

        Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Ditolak->value]);

        $this->actingAs($this->admin)->get(route('usulan.list'))->assertOk()->assertSee('Ditolak');
    }

    public function test_daftar_usulan_dikelompokkan_per_bulan(): void
    {
        Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Selesai->value,
            'tanggal_mulai' => '2026-03-10',
            'tanggal_selesai' => '2026-03-12',
        ]);

        Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Selesai->value,
            'tanggal_mulai' => '2026-08-14',
            'tanggal_selesai' => '2026-08-15',
        ]);

        $this->actingAs($this->admin)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSeeInOrder(['Agustus 2026', 'Maret 2026']);
    }

    public function test_tab_status_menyaring_daftar_usulan(): void
    {
        $draf = Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Draft->value]);
        $selesai = Usulan::factory()->create(['id_user' => $this->admin->id, 'status' => StatusUsulan::Selesai->value]);

        $this->actingAs($this->admin)
            ->get(route('usulan.list', ['status' => StatusUsulan::Draft->value]))
            ->assertOk()
            ->assertSee($draf->no_usulan)
            ->assertDontSee($selesai->no_usulan);
    }

    // ── Pembayaran per bulan dan tahun ──

    /** Perjalanan yang menunggu transfer, dengan tanggal berangkat tertentu. */
    private function menungguPembayaran(string $tanggal): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        return $usulan;
    }

    public function test_pembayaran_dikelompokkan_per_bulan(): void
    {
        $this->menungguPembayaran('2026-04-06');
        $this->menungguPembayaran('2026-09-21');

        // Menaik: April lebih dulu, karena yang terdekat jatuh temponya dibayar dulu.
        $this->actingAs($this->admin)
            ->get(route('pembayaran'))
            ->assertOk()
            ->assertSeeInOrder(['April 2026', 'September 2026']);
    }

    public function test_pembayaran_disaring_menurut_bulan(): void
    {
        $april = $this->menungguPembayaran('2026-04-06');
        $september = $this->menungguPembayaran('2026-09-21');

        $this->actingAs($this->admin)
            ->get(route('pembayaran', ['bulan' => 4]))
            ->assertOk()
            ->assertSee($april->no_usulan)
            ->assertDontSee($september->no_usulan);
    }

    public function test_saringan_pembayaran_mempertahankan_tahapnya(): void
    {
        $this->menungguPembayaran('2026-04-06');

        // Memilih bulan tidak boleh melempar bendahara kembali ke tahap awal.
        $this->actingAs($this->admin)
            ->get(route('pembayaran', ['tahap' => 'lunas']))
            ->assertOk()
            ->assertSee('tahap=lunas&amp;bulan=4', false);
    }

    public function test_pembayaran_hanya_menawarkan_tahun_yang_berisi(): void
    {
        $this->menungguPembayaran('2026-04-06');

        $this->actingAs($this->admin)
            ->get(route('pembayaran'))
            ->assertOk()
            ->assertSee('tahun=2026', false)
            ->assertDontSee('tahun=2025', false);
    }

    // ── Kartu jadwal mengikuti bulan berjalan ──

    public function test_kartu_jadwal_menyebut_bulan_berjalan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('jadwal-perjalanan'))
            ->assertOk()
            ->assertSee(now()->translatedFormat('F Y'));
    }

    public function test_kartu_jadwal_hanya_menghitung_bulan_berjalan(): void
    {
        $bulanIni = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => now()->startOfMonth()->toDateString(),
            'tanggal_selesai' => now()->startOfMonth()->addDay()->toDateString(),
        ]);

        $bulanLalu = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'tanggal_selesai' => now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString(),
        ]);

        foreach ([$bulanIni, $bulanLalu] as $usulan) {
            $usulan->peserta()->create([
                'id_user' => $this->admin->id,
                'nama' => $this->admin->nama,
                'nip' => $this->admin->nip,
                'peran' => 'ketua',
            ]);
        }

        // Satu perjalanan pada bulan berjalan, walau arsipnya memuat dua.
        $this->actingAs($this->admin)
            ->get(route('jadwal-perjalanan'))
            ->assertOk()
            ->assertViewHas('labelBulanIni', now()->translatedFormat('F Y'))
            ->assertViewHas('totalPerjalanan', 1)
            ->assertViewHas('totalFix', 1);
    }
    // ── Dokumen per bulan dan tanggal ──

    public function test_dokumen_dikelompokkan_per_bulan_dan_tanggal(): void
    {
        Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-03-10',
            'tanggal_selesai' => '2026-03-12',
        ]);

        Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-08-14',
            'tanggal_selesai' => '2026-08-15',
        ]);

        $this->actingAs($this->admin)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee('Maret 2026')
            ->assertSee('Agustus 2026')
            ->assertSeeInOrder(['Agustus 2026', 'Maret 2026']);
    }

    // ── Verifikasi nominatif per status tanda tangan ──

    public function test_nominatif_disaring_menurut_status_tanda_tangan(): void
    {
        DaftarNominatif::create(['no_tugas' => 'KP.03.01/BELUM/2026']);

        DaftarNominatif::create([
            'no_tugas' => 'KP.03.01/SUDAH/2026',
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
        ]);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif', ['tanda-tangan' => 'sudah']))
            ->assertOk()
            ->assertSee('Sudah Ditandatangani')
            ->assertDontSee('Tidak ada daftar nominatif yang menunggu tanda tangan.');
    }

    // ── Riwayat tanda tangan PPK ──

    public function test_riwayat_memuat_kedua_jenis_dokumen(): void
    {
        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Selesai->value]);
        $peserta = PesertaUsulan::factory()->create(['id_usulan' => $usulan->id]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan->id,
            'id_peserta' => $peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now()->subDay(),
            'id_ppk' => $this->ppk->id,
        ]);

        DaftarNominatif::create([
            'no_tugas' => 'KP.03.01/F.XXXVIII/55/2026',
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
        ]);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.riwayat'))
            ->assertOk()
            ->assertSee('Riwayat Tanda Tangan')
            ->assertSee($usulan->no_usulan)
            ->assertSee('KP.03.01/F.XXXVIII/55/2026')
            // Terbaru lebih dulu.
            ->assertSeeInOrder(['KP.03.01/F.XXXVIII/55/2026', $usulan->no_usulan]);
    }

    public function test_riwayat_dapat_disaring_per_jenis(): void
    {
        DaftarNominatif::create([
            'no_tugas' => 'KP.03.01/F.XXXVIII/56/2026',
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
        ]);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.riwayat', ['jenis' => 'berkas']))
            ->assertOk()
            ->assertDontSee('KP.03.01/F.XXXVIII/56/2026');
    }

    /**
     * Yang belum ditandatangani tidak masuk riwayat — riwayat mencatat
     * keputusan yang sudah dibuat, bukan yang masih menunggu.
     */
    public function test_riwayat_hanya_memuat_yang_sudah_ditandatangani(): void
    {
        DaftarNominatif::create(['no_tugas' => 'KP.03.01/BELUM-TTD/2026']);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.riwayat'))
            ->assertOk()
            ->assertDontSee('KP.03.01/BELUM-TTD/2026');
    }

    public function test_riwayat_tertutup_bagi_peran_lain(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('persetujuan.riwayat'))
            ->assertForbidden();
    }

    // ── Jejak audit per peran ──

    public function test_jejak_audit_dikelompokkan_per_peran(): void
    {
        $bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);

        AuditLog::create([
            'id_user' => $bendahara->id,
            'aksi' => AuditLog::AKSI_PEMBAYARAN,
            'deskripsi' => 'Uang muka dibayarkan.',
        ]);

        AuditLog::create([
            'id_user' => $this->ppk->id,
            'aksi' => AuditLog::AKSI_DOKUMEN,
            'deskripsi' => 'Berkas ditandatangani.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log'))
            ->assertOk()
            ->assertSee('Semua Peran')
            ->assertSee(PeranPengguna::Bendahara->label())
            ->assertSee(PeranPengguna::Ppk->label());
    }

    public function test_saringan_peran_membatasi_jejaknya(): void
    {
        $bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);

        AuditLog::create([
            'id_user' => $bendahara->id,
            'aksi' => AuditLog::AKSI_PEMBAYARAN,
            'deskripsi' => 'Uang muka dibayarkan bendahara.',
        ]);

        AuditLog::create([
            'id_user' => $this->ppk->id,
            'aksi' => AuditLog::AKSI_DOKUMEN,
            'deskripsi' => 'Berkas ditandatangani PPK.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log', ['peran' => PeranPengguna::Bendahara->value]))
            ->assertOk()
            ->assertSee('Uang muka dibayarkan bendahara.')
            ->assertDontSee('Berkas ditandatangani PPK.');
    }

    /**
     * Jejak tanpa pelaku — pekerjaan terjadwal, misalnya — tetap dapat
     * ditelusuri lewat kelompoknya sendiri.
     */
    public function test_jejak_tanpa_pelaku_dikelompokkan_sebagai_sistem(): void
    {
        AuditLog::create([
            'id_user' => null,
            'aksi' => AuditLog::AKSI_DOKUMEN,
            'deskripsi' => 'Pengingat otomatis dikirim.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log', ['peran' => 'sistem']))
            ->assertOk()
            ->assertSee('Pengingat otomatis dikirim.');
    }
}
