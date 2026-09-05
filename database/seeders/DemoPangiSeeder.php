<?php

namespace Database\Seeders;

use App\Enums\ArahTiket;
use App\Enums\KategoriBiaya;
use App\Enums\RuasTransport;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\KategoriPembiayaan;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\LokasiTujuan;
use App\Models\Notifikasi;
use App\Models\ObrolanBantuan;
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
use App\Services\PenomoranPerjadin;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data uji coba PANGI, disusun mengikuti alurnya — bukan daftar kasus lepas.
 *
 * Perjalanan dinas berjalan lewat satu rangkaian tahap yang tetap, dari
 * draf sampai pelunasan. Seeder ini menuliskan rangkaian itu sekali saja
 * pada jalankanSampai(), lalu tiap berkas demo cukup menyatakan sampai
 * tahap mana ia berhenti. Dengan begitu data demo tidak pernah menyimpang
 * dari alur yang sesungguhnya, dan menambah skenario baru berarti menambah
 * satu baris, bukan satu metode.
 *
 * Seluruh tindakan dijalankan lewat model dan layanan yang sama dengan yang
 * dipakai aplikasi — bukan lewat update() langsung — supaya keadaan yang
 * terbentuk mustahil berbeda dari hasil pemakaian nyata.
 *
 * Aman dijalankan berulang: berkas demo ditandai kode rombongan berawalan
 * DEMO- dan dibersihkan lebih dulu setiap kali seeder berjalan.
 */
class DemoPangiSeeder extends Seeder
{
    /** NIP Octavianus Elricth Waters Modami, akun uji coba utama. */
    private const NIP_UJI = '199310182025061003';

    /** Penanda supaya data demo dapat dibersihkan tanpa menyentuh data lain. */
    private const TANDA = 'DEMO-UJI';

    // ── Tahap alur, berurutan. Satu berkas berhenti di salah satunya. ──

    private const TAHAP_DRAF = 10;

    private const TAHAP_DIAJUKAN = 20;

    private const TAHAP_DISETUJUI = 30;

    private const TAHAP_SPD_TERBIT = 40;

    private const TAHAP_UANG_MUKA = 50;

    private const TAHAP_LPJ_DIISI = 60;

    private const TAHAP_DIVALIDASI = 70;

    private const TAHAP_DIKIRIM_PELAKSANA = 80;

    private const TAHAP_DISIKAPI_PELAKSANA = 90;

    private const TAHAP_DITANDATANGANI_PPK = 100;

    private const TAHAP_NOMINATIF_TERBIT = 110;

    private const TAHAP_LUNAS = 120;

    private User $octa;

    private User $ppk;

    private User $bendahara;

    private User $timKeuangan;

    /** @var Collection<int, User> */
    private Collection $rekan;

    public function run(): void
    {
        if (! $this->siapkanPemeran()) {
            return;
        }

        $this->bersihkanDemoLama();
        $this->bersihkanObrolanLama();
        $this->pastikanTahunAnggaran();

        // Berkas utama: satu untuk tiap tahap alur, tersebar sepanjang tahun
        // agar pengelompokan per bulan pada tiap menu ada isinya.
        foreach ($this->skenario() as $baris) {
            $this->rakitBerkas($baris);
        }

        // Keadaan di luar alur lurus, yang tetap harus dapat dicoba.
        $this->berkasDitolak();
        $this->rombonganMenungguKonfirmasi();
        $this->berkasTerlambatMelapor();
        $this->berkasDisanggahSebagian();
        $this->berkasDikembalikanPpk();
        $this->obrolanBantuan();

        $this->ringkasan();
    }

    /**
     * Satu baris per berkas demo: siapa, ke mana, kapan, dan berhenti di
     * tahap mana.
     *
     * @return list<array<string, mixed>>
     */
    private function skenario(): array
    {
        return [
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_DRAF,
                'lokasi' => 'Jakarta', 'instansi' => 'Kementerian Kesehatan RI',
                'uraian' => 'Konsultasi teknis pengembangan sistem informasi akademik.',
                'mulai' => now()->addDays(21), 'hari' => 3,
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_DIAJUKAN,
                'lokasi' => 'Surabaya', 'instansi' => 'Poltekkes Kemenkes Surabaya',
                'uraian' => 'Studi banding tata kelola laboratorium terpadu.',
                'mulai' => now()->addDays(12), 'hari' => 3,
            ],
            [
                'pemilik' => $this->rekan[0], 'tahap' => self::TAHAP_DISETUJUI,
                'lokasi' => 'Bandung', 'instansi' => 'Balai Besar Pelatihan Kesehatan Bandung',
                'uraian' => 'Pelatihan penyusunan kurikulum berbasis kompetensi.',
                'mulai' => now()->addDays(9), 'hari' => 4,
            ],
            [
                'pemilik' => $this->rekan[1], 'tahap' => self::TAHAP_SPD_TERBIT,
                'lokasi' => 'Yogyakarta', 'instansi' => 'Poltekkes Kemenkes Yogyakarta',
                'uraian' => 'Penyusunan naskah akademik program studi baru.',
                'mulai' => now()->addDays(5), 'hari' => 3, 'nomor_spd' => '311',
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_UANG_MUKA,
                'lokasi' => 'Makassar', 'instansi' => 'Dinas Kesehatan Provinsi Sulawesi Selatan',
                'uraian' => 'Pendampingan penyusunan rencana kerja tahunan.',
                'mulai' => now()->addDays(2), 'hari' => 3, 'nomor_spd' => '312',
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_LPJ_DIISI,
                'lokasi' => 'Denpasar', 'instansi' => 'Poltekkes Kemenkes Denpasar',
                'uraian' => 'Workshop penjaminan mutu pendidikan vokasi kesehatan.',
                'mulai' => now()->subDays(6), 'hari' => 3, 'nomor_spd' => '313',
            ],
            [
                'pemilik' => $this->rekan[2], 'tahap' => self::TAHAP_DIVALIDASI,
                'lokasi' => 'Semarang', 'instansi' => 'Poltekkes Kemenkes Semarang',
                'uraian' => 'Rapat koordinasi pengelolaan praktik kerja lapangan.',
                'mulai' => now()->subDays(9), 'hari' => 3, 'nomor_spd' => '314',
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_DIKIRIM_PELAKSANA,
                'lokasi' => 'Palu', 'instansi' => 'Poltekkes Kemenkes Palu',
                'uraian' => 'Pendampingan akreditasi program studi.',
                'mulai' => now()->subDays(12), 'hari' => 3, 'nomor_spd' => '315',
            ],
            [
                'pemilik' => $this->rekan[3], 'tahap' => self::TAHAP_DISIKAPI_PELAKSANA,
                'lokasi' => 'Kendari', 'instansi' => 'Poltekkes Kemenkes Kendari',
                'uraian' => 'Penguji eksternal ujian akhir program studi keperawatan.',
                'mulai' => now()->subDays(16), 'hari' => 3, 'nomor_spd' => '316',
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_DITANDATANGANI_PPK,
                'lokasi' => 'Ternate', 'instansi' => 'Poltekkes Kemenkes Ternate',
                'uraian' => 'Narasumber pelatihan penulisan karya tulis ilmiah.',
                'mulai' => now()->subDays(24), 'hari' => 3, 'nomor_spd' => '317',
            ],
            [
                'pemilik' => $this->rekan[4], 'tahap' => self::TAHAP_NOMINATIF_TERBIT,
                'lokasi' => 'Gorontalo', 'instansi' => 'Dinas Kesehatan Provinsi Gorontalo',
                'uraian' => 'Monitoring dan evaluasi program praktik kerja lapangan.',
                'mulai' => now()->subDays(38), 'hari' => 4, 'nomor_spd' => '318',
            ],
            [
                // Berhenti sebelum PPK menandatangani nominatifnya, supaya
                // menu Verifikasi Daftar Nominatif ada isinya.
                'pemilik' => $this->rekan[5], 'tahap' => self::TAHAP_NOMINATIF_TERBIT,
                'lokasi' => 'Ambon', 'instansi' => 'Rumah Sakit Umum Daerah Ambon',
                'uraian' => 'Pendampingan penyusunan standar pelayanan minimal.',
                'mulai' => now()->subDays(45), 'hari' => 3, 'nomor_spd' => '323',
                'nominatif_ditandatangani' => false,
            ],
            [
                'pemilik' => $this->octa, 'tahap' => self::TAHAP_LUNAS,
                'lokasi' => 'Manado', 'instansi' => 'Balai Pelatihan Kesehatan Manado',
                'uraian' => 'Pertemuan koordinasi lintas sektor bidang kesehatan.',
                'mulai' => now()->subDays(52), 'hari' => 2, 'nomor_spd' => '319',
                'laporan' => true,
            ],
            [
                'pemilik' => $this->rekan[5], 'tahap' => self::TAHAP_LUNAS,
                'lokasi' => 'Tomohon', 'instansi' => 'Rumah Sakit Gunung Maria',
                'uraian' => 'Supervisi klinik mahasiswa program profesi.',
                'mulai' => now()->subDays(70), 'hari' => 3, 'nomor_spd' => '320',
                'laporan' => true,
            ],
        ];
    }

    // ── Rangkaian tahap ──

    /**
     * Jalankan alurnya dari awal sampai tahap yang diminta.
     *
     * @param  array<string, mixed>  $baris
     */
    private function rakitBerkas(array $baris): Usulan
    {
        $tahap = $baris['tahap'];
        $pemilik = $baris['pemilik'];

        $usulan = $this->buatUsulan($pemilik, $this->statusUntuk($tahap), $baris);
        $peserta = $this->tambahPeserta($usulan, $pemilik, 'ketua');
        $this->lampirkanBerkasAwal($usulan);

        if ($tahap < self::TAHAP_DISETUJUI) {
            return $usulan;
        }

        $this->putusan($usulan, Persetujuan::KEPUTUSAN_SETUJU);

        if ($tahap >= self::TAHAP_SPD_TERBIT && isset($baris['nomor_spd'])) {
            $this->terbitkanSpd($usulan, $pemilik, $baris['nomor_spd']);
        }

        // Keuangan dibuat kosong lalu diisi dari dokumen: sama seperti
        // aplikasi, yang tidak pernah mengetik totalnya langsung.
        Keuangan::create(['id_usulan' => $usulan->id, 'total' => 0, 'uang_muka' => 0, 'sisa' => 0]);

        if ($tahap < self::TAHAP_LPJ_DIISI) {
            // Uang muka dapat cair sebelum pertanggungjawaban masuk, jadi
            // nominalnya disusun dari rencana biaya lebih dulu.
            $this->susunRencanaBiaya($usulan);

            if ($tahap >= self::TAHAP_UANG_MUKA) {
                $this->bayarUangMuka($usulan);
            }

            return $usulan;
        }

        $this->isiDokumenPertanggungjawaban($usulan);
        $this->selaraskan($usulan);
        $this->tambahUangHarian($usulan);

        // Daftar nominatif menuntut berkas pertanggungjawaban lengkap,
        // termasuk laporan perjadinnya — jadi tiap berkas yang berjalan
        // sampai ke sana mengisinya lebih dulu.
        if (($baris['laporan'] ?? false) || $tahap >= self::TAHAP_NOMINATIF_TERBIT) {
            $this->isiLaporanPerjadin($usulan);
        }

        $this->bayarUangMuka($usulan);

        if ($tahap < self::TAHAP_DIVALIDASI) {
            return $usulan;
        }

        $this->validasiSeluruhNominal($usulan);

        if ($tahap < self::TAHAP_DIKIRIM_PELAKSANA) {
            return $usulan;
        }

        $riil = $this->daftarRiil($usulan);
        $riil->kirimKePegawai();

        $this->beritahu(
            $pemilik, $usulan,
            'Berkas pertanggungjawaban menunggu tanda tangan Anda',
            "Rincian biaya dan daftar riil usulan {$usulan->no_usulan} sudah dikirim tim keuangan."
        );

        if ($tahap < self::TAHAP_DISIKAPI_PELAKSANA) {
            return $usulan;
        }

        // Pelaksana menyikapi kedua dokumen satu per satu.
        $riil->jalur()->setujui();
        $riil->jalurRincian()->setujui();

        if ($tahap < self::TAHAP_DITANDATANGANI_PPK) {
            return $usulan;
        }

        $riil->jalur()->tandaTangani($this->ppk);
        $riil->jalurRincian()->tandaTangani($this->ppk);

        $this->catat(
            $usulan, AuditLog::AKSI_DOKUMEN,
            "Rincian biaya dan daftar riil {$peserta->nama} ditandatangani PPK.",
            $this->ppk
        );

        if ($tahap < self::TAHAP_NOMINATIF_TERBIT) {
            return $usulan;
        }

        $this->terbitkanNominatif(
            $usulan,
            tandaTangani: $baris['nominatif_ditandatangani'] ?? true,
            kirim: $tahap >= self::TAHAP_LUNAS,
        );

        if ($tahap >= self::TAHAP_LUNAS) {
            $this->lunasi($usulan);
        }

        return $usulan;
    }

    /**
     * Status usulan yang sesuai dengan tahap yang dituju.
     */
    private function statusUntuk(int $tahap): StatusUsulan
    {
        return match (true) {
            $tahap <= self::TAHAP_DIAJUKAN => StatusUsulan::Draft,
            $tahap >= self::TAHAP_LUNAS => StatusUsulan::Selesai,
            default => StatusUsulan::Disetujui,
        };
    }

    // ── Keadaan di luar alur lurus ──

    /** Ditolak PPK: menguji tampilan status dan alasan penolakannya. */
    private function berkasDitolak(): void
    {
        $usulan = $this->buatUsulan($this->rekan[1], StatusUsulan::Ditolak, [
            'lokasi' => 'Denpasar',
            'instansi' => 'Universitas Udayana',
            'uraian' => 'Seminar nasional kesehatan masyarakat.',
            'mulai' => now()->addDays(30),
            'hari' => 2,
        ]);

        $this->tambahPeserta($usulan, $this->rekan[1], 'ketua');
        $this->putusan($usulan, Persetujuan::KEPUTUSAN_TOLAK, 'Anggaran perjalanan luar daerah triwulan ini sudah habis.');

        $this->beritahu(
            $this->rekan[1], $usulan, 'Usulan perjalanan dinas ditolak',
            "Usulan {$usulan->no_usulan} ditolak PPK. Lihat catatan penolakannya."
        );
    }

    /**
     * Rombongan tiga orang dengan ketiga keadaan konfirmasi sekaligus:
     * sudah dikonfirmasi, masih menunggu, dan membatalkan.
     */
    private function rombonganMenungguKonfirmasi(): void
    {
        $kode = self::TANDA.'-ROMBONGAN';

        $usulan = $this->buatUsulan($this->octa, StatusUsulan::Draft, [
            'lokasi' => 'Bandung',
            'instansi' => 'Balai Besar Pelatihan Kesehatan Bandung',
            'uraian' => 'Pelatihan peningkatan mutu pembelajaran klinik.',
            'mulai' => now()->addDays(16),
            'hari' => 4,
            'kelompok' => true,
            'kode_rombongan' => $kode,
        ]);

        $this->tambahPeserta($usulan, $this->octa, 'ketua');

        $anggota = [
            [$this->rekan[0], Usulan::KONFIRMASI_DIKONFIRMASI, null],
            [$this->rekan[1], Usulan::KONFIRMASI_MENUNGGU, null],
            [$this->rekan[2], Usulan::KONFIRMASI_DIBATALKAN, 'Berbenturan dengan jadwal mengajar semester ganjil.'],
        ];

        foreach ($anggota as [$pegawai, $konfirmasi, $alasan]) {
            $ikut = $this->buatUsulan($pegawai, StatusUsulan::Draft, [
                'lokasi' => $usulan->lokasi,
                'instansi' => $usulan->instansi,
                'uraian' => $usulan->uraian,
                'mulai' => Carbon::parse($usulan->tanggal_mulai),
                'hari' => 4,
                'kelompok' => true,
                'kode_rombongan' => $kode,
                'pembuat' => $this->octa,
                'konfirmasi' => $konfirmasi,
                'alasan_batal' => $alasan,
            ]);

            $this->tambahPeserta($ikut, $pegawai, 'anggota');

            if ($konfirmasi === Usulan::KONFIRMASI_MENUNGGU) {
                $this->beritahu(
                    $pegawai, $ikut, 'Konfirmasi keikutsertaan perjalanan dinas',
                    "Anda diikutsertakan pada perjalanan dinas ke {$ikut->lokasi}. Mohon konfirmasi."
                );
            }
        }
    }

    /**
     * Perjalanan selesai tetapi berkasnya belum lengkap — menguji pengingat
     * otomatis dan daftar "belum melapor".
     */
    private function berkasTerlambatMelapor(): void
    {
        $usulan = $this->buatUsulan($this->rekan[3], StatusUsulan::Disetujui, [
            'lokasi' => 'Ambon',
            'instansi' => 'Poltekkes Kemenkes Maluku',
            'uraian' => 'Bimbingan teknis penjaminan mutu internal.',
            'mulai' => now()->subDays(28),
            'hari' => 3,
        ]);

        $this->tambahPeserta($usulan, $this->rekan[3], 'ketua');
        $this->putusan($usulan, Persetujuan::KEPUTUSAN_SETUJU);
        Keuangan::create(['id_usulan' => $usulan->id, 'total' => 0, 'uang_muka' => 0, 'sisa' => 0]);
        $this->susunRencanaBiaya($usulan);
        $this->bayarUangMuka($usulan);

        // Sengaja tidak lengkap: kuitansi dan bill hotel belum diunggah.
        Dokumen::updateOrCreate(['id_usulan' => $usulan->id], [
            'surat_tugas' => 'demo/surat-tugas.pdf',
            'sppd' => 'demo/sppd.pdf',
            'boarding_pass' => 'demo/boarding-pass.pdf',
        ]);

        $this->beritahu(
            $this->rekan[3], $usulan, 'Berkas pertanggungjawaban belum lengkap',
            "Perjalanan {$usulan->no_usulan} sudah berakhir. Lengkapi kuitansi dan bill hotel."
        );
    }

    /**
     * Pelaksana menerima daftar riilnya tetapi menyanggah rincian biayanya.
     * Inilah bukti kedua dokumen berjalan sendiri-sendiri.
     */
    private function berkasDisanggahSebagian(): void
    {
        $usulan = $this->rakitBerkas([
            'pemilik' => $this->rekan[0], 'tahap' => self::TAHAP_DIKIRIM_PELAKSANA,
            'lokasi' => 'Jayapura', 'instansi' => 'Dinas Kesehatan Provinsi Papua',
            'uraian' => 'Pendampingan penguatan sistem rujukan daerah.',
            'mulai' => now()->subDays(20), 'hari' => 4, 'nomor_spd' => '321',
        ]);

        $riil = $this->daftarRiil($usulan);
        $riil->jalur()->setujui();
        $riil->jalurRincian()->sanggah('Uang harian dihitung 4 hari, sedangkan SPD menyebut 3 hari.');

        $this->beritahu(
            $this->timKeuangan, $usulan, 'Rincian biaya disanggah',
            "{$this->rekan[0]->nama} menyanggah rincian biaya usulan {$usulan->no_usulan}."
        );
    }

    /** PPK mengembalikan berkas ke tim keuangan untuk diperiksa ulang. */
    private function berkasDikembalikanPpk(): void
    {
        $usulan = $this->rakitBerkas([
            'pemilik' => $this->rekan[4], 'tahap' => self::TAHAP_DISIKAPI_PELAKSANA,
            'lokasi' => 'Sorong', 'instansi' => 'Poltekkes Kemenkes Sorong',
            'uraian' => 'Sosialisasi kebijakan pengembangan sumber daya manusia kesehatan.',
            'mulai' => now()->subDays(30), 'hari' => 3, 'nomor_spd' => '322',
        ]);

        $this->daftarRiil($usulan)->kembalikanKeKeuangan(
            'Biaya hotel melebihi standar tarif provinsi. Mohon diperiksa ulang.',
            $this->ppk,
        );

        $this->beritahu(
            $this->bendahara, $usulan, 'Berkas dikembalikan PPK',
            "Berkas {$usulan->no_usulan} dikembalikan PPK untuk diperiksa ulang."
        );
    }

    /**
     * Saluran bantuan: satu laporan menunggu jawaban, satu sudah dijawab,
     * satu sudah selesai — supaya ketiga keadaannya dapat dicoba.
     */
    private function obrolanBantuan(): void
    {
        $admin = User::firstWhere('role', User::ROLE_SUPER_ADMIN);

        $naskah = [
            [
                'pelapor' => $this->octa,
                'judul' => 'Tombol unggah SPPD tidak memberi tanggapan',
                'isi' => 'Saat membuka menu Dokumen lalu menekan tombol unggah SPPD, tidak ada yang terjadi. '
                    .'Sudah saya coba dengan peramban lain, hasilnya sama.',
                'jawaban' => null,
                'selesai' => false,
            ],
            [
                'pelapor' => $this->rekan[0],
                'judul' => 'Nominal uang harian berbeda dengan SPD',
                'isi' => 'Pada rincian biaya, uang harian tertulis 4 hari, sedangkan SPD saya menyebut 3 hari. '
                    .'Mohon diperiksa sebelum saya tanda tangani.',
                'jawaban' => 'Terima kasih laporannya. Sudah kami teruskan ke tim keuangan untuk diperiksa ulang, '
                    .'dan berkasnya akan dikirimkan kembali kepada Bapak setelah diperbaiki.',
                'selesai' => false,
            ],
            [
                'pelapor' => $this->rekan[1],
                'judul' => 'Lupa kata sandi akun PANGI',
                'isi' => 'Saya tidak dapat masuk sejak kemarin. Mohon bantuannya untuk mengatur ulang kata sandi.',
                'jawaban' => 'Kata sandi sudah kami atur ulang. Silakan masuk memakai NIP, lalu segera menggantinya '
                    .'lewat menu Profil.',
                'selesai' => true,
            ],
        ];

        foreach ($naskah as $baris) {
            $obrolan = ObrolanBantuan::create([
                'id_pelapor' => $baris['pelapor']->id,
                'judul' => $baris['judul'],
                'status' => ObrolanBantuan::STATUS_TERBUKA,
            ]);

            $obrolan->balas($baris['pelapor'], $baris['isi']);

            if ($baris['jawaban'] && $admin) {
                $obrolan->balas($admin, $baris['jawaban']);
            }

            if ($baris['selesai'] && $admin) {
                $obrolan->selesaikan($admin);
            }
        }
    }

    // ── Tindakan tiap tahap ──

    private function daftarRiil(Usulan $usulan): DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $usulan->id)->fresh();
    }

    /**
     * Rencana biaya sebelum pertanggungjawaban masuk: uang harian, tiket,
     * dan penginapan menurut lama perjalanan.
     */
    private function susunRencanaBiaya(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;
        $hari = max(1, (int) $usulan->durasi);

        $komponen = [
            [KategoriBiaya::Transport, 'Tiket pesawat pergi pulang', 1, 'paket', 4_735_000],
            [KategoriBiaya::UangHarian, 'Uang harian perjalanan dinas', $hari, 'hari', 530_000],
            [KategoriBiaya::Penginapan, 'Biaya penginapan', $hari, 'hari', 700_000],
        ];

        foreach ($komponen as [$kategori, $nama, $volume, $satuan, $harga]) {
            RincianBiaya::updateOrCreate(
                ['id_keuangan' => $keuangan->id, 'kategori' => $kategori->value],
                [
                    'komponen' => $nama,
                    'volume' => $volume,
                    'satuan' => $satuan,
                    'harga_satuan' => $harga,
                    'jumlah' => $volume * $harga,
                    'sumber' => RincianBiaya::SUMBER_KEUANGAN,
                ],
            );
        }

        $keuangan->hitungTotal();

        $this->catat($usulan, AuditLog::AKSI_BIAYA, "Rincian biaya usulan {$usulan->no_usulan} disusun.", $this->timKeuangan);
    }

    /**
     * Uang muka cair, lengkap dengan jurnal riwayat pembayarannya.
     */
    private function bayarUangMuka(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan || $keuangan->uang_muka <= 0 || $keuangan->uangMukaTerbayar()) {
            return;
        }

        $tanggal = Carbon::parse($usulan->tanggal_mulai)->subDays(2)->toDateString();

        $keuangan->update([
            'tanggal_transfer' => $tanggal,
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $keuangan->dokumenKeuangan()->updateOrCreate([], [
            'transfer_uang_muka' => 'demo/bukti-transfer.pdf',
            'transfer_sisa' => '',
        ]);

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_UANG_MUKA,
            (float) $keuangan->uang_muka,
            $tanggal,
            $this->bendahara,
            'demo/bukti-transfer.pdf',
        );

        $this->catat(
            $usulan, AuditLog::AKSI_PEMBAYARAN,
            'Uang muka sebesar Rp '.number_format($keuangan->uang_muka, 0, ',', '.')." dibayarkan untuk usulan {$usulan->no_usulan}.",
            $this->bendahara,
        );

        $this->beritahu(
            $usulan->user, $usulan, 'Uang muka telah dibayarkan',
            'Uang muka perjalanan dinas Rp '.number_format($keuangan->uang_muka, 0, ',', '.').' sudah ditransfer.'
        );
    }

    /**
     * Daftar nominatif surat tugas terbit, ditandatangani PPK, dan bila
     * diminta ikut dikirimkan ke tim keuangan berikut pembebanannya.
     */
    private function terbitkanNominatif(Usulan $usulan, bool $tandaTangani, bool $kirim): void
    {
        if (! $usulan->no_tugas) {
            return;
        }

        $penyusun = app(PenyusunNominatif::class);

        if (! $penyusun->siapTerbit($usulan->no_tugas)) {
            return;
        }

        $nominatif = $penyusun->terbitkan($usulan->no_tugas);

        if (! $tandaTangani) {
            return;
        }

        if (! $nominatif->sudahDitandatangani()) {
            $nominatif->update(['id_ppk' => $this->ppk->id, 'ditandatangani_at' => now()]);
            $nominatif->terbitkanKodeVerifikasi();
        }

        if (! $kirim) {
            return;
        }

        $nominatif->update(['dikirim_at' => now()]);

        // Pembebanan ditetapkan tim keuangan setelah daftarnya diterima.
        $nominatif->update([
            'id_kategori_pembiayaan' => KategoriPembiayaan::orderBy('kode')->value('id'),
            'id_akun_pembiayaan' => AkunPembiayaan::orderBy('kode')->value('id'),
        ]);
    }

    /** Pelunasan: sisa uang harian ditambah penggantian transport lokal. */
    private function lunasi(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan || $keuangan->sudahLunas()) {
            return;
        }

        $tanggal = Carbon::parse($usulan->tanggal_selesai)->addDays(5)->toDateString();
        $nilai = $keuangan->nilaiPelunasan();

        $keuangan->update([
            'tanggal_pelunasan' => $tanggal,
            'status' => Keuangan::STATUS_LUNAS,
        ]);

        $keuangan->konfirmasiPelunasan();

        $keuangan->dokumenKeuangan()->updateOrCreate([], ['transfer_sisa' => 'demo/bukti-pelunasan.pdf']);

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_PELUNASAN,
            $nilai,
            $tanggal,
            $this->bendahara,
            'demo/bukti-pelunasan.pdf',
            'Sisa Rp '.number_format($keuangan->sisa, 0, ',', '.')
                .' + penggantian transport lokal Rp '.number_format($keuangan->reimbursementTransport(), 0, ',', '.'),
        );

        $this->catat(
            $usulan, AuditLog::AKSI_PEMBAYARAN,
            'Pelunasan Rp '.number_format($nilai, 0, ',', '.')." dibayarkan untuk usulan {$usulan->no_usulan}.",
            $this->bendahara,
        );

        $this->beritahu(
            $usulan->user, $usulan, 'Sisa pembayaran telah dilunasi',
            'Pelunasan perjalanan dinas Rp '.number_format($nilai, 0, ',', '.').' sudah ditransfer.'
        );
    }

    // ── Pemeran dan pembersihan ──

    private function siapkanPemeran(): bool
    {
        $octa = User::firstWhere('nip', self::NIP_UJI);

        if (! $octa) {
            $this->command?->error('Akun uji coba tidak ditemukan. Jalankan RilisSeeder lebih dulu.');

            return false;
        }

        $this->octa = $octa;
        $this->ppk = User::firstWhere('role', User::ROLE_PPK) ?? $octa;
        $this->bendahara = User::firstWhere('role', User::ROLE_BENDAHARA) ?? $octa;
        $this->timKeuangan = User::firstWhere('role', User::ROLE_TIM_KEUANGAN) ?? $this->bendahara;

        $this->rekan = User::where('role', User::ROLE_DOSEN_TENDIK)
            ->whereKeyNot($octa->id)
            ->inRandomOrder()
            ->take(6)
            ->get();

        if ($this->rekan->count() < 6) {
            $this->command?->error('Pegawai belum cukup untuk menyusun rombongan. Jalankan RilisSeeder lebih dulu.');

            return false;
        }

        return true;
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

        // Baris rincian daftar riil ikut terhapus lewat kunci asingnya.
        DaftarRiil::whereIn('id_usulan', $id)->delete();
        DaftarNominatif::whereIn('no_tugas', $usulan->pluck('no_tugas')->filter())->delete();

        LaporanPerjadin::whereIn('id_usulan', $id)->delete();
        Dokumen::whereIn('id_usulan', $id)->delete();
        PesertaUsulan::whereIn('id_usulan', $id)->delete();
        Persetujuan::whereIn('id_usulan', $id)->delete();
        Notifikasi::whereIn('id_usulan', $id)->delete();
        AuditLog::whereIn('id_usulan', $id)->delete();

        $spd = $usulan->pluck('id_spd')->filter();

        Usulan::whereIn('id', $id)->delete();
        SuratPerjalananDinas::whereIn('id', $spd)->delete();

        $this->command?->warn("Data demo sebelumnya dihapus: {$usulan->count()} usulan.");
    }

    /**
     * Obrolan bantuan tidak tertaut usulan, jadi dibersihkan terpisah
     * berdasarkan pemerannya.
     */
    private function bersihkanObrolanLama(): void
    {
        ObrolanBantuan::whereIn('id_pelapor', $this->rekan->pluck('id')->push($this->octa->id))->delete();
    }

    private function pastikanTahunAnggaran(): void
    {
        foreach ([now()->year, now()->subYear()->year] as $tahun) {
            TahunAnggaran::firstOrCreate(
                ['tahun' => $tahun],
                ['pagu' => 2_500_000_000, 'is_aktif' => $tahun === now()->year],
            );
        }
    }

    // ── Pembantu penyusun data ──

    /**
     * @param  array<string, mixed>  $opsi
     */
    private function buatUsulan(User $pemilik, StatusUsulan $status, array $opsi): Usulan
    {
        $mulai = $opsi['mulai'] instanceof Carbon ? $opsi['mulai'] : Carbon::parse($opsi['mulai']);
        $selesai = $mulai->copy()->addDays(max(0, ($opsi['hari'] ?? 1) - 1));
        $lokasi = LokasiTujuan::firstWhere('nama', $opsi['lokasi']);

        $usulan = Usulan::create([
            'no_usulan' => app(PenomoranPerjadin::class)->nomorPerjadin($pemilik, $mulai->toDateString()),
            'no_tugas' => 'KP.01.02/F.XXX/'.fake()->unique()->numberBetween(1000, 1999).'/'.$mulai->format('Y'),
            'status' => $status->value,
            'jenis_pengajuan' => ($opsi['kelompok'] ?? false) ? Usulan::PENGAJUAN_KELOMPOK : Usulan::PENGAJUAN_PERSONAL,
            'lokasi' => $opsi['lokasi'],
            'id_lokasi' => $lokasi?->id,
            'instansi' => $opsi['instansi'],
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $selesai->toDateString(),
            'uraian' => $opsi['uraian'],
            'catatan' => $opsi['catatan'] ?? null,
            'id_user' => $pemilik->id,
            'id_pembuat' => ($opsi['pembuat'] ?? $pemilik)->id,
            'id_kategori_perjadin' => KategoriPerjadin::inRandomOrder()->value('id'),
            'id_tahun_anggaran' => TahunAnggaran::where('tahun', (int) $mulai->format('Y'))->value('id')
                ?? TahunAnggaran::value('id'),
            'kode_rombongan' => $opsi['kode_rombongan'] ?? self::TANDA.'-'.strtoupper(fake()->bothify('??##')),
            'konfirmasi' => $opsi['konfirmasi'] ?? Usulan::KONFIRMASI_DIKONFIRMASI,
            'alasan_batal' => $opsi['alasan_batal'] ?? null,
            'dikonfirmasi_at' => ($opsi['konfirmasi'] ?? null) === Usulan::KONFIRMASI_MENUNGGU ? null : now(),
        ]);

        $this->catat($usulan, AuditLog::AKSI_DIBUAT, "Usulan {$usulan->no_usulan} dibuat.", $opsi['pembuat'] ?? $pemilik);

        return $usulan;
    }

    private function tambahPeserta(Usulan $usulan, User $pegawai, string $peran): PesertaUsulan
    {
        return PesertaUsulan::create([
            'id_usulan' => $usulan->id,
            'id_user' => $pegawai->id,
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'peran' => $peran,
        ]);
    }

    private function lampirkanBerkasAwal(Usulan $usulan): void
    {
        Dokumen::updateOrCreate(
            ['id_usulan' => $usulan->id],
            ['surat_tugas' => 'demo/surat-tugas.pdf', 'rundown' => 'demo/rundown.pdf'],
        );
    }

    private function putusan(Usulan $usulan, string $keputusan, ?string $catatan = null): void
    {
        Persetujuan::create([
            'id_usulan' => $usulan->id,
            'id_approver' => $this->ppk->id,
            'level' => 1,
            'peran' => User::ROLE_PPK,
            'keputusan' => $keputusan,
            'catatan' => $catatan,
            'waktu_keputusan' => now()->subDays(2),
        ]);

        $aksi = match ($keputusan) {
            Persetujuan::KEPUTUSAN_SETUJU => AuditLog::AKSI_DISETUJUI,
            Persetujuan::KEPUTUSAN_TOLAK => AuditLog::AKSI_DITOLAK,
            default => AuditLog::AKSI_REVISI,
        };

        $this->catat($usulan, $aksi, "Usulan {$usulan->no_usulan} {$keputusan} oleh PPK.", $this->ppk);
    }

    private function beritahu(?User $penerima, Usulan $usulan, string $judul, string $pesan): void
    {
        if (! $penerima) {
            return;
        }

        Notifikasi::create([
            'id_user' => $penerima->id,
            'id_usulan' => $usulan->id,
            'judul' => $judul,
            'pesan' => $pesan,
            'url' => route('usulan.show', $usulan->no_usulan),
        ]);
    }

    private function catat(Usulan $usulan, string $aksi, string $deskripsi, User $pelaku): void
    {
        AuditLog::create([
            'id_usulan' => $usulan->id,
            'id_user' => $pelaku->id,
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
        ]);
    }

    /**
     * Terbitkan SPD beserta pelaksananya, lalu tautkan ke usulan.
     *
     * Nomor suratnya dirakit lewat SpdPelaksana::rakitNomor agar polanya
     * sama persis dengan surat yang lahir dari formulir.
     */
    private function terbitkanSpd(Usulan $usulan, User $pelaksana, string $nomorUrut): SuratPerjalananDinas
    {
        $mulai = Carbon::parse($usulan->tanggal_mulai);

        $spd = SuratPerjalananDinas::create([
            'id_usulan' => $usulan->id,
            'id_pembuat' => $pelaksana->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => $mulai->copy()->subDays(5)->toDateString(),
            'maksud' => $usulan->uraian,
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => $usulan->lokasi,
            'tanggal_berangkat' => $usulan->tanggal_mulai,
            'tanggal_kembali' => $usulan->tanggal_selesai,
            'lama_hari' => max(1, (int) $usulan->durasi),
            'instansi_pembebanan' => 'Politeknik Kesehatan Kemenkes Manado',
            'akun_pembebanan' => '2079.QEB.001.052.A.524111',
        ]);

        SpdPelaksana::create([
            'id_spd' => $spd->id,
            'id_user' => $pelaksana->id,
            'urutan' => 1,
            'nomor_surat' => SpdPelaksana::rakitNomor($nomorUrut, (int) $mulai->format('Y')),
            'nama' => $pelaksana->nama,
            'nip' => $pelaksana->nip,
            'pangkat_golongan' => $pelaksana->pangkat_golongan ?? 'Penata Muda / III-a',
            'jabatan_instansi' => $pelaksana->jabatan ?? 'Dosen',
            'tingkat_biaya' => 'B',
        ]);

        $usulan->update(['id_spd' => $spd->id]);

        return $spd;
    }

    /**
     * Isi seluruh berkas pertanggungjawaban: tiket pergi dan pulang, empat
     * ruas nota transportasi, serta bill hotel berikut nomor transaksinya.
     */
    private function isiDokumenPertanggungjawaban(Usulan $usulan): void
    {
        $hari = max(1, (int) $usulan->durasi);

        Dokumen::updateOrCreate(['id_usulan' => $usulan->id], [
            'surat_tugas' => 'demo/surat-tugas.pdf',
            'sppd' => 'demo/sppd.pdf',
            'boarding_pass' => 'demo/boarding-pass.pdf',
            'nota_transportasi' => 'demo/nota-transportasi.pdf',
            'kwintasi' => 'demo/kwitansi.pdf',
            'bill_hotel' => 'demo/bill-hotel.pdf',
            'laporan_hasil' => 'demo/laporan-hasil.pdf',
            'bill_hotel_no_transaksi' => 'TRX-'.fake()->numerify('######'),
            'bill_hotel_nominal' => 700_000 * $hari,
        ]);

        foreach (ArahTiket::urutan() as $arah) {
            $pergi = $arah === ArahTiket::Pergi;

            $usulan->tiket()->updateOrCreate(['arah' => $arah->value], [
                'kota_asal' => $pergi ? 'Manado' : $usulan->lokasi,
                'kota_tujuan' => $pergi ? $usulan->lokasi : 'Manado',
                'nomor_tiket' => 'GA-'.fake()->numerify('###'),
                'kode_booking' => strtoupper(fake()->bothify('??#?#?')),
                'harga' => $pergi ? 2_450_000 : 2_285_000,
                'boarding_pass' => 'demo/boarding-pass.pdf',
            ]);
        }

        $nominalRuas = [1 => 75_000, 2 => 160_000, 3 => 160_000, 4 => 79_500];

        foreach (RuasTransport::urutan() as $ruas) {
            $usulan->notaTransport()->updateOrCreate(['urutan' => $ruas->value], [
                'nominal' => $nominalRuas[$ruas->value],
                'keterangan' => $ruas->keterangan(),
                'bukti' => 'demo/nota-transportasi.pdf',
            ]);
        }
    }

    /**
     * Salin nominal dokumen menjadi rincian biaya dan daftar riil, lewat
     * layanan yang sama dengan yang dipakai aplikasi.
     */
    private function selaraskan(Usulan $usulan): void
    {
        app(SinkronBiayaDokumen::class)->selaraskan($usulan->fresh());
    }

    /**
     * Uang harian tidak lahir dari dokumen pelaksana — ia dihitung tim
     * keuangan dari lama perjalanan dan tarif golongan, lalu diketik pada
     * menu Keuangan. Tanpa baris ini kolom uang harian pada daftar
     * nominatif kosong.
     */
    private function tambahUangHarian(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan) {
            return;
        }

        $hari = max(1, (int) $usulan->durasi);

        RincianBiaya::updateOrCreate(
            ['id_keuangan' => $keuangan->id, 'kategori' => KategoriBiaya::UangHarian->value],
            [
                'komponen' => 'Uang harian perjalanan dinas',
                'volume' => $hari,
                'satuan' => 'hari',
                'harga_satuan' => 530_000,
                'jumlah' => $hari * 530_000,
                'sumber' => RincianBiaya::SUMBER_KEUANGAN,
                'divalidasi_at' => now(),
                'id_validator' => $this->timKeuangan->id,
            ],
        );

        $keuangan->hitungTotal();
    }

    /** Tim keuangan menyatakan seluruh nominal dokumen sudah diperiksa. */
    private function validasiSeluruhNominal(Usulan $usulan): void
    {
        $keuangan = $usulan->fresh('keuangan')->keuangan;

        if (! $keuangan) {
            return;
        }

        $keuangan->rincianBiaya()
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $riil = DaftarRiil::firstWhere('id_usulan', $usulan->id);

        $riil?->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $this->catat(
            $usulan, AuditLog::AKSI_BIAYA,
            "Seluruh nominal usulan {$usulan->no_usulan} divalidasi tim keuangan.",
            $this->timKeuangan,
        );
    }

    /**
     * Laporan perjalanan dinas beserta uraian kegiatan per hari dan rencana
     * tindak lanjutnya — ketiga status tindak lanjut terwakili.
     */
    private function isiLaporanPerjadin(Usulan $usulan): void
    {
        $laporan = LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);
        $mulai = Carbon::parse($usulan->tanggal_mulai);

        $kegiatan = [
            'Pembukaan dan pemaparan kebijakan oleh pimpinan penyelenggara.',
            'Diskusi kelompok penyusunan rencana tindak lanjut satuan kerja.',
            'Perumusan hasil dan penutupan kegiatan.',
        ];

        foreach (array_slice($kegiatan, 0, max(1, (int) $usulan->durasi)) as $i => $uraian) {
            $laporan->kegiatan()->updateOrCreate(['urutan' => $i + 1], [
                'tanggal' => $mulai->copy()->addDays($i)->toDateString(),
                'uraian' => $uraian,
            ]);
        }

        $tindakLanjut = [
            ['Menyusun rencana penerapan hasil kegiatan di unit kerja.', StatusTindakLanjut::Rencana],
            ['Menyampaikan hasil kegiatan pada rapat unit kerja.', StatusTindakLanjut::Berjalan],
            ['Melaporkan hasil kegiatan kepada pimpinan.', StatusTindakLanjut::Selesai],
        ];

        foreach ($tindakLanjut as $i => [$uraian, $status]) {
            $laporan->tindakLanjut()->updateOrCreate(['urutan' => $i + 1], [
                'uraian' => $uraian,
                'penanggung_jawab' => $usulan->user?->nama,
                'target_selesai' => Carbon::parse($usulan->tanggal_selesai)->addDays(30 * ($i + 1))->toDateString(),
                'status' => $status->value,
            ]);
        }

        $laporan->update([
            'kesimpulan' => 'Kegiatan terlaksana sesuai rencana dan hasilnya dapat diterapkan di unit kerja.',
            'id_status_hasil' => StatusHasil::where('is_aktif', true)->orderBy('urutan')->value('id'),
            'diselesaikan_at' => Carbon::parse($usulan->tanggal_selesai)->addDay(),
        ]);
    }

    private function ringkasan(): void
    {
        $demo = Usulan::where('kode_rombongan', 'like', self::TANDA.'%');
        $id = (clone $demo)->pluck('id');

        $this->command?->newLine();
        $this->command?->info('Data demo siap: '.(clone $demo)->count().' usulan, '
            .(clone $demo)->where('id_user', $this->octa->id)->count().' di antaranya milik akun uji coba.');

        $this->command?->line('Daftar riil      : '.DaftarRiil::whereIn('id_usulan', $id)->count());
        $this->command?->line('Daftar nominatif : '.DaftarNominatif::count());
        $this->command?->line('Riwayat bayar    : '.RiwayatPembayaran::whereIn('id_usulan', $id)->count());
        $this->command?->newLine();

        $this->command?->line('Akun uji coba : '.$this->octa->nama.' — NIP '.self::NIP_UJI);
        $this->command?->line("PPK           : {$this->ppk->nama} — NIP {$this->ppk->nip}");
        $this->command?->line("Bendahara     : {$this->bendahara->nama} — NIP {$this->bendahara->nip}");
        $this->command?->line("Tim Keuangan  : {$this->timKeuangan->nama} — NIP {$this->timKeuangan->nip}");

        $sdm = User::firstWhere('role', User::ROLE_TIM_SDM);

        if ($sdm) {
            $this->command?->line("Tim SDM       : {$sdm->nama} — NIP {$sdm->nip}");
        }

        $this->command?->line('Kata sandi    : sama dengan NIP masing-masing');
    }
}
