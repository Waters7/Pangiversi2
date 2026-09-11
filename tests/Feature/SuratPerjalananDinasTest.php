<?php

namespace Tests\Feature;

use App\Models\LokasiTujuan;
use App\Models\SpdPelaksana;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Services\KertasCetak;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu Buat SPD sebelumnya hanya menautkan ke Google Form di luar aplikasi.
 * Versi ini menyusun Surat Perjalanan Dinas di dalam sistem, mengikuti format
 * baku Kementerian Keuangan.
 */
class SuratPerjalananDinasTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'OCTAVIANUS ELRICTH WATERS MODAMI, S.Kom',
            'nip' => '199310182025061003',
            'jabatan' => 'Pranata Komputer Ahli Pertama',
            'golongan' => 'III/a',
        ]);

        User::factory()->create([
            'role' => User::ROLE_PPK,
            'nama' => 'STEFANNY ZULISTYA WENNO, SKM, M.Kes',
            'nip' => '198609262008122003',
        ]);

        User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Dr. HANUNG PRASETYA, S.Kp, M.Si',
            'nip' => '197104041994031002',
            'jabatan' => 'Direktur',
        ]);

        // Wakil Direktur tidak boleh terpilih sebagai penanda tangan.
        User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Wakil Direktur I',
            'jabatan' => 'Wakil Direktur I',
        ]);
    }

    /** Pengikut hanya boleh dicantumkan pimpinan, jadi ujinya perlu akun itu. */
    private function pimpinan(): User
    {
        return User::where('role', User::ROLE_PIMPINAN)->firstOrFail();
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
                    'nomor_surat' => '123',
                    'id_user' => $this->pengguna->id,
                    'nama' => $this->pengguna->nama,
                    'nip' => $this->pengguna->nip,
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

    // ── Menu ──

    public function test_menu_menunjuk_ke_dalam_aplikasi_bukan_google_form(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('docs.google.com/forms', $isi);
        $this->assertStringContainsString(route('spd.index'), $isi);
    }

    public function test_formulir_memuat_seluruh_bagian_yang_diminta(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('Identitas Surat')
            ->assertSee('Pelaksana Perjalanan Dinas')
            ->assertSee('Rencana Perjalanan')
            ->assertSee('Pembebanan Anggaran', false)
            ->assertSee('Pratinjau')
            ->assertSee('Maksud Perjalanan Dinas');
    }

    public function test_pelaksana_pertama_mengikuti_akun_yang_sedang_masuk(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee($this->pengguna->nama, false)
            ->assertSee($this->pengguna->nip)
            ->assertSee('Sesuai akun yang sedang masuk.');
    }

    /**
     * Golongan pegawai tercatat sejak berkas DUK dimuat, sehingga kolom
     * pangkat tidak perlu diketik ulang.
     */
    public function test_pangkat_terisi_dari_golongan_pegawai(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('spd.create'))->assertOk()->getContent();

        // Nilainya disisipkan lewat @js, yang meng-escape garis miring
        // berlapis. Dibandingkan setelah escape-nya dilepas.
        $polos = str_replace(['\\\\/', '\\/'], '/', $isi);

        $this->assertStringContainsString('Penata Muda (III/a)', $polos);
    }

    public function test_pilihan_pangkat_dikelompokkan_menurut_golongan(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('Golongan IV (Pembina)')
            ->assertSee('Golongan III (Penata)')
            ->assertSee('Pembina Utama Muda (IV/c)')
            ->assertSee('Juru Muda (I/a)');
    }

    // ── Penyimpanan ──

    public function test_spd_tersimpan_beserta_pelaksananya(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $this->isian())
            ->assertRedirect();

        $spd = SuratPerjalananDinas::first();

        $this->assertNotNull($spd);
        $this->assertSame('Jakarta', $spd->tempat_tujuan);
        $this->assertSame('Angkutan Udara', $spd->alat_angkut);
        $this->assertCount(1, $spd->pelaksana);
        $this->assertStringStartsWith('PJ-', $spd->pelaksana->first()->nomor_surat);
    }

    public function test_lama_perjalanan_dihitung_inklusif(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());

        // 10 sampai 12 September terhitung tiga hari.
        $this->assertSame(3, SuratPerjalananDinas::first()->lama_hari);
    }

    public function test_perjalanan_sehari_terhitung_satu_hari(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian([
            'tanggal_berangkat' => '2026-09-10',
            'tanggal_kembali' => '2026-09-10',
        ]));

        $this->assertSame(1, SuratPerjalananDinas::first()->lama_hari);
    }

    public function test_beberapa_pelaksana_tersimpan_berurutan(): void
    {
        $rekan = User::factory()->create(['nama' => 'Rekan Kedua', 'nip' => '198001012010011001']);

        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian([
            'pelaksana' => [
                1 => [
                    'nomor_surat' => '124',
                    'id_user' => $rekan->id,
                    'nama' => $rekan->nama,
                    'nip' => $rekan->nip,
                ],
            ],
        ]));

        $pelaksana = SuratPerjalananDinas::first()->pelaksana;

        $this->assertCount(2, $pelaksana);
        $this->assertSame([1, 2], $pelaksana->pluck('urutan')->all());
        $this->assertSame('Rekan Kedua', $pelaksana->last()->nama);
    }

    public function test_pelaksana_dibatasi_lima_orang(): void
    {
        $banyak = [];
        for ($i = 0; $i < 6; $i++) {
            $banyak[$i] = ['nomor_surat' => (string) $i, 'nama' => "Orang {$i}", 'nip' => "NIP{$i}"];
        }

        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $this->isian(['pelaksana' => $banyak]))
            ->assertSessionHasErrors('pelaksana');

        $this->assertDatabaseCount('surat_perjalanan_dinas', 0);
    }

    public function test_pengikut_tersimpan_dan_baris_kosong_diabaikan(): void
    {
        $this->actingAs($this->pimpinan())->post(route('spd.store'), $this->isian([
            'pengikut' => [
                ['nama' => 'Anak Pertama', 'tanggal_lahir' => '2015-05-05', 'keterangan' => 'Anak'],
                ['nama' => '', 'tanggal_lahir' => null, 'keterangan' => ''],
            ],
        ]));

        $pengikut = SuratPerjalananDinas::first()->pengikut;

        $this->assertCount(1, $pengikut);
        $this->assertSame('Anak Pertama', $pengikut->first()->nama);
    }

    public function test_tanggal_kembali_tidak_boleh_mendahului_berangkat(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $this->isian([
                'tanggal_berangkat' => '2026-09-12',
                'tanggal_kembali' => '2026-09-10',
            ]))
            ->assertSessionHasErrors('tanggal_kembali');
    }

    /** Nomor surat terbit sendiri, jadi surat tanpa nomor kiriman tetap sah. */
    public function test_surat_tersimpan_tanpa_nomor_pada_kiriman(): void
    {
        $isian = $this->isian();
        unset($isian['pelaksana'][0]['nomor_surat']);

        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $isian)
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith(
            'PJ-',
            SuratPerjalananDinas::first()->pelaksana->first()->nomor_surat,
        );
    }

    // ── Dokumen ──

    public function test_pratinjau_menghasilkan_pdf_tanpa_menyimpan(): void
    {
        $balasan = $this->actingAs($this->pengguna)->post(route('spd.pratinjau'), $this->isian());

        $balasan->assertOk();
        $this->assertSame('application/pdf', $balasan->headers->get('content-type'));
        $this->assertDatabaseCount('surat_perjalanan_dinas', 0);
    }

    public function test_spd_dapat_diunduh_sebagai_pdf(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());
        $spd = SuratPerjalananDinas::first();

        $balasan = $this->actingAs($this->pengguna)->get(route('spd.cetak', $spd));

        $balasan->assertOk();
        $this->assertSame('application/pdf', $balasan->headers->get('content-type'));
    }

    // ── Hak akses ──

    public function test_spd_milik_orang_lain_tidak_dapat_dibuka(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());
        $spd = SuratPerjalananDinas::first();

        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($orangLain)->get(route('spd.show', $spd))->assertForbidden();
    }

    public function test_pelaksana_yang_tercantum_dapat_membuka_spd(): void
    {
        $rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rekan']);

        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian([
            'pelaksana' => [
                1 => ['nomor_surat' => '125', 'id_user' => $rekan->id, 'nama' => 'Rekan', 'nip' => '123'],
            ],
        ]));

        $this->actingAs($rekan)
            ->get(route('spd.show', SuratPerjalananDinas::first()))
            ->assertOk();
    }

    public function test_daftar_hanya_menampilkan_spd_yang_berkaitan(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());

        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($orangLain)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Belum ada Surat Perjalanan Dinas');
    }

    public function test_tamu_tidak_dapat_membuka_formulir(): void
    {
        $this->get(route('spd.create'))->assertRedirect(route('login'));
    }

    // ── Penomoran otomatis ──

    /**
     * Nomor SPD terbit sendiri dengan pola yang sama seperti nomor perjadin.
     *
     * Nomor resmi pada dokumen cetak datang dari SRIKANDI lewat penanda
     * nomor naskah; yang tersimpan di sini penanda internalnya, dipakai
     * memasangkan SPD dengan usulan perjalanan dinasnya.
     */
    public function test_nomor_terbit_sendiri_berpola_perjadin(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());

        $this->assertMatchesRegularExpression(
            '/^PJ-[A-Z]+-'.now()->format('Y').'-\d{2}-\d{3}$/',
            SuratPerjalananDinas::first()->pelaksana->first()->nomor_surat,
        );
    }

    public function test_nomor_berikutnya_melanjutkan_deret(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());

        $nomor = SpdPelaksana::orderBy('nomor_surat')->pluck('nomor_surat')->all();

        $this->assertCount(2, $nomor);
        $this->assertStringEndsWith('-001', $nomor[0]);
        $this->assertStringEndsWith('-002', $nomor[1]);
    }

    /** Bulan pada nomor mengikuti keberangkatan, bukan bulan surat dibuat. */
    public function test_bulan_mengikuti_tanggal_berangkat(): void
    {
        $berangkat = now()->addMonths(2)->startOfMonth();

        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian([
            'tanggal_berangkat' => $berangkat->toDateString(),
            'tanggal_kembali' => $berangkat->copy()->addDays(2)->toDateString(),
        ]));

        $this->assertStringContainsString(
            '-'.$berangkat->format('m').'-',
            SuratPerjalananDinas::first()->pelaksana->first()->nomor_surat,
        );
    }

    public function test_nomor_tidak_pernah_kembar(): void
    {
        foreach (range(1, 3) as $ke) {
            $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());
        }

        $nomor = SpdPelaksana::pluck('nomor_surat');

        $this->assertCount(3, $nomor);
        $this->assertSame($nomor->count(), $nomor->unique()->count());
    }

    /** Formulir tidak lagi meminta nomor surat diketik. */
    public function test_formulir_tidak_meminta_nomor_surat(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertDontSee('nomor_surat')
            ->assertSee('Nomor surat terbit sendiri');
    }

    /** Nomor kiriman dari luar formulir tidak dipakai. */
    public function test_nomor_kiriman_diabaikan(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian([
            'pelaksana' => [['nomor_surat' => 'KU.02.04/PALSU/999/2026']],
        ]));

        $this->assertStringStartsWith(
            'PJ-',
            SuratPerjalananDinas::first()->pelaksana->first()->nomor_surat,
        );
    }

    // ── Penyuntingan ──

    private function buatSpd(): SuratPerjalananDinas
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isian());

        return SuratPerjalananDinas::first();
    }

    public function test_formulir_ubah_memuat_data_yang_tersimpan(): void
    {
        $spd = $this->buatSpd();

        $this->actingAs($this->pengguna)
            ->get(route('spd.edit', $spd))
            ->assertOk()
            ->assertSee('Ubah Surat Perjalanan Dinas')
            ->assertSee('Jakarta')
            ->assertSee('Konsultasi teknis pengembangan sistem informasi.');
    }

    public function test_spd_dapat_diperbarui(): void
    {
        $spd = $this->buatSpd();

        $this->actingAs($this->pengguna)
            ->put(route('spd.update', $spd), $this->isian([
                'tempat_tujuan' => 'Surabaya',
                'alat_angkut' => 'Angkutan Laut',
                'tanggal_kembali' => '2026-09-15',
            ]))
            ->assertRedirect(route('spd.show', $spd));

        $spd->refresh();

        $this->assertSame('Surabaya', $spd->tempat_tujuan);
        $this->assertSame('Angkutan Laut', $spd->alat_angkut);
        $this->assertSame(6, $spd->lama_hari);
    }

    public function test_pelaksana_disusun_ulang_saat_diperbarui(): void
    {
        $spd = $this->buatSpd();
        $rekan = User::factory()->create(['nama' => 'Rekan Baru', 'nip' => '198001012010011002']);

        $this->actingAs($this->pengguna)->put(route('spd.update', $spd), $this->isian([
            'pelaksana' => [
                1 => [
                    'nomor_surat' => '999',
                    'id_user' => $rekan->id,
                    'nama' => $rekan->nama,
                    'nip' => $rekan->nip,
                ],
            ],
        ]));

        $pelaksana = $spd->fresh()->pelaksana;

        $this->assertCount(2, $pelaksana);
        $this->assertSame([1, 2], $pelaksana->pluck('urutan')->all());
        // Baris lama dihapus, tidak menumpuk.
        $this->assertSame(2, SpdPelaksana::count());
    }

    public function test_spd_orang_lain_tidak_dapat_disunting(): void
    {
        $spd = $this->buatSpd();
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($orangLain)->get(route('spd.edit', $spd))->assertForbidden();
        $this->actingAs($orangLain)->put(route('spd.update', $spd), $this->isian())->assertForbidden();
    }

    // ── Pratinjau cetak ──

    /**
     * Halaman pemilih pratinjau ditiadakan: ia hanya menampilkan daftar, lalu
     * pengguna masih harus mengeklik sekali lagi untuk melihat PDF-nya.
     * Mencetak sekarang langsung dari baris Daftar SPD.
     */
    public function test_halaman_pemilih_pratinjau_sudah_tidak_ada(): void
    {
        $this->assertFalse(
            app('router')->has('spd.pratinjau-cetak'),
            'Rute pemilih pratinjau cetak seharusnya sudah dihapus.'
        );
    }

    public function test_cetak_dapat_dicapai_dari_daftar_spd(): void
    {
        $spd = $this->buatSpd();

        $this->actingAs($this->pengguna)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee(route('spd.cetak', $spd), false);
    }

    public function test_bingkai_pratinjau_menampilkan_pdf_di_tempat(): void
    {
        $spd = $this->buatSpd();

        $balasan = $this->actingAs($this->pengguna)
            ->get(route('spd.cetak', ['spd' => $spd, 'tampil' => 1]));

        $balasan->assertOk();
        $this->assertStringContainsString('inline', (string) $balasan->headers->get('content-disposition'));
    }

    // ── Kop dan footer resmi ──

    /**
     * Dokumen dirender langsung sebagai HTML supaya isinya dapat diperiksa;
     * DomPDF hanya mengubah HTML yang sama ini menjadi PDF.
     */
    private function htmlDokumen(): string
    {
        $spd = $this->buatSpd()->load('pelaksana', 'pengikut');

        return view('spd.cetak', [
            'spd' => $spd,
            'daftarPelaksana' => $spd->pelaksana,
            'pengikut' => $spd->pengikut,
            'ppk' => User::firstWhere('role', User::ROLE_PPK),
            'direktur' => User::where('role', User::ROLE_PIMPINAN)
                ->where('jabatan', 'Direktur')
                ->first(),
        ])->render();
    }

    // ── Penanda tangan elektronik SRIKANDI ──

    /**
     * SRIKANDI menggantikan penanda ini dengan QR tanda tangan elektronik.
     * Tulisannya tidak boleh berubah sedikit pun.
     */
    public function test_penanda_tanda_tangan_srikandi_tidak_berubah(): void
    {
        $html = $this->htmlDokumen();

        // PPK menandatangani lembar pertama dan kolom pemberi perintah.
        $this->assertSame(2, substr_count($html, '${ttd_pengirim1}'));

        // Direktur mengesahkan keberangkatan dan kedatangan kembali.
        $this->assertSame(2, substr_count($html, '${ttd_pengirim2}'));
    }

    public function test_pembagian_penanda_tangan_direktur_dan_ppk(): void
    {
        $html = $this->htmlDokumen();

        $this->assertSame(2, substr_count($html, 'Direktur Poltekkes Manado'));

        // Tiga kali: isian nomor 1, blok tanda tangan lembar pertama, dan
        // kolom pemberi perintah. Isian nomor 1 menyebut PPK sebagai
        // pemegang anggaran, bukan sebagai penanda tangan.
        $this->assertSame(3, substr_count($html, 'Pejabat Pembuat Komitmen'));
    }

    /**
     * Pada butir V, kolom kiri disahkan Direktur dan kolom kanan oleh PPK
     * selaku pejabat pemberi perintah. Urutannya diperiksa lewat posisi
     * agar tidak tertukar sisi.
     */
    public function test_lembar_pertama_ditandatangani_ppk(): void
    {
        $html = $this->htmlDokumen();

        // Lembar pertama berakhir pada pemisah halaman.
        $pemisah = strpos($html, 'class="pecah"');
        $this->assertNotFalse($pemisah);

        $mulai = strpos($html, 'SURAT PERJALANAN DINAS (SPD)');
        $depan = substr($html, $mulai, $pemisah - $mulai);

        $this->assertStringContainsString('${ttd_pengirim1}', $depan);
        $this->assertStringNotContainsString('${ttd_pengirim2}', $depan);
        $this->assertStringNotContainsString('Direktur Poltekkes Manado', $depan);
    }

    public function test_butir_lima_menaruh_direktur_di_kiri_dan_ppk_di_kanan(): void
    {
        $html = $this->htmlDokumen();

        $pemberiPerintah = strpos($html, 'Pejabat yang memberi perintah');
        $this->assertNotFalse($pemberiPerintah);

        // Penanda Direktur yang terakhir ada di kolom kiri, sebelum kolom
        // kanan; penanda PPK yang terakhir ada di kolom kanan itu sendiri.
        $this->assertLessThan($pemberiPerintah, strrpos($html, '${ttd_pengirim2}'));
        $this->assertGreaterThan($pemberiPerintah, strrpos($html, '${ttd_pengirim1}'));
    }

    public function test_nama_penanda_tangan_tercetak_pada_tiap_blok(): void
    {
        $html = $this->htmlDokumen();
        $direktur = User::firstWhere('jabatan', 'Direktur');

        $this->assertSame(2, substr_count($html, $direktur->nama));

        // Nama PPK muncul pada isian nomor 1 dan pada dua blok tanda
        // tangannya: lembar pertama dan kolom pemberi perintah.
        $this->assertSame(3, substr_count($html, User::firstWhere('role', User::ROLE_PPK)->nama));
    }

    /**
     * Dokumennya harus tetap dua lembar. Label yang memanjang atau baris
     * tambahan pernah mendorongnya jadi tiga halaman, dan lembar ketiga yang
     * nyaris kosong itu terlanjur tercetak sebelum ketahuan.
     */
    public function test_dokumen_tetap_dua_halaman(): void
    {
        $spd = $this->buatSpd()->load('pelaksana', 'pengikut');

        $pdf = Pdf::loadView('spd.cetak', [
            'spd' => $spd,
            'daftarPelaksana' => $spd->pelaksana,
            'pengikut' => $spd->pengikut,
            'ppk' => User::firstWhere('role', User::ROLE_PPK),
            'direktur' => User::firstWhere('jabatan', 'Direktur'),
        ])->setPaper(KertasCetak::UKURAN);

        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $pdf->output()));
    }

    /**
     * Penanda diletakkan di dalam kotak 30 mm, rata tengah mendatar maupun
     * tegak, supaya QR 3x3 cm dari SRIKANDI menempatinya persis.
     */
    public function test_penanda_berada_di_tengah_kotak_tiga_sentimeter(): void
    {
        $html = $this->htmlDokumen();

        $this->assertMatchesRegularExpression(
            '/table\.kotak-ttd td\s*\{[^}]*height:\s*3\dmm/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/table\.kotak-ttd td\s*\{[^}]*vertical-align:\s*middle/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/table\.kotak-ttd td\s*\{[^}]*text-align:\s*center/s',
            $html
        );

        // Setiap penanda memang berada di dalam kotak itu.
        $this->assertSame(4, substr_count($html, 'class="kotak-ttd"'));
        $this->assertSame(2, substr_count($html, '<td>${ttd_pengirim1}</td>'));
        $this->assertSame(2, substr_count($html, '<td>${ttd_pengirim2}</td>'));
    }

    /**
     * Tabel dalam untuk "Berangkat dari / Ke / Pada Tanggal" mewarisi border
     * dari tabel induknya bila tidak dimatikan, sehingga tampak berkotak-kotak.
     */
    public function test_tabel_dalam_tidak_bergaris(): void
    {
        $html = $this->htmlDokumen();

        $this->assertMatchesRegularExpression(
            '/table\.rincian-dalam td\s*\{[^}]*border:\s*none/s',
            $html
        );

        // Tidak ada lagi tabel dalam yang memakai gaya sebaris tanpa kelas.
        $this->assertStringNotContainsString(
            '<table style="width:100%; border-collapse:collapse">',
            $html
        );
    }

    /**
     * "table.kotak-ttd td" dan "table.belakang td" berbobot specificity sama,
     * jadi yang menang adalah yang ditulis belakangan. Bila urutannya
     * terbalik, sel tanda tangan di halaman kedua kembali mewarisi border dan
     * vertical-align tabel induknya — penanda pun tidak lagi di tengah.
     *
     * Memeriksa keberadaan aturannya saja tidak cukup; urutannya yang
     * menentukan.
     */
    public function test_aturan_tabel_dalam_ditulis_setelah_tabel_induk(): void
    {
        $html = $this->htmlDokumen();

        $induk = strpos($html, 'table.belakang td');
        $kotak = strpos($html, 'table.kotak-ttd td');
        $dalam = strpos($html, 'table.rincian-dalam td');

        $this->assertNotFalse($induk);
        $this->assertNotFalse($kotak);
        $this->assertNotFalse($dalam);

        $this->assertGreaterThan(
            $induk,
            $kotak,
            'Aturan kotak tanda tangan harus ditulis setelah table.belakang agar tidak kalah.'
        );
        $this->assertGreaterThan(
            $induk,
            $dalam,
            'Aturan tabel dalam harus ditulis setelah table.belakang agar bordernya benar-benar hilang.'
        );
    }

    public function test_direktur_menandatangani_keberangkatan_awal(): void
    {
        $html = $this->htmlDokumen();

        $this->assertStringContainsString('Direktur Poltekkes Manado', $html);
        $this->assertStringContainsString('Dr. HANUNG PRASETYA, S.Kp, M.Si', $html);
        $this->assertStringContainsString('197104041994031002', $html);

        // Wakil Direktur tidak boleh ikut terpilih.
        $this->assertStringNotContainsString('Wakil Direktur I', $html);
    }

    public function test_susunan_butir_mengikuti_format_baku(): void
    {
        $html = $this->htmlDokumen();

        foreach ([
            'SURAT PERJALANAN DINAS (SPD)',
            'Kode Nomor',
            'Nama / NIP yang melaksanakan perjalanan dinas',
            'Tingkat Biaya Perjalanan Dinas',
            'Alat Angkutan yang Dipergunakan',
            'Tanggal Harus Kembali / Tiba',
            'ditempat baru *)',
            'Pembebanan Anggaran',
            'Keterangan Lain-lain',
            '*) Coret yang tidak perlu',
            'DIKELUARKAN DI',
            'Tempat Kedudukan',
            'Telah diperiksa dengan keterangan',
            'Pejabat yang memberi perintah',
            'Catatan Lain-lain',
            'PERHATIAN',
        ] as $butir) {
            $this->assertStringContainsString($butir, $html, "Butir \"{$butir}\" tidak ada.");
        }
    }

    public function test_penanda_tanda_tangan_berada_di_tengah(): void
    {
        $html = $this->htmlDokumen();

        // Setiap penanda dibungkus blok rata tengah agar QR jatuh di tengah.
        $this->assertMatchesRegularExpression('/\.blok-ttd\s*\{\s*text-align:\s*center/', $html);
        $this->assertSame(4, substr_count($html, 'class="blok-ttd"'));
    }

    // ── Tanggal dan lokasi ──

    public function test_tanggal_dikeluarkan_mengikuti_tanggal_pembuatan(): void
    {
        $spd = $this->buatSpd();

        $this->assertTrue(today()->isSameDay($spd->tanggal_surat));
        $this->assertStringContainsString(today()->translatedFormat('d F Y'), $this->htmlDokumen());
    }

    public function test_tanggal_dikeluarkan_tidak_berubah_saat_disunting(): void
    {
        $spd = $this->buatSpd();
        $spd->update(['tanggal_surat' => '2026-01-15']);

        $this->actingAs($this->pengguna)
            ->put(route('spd.update', $spd), $this->isian(['tempat_tujuan' => 'Bandung']))
            ->assertRedirect();

        $spd->refresh();

        $this->assertSame('Bandung', $spd->tempat_tujuan);
        $this->assertSame('2026-01-15', $spd->tanggal_surat->toDateString());
    }

    public function test_formulir_menampilkan_tanggal_pembuatan_sebagai_bacaan_saja(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('spd.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Tanggal Dikeluarkan', $isi);
        $this->assertStringContainsString('Mengikuti tanggal pembuatan SPD.', $isi);
        $this->assertStringNotContainsString('name="tanggal_surat"', $isi);
    }

    /**
     * Lokasi memakai data referensi yang sama dengan formulir usulan perjadin,
     * supaya penulisan kotanya seragam di kedua dokumen.
     */
    public function test_tempat_dapat_dipilih_dari_lokasi_terdaftar(): void
    {
        LokasiTujuan::factory()->create(['nama' => 'Yogyakarta']);
        LokasiTujuan::factory()->create(['nama' => 'Surabaya']);

        $isi = $this->actingAs($this->pengguna)->get(route('spd.create'))->assertOk()->getContent();

        $this->assertStringContainsString('<datalist id="daftar-lokasi-spd">', $isi);
        $this->assertStringContainsString('Yogyakarta', $isi);
        $this->assertStringContainsString('Surabaya', $isi);

        // Kedua kolom tempat menunjuk daftar yang sama.
        $this->assertSame(2, substr_count($isi, 'list="daftar-lokasi-spd"'));
    }

    public function test_penomoran_halaman_belakang_mengikuti_contoh(): void
    {
        $html = $this->htmlDokumen();

        // Contoh resminya melompat dari V langsung ke VII lalu VIII.
        foreach (['II.', 'III.', 'IV.', 'V.', 'VII.', 'VIII.'] as $rom) {
            $this->assertStringContainsString($rom, $html);
        }
    }

    public function test_dokumen_memakai_kop_surat_resmi(): void
    {
        $html = $this->htmlDokumen();

        $this->assertStringContainsString('kop-surat-poltekkes.jpg', $html);
        $this->assertFileExists(public_path('images/kop-surat-poltekkes.jpg'));
    }

    /**
     * Halaman depan ditutup kotak imbauan antigratifikasi di kiri dan logo
     * akreditasi di kanan, mengikuti berkas cetakan baku yang berlaku.
     */
    public function test_halaman_depan_ditutup_kotak_gratifikasi_dan_logo(): void
    {
        $html = $this->htmlDokumen();

        $this->assertStringContainsString('logo-akreditasi.jpg', $html);
        $this->assertFileExists(public_path('images/logo-akreditasi.jpg'));

        $this->assertStringContainsString(
            'Kementerian Kesehatan tidak menerima suap dan/atau gratifikasi dalam bentuk apapun.',
            $html,
        );
        $this->assertStringContainsString('HALO KEMENKES', $html);
        $this->assertStringContainsString('https://wbs.kemkes.go.id', $html);
        $this->assertStringContainsString('https://tte.kominfo.go.id/verifyPDF', $html);
    }

    /**
     * Butir 8 menyusun pengikut bertumpuk: "Pengikut :" lalu "Nama 1.", "2.",
     * "3." di bawahnya, dengan Tanggal Lahir dan Keterangan sebagai judul
     * kolom yang berdiri sekali di baris teratas.
     */
    public function test_butir_pengikut_bertumpuk_dengan_judul_kolom(): void
    {
        $html = $this->htmlDokumen();

        $this->assertMatchesRegularExpression(
            '/Pengikut&nbsp;\s*:.*?Tanggal Lahir.*?Keterangan.*?Nama\s+1\..*?2\..*?3\./s',
            $html,
        );
    }

    /** Lamanya perjalanan dinas dihitung dalam hari, bukan nominal uang. */
    public function test_lama_perjalanan_tidak_berakhiran_rupiah(): void
    {
        $html = $this->htmlDokumen();

        $this->assertMatchesRegularExpression('/\(\w+\) hari/', $html);
        $this->assertStringNotContainsString('rupiah', $html);
    }

    // ── Menu ──

    public function test_menu_memuat_dua_submenu(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Pembuatan SPD', $isi);
        $this->assertStringContainsString('Daftar SPD', $isi);
        $this->assertStringNotContainsString('Pratinjau Cetak', $isi);
    }

    public function test_daftar_menyediakan_aksi_ubah(): void
    {
        $spd = $this->buatSpd();

        $this->actingAs($this->pengguna)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee(route('spd.edit', $spd), false)
            ->assertSee('Ubah');
    }
}
