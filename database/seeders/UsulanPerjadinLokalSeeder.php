<?php

namespace Database\Seeders;

use App\Enums\KategoriBiaya;
use App\Enums\RuasTransport;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\LokasiTujuan;
use App\Models\Notifikasi;
use App\Models\Persetujuan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\RiwayatPembayaran;
use App\Models\SpdPelaksana;
use App\Models\StatusHasil;
use App\Models\SuratPerjalananDinas;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PengirimanBerkas;
use App\Services\PenomoranPerjadin;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use App\Services\WorkflowUsulan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Data uji coba perjalanan dinas dalam kota (perjadin lokal).
 *
 * Perjadin dalam kota menempuh alur yang sama dengan luar kota, tetapi
 * berkasnya jauh lebih ringkas: tanpa tiket, penginapan, maupun kuitansi —
 * yang dipertanggungjawabkan hanya SPPD dan nota transportasi lokalnya.
 * Seeder ini menaburkan satu berkas untuk tiap tahap alur supaya seluruh
 * menu yang menyangkut perjadin lokal ada isinya untuk dicoba.
 *
 * Seluruh tindakan dijalankan lewat model dan layanan yang sama dengan
 * yang dipakai aplikasi, supaya keadaan yang terbentuk tidak berbeda dari
 * hasil pemakaian sungguhan.
 *
 * Sengaja TIDAK dipanggil dari DatabaseSeeder dan menolak berjalan di
 * produksi: jalankan sendiri dengan
 *
 *     php artisan db:seed --class=UsulanPerjadinLokalSeeder
 *
 * Aman diulang — berkasnya ditandai kode rombongan berawalan DEMO-LOKAL
 * dan dibersihkan lebih dulu setiap kali seeder berjalan.
 */
class UsulanPerjadinLokalSeeder extends Seeder
{
    /** Penanda supaya data demo dapat dibersihkan tanpa menyentuh data lain. */
    private const TANDA = 'DEMO-LOKAL';

    /** Berkas contoh yang dirujuk seluruh unggahan demo. */
    private const BERKAS = 'demo/berkas-contoh.pdf';

    /** Uang harian dalam kota per hari (contoh, bukan tarif resmi). */
    private const UANG_HARIAN = 150_000;

    // ── Tahap alur, berurutan. Satu berkas berhenti di salah satunya. ──

    private const TAHAP_DRAF = 10;

    private const TAHAP_DIAJUKAN = 20;

    private const TAHAP_LPJ_DIISI = 30;

    private const TAHAP_DIKIRIM_PELAKSANA = 40;

    private const TAHAP_DITANDATANGANI_PPK = 50;

    private const TAHAP_LUNAS = 60;

    private User $ppk;

    private User $bendahara;

    private User $timKeuangan;

    private ?User $direktur = null;

    /** @var Collection<int, User> */
    private Collection $pelaksana;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Seeder data uji coba tidak boleh dijalankan di produksi.');

            return;
        }

        if (! $this->siapkanPemeran()) {
            return;
        }

        $this->bersihkanDemoLama();
        $this->siapkanBerkasContoh();
        $this->pastikanTahunAnggaran();

        foreach ($this->skenario() as $baris) {
            $this->rakitBerkas($baris);
        }

        $this->rombonganSebagianTuntas();

        $this->command?->info('Data uji coba perjadin lokal selesai ditaburkan: '
            .Usulan::where('kode_rombongan', 'like', self::TANDA.'%')->count().' usulan.');
    }

    /**
     * Satu baris per berkas: siapa, ke mana, kapan, kategorinya, dan
     * berhenti di tahap mana.
     *
     * @return list<array<string, mixed>>
     */
    private function skenario(): array
    {
        return [
            [
                'pemilik' => $this->pelaksana[0], 'tahap' => self::TAHAP_DRAF,
                'kategori' => 'DK-HDP', 'lokasi' => 'Manado',
                'instansi' => 'Dinas Kesehatan Provinsi Sulawesi Utara',
                'uraian' => 'Rapat koordinasi program imunisasi tingkat provinsi.',
                'mulai' => now()->addDays(10), 'hari' => 1,
            ],
            [
                'pemilik' => $this->pelaksana[1], 'tahap' => self::TAHAP_DIAJUKAN,
                'kategori' => 'DK-FD', 'lokasi' => 'Tomohon',
                'instansi' => 'Rumah Sakit Gunung Maria Tomohon',
                'uraian' => 'Supervisi praktik klinik mahasiswa program profesi.',
                'mulai' => now()->addDays(4), 'hari' => 1,
            ],
            [
                'pemilik' => $this->pelaksana[2], 'tahap' => self::TAHAP_LPJ_DIISI,
                'kategori' => 'DK-8J', 'lokasi' => 'Bitung',
                'instansi' => 'Dinas Kesehatan Kota Bitung',
                'uraian' => 'Pendampingan penyusunan profil kesehatan kota.',
                'mulai' => now()->subDays(5), 'hari' => 1,
            ],
            [
                'pemilik' => $this->pelaksana[3], 'tahap' => self::TAHAP_DIKIRIM_PELAKSANA,
                'kategori' => 'DK-FD', 'lokasi' => 'Airmadidi',
                'instansi' => 'Dinas Kesehatan Kabupaten Minahasa Utara',
                'uraian' => 'Narasumber pelatihan kader posyandu.',
                'mulai' => now()->subDays(9), 'hari' => 2,
            ],
            [
                'pemilik' => $this->pelaksana[0], 'tahap' => self::TAHAP_DITANDATANGANI_PPK,
                'kategori' => 'TL', 'lokasi' => 'Manado',
                'instansi' => 'Kantor Wilayah Kementerian Hukum dan HAM Sulawesi Utara',
                'uraian' => 'Konsultasi pengurusan hak kekayaan intelektual karya dosen.',
                'mulai' => now()->subDays(16), 'hari' => 1,
            ],
            [
                'pemilik' => $this->pelaksana[1], 'tahap' => self::TAHAP_LUNAS,
                'kategori' => 'DK-FD', 'lokasi' => 'Tondano',
                'instansi' => 'Dinas Kesehatan Kabupaten Minahasa',
                'uraian' => 'Monitoring dan evaluasi praktik kerja lapangan mahasiswa.',
                'mulai' => now()->subDays(30), 'hari' => 2,
            ],
        ];
    }

    // ── Rangkaian tahap ──

    /**
     * Jalankan alurnya dari awal sampai tahap yang diminta.
     *
     * @param  array<string, mixed>  $baris
     */
    private function rakitBerkas(array $baris, ?SuratPerjalananDinas $spdBersama = null): Usulan
    {
        $tahap = $baris['tahap'];
        $pemilik = $baris['pemilik'];

        // SPD lahir lebih dulu — usulan hanya boleh diajukan atas SPD yang
        // sudah ada dan ditandatangani.
        $spd = $spdBersama ?? $this->terbitkanSpd($baris);
        $pelaksanaSpd = $this->tambahPelaksanaSpd($spd, $pemilik);

        $usulan = $this->buatUsulan($pemilik, $spd, $pelaksanaSpd, $baris);
        $this->tambahPeserta($usulan, $pemilik);
        $this->lampirkanBerkasPengajuan($usulan);

        if ($tahap < self::TAHAP_DIAJUKAN) {
            return $usulan;
        }

        $this->ajukan($usulan);

        // Keuangan dibuat kosong lalu diisi dari dokumen dan uang harian:
        // sama seperti aplikasi, yang tidak pernah mengetik totalnya langsung.
        Keuangan::create(['id_usulan' => $usulan->id, 'total' => 0, 'uang_muka' => 0, 'sisa' => 0]);

        if ($tahap < self::TAHAP_LPJ_DIISI) {
            return $usulan;
        }

        $this->isiDokumenPertanggungjawaban($usulan);
        $this->selaraskan($usulan);
        $this->tambahUangHarian($usulan);
        $this->isiLaporanPerjadin($usulan);
        $this->bayarUangMuka($usulan);

        if ($tahap < self::TAHAP_DIKIRIM_PELAKSANA) {
            return $usulan;
        }

        $this->validasiTransportLokal($usulan);
        $this->kirimKePelaksana($usulan);

        if ($tahap < self::TAHAP_DITANDATANGANI_PPK) {
            return $usulan;
        }

        $riil = $this->daftarRiil($usulan);
        $riil->jalur()->setujui();
        $riil->jalurRincian()->setujui();
        $riil->jalur()->tandaTangani($this->ppk);
        $riil->jalurRincian()->tandaTangani($this->ppk);

        $this->catat(
            $usulan, AuditLog::AKSI_DOKUMEN,
            "Rincian biaya dan daftar riil {$pemilik->nama} ditandatangani PPK.",
            $this->ppk,
        );

        $this->terbitkanNominatif($usulan, kirim: $tahap >= self::TAHAP_LUNAS);

        if ($tahap < self::TAHAP_LUNAS) {
            return $usulan;
        }

        $this->konfirmasiLaporan($usulan);
        $this->lunasi($usulan);

        return $usulan;
    }

    /**
     * Satu surat tugas untuk dua pelaksana: yang satu sudah disahkan PPK,
     * yang lain usulannya masih draf. Daftar nominatifnya tetap terbit
     * dengan satu baris; kawannya bertambah sendiri begitu tuntas.
     */
    private function rombonganSebagianTuntas(): void
    {
        $dasar = [
            'kategori' => 'DK-FD', 'lokasi' => 'Manado',
            'instansi' => 'Balai Pelatihan Kesehatan Manado',
            'uraian' => 'Peserta lokakarya penyusunan kurikulum merdeka belajar.',
            'mulai' => now()->subDays(21), 'hari' => 2,
        ];

        $spd = $this->terbitkanSpd($dasar);

        $tuntas = $this->rakitBerkas(
            $dasar + ['pemilik' => $this->pelaksana[2], 'tahap' => self::TAHAP_DITANDATANGANI_PPK],
            $spd,
        );

        $this->rakitBerkas(
            $dasar + ['pemilik' => $this->pelaksana[3], 'tahap' => self::TAHAP_DRAF, 'no_tugas' => $tuntas->no_tugas],
            $spd,
        );
    }

    // ── Tindakan tiap tahap ──

    /**
     * @param  array<string, mixed>  $baris
     */
    private function terbitkanSpd(array $baris): SuratPerjalananDinas
    {
        $mulai = $this->tanggal($baris['mulai']);
        $hari = max(1, (int) ($baris['hari'] ?? 1));

        return SuratPerjalananDinas::create([
            'id_pembuat' => $this->ppk->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => $mulai->copy()->subDays(3)->toDateString(),
            'maksud' => $baris['uraian'],
            'alat_angkut' => 'Kendaraan Umum',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => $baris['lokasi'],
            'tanggal_berangkat' => $mulai->toDateString(),
            'tanggal_kembali' => $mulai->copy()->addDays($hari - 1)->toDateString(),
            'lama_hari' => $hari,
            'instansi_pembebanan' => 'Politeknik Kesehatan Kemenkes Manado',
            'akun_pembebanan' => '2079.QEB.001.052.A.524113',
        ]);
    }

    private function tambahPelaksanaSpd(SuratPerjalananDinas $spd, User $pegawai): SpdPelaksana
    {
        $urutan = $spd->pelaksana()->count() + 1;

        return SpdPelaksana::create([
            'id_spd' => $spd->id,
            'id_user' => $pegawai->id,
            'urutan' => $urutan,
            'nomor_surat' => SpdPelaksana::rakitNomor(
                (string) fake()->unique()->numberBetween(7000, 7999),
                (int) Carbon::parse($spd->tanggal_surat)->format('Y'),
            ),
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'pangkat_golongan' => $pegawai->pangkat_golongan ?? 'Penata Muda / III-a',
            'jabatan_instansi' => $pegawai->jabatan ?? 'Dosen',
            'tingkat_biaya' => 'C',
        ]);
    }

    /**
     * @param  array<string, mixed>  $baris
     */
    private function buatUsulan(User $pemilik, SuratPerjalananDinas $spd, SpdPelaksana $pelaksanaSpd, array $baris): Usulan
    {
        $mulai = $this->tanggal($baris['mulai']);
        $selesai = $mulai->copy()->addDays(max(0, ($baris['hari'] ?? 1) - 1));
        $kategori = KategoriPerjadin::firstWhere('kode', $baris['kategori'])
            ?? KategoriPerjadin::where('dalam_kota', true)->orderBy('urutan')->firstOrFail();

        $usulan = Usulan::create([
            'no_usulan' => app(PenomoranPerjadin::class)->nomorPerjadin($pemilik, $mulai->toDateString()),
            'no_tugas' => $baris['no_tugas']
                ?? 'KP.01.02/F.XXX/'.fake()->unique()->numberBetween(9000, 9999).'/'.$mulai->format('Y'),
            'status' => StatusUsulan::Draft->value,
            'jenis_pengajuan' => Usulan::PENGAJUAN_PERSONAL,
            'lokasi' => $baris['lokasi'],
            'id_lokasi' => LokasiTujuan::firstWhere('nama', $baris['lokasi'])?->id,
            'instansi' => $baris['instansi'],
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'uraian' => $baris['uraian'],
            'id_user' => $pemilik->id,
            'id_pembuat' => $pemilik->id,
            'id_kegiatan' => Kegiatan::orderBy('id')->value('id'),
            'id_kategori_perjadin' => $kategori->id,
            'id_spd' => $spd->id,
            'no_spd' => $pelaksanaSpd->nomor_surat,
            'id_tahun_anggaran' => TahunAnggaran::where('tahun', (int) $mulai->format('Y'))->value('id')
                ?? TahunAnggaran::value('id'),
            'kode_rombongan' => self::TANDA.'-'.strtoupper(fake()->unique()->bothify('??##')),
            'konfirmasi' => Usulan::KONFIRMASI_DIKONFIRMASI,
            'dikonfirmasi_at' => now(),
        ]);

        $this->catat($usulan, AuditLog::AKSI_DIBUAT, "Usulan {$usulan->no_usulan} dibuat.", $pemilik);

        return $usulan;
    }

    private function tambahPeserta(Usulan $usulan, User $pegawai): PesertaUsulan
    {
        return PesertaUsulan::create([
            'id_usulan' => $usulan->id,
            'id_user' => $pegawai->id,
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'peran' => 'ketua',
        ]);
    }

    /** Surat tugas dan SPD bertanda tangan: syarat usulan boleh dikirim. */
    private function lampirkanBerkasPengajuan(Usulan $usulan): void
    {
        Dokumen::updateOrCreate(
            ['id_usulan' => $usulan->id],
            ['surat_tugas' => self::BERKAS, 'spd_ditandatangani' => self::BERKAS],
        );
    }

    /**
     * Pengajuan lewat alur aplikasi: status berubah, persetujuan PPK
     * tercatat dari SPD bertanda tangan, dan jejak auditnya terisi.
     */
    private function ajukan(Usulan $usulan): void
    {
        $this->sebagai($usulan->user, fn () => app(WorkflowUsulan::class)->ajukan($usulan));
    }

    /**
     * Berkas pertanggungjawaban dalam kota: SPPD dan satu nota transport
     * lokal — tidak ada ruas bandara.
     */
    private function isiDokumenPertanggungjawaban(Usulan $usulan): void
    {
        Dokumen::updateOrCreate(['id_usulan' => $usulan->id], [
            'sppd' => self::BERKAS,
            'nota_transportasi' => self::BERKAS,
        ]);

        $usulan->notaTransport()->updateOrCreate(['urutan' => RuasTransport::Lokal->value], [
            'nominal' => 100_000,
            'keterangan' => 'Ojek daring pulang-pergi ke lokasi kegiatan',
            'bukti' => self::BERKAS,
        ]);

        $this->catat(
            $usulan, AuditLog::AKSI_DOKUMEN,
            "Berkas pertanggungjawaban usulan {$usulan->no_usulan} diunggah.",
            $usulan->user,
        );
    }

    /** Salin nominal dokumen ke rincian biaya dan daftar riil lewat layanan aplikasi. */
    private function selaraskan(Usulan $usulan): void
    {
        app(SinkronBiayaDokumen::class)->selaraskan($usulan->fresh());
    }

    /**
     * Uang harian dalam kota diketik tim keuangan dari lama perjalanan —
     * satu-satunya baris rincian biaya pada perjadin lokal.
     */
    private function tambahUangHarian(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;
        $hari = max(1, (int) $usulan->durasi);

        RincianBiaya::updateOrCreate(
            ['id_keuangan' => $keuangan->id, 'kategori' => KategoriBiaya::UangHarian->value],
            [
                'komponen' => 'Uang harian perjalanan dinas dalam kota',
                'volume' => $hari,
                'satuan' => 'hari',
                'harga_satuan' => self::UANG_HARIAN,
                'jumlah' => $hari * self::UANG_HARIAN,
                'sumber' => RincianBiaya::SUMBER_KEUANGAN,
                'divalidasi_at' => now(),
                'id_validator' => $this->timKeuangan->id,
            ],
        );

        $keuangan->hitungTotal();

        $this->catat(
            $usulan, AuditLog::AKSI_BIAYA,
            "Uang harian dalam kota usulan {$usulan->no_usulan} disusun tim keuangan.",
            $this->timKeuangan,
        );
    }

    /**
     * Laporan perjalanan dinas diisi lengkap lalu dikirim ke pimpinan —
     * bagian dari kelengkapan berkas yang ditagih sistem.
     */
    private function isiLaporanPerjadin(Usulan $usulan): void
    {
        $laporan = LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);
        $mulai = Carbon::parse($usulan->tanggal_mulai);

        $kegiatan = [
            'Mengikuti pembukaan dan pemaparan materi oleh penyelenggara.',
            'Diskusi dan perumusan tindak lanjut bersama peserta lain.',
        ];

        foreach (array_slice($kegiatan, 0, max(1, (int) $usulan->durasi)) as $i => $uraian) {
            $laporan->kegiatan()->updateOrCreate(['urutan' => $i + 1], [
                'tanggal' => $mulai->copy()->addDays($i)->toDateString(),
                'tempat' => "{$usulan->instansi} — {$usulan->lokasi}",
                'uraian' => $uraian,
            ]);
        }

        $laporan->tindakLanjut()->updateOrCreate(['urutan' => 1], [
            'uraian' => 'Menyampaikan hasil kegiatan pada rapat unit kerja.',
            'penanggung_jawab' => $usulan->user?->nama,
            'target_selesai' => $mulai->copy()->addMonth()->toDateString(),
            'status' => StatusTindakLanjut::Rencana->value,
        ]);

        $laporan->update([
            'id_status_hasil' => StatusHasil::orderBy('urutan')->value('id'),
            'diselesaikan_at' => now(),
        ]);

        $laporan->fresh()->kirim();
    }

    /** Tim keuangan memeriksa nota transportasi lokalnya. */
    private function validasiTransportLokal(Usulan $usulan): void
    {
        $this->daftarRiil($usulan)->update([
            'divalidasi_at' => now(),
            'id_validator' => $this->timKeuangan->id,
        ]);

        $this->catat(
            $usulan, AuditLog::AKSI_BIAYA,
            "Transport lokal usulan {$usulan->no_usulan} divalidasi tim keuangan.",
            $this->timKeuangan,
        );
    }

    /** Berkas dikirim ke pelaksana lewat layanan yang sama dengan aplikasi. */
    private function kirimKePelaksana(Usulan $usulan): void
    {
        $usulan = $usulan->fresh();
        $peserta = $usulan->peserta()->where('id_user', $usulan->id_user)->firstOrFail();

        $this->sebagai($this->timKeuangan, fn () => app(PengirimanBerkas::class)
            ->kirim($usulan, $peserta, $this->daftarRiil($usulan)));
    }

    private function daftarRiil(Usulan $usulan): DaftarRiil
    {
        return DaftarRiil::where('id_usulan', $usulan->id)->firstOrFail()->fresh();
    }

    /** Uang muka cair, lengkap dengan jurnal riwayat pembayarannya. */
    private function bayarUangMuka(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan || $keuangan->uang_muka <= 0 || $keuangan->uangMukaTerbayar()) {
            return;
        }

        $tanggal = Carbon::parse($usulan->tanggal_mulai)->subDay()->toDateString();

        $keuangan->update([
            'tanggal_transfer' => $tanggal,
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $keuangan->dokumenKeuangan()->updateOrCreate([], [
            'transfer_uang_muka' => self::BERKAS,
            'transfer_sisa' => '',
        ]);

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_UANG_MUKA,
            (float) $keuangan->uang_muka,
            $tanggal,
            $this->bendahara,
            self::BERKAS,
        );

        $this->catat(
            $usulan, AuditLog::AKSI_PEMBAYARAN,
            'Uang muka sebesar Rp '.number_format($keuangan->uang_muka, 0, ',', '.')
                ." dibayarkan untuk usulan {$usulan->no_usulan}.",
            $this->bendahara,
        );
    }

    /**
     * Daftar nominatif surat tugasnya terbit lewat penyusun yang sama
     * dengan aplikasi, ditandatangani PPK, dan bila diminta dikirim ke tim
     * keuangan.
     */
    private function terbitkanNominatif(Usulan $usulan, bool $kirim): void
    {
        $penyusun = app(PenyusunNominatif::class);

        if (! $penyusun->siapTerbit($usulan->no_tugas)) {
            return;
        }

        $nominatif = $penyusun->terbitkan($usulan->no_tugas);

        if (! $nominatif->sudahDitandatangani()) {
            $nominatif->update(['id_ppk' => $this->ppk->id, 'ditandatangani_at' => now()]);
            $nominatif->terbitkanKodeVerifikasi();
        }

        if ($kirim) {
            $nominatif->update(['dikirim_at' => now()]);
        }
    }

    /** Laporan dikonfirmasi dan ditandatangani Direktur — syarat pelunasan. */
    private function konfirmasiLaporan(Usulan $usulan): void
    {
        if (! $this->direktur) {
            return;
        }

        LaporanPerjadin::firstWhere('id_usulan', $usulan->id)?->konfirmasi($this->direktur);
    }

    /** Pelunasan: sisa uang harian ditambah penggantian transport lokal. */
    private function lunasi(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan || $keuangan->sudahLunas()) {
            return;
        }

        $tanggal = Carbon::parse($usulan->tanggal_selesai)->addDays(7)->toDateString();
        $nilai = $keuangan->nilaiPelunasan();

        $keuangan->update([
            'tanggal_pelunasan' => $tanggal,
            'status' => Keuangan::STATUS_LUNAS,
        ]);

        $keuangan->konfirmasiPelunasan();
        $keuangan->dokumenKeuangan()->updateOrCreate([], ['transfer_sisa' => self::BERKAS]);

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_PELUNASAN,
            $nilai,
            $tanggal,
            $this->bendahara,
            self::BERKAS,
        );

        $usulan->update(['status' => StatusUsulan::Selesai->value]);

        $this->catat(
            $usulan, AuditLog::AKSI_PEMBAYARAN,
            'Pelunasan Rp '.number_format($nilai, 0, ',', '.')." dibayarkan untuk usulan {$usulan->no_usulan}.",
            $this->bendahara,
        );
    }

    // ── Pemeran, berkas contoh, dan pembersihan ──

    private function siapkanPemeran(): bool
    {
        $ppk = User::firstWhere('role', User::ROLE_PPK);
        $bendahara = User::firstWhere('role', User::ROLE_BENDAHARA);

        if (! $ppk || ! $bendahara) {
            $this->command?->error('Akun PPK dan bendahara belum ada. Jalankan seeder pegawai lebih dulu.');

            return false;
        }

        $this->ppk = $ppk;
        $this->bendahara = $bendahara;
        $this->timKeuangan = User::firstWhere('role', User::ROLE_TIM_KEUANGAN) ?? $bendahara;
        $this->direktur = User::where('role', User::ROLE_PIMPINAN)
            ->get()
            ->first(fn (User $orang) => $orang->isDirektur());

        $this->pelaksana = User::where('role', User::ROLE_DOSEN_TENDIK)
            ->whereNotNull('nip')
            ->orderBy('nama')
            ->take(4)
            ->get();

        if ($this->pelaksana->count() < 4) {
            $this->command?->error('Pegawai belum cukup (perlu 4 dosen/tendik). Jalankan seeder pegawai lebih dulu.');

            return false;
        }

        return true;
    }

    /**
     * Satu berkas PDF kecil yang sah, supaya tautan unggahan demo dapat
     * dibuka — bukan sekadar nama berkas yang tidak ada.
     */
    private function siapkanBerkasContoh(): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists(self::BERKAS)) {
            return;
        }

        $disk->put(self::BERKAS, implode("\n", [
            '%PDF-1.4',
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 936] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length 62 >> stream',
            'BT /F1 18 Tf 72 850 Td (Berkas contoh uji coba PANGI) Tj ET',
            'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            'trailer << /Root 1 0 R >>',
            '%%EOF',
        ]));
    }

    private function pastikanTahunAnggaran(): void
    {
        foreach (array_unique([now()->year, now()->subYear()->year]) as $tahun) {
            TahunAnggaran::firstOrCreate(
                ['tahun' => $tahun],
                ['pagu' => 2_500_000_000, 'is_aktif' => $tahun === now()->year],
            );
        }
    }

    /**
     * Hapus jejak demo sebelumnya supaya seeder dapat diulang tanpa menumpuk.
     */
    private function bersihkanDemoLama(): void
    {
        $usulan = Usulan::where('kode_rombongan', 'like', self::TANDA.'%')->get();

        if ($usulan->isEmpty()) {
            return;
        }

        $id = $usulan->pluck('id');
        $keuangan = Keuangan::whereIn('id_usulan', $id)->pluck('id');

        RiwayatPembayaran::whereIn('id_usulan', $id)->delete();
        RincianBiaya::whereIn('id_keuangan', $keuangan)->delete();
        Keuangan::whereIn('id_usulan', $id)->delete();
        DaftarRiil::whereIn('id_usulan', $id)->delete();
        DaftarNominatif::whereIn('no_tugas', $usulan->pluck('no_tugas')->filter())->delete();
        LaporanPerjadin::whereIn('id_usulan', $id)->delete();
        Dokumen::whereIn('id_usulan', $id)->delete();
        PesertaUsulan::whereIn('id_usulan', $id)->delete();
        Persetujuan::whereIn('id_usulan', $id)->delete();
        Notifikasi::whereIn('id_usulan', $id)->delete();
        AuditLog::whereIn('id_usulan', $id)->delete();

        foreach (['tiket', 'notaTransport'] as $relasi) {
            $usulan->each(fn (Usulan $item) => $item->{$relasi}()->delete());
        }

        $spd = $usulan->pluck('id_spd')->filter()->unique();

        Usulan::whereIn('id', $id)->delete();
        SpdPelaksana::whereIn('id_spd', $spd)->delete();
        SuratPerjalananDinas::whereIn('id', $spd)->delete();

        $this->command?->warn("Data demo perjadin lokal sebelumnya dihapus: {$usulan->count()} usulan.");
    }

    // ── Pembantu ──

    /**
     * Layanan aplikasi mencatat pelakunya dari pengguna yang sedang masuk;
     * di seeder tidak ada yang masuk, jadi pemerannya dipasang sementara.
     */
    private function sebagai(?User $pelaku, callable $tindakan): void
    {
        $sebelumnya = Auth::user();

        if ($pelaku) {
            Auth::setUser($pelaku);
        }

        try {
            $tindakan();
        } finally {
            if ($sebelumnya) {
                Auth::setUser($sebelumnya);
            } else {
                Auth::logout();
            }
        }
    }

    private function catat(Usulan $usulan, string $aksi, string $deskripsi, ?User $pelaku): void
    {
        AuditLog::create([
            'id_usulan' => $usulan->id,
            'id_user' => $pelaku?->id,
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
        ]);
    }

    private function tanggal(mixed $nilai): Carbon
    {
        return $nilai instanceof Carbon ? $nilai->copy() : Carbon::parse($nilai);
    }
}
