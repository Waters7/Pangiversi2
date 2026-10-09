<?php

namespace App\Http\Controllers;

use App\Enums\Kemampuan;
use App\Models\DaftarRiil;
use App\Models\User;
use App\Services\VersiAplikasi;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panduan penggunaan PANGI, disusun mengikuti urutan yang benar-benar
 * dilalui pengguna: menerbitkan SPD, mengajukan, berangkat, lalu
 * mempertanggungjawabkan.
 *
 * Bagiannya menyesuaikan kewenangan yang membuka. Seluruh peran dapat
 * bepergian, jadi bagian pelaksana selalu tampil; bagian PPK, tim keuangan,
 * bendahara, dan administrator hanya muncul bagi yang memang mengerjakannya
 * — panduan yang memuat pekerjaan orang lain hanya menyulitkan pembacanya
 * menemukan bagiannya sendiri.
 */
class PanduanController extends Controller
{
    /**
     * Buku panduan lengkap yang ikut dikemas bersama aplikasi.
     *
     * Disimpan di resources/ dan ikut versi kode, supaya buku yang diunduh
     * selalu sepasang dengan sistem yang sedang berjalan — bukan berkas lepas
     * di storage yang bisa tertinggal saat aplikasi diperbarui.
     */
    public const BERKAS_BUKU = 'Buku-Panduan-PANGI-v2.5.docx';

    /**
     * Buku yang sama dalam PDF — sampul dan gambarnya tampil utuh di perangkat
     * apa pun, termasuk ponsel yang tidak memasang pengolah kata.
     */
    public const BERKAS_PDF = 'Buku-Panduan-PANGI-v2.5.pdf';

    public function __invoke(Request $request, VersiAplikasi $versi): View
    {
        $pengguna = $request->user();

        return view('panduan.index', [
            'peran' => $pengguna->peran,
            'bagian' => $this->bagian($pengguna),
            'tugasPeran' => $this->tugasPeran($pengguna),
            // Tombol unduh disembunyikan bila bukunya memang tidak terpasang,
            // supaya tidak menjanjikan berkas yang berujung halaman 404.
            'bukuTersedia' => is_file($this->jalurBuku(self::BERKAS_BUKU)),
            'pdfTersedia' => is_file($this->jalurBuku(self::BERKAS_PDF)),
            // Sebagian bagian memuat langkah yang tidak berlaku bagi seluruh
            // pembacanya — Tim SDM mengelola pengguna tanpa menyentuh master
            // data maupun jejak audit.
            'boleh' => [
                'masterData' => $pengguna->punyaKemampuan(Kemampuan::MengelolaMasterData),
                'peran' => $pengguna->punyaKemampuan(Kemampuan::MengelolaPeran),
                'dokumen' => $pengguna->punyaKemampuan(Kemampuan::MengaturDokumenCetak),
                'jejakAudit' => $pengguna->punyaKemampuan(Kemampuan::MelihatJejakAudit),
            ],
            'hariSanggah' => DaftarRiil::HARI_MASA_SANGGAH,
            'versi' => $versi->label(),
            'riwayat' => $this->riwayatPerubahan(),
        ]);
    }

    /**
     * Unduh buku panduan lengkap — Word secara bawaan, PDF bila diminta.
     *
     * Yang di layar menyesuaikan peran pembacanya; berkas ini memuat
     * seluruhnya sekaligus, untuk dicetak atau dibagikan di luar aplikasi.
     */
    public function unduh(Request $request): BinaryFileResponse
    {
        $berkas = $request->query('format') === 'pdf' ? self::BERKAS_PDF : self::BERKAS_BUKU;

        abort_unless(
            is_file($this->jalurBuku($berkas)),
            404,
            'Buku panduan belum terpasang pada aplikasi ini.',
        );

        return response()->download($this->jalurBuku($berkas), $berkas);
    }

    private function jalurBuku(string $berkas): string
    {
        return resource_path('panduan/'.$berkas);
    }

    /**
     * Bagian panduan yang berlaku bagi pengguna ini, berurutan menurut alur
     * kerjanya.
     *
     * @return array<string, string>
     */
    private function bagian(User $pengguna): array
    {
        $bagian = [
            'spd' => 'Menerbitkan SPD',
            'pengajuan' => 'Mengajukan Perjadin',
            'berkas' => 'Berkas & Laporan',
            'rincian' => 'Memeriksa & Menandatangani',
        ];

        $tambahan = [
            'keuangan' => [Kemampuan::MemvalidasiBiaya, 'Menyusun Rincian Biaya'],
            'ppk' => [Kemampuan::MenandatanganiDaftarRiil, 'Tanda Tangan PPK'],
            'bendahara' => [Kemampuan::MencatatPembayaran, 'Mencatat Pembayaran'],
            'laporan-pimpinan' => [Kemampuan::MengonfirmasiLaporanPerjadin, 'Mengonfirmasi Laporan Perjadin'],
            'pemantauan' => [Kemampuan::MelihatLaporan, 'Memantau & Melaporkan'],
            'administrasi' => [Kemampuan::MengelolaPengguna, 'Mengelola Pengguna'],
        ];

        foreach ($tambahan as $kunci => [$kemampuan, $label]) {
            if ($pengguna->punyaKemampuan($kemampuan)) {
                $bagian[$kunci] = $label;
            }
        }

        $bagian['bantuan'] = 'Melapor Kendala';
        $bagian['perubahan'] = 'Riwayat Perubahan';

        return $bagian;
    }

    /**
     * Yang berubah dari versi ke versi — terbaru di atas — supaya pembaca
     * versi lama tahu bagian mana yang perlu dibaca ulang. Sengaja ditulis
     * dari sudut pengguna, bukan daftar commit.
     *
     * @return list<array{versi: string, tanggal: string, butir: list<string>}>
     */
    private function riwayatPerubahan(): array
    {
        return [
            [
                'versi' => '2.5.23',
                'tanggal' => '9 Oktober 2026',
                'butir' => [
                    'Satu SPD hanya untuk satu usulan perjadin: SPD yang sudah dipakai usulan pelaksana tidak tampil lagi pada pilihan SPD formulir usulan dan ditolak bila dikirim. SPD itu dapat dipakai kembali setelah usulannya dibatalkan atau dihapus — begitu pula nomor SPD-nya. Rekan pada SPD yang sama tetap mengajukan usulannya sendiri.',
                    'SPD yang dibuat bersama pelaksana lain dapat dikurangi satu pelaksana saja; SPD dan pelaksana lainnya tetap. Pelaksana yang bukan pembuat SPD hanya menghapus dirinya sendiri, bukan SPD rekannya.',
                ],
            ],
            [
                'versi' => '2.5.22',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Hak akses baru: Menghapus komponen rincian biaya (Keuangan → Hapus) dan Melihat persetujuan (Persetujuan → Lihat) — yang terakhir membuka menu Persetujuan tanpa ikut menandatangani.',
                    'Komponen pada tabel rincian biaya dapat dihapus kembali. Nominal dari berkas pelaksana yang dihapus tidak tersalin lagi kecuali pelaksana mengubahnya, dan dapat dikembalikan.',
                    'Hanya baris tulisan tim keuangan dan nominal pelaksana yang sudah divalidasi yang terhitung pada total, uang muka, sisa bayar, dan cetakan rincian biaya. Transport lokal tidak lagi tercetak pada rincian biaya — ia tercetak pada daftar riil.',
                    'Tabel Status Pembayaran Komponen: tiap komponen dibayarkan lewat uang muka atau dibayar pelaksana dahulu lalu diganti saat pelunasan, dikonfirmasi tim keuangan. Kartu ringkasan komponen membantu bendahara saat mencatat uang muka, dan uang muka yang sudah ditransfer tidak bergeser lagi.',
                    'Bill hotel tidak ditagih bila penginapan sudah termasuk biaya penyelenggaraan.',
                    'Daftar Input Rincian Biaya dipilah per tahap — Sedang Diproses, Dikirim ke Pelaksana, Proses Pembayaran, Selesai — dan dapat diurutkan.',
                ],
            ],
            [
                'versi' => '2.5.21',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Nominal yang diisi pelaksana — tiket, bill hotel, biaya penyelenggaraan — selalu tampil pada tabel rincian biaya agar dapat dikoreksi tim keuangan; baris yang tertinggal disalin kembali saat halamannya dibuka, dan baris dari berkas pelaksana tidak dapat dihapus.',
                    'Koreksi tim keuangan atas nominal pelaksana tidak lagi tertimpa ketika pelaksana menyimpan ulang berkasnya; baru berubah bila pelaksana mengubah nominal itu sendiri. Isian asli pelaksana tetap disebut di bawah baris yang dikoreksi.',
                ],
            ],
            [
                'versi' => '2.5.20',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Keuangan → Input Rincian Biaya: surat tugas pelaksana dapat dibuka langsung dari daftar usulan maupun dari halaman rincian biayanya, beserta nomornya.',
                ],
            ],
            [
                'versi' => '2.5.19',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Usulan dengan SPD berangkutan udara atau laut wajib berkategori luar kota: kategori dalam kota tertutup pada formulir dan ditolak saat dikirim, supaya pelaksana tidak keliru memilih SPD.',
                    'Usulan yang terkirim dua kali ditandai Duplikat pada daftar dan detail usulan, dan dapat dihapus sendiri oleh pelaksananya walau sudah diajukan — selama belum ada pembayaran dan belum ditandatangani PPK.',
                    'Perbaikan: notifikasi dan jejak audit yang panjang tidak lagi gagal disimpan di MySQL (galat 500), misalnya pengingat berkas, pemberitahuan bendahara, dan perubahan hak akses.',
                ],
            ],
            [
                'versi' => '2.5.18',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Perbaikan: hak akses Melihat seluruh usulan yang diberikan lewat Peran & Hak Akses kini benar-benar membuka daftar Usulan Perjadin semua pegawai — sebelumnya hanya super administrator yang melihatnya. Menyunting dan menghapus tetap hanya bagi pelaksana, pembuat, atau peserta usulannya.',
                    'Pengguna menerima notifikasi Hak akses Anda diperbarui setiap kali hak akses perannya diubah, menyebut apa saja yang ditambahkan dan dicabut beserta menunya.',
                ],
            ],
            [
                'versi' => '2.5.17',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Dokumen rincian biaya kini menuliskan perkalian uang harian walau hanya sehari, misalnya 1 OH × Rp 380.000.',
                    'Komponen yang dipindah tim keuangan ke kategori Transport Lokal tidak lagi hilang: ia masuk ke tabel Transport Lokal (Daftar Pengeluaran Riil), tampil pada Periksa Transport Lokal, dan dapat dihapus bila keliru. Kategori Transport Lokal juga dapat dipilih langsung saat menambah komponen.',
                    'Nota transport lokal yang diunggah pelaksana wajib bernominal agar masuk ke tabel Transport Lokal dan Periksa Transport Lokal; nota yang salah unggah dapat dihapus.',
                    'Bendahara menerima pemberitahuan Perjadin lengkap, segera dibayarkan begitu berkas pertanggungjawaban lengkap dan rincian biaya serta daftar pengeluaran riil ditandatangani PPK.',
                ],
            ],
            [
                'versi' => '2.5.16',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Perbaikan: menyunting baris rincian biaya ke kategori Biaya Penyelenggaraan tidak lagi berujung galat 500, dan nominal biaya penyelenggaraan dari berkas pelaksana kini benar-benar masuk ke rincian biaya. Nominal yang dulu tertinggal disusulkan sekali jalan oleh administrator.',
                ],
            ],
            [
                'versi' => '2.5.15',
                'tanggal' => '8 Oktober 2026',
                'butir' => [
                    'Rincian biaya tidak lagi mencatat satu komponen dua kali. Nominal tiket, bill hotel, atau biaya penyelenggaraan yang ditetapkan tim keuangan mengunci kolom nominal yang sama pada berkas pelaksana; sebaliknya komponen yang sudah diisi pelaksana beserta buktinya tidak dapat ditambahkan tim keuangan. Baris transport tim keuangan kini menyebut tiket yang diwakilinya — pergi, pulang, PP, atau bukan tiket pelaksana — dan transport lokal tidak lagi ditulis di rincian biaya karena berasal dari nota pelaksana.',
                    'Administrasi Sistem → Pemantauan Server (super administrator): ukuran aplikasi di peladen — berkas unggahan per jenis, basis data, log, pustaka — beserta pertumbuhan hariannya dan pemakaian kuota hosting.',
                    'Peringatan WhatsApp otomatis saat aplikasi tidak dapat diakses: situs, basis data, dan penyimpanan diperiksa tiap menit; nomor yang diatur menerima peringatan, pengingat selama belum pulih, dan kabar saat kembali normal, lewat gateway Fonnte atau Wablas.',
                ],
            ],
            [
                'versi' => '2.5.14',
                'tanggal' => '6 Oktober 2026',
                'butir' => [
                    'Contoh pengisian Registrasi Naskah Keluar di SRIKANDI untuk SPD dari PANGI — tipe form, pengirim, klasifikasi, Ambil Nomor, berkas, tujuan, verifikator, serta urutan penandatangan PPK lalu Direktur. Tampil di halaman SPD (Langkah berikutnya) dan di Panduan Penggunaan lengkap dengan gambar bertanda nomor.',
                ],
            ],
            [
                'versi' => '2.5.13',
                'tanggal' => '6 Oktober 2026',
                'butir' => [
                    'Rincian Saya — baik Daftar Riil Saya maupun Rincian Biaya Saya — kini menyebut pada Status Pembayaran siapa bendahara yang membayarkan uang muka, pelunasan, dan transport lokal, beserta tautan Lihat bukti bayar untuk membuka bukti transfernya.',
                    'Administrasi Sistem → Pengguna: nomor handphone (WhatsApp) dapat diisi saat menambah maupun menyunting pengguna.',
                ],
            ],
            [
                'versi' => '2.5.12',
                'tanggal' => '6 Oktober 2026',
                'butir' => [
                    'Menu Keuangan → Input Rincian Biaya kini menampilkan navigasi halaman: daftarnya dibagi sepuluh baris per halaman, dan sebelumnya baris setelah halaman pertama tidak dapat dibuka — "17 data" tetapi yang tampil hanya sepuluh. Halaman Persetujuan mendapat perbaikan yang sama.',
                ],
            ],
            [
                'versi' => '2.5.11',
                'tanggal' => '2 Oktober 2026',
                'butir' => [
                    'Halaman SPD kini memuat keterangan langkah sesudah SPD disimpan: unduh dokumen berformat SRIKANDI, masukkan ke SRIKANDI untuk ditandatangani PPK dan Direktur, unduh SPD bertanda tangan beserta nomor naskahnya, lalu buat usulan perjadin dengan dokumen dan nomor itu.',
                    'Kolom nomor SPD pada formulir usulan tidak lagi terisi nomor internal PJ-… dari PANGI, dan keterangannya mengingatkan bahwa yang diisi nomor naskah dari SRIKANDI.',
                ],
            ],
            [
                'versi' => '2.5.10',
                'tanggal' => '2 Oktober 2026',
                'butir' => [
                    'Jejak Audit kini bersubmenu. Semua Aktivitas memuat seluruh jejak seperti sebelumnya; Penghapusan Usulan & SPD memuat usulan perjadin dan Surat Perjalanan Dinas yang dihapus lengkap dengan isinya — nomor, pelaksana, tujuan, tanggal perjalanan, maksud — beserta siapa yang menghapus, kapan, dan dari alamat mana. Penghapusan SPD sebelumnya tidak tercatat sama sekali.',
                    'Submenu Nomor Surat pada Jejak Audit mendaftar nomor SPD aplikasi, nomor SPD bertanda tangan, dan nomor surat tugas per bulan dan tahun, lengkap dengan pelaksana dan tujuannya. Daftarnya dapat diekspor ke Excel untuk dicocokkan arsiparis dengan buku agenda surat keluar.',
                    'Dashboard Eksekutif kini bersubmenu: Dashboard Utama berisi angka-angka seperti sebelumnya, dan News Feed menyajikan siapa yang akan dan sedang melakukan perjalanan dinas serta tindak lanjut yang dijadwalkan, dalam bentuk lini masa seperti media sosial.',
                ],
            ],
            [
                'versi' => '2.5.9',
                'tanggal' => '1 Oktober 2026',
                'butir' => [
                    'Sanggahan pelaksana atas rincian biaya kini tampil di halaman Keuangan — tempat nominalnya diperbaiki — lengkap dengan alasan dan waktunya, beserta tombol Kirim Ulang ke Pelaksana. Sebelumnya berkas yang rinciannya disanggah tidak dapat dikirim ulang.',
                    'Mengirim ulang hanya membuka dokumen yang disanggah: dokumen yang sudah ditandatangani pelaksana tetap berlaku beserta kode konfirmasinya, dan pemberitahuannya hanya menyebut dokumen yang perlu disikapi lagi.',
                    'Halaman Detail Keuangan kini menampilkan pesan hasil tiap tindakan — komponen tersimpan, berkas terkirim, atau alasan tertahan — dan menyebut tujuan perjalanan pada kepalanya. Pemberitahuan sanggahan kepada tim keuangan langsung membuka halaman ini.',
                ],
            ],
            [
                'versi' => '2.5.8',
                'tanggal' => '1 Oktober 2026',
                'butir' => [
                    'Panduan bergambar: setiap langkah membuat SPD sampai mengajukan perjadin kini disertai tangkapan layar bertanda nomor, baik di menu Panduan Penggunaan maupun di buku panduan. Ketuk gambar untuk memperbesarnya.',
                    'Buku panduan bersampul baru dengan warna Kemenkes dan dapat diunduh dalam format PDF maupun Word.',
                    'Kotak konfirmasi sebelum mengirim pengajuan perjadin kini benar-benar menampilkan tujuan, tanggal, dan nomor SPD yang diisi — sebelumnya selalu tertulis "—".',
                ],
            ],
            [
                'versi' => '2.5.7',
                'tanggal' => '1 Oktober 2026',
                'butir' => [
                    'Supervisi kerja praktek / magang kini dibedakan menurut tujuannya: supervisi ke luar kota tetap menerbitkan Surat Perjalanan Dinas — berkas SPD bertanda tangan beserta nomornya wajib dilampirkan — sedangkan supervisi di dalam kota cukup surat tugas seperti sebelumnya.',
                    'Isian SPD pada formulir usulan muncul dan menghilang sendiri begitu kategori supervisi dipilih, jadi tidak ada kolom yang terisi percuma. Checklist kelengkapan dan formulir Dokumen Perdin ikut menyesuaikan: SPPD bertanda tangan hanya ditagih pada perjalanan yang memang menerbitkan SPD.',
                ],
            ],
            [
                'versi' => '2.5.6',
                'tanggal' => '1 Oktober 2026',
                'butir' => [
                    'Berkas Pertanggungjawaban (submenu Administrasi Sistem): berkas apa saja yang ditagih sesudah perjalanan kini dipilih sendiri untuk tiap jalur pengajuan — dalam kota, luar kota, dan supervisi kerja praktek / magang. Pilihannya meliputi SPPD bertanda tangan, tiket, nota transport lokal, bill hotel, kuitansi penyelenggara/hotel, bukti biaya penyelenggaraan, dan laporan perjalanan dinas.',
                    'Centangan itu berlaku serentak pada formulir Dokumen Perdin, checklist kelengkapan, dan penagihan ke pelaksana; berkas yang dicabut tidak lagi menahan penyelesaian berkas. Jalur yang belum diubah menagih berkas yang sama persis seperti sebelumnya, dan tiap jalur dapat dikembalikan ke bawaan dengan satu tombol.',
                ],
            ],
            [
                'versi' => '2.5.5',
                'tanggal' => '1 Oktober 2026',
                'butir' => [
                    'Pengajuan perjadin kini dimulai dengan satu pertanyaan: perjalanan dalam kota, luar kota, atau supervisi kerja praktek / magang. Jalur yang dipilih menyaring kategori perjalanan dinas dari Master Data, sehingga yang tampil pada formulir hanya kategori yang memang berlaku.',
                    'Jalur supervisi kerja praktek / magang tidak memakai Surat Perjalanan Dinas: cukup surat tugas beserta nomornya, dan jenis kegiatannya terisi sendiri. Kategori "Supervisi Dalam Kota" dan "Supervisi Luar Kota" ditambahkan ke Master Data.',
                    'Kaki halaman SPD mengikuti cetakan terbaru: lambang Garuda Sertifikasi Indonesia dilepas — tinggal KAN dan BLU — dan alamat verifikasi tanda tangan elektronik menjadi tte.komdigi.go.id.',
                    'Butir V pada lembar kedua SPD tidak lagi memuat penanda QR SRIKANDI; ruang tanda tangannya tetap disediakan. Nama PPK dan Direktur tidak lagi digarisbawahi.',
                    'Daftar Usulan Perjadin: kolom "Pemohon" yang selalu kosong diganti "Pelaksana" dan menyebut pelaksana perjalanan beserta jumlah rekan seperjalanannya.',
                ],
            ],
            [
                'versi' => '2.5.4',
                'tanggal' => '30 September 2026',
                'butir' => [
                    'Dokumen Output (submenu Administrasi Sistem): tampilan empat dokumen cetak — Perjadin (SPD), Rincian Biaya, Daftar Pengeluaran Riil, dan Daftar Nominatif — diatur dari aplikasi. Ukuran kertas dan ukuran huruf dapat diubah, elemen seperti kop surat, tabel pengikut, baris terbilang, blok tanda tangan, dan QR dapat disembunyikan, serta teks baku seperti judul dokumen dan paragraf pernyataan dapat ditulis ulang. Tiap perubahan tercatat pada jejak audit dan dapat dikembalikan ke bawaan dengan satu tombol.',
                    'Dokumen Output memuat pratinjau: dokumen contoh tampil di bawah formulir dan ikut berubah begitu centangan elemen diubah — tanpa perlu menyimpan lebih dulu, tanpa menyentuh data sungguhan. Tersedia pula tombol membuka pratinjau di tab baru dan mengunduh contoh PDF-nya.',
                    'Penanda tanda tangan elektronik SRIKANDI dan nomor naskah sengaja tidak dapat dimatikan, supaya surat tetap dapat ditandatangani secara elektronik.',
                ],
            ],
            [
                'versi' => '2.5.3',
                'tanggal' => '21 September 2026',
                'butir' => [
                    'Formulir SPD: surat tugas dilampirkan sejak SPD dibuat — nomor dan berkasnya diisi pada kartu Surat Tugas (tidak wajib). Usulan perjadin yang memilih SPD itu mengambil keduanya secara otomatis; berkas surat tugas tidak diunggah lagi dan nomornya tidak disalin ulang.',
                    'Formulir SPD: kolom Akun pembebanan dihapus — akun diisi PPK saat verifikasi dan tanda tangan, bukan oleh pembuat SPD.',
                    'Berkas pertanggungjawaban: unggahan dan ceklist "Kuitansi" kini disebut "Kuitansi penyelenggara / hotel" agar jelas kuitansi mana yang dimaksud — dari penyelenggara kegiatan atau dari hotel tempat menginap.',
                    'Jadwal Perjalanan kini bermenu: Jadwal Keberangkatan, Peta Dalam Kota & Sekitarnya, dan Peta Luar Kota. Peta memperlihatkan kota-kota tujuan pegawai beserta jumlah perjalanan dan orang yang berangkat; klik penanda untuk rinciannya, saring per tahun. Dalam kota & sekitarnya berarti tujuan di Sulawesi Utara. Kota-kota besar sudah dikenal sistem; kota lain diberi lintang-bujur pada Master Data → Lokasi Tujuan.',
                    'Peran baru Mahasiswa, dapat dipilih saat membuat akun atau diimpor lewat kolom role bernilai "mahasiswa".',
                    'Peran & Hak Akses (submenu Administrasi Sistem, super administrator): menambah peran baru dan mengatur, per menu, kemampuan yang boleh dilihat, diubah, dan dihapus tiap peran — termasuk peran bawaan. Perubahan berlaku seketika dan tercatat pada jejak audit; Super Administrator selalu memegang seluruh akses. Hak menghapus master data dan menghapus pengguna kini kemampuan tersendiri, sehingga dapat diberikan atau dicabut terpisah dari hak mengelolanya.',
                    'Pegawai eksternal, mahasiswa, dan outsourcing: modul perjalanan dinasnya dikembangkan terpisah. Sementara itu, setelah masuk mereka melihat halaman pemberitahuan "fitur masih dikembangkan" dengan tombol keluar; menu lain belum dapat dibuka.',
                ],
            ],
            [
                'versi' => '2.5.2',
                'tanggal' => '15 September 2026',
                'butir' => [
                    'Impor pengguna: sel yang kosong pada berkas CSV tidak lagi menghapus surel, nomor HP, dan rekening yang sudah diisi pengguna, sehingga data pegawai aman diimpor ulang untuk menambah orang. Wakil Direktur II ditambahkan ke data pegawai bawaan.',
                    'Integrasi Data (submenu Administrasi Sistem): token API dashboard eksekutif dibuat, diganti, atau dicabut langsung dari aplikasi — tanpa menyunting .env di server — lengkap dengan alamat dan jalur API-nya; token yang berlaku dapat ditampilkan dan disalin.',
                    'Integrasi Data: pemantauan setiap permintaan API yang masuk (alamat IP, jalur, token yang dibawa — hanya ujung-ujungnya — diterima atau ditolak) dan pengiriman data dashboard eksekutif terjadwal (tiap jam, harian, atau mingguan) ke aplikasi tujuan memakai token, lengkap dengan riwayat pengirimannya dan tombol Kirim Sekarang.',
                    'Pengaturan Sistem: kunci API Anthropic untuk Wawasan AI dan agen tanya-jawab Dashboard Eksekutif dipasang, diganti, atau dihapus dari aplikasi — tersimpan terenkripsi, tanpa menyunting .env di server.',
                    'Membuka atau mengunci tanggal dikeluarkan SPD serta mengelola token API dan kunci AI kini melalui kotak konfirmasi.',
                    'Nomor SPD pada usulan bersifat unik: nomor yang sudah dipakai usulan lain — milik siapa pun — ditolak saat mengajukan maupun menyunting, dengan pesan yang menyebut usulan mana yang memakainya, sehingga tidak ada usulan ganda atas SPD yang sama.',
                    'Usulan perjadin tidak lagi mensyaratkan SPD dibuat lewat aplikasi lebih dulu: formulir usulan terbuka bagi semua, memilih SPD dari aplikasi hanya untuk mengisi otomatis. SPD bertanda tangan beserta nomornya tetap wajib diunggah sebagai dasar persetujuan PPK.',
                    'Rincian Saya: tiap berkas memuat panel Pemantauan Berkas — status seluruh tanda tangan (tim keuangan, pelaksana, PPK, konfirmasi Direktur, daftar nominatif) dan status pembayaran (uang muka, pelunasan, transport lokal) beserta tanggal dan kodenya.',
                ],
            ],
            [
                'versi' => '2.5.1',
                'tanggal' => '15 September 2026',
                'butir' => [
                    'Administrasi Sistem: super administrator dapat membuka atau mengunci tanggal dikeluarkan SPD bagi seluruh peran untuk kasus tanggal mundur (backdate); bawaannya terkunci, tiap perubahan tercatat pada jejak audit.',
                    'Administrasi Sistem dipecah menjadi tiga halaman bermenu — Pengguna, Impor & Ekspor, Pengaturan Sistem — dengan submenu di sidebar dan navigasi di kepala halaman.',
                ],
            ],
            [
                'versi' => '2.5',
                'tanggal' => '12 September 2026',
                'butir' => [
                    'Usulan wajib melampirkan SPD bertanda tangan beserta nomornya; persetujuan PPK tercatat otomatis dari SPD itu. Pengajuan berkelompok dan tombol Salin dihapus — satu orang satu usulan.',
                    'Laporan perjalanan dinas: tempat kegiatan per hari, tersimpan sekaligus terkirim ke pimpinan, dikonfirmasi dan ditandatangani Direktur lewat QR, atau dikembalikan untuk direvisi. Menu Laporan Perjadin bagi pimpinan.',
                    'Pelunasan hanya menunggu konfirmasi laporan oleh Direktur — bukan tanda tangan PPK maupun daftar nominatif.',
                    'Daftar nominatif terbit per surat tugas begitu satu pelaksana tuntas; yang belum tercantum disebut dan bertambah sendiri. List Daftar Nominatif memuat seluruh daftar beserta status tanda tangan tiap pelaksana; cetakannya memuat QR PPK.',
                    'Berkas terkirim otomatis ke pelaksana begitu seluruh validasi rampung, berstatus Sudah Dicek Tim Keuangan; tombol tanda tangan pelaksana tetap ada setelah masa sanggah; validasi dan pencabutannya dikonfirmasi lewat popup.',
                    'Biaya penyelenggaraan: ditanya ada atau tidak; bila ada, nominal, bukti bayar, dan nomor invoice, tercetak di bawah uang penginapan.',
                    'Tiket membawa invoice; nota transport lokal hanya untuk ruas bernominal (transport lokal boleh tidak ada); dalam kota satu baris transport lokal; checklist kelengkapan mengikuti formulir.',
                    'Verifikasi PPK dan Arsip Rincian Lengkap dikelompokkan per bulan dan tahun; arsip tidak lagi menunggu nominatif; menu Keuangan bagi PPK dalam mode lihat saja.',
                    'Pembatalan penggantian transport lokal dengan alasan, tercatat pada riwayat.',
                    'Seluruh dokumen cetak berukuran folio dan dirapikan: usulan sebagai dokumen resmi berkop, rincian satu halaman, tanggal berbahasa Indonesia, WITA.',
                    'Pelacakan berkas: sepuluh tonggak dengan lama hari antar tahap; versi aplikasi tertera di halaman masuk.',
                ],
            ],
            [
                'versi' => '2.4',
                'tanggal' => '5 September 2026',
                'butir' => [
                    'Rilis dasar versi 2: Surat Perjalanan Dinas dengan nomor unik dan pengikut, pertanggungjawaban per pelaksana (tiket, nota, bill hotel, laporan), rincian biaya dan daftar riil dengan dua jalur tanda tangan, daftar nominatif, pembayaran beserta riwayatnya, pelacakan berkas, dan saluran bantuan.',
                ],
            ],
        ];
    }

    /**
     * Pekerjaan yang menjadi tanggung jawab peran ini di luar bepergian
     * sendiri, beserta bagian panduan yang menjelaskannya.
     *
     * @return list<array{tugas: string, bagian: string}>
     */
    private function tugasPeran(User $pengguna): array
    {
        $tugas = [
            [Kemampuan::MemvalidasiBiaya, 'Menyusun rincian biaya dan menyatakan nominalnya benar', 'keuangan'],
            [Kemampuan::MenandatanganiDaftarRiil, 'Menandatangani rincian biaya, daftar riil, dan daftar nominatif', 'ppk'],
            [Kemampuan::MencatatPembayaran, 'Mencairkan uang muka, pelunasan, dan penggantian transport lokal', 'bendahara'],
            [Kemampuan::MengonfirmasiLaporanPerjadin, 'Mengonfirmasi dan menandatangani laporan perjalanan dinas, atau mengembalikannya untuk direvisi', 'laporan-pimpinan'],
            [Kemampuan::MelihatLaporan, 'Memantau realisasi anggaran dan menarik rekap perjalanan dinas', 'pemantauan'],
            [Kemampuan::MengelolaPengguna, 'Mengelola akun pengguna dan penempatannya', 'administrasi'],
            [Kemampuan::MengelolaMasterData, 'Mengelola master data: kategori, komponen biaya, dan tujuan', 'administrasi'],
        ];

        return collect($tugas)
            ->filter(fn (array $baris) => $pengguna->punyaKemampuan($baris[0]))
            ->map(fn (array $baris) => ['tugas' => $baris[1], 'bagian' => $baris[2]])
            ->values()
            ->all();
    }
}
