<?php

namespace Tests\Feature;

use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HakAksesPeranTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->create(['role' => $peran->value]);
    }

    // ── Kemampuan dasar ──

    public function test_seluruh_peran_dapat_mengajukan_perjalanan_dinas(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $this->assertTrue(
                $peran->punya(Kemampuan::MengajukanUsulan),
                "Peran {$peran->value} seharusnya dapat mengajukan perjalanan dinas."
            );
        }
    }

    public function test_super_administrator_memiliki_seluruh_kemampuan(): void
    {
        foreach (Kemampuan::cases() as $kemampuan) {
            $this->assertTrue(PeranPengguna::SuperAdministrator->punya($kemampuan));
        }
    }

    public function test_pengusul_biasa_tidak_punya_kewenangan_tambahan(): void
    {
        $pengusul = [PeranPengguna::DosenTendik, PeranPengguna::PegawaiEksternal, PeranPengguna::Outsourcing];

        foreach ($pengusul as $peran) {
            $this->assertSame([Kemampuan::MengajukanUsulan], $peran->kemampuan());
            $this->assertTrue($peran->pengusulBiasa());
        }
    }

    // ── Pemisahan modul keuangan ──

    public function test_tim_keuangan_dapat_input_biaya_tetapi_tidak_mencatat_pembayaran(): void
    {
        $timKeuangan = PeranPengguna::TimKeuangan;

        $this->assertTrue($timKeuangan->punya(Kemampuan::MengelolaBiaya));
        $this->assertTrue($timKeuangan->punya(Kemampuan::MelihatKeuangan));
        $this->assertFalse($timKeuangan->punya(Kemampuan::MencatatPembayaran));
    }

    public function test_bendahara_dapat_input_biaya_sekaligus_mencatat_pembayaran(): void
    {
        $bendahara = PeranPengguna::Bendahara;

        $this->assertTrue($bendahara->punya(Kemampuan::MengelolaBiaya));
        $this->assertTrue($bendahara->punya(Kemampuan::MencatatPembayaran));
    }

    public function test_tim_keuangan_ditolak_saat_mencoba_mencatat_pembayaran(): void
    {
        Storage::fake('public');

        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $usulan->keuangan()->create(['total' => 1_000_000, 'uang_muka' => 800_000, 'sisa' => 200_000]);

        $this->actingAs($this->pengguna(PeranPengguna::TimKeuangan))
            ->post(route('keuangan.bayar-uang-muka', $usulan), [
                'tanggal_transfer' => '2026-09-01',
                'bukti_transfer' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_bendahara_dapat_mencatat_pembayaran_uang_muka(): void
    {
        Storage::fake('public');

        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $usulan->keuangan()->create(['total' => 1_000_000, 'uang_muka' => 800_000, 'sisa' => 200_000]);

        $this->actingAs($this->pengguna(PeranPengguna::Bendahara))
            ->post(route('keuangan.bayar-uang-muka', $usulan), [
                'tanggal_transfer' => '2026-09-01',
                'bukti_transfer' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame('bayar sebagian', $usulan->keuangan->fresh()->status);
    }

    public function test_ppk_tidak_dapat_menginput_rincian_biaya(): void
    {
        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $usulan->keuangan()->create(['total' => 0, 'uang_muka' => 0, 'sisa' => 0]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->post(route('keuangan.rincian.store', $usulan), [
                'komponen' => 'Uang Harian',
                'volume' => 2,
                'satuan' => 'OH',
                'harga_satuan' => 430_000,
            ])
            ->assertForbidden();
    }

    public function test_ppk_tetap_dapat_membuka_halaman_keuangan(): void
    {
        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->get(route('keuangan.detail', $usulan))
            ->assertOk();
    }

    /**
     * PPK membuka modul keuangan dalam mode lihat saja: seluruh usulan
     * terbuka, spanduknya menyatakan begitu, dan tidak satu pun tombol
     * ubah — input rincian, validasi, pembayaran, penagihan — yang tampil.
     */
    public function test_ppk_membuka_keuangan_dalam_mode_lihat_saja(): void
    {
        $usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $usulan->keuangan()->create(['total' => 0, 'uang_muka' => 0, 'sisa' => 0]);

        $ppk = $this->pengguna(PeranPengguna::Ppk);

        $this->assertTrue($ppk->hanyaMelihatKeuangan());
        $this->assertFalse($this->pengguna(PeranPengguna::TimKeuangan)->hanyaMelihatKeuangan());
        $this->assertFalse($this->pengguna(PeranPengguna::Bendahara)->hanyaMelihatKeuangan());

        $this->actingAs($ppk)
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('Mode lihat saja')
            ->assertSee($usulan->no_usulan)
            ->assertSee('Rincian Biaya Usulan')
            ->assertDontSee('Input Rincian Biaya')
            ->assertDontSee('Tagih ');

        $this->actingAs($ppk)
            ->get(route('keuangan.detail', $usulan))
            ->assertOk()
            ->assertSee('Mode lihat saja')
            ->assertDontSee(route('keuangan.rincian.store', $usulan->no_usulan))
            ->assertDontSee(route('keuangan.bayar-uang-muka', $usulan->no_usulan))
            ->assertDontSee('Simpan Rincian');
    }

    public function test_tim_keuangan_tidak_melihat_spanduk_lihat_saja(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::TimKeuangan))
            ->get(route('keuangan'))
            ->assertOk()
            ->assertDontSee('Mode lihat saja')
            ->assertSee('Input Rincian Biaya');
    }

    // ── Tim SDM ──

    public function test_tim_sdm_melihat_jadwal_dan_mengelola_pengguna_saja(): void
    {
        $timSdm = $this->pengguna(PeranPengguna::TimSdm);

        $this->actingAs($timSdm)->get(route('jadwal-perjalanan'))->assertOk();
        $this->actingAs($timSdm)->get(route('administrasi'))->assertOk();

        // Tidak boleh menembus modul biaya, validasi, maupun master data.
        $this->actingAs($timSdm)->get(route('keuangan'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('laporan'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('persetujuan'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('audit-log'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('daftar-riil.show', Usulan::factory()->create()))->assertForbidden();

        // Tetapi arsipnya terbuka: Tim SDM yang menyimpan berkas jadinya.
        $this->actingAs($timSdm)->get(route('laporan.daftar-riil'))->assertOk();
        $this->actingAs($timSdm)->get(route('laporan.nominatif'))->assertOk();
        $this->actingAs($timSdm)->get(route('master.unit-kerja'))->assertForbidden();
        $this->actingAs($timSdm)->get(route('dashboard-eksekutif'))->assertForbidden();
    }

    public function test_jadwal_memuat_perjalanan_yang_fix_maupun_yang_masih_diajukan(): void
    {
        $disetujui = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDay()->toDateString(),
        ]);
        PesertaUsulan::factory()->create(['id_usulan' => $disetujui->id, 'nama' => 'Pegawai Berangkat']);

        $masihDiajukan = Usulan::factory()->create([
            'status' => StatusUsulan::MenungguPpk->value,
            'tanggal_mulai' => today()->addDays(2)->toDateString(),
            'tanggal_selesai' => today()->addDays(3)->toDateString(),
        ]);
        PesertaUsulan::factory()->create(['id_usulan' => $masihDiajukan->id, 'nama' => 'Pegawai Menunggu']);

        $this->actingAs($this->pengguna(PeranPengguna::TimSdm))
            ->get(route('jadwal-perjalanan'))
            ->assertOk()
            ->assertSee('Pegawai Berangkat')
            ->assertSee('Pegawai Menunggu')
            ->assertViewHas('totalFix', 1)
            ->assertViewHas('totalSementara', 1);
    }

    public function test_jadwal_dapat_disaring_hanya_yang_sudah_fix(): void
    {
        $disetujui = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->addDay()->toDateString(),
        ]);
        PesertaUsulan::factory()->create(['id_usulan' => $disetujui->id, 'nama' => 'Pegawai Berangkat']);

        $masihDiajukan = Usulan::factory()->create([
            'status' => StatusUsulan::MenungguPpk->value,
            'tanggal_mulai' => today()->addDays(2)->toDateString(),
            'tanggal_selesai' => today()->addDays(3)->toDateString(),
        ]);
        PesertaUsulan::factory()->create(['id_usulan' => $masihDiajukan->id, 'nama' => 'Pegawai Menunggu']);

        $this->actingAs($this->pengguna(PeranPengguna::TimSdm))
            ->get(route('jadwal-perjalanan', ['kepastian' => 'fix']))
            ->assertOk()
            ->assertViewHas('totalSementara', 0)
            ->assertViewHas('totalFix', 1);
    }

    public function test_halaman_jadwal_tidak_menampilkan_nominal_biaya(): void
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'uraian' => 'Rahasia uraian kegiatan internal',
            'tanggal_mulai' => today()->addDay()->toDateString(),
            'tanggal_selesai' => today()->addDays(2)->toDateString(),
        ]);
        $usulan->keuangan()->create(['total' => 7_777_777, 'uang_muka' => 0, 'sisa' => 0]);
        PesertaUsulan::factory()->create(['id_usulan' => $usulan->id]);

        $this->actingAs($this->pengguna(PeranPengguna::TimSdm))
            ->get(route('jadwal-perjalanan'))
            ->assertOk()
            ->assertDontSee('7.777.777')
            ->assertDontSee('Rahasia uraian kegiatan internal');
    }

    // ── Pimpinan ──

    public function test_pimpinan_memantau_semua_tanpa_menjadi_tahap_validasi(): void
    {
        $pimpinan = $this->pengguna(PeranPengguna::Pimpinan);

        $this->assertTrue($pimpinan->bisaMelihatSemuaUsulan());
        $this->assertTrue($pimpinan->bisaMembukaPersetujuan());
        $this->assertFalse($pimpinan->bisaMenyetujui());

        $this->actingAs($pimpinan)->get(route('persetujuan'))->assertOk();
        $this->actingAs($pimpinan)->get(route('laporan'))->assertOk();
        $this->actingAs($pimpinan)->get(route('dashboard-eksekutif'))->assertOk();
    }

    public function test_pimpinan_tidak_dapat_mengelola_master_data_maupun_pengguna(): void
    {
        $pimpinan = $this->pengguna(PeranPengguna::Pimpinan);

        $this->actingAs($pimpinan)->get(route('master.unit-kerja'))->assertForbidden();
        $this->actingAs($pimpinan)->get(route('administrasi'))->assertForbidden();
    }

    public function test_hanya_ppk_yang_memvalidasi_usulan(): void
    {
        $this->assertTrue(PeranPengguna::Ppk->punya(Kemampuan::MemvalidasiUsulan));

        foreach (PeranPengguna::cases() as $peran) {
            if (in_array($peran, [PeranPengguna::Ppk, PeranPengguna::SuperAdministrator], true)) {
                continue;
            }

            $this->assertFalse(
                $peran->punya(Kemampuan::MemvalidasiUsulan),
                "Peran {$peran->value} seharusnya tidak memvalidasi usulan."
            );
        }
    }

    public function test_dashboard_eksekutif_terbuka_untuk_peran_pengelola(): void
    {
        $berhak = [
            PeranPengguna::SuperAdministrator,
            PeranPengguna::Pimpinan,
            PeranPengguna::Ppk,
            PeranPengguna::Bendahara,
            PeranPengguna::TimKeuangan,
        ];

        foreach ($berhak as $peran) {
            $this->actingAs($this->pengguna($peran))
                ->get(route('dashboard-eksekutif'))
                ->assertOk();
        }

        foreach ([PeranPengguna::DosenTendik, PeranPengguna::Outsourcing, PeranPengguna::TimSdm] as $peran) {
            $this->actingAs($this->pengguna($peran))
                ->get(route('dashboard-eksekutif'))
                ->assertForbidden();
        }
    }

    // ── Master data dan administrasi ──

    public function test_master_data_hanya_super_administrator_sedangkan_pengguna_juga_tim_sdm(): void
    {
        $superAdmin = $this->pengguna(PeranPengguna::SuperAdministrator);

        $this->actingAs($superAdmin)->get(route('master.unit-kerja'))->assertOk();
        $this->actingAs($superAdmin)->get(route('administrasi'))->assertOk();
        $this->actingAs($superAdmin)->get(route('audit-log'))->assertOk();

        // Akun pengguna dibuat oleh Tim SDM dan super administrator saja.
        $this->actingAs($this->pengguna(PeranPengguna::TimSdm))->get(route('administrasi'))->assertOk();
    }

    /**
     * Administrasi Sistem dipecah menjadi tiga halaman bermenu supaya tiap
     * urusan punya pintunya sendiri; ketiganya terbuka bagi yang mengelola
     * pengguna dan saling bertaut lewat navigasi di kepala halaman.
     */
    public function test_administrasi_sistem_terbagi_menjadi_tiga_halaman(): void
    {
        $timSdm = $this->pengguna(PeranPengguna::TimSdm);

        $this->actingAs($timSdm)->get(route('administrasi'))
            ->assertOk()
            ->assertSee('Daftar Pengguna')
            ->assertSee(route('administrasi.massal'))
            ->assertSee(route('administrasi.pengaturan'))
            ->assertDontSee('Data Pengguna Massal')
            ->assertDontSee('Pengingat Kelengkapan Berkas');

        $this->actingAs($timSdm)->get(route('administrasi.massal'))
            ->assertOk()
            ->assertSee('Data Pengguna Massal')
            ->assertSee(route('administrasi.export'));

        $this->actingAs($timSdm)->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertSee('Pengingat Kelengkapan Berkas');

        // Menu sampingnya ikut bertingkat.
        $this->actingAs($timSdm)->get(route('administrasi'))
            ->assertSee('Impor &amp; Ekspor', false)
            ->assertSee('Pengaturan Sistem');

        foreach ([PeranPengguna::Ppk, PeranPengguna::Bendahara, PeranPengguna::TimKeuangan, PeranPengguna::Pimpinan] as $peran) {
            $this->actingAs($this->pengguna($peran))->get(route('master.unit-kerja'))->assertForbidden();
            $this->actingAs($this->pengguna($peran))->get(route('administrasi'))->assertForbidden();
        }
    }

    // ── Peran lama ──

    public function test_peran_lama_dipetakan_ke_peran_baru(): void
    {
        $pemetaan = [
            'administrator' => PeranPengguna::SuperAdministrator,
            'direktur' => PeranPengguna::Pimpinan,
            'keuangan' => PeranPengguna::Bendahara,
            'sdm' => PeranPengguna::TimSdm,
            'pegawai' => PeranPengguna::DosenTendik,
        ];

        foreach ($pemetaan as $lama => $baru) {
            $this->assertSame($baru, PeranPengguna::dari($lama), "Peran lama {$lama} salah dipetakan.");
        }
    }
}
