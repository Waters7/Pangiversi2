
Dokumen ini memuat kebutuhan bisnis, kebutuhan fungsional, kebutuhan nonfungsional, rancangan data, rancangan ERD, dan rancangan UI/UX untuk pengembangan aplikasi digital administrasi perjalanan dinas di Poltekkes Kemenkes Manado.
Atribut	Nilai
Nama Aplikasi	PANGI (Perdin Aman, Nggak Drama, Gampang, Inovatif)
Organisasi	Poltekkes Kemenkes Manado
Jenis Dokumen	Software Requirements Specification (SRS)
Versi	3.0
Tanggal	27 Maret 2026
Status	Draft final untuk reviu pengguna, pengembang, dan pimpinan

Ringkasan perubahan versi 3.0
Versi ini menyatukan isi SRS v2.0 dengan rancangan ERD inti, catatan desain database implementatif, dan katalog rancangan UI/UX layar utama. Dokumen ini disiapkan agar dapat dipakai sebagai acuan analisis, desain, dan implementasi fase awal aplikasi PANGI.
Disusun untuk kebutuhan analisis, desain sistem, pengembangan, pengujian, dan penerimaan solusi.

Daftar Isi
Bagian	Uraian ringkas
1. Pendahuluan	Latar belakang, tujuan, ruang lingkup, definisi, dan referensi dokumen
2. Deskripsi Umum Sistem	Sasaran bisnis, aktor, alur target, dan prinsip solusi
3. Kebutuhan Fungsional	Use case inti, modul keuangan, kebutuhan per modul, dan status proses
4. Kebutuhan Nonfungsional	Target performa, keamanan, audit, kompatibilitas, dan kepatuhan
5. Aturan Bisnis, Data, dan Arsitektur	Aturan bisnis, entitas data, komponen logis, dan menu sistem
6. Rancangan ERD	ERD inti aplikasi dan catatan desain database implementatif
7. Rancangan UI/UX	Prinsip desain, navigasi per peran, katalog layar, dan mockup layar utama
8. Kriteria Penerimaan, Roadmap, dan Risiko	Acceptance criteria, tahap pengembangan, dan catatan implementasi


1. Pendahuluan
1.1 Latar Belakang
Proses administrasi perjalanan dinas di lingkungan Poltekkes masih melibatkan banyak pihak, dokumen fisik, dan titik pemeriksaan manual. Kondisi ini berpotensi menimbulkan keterlambatan, salah input data, duplikasi berkas, kurangnya visibilitas status usulan, dan kesulitan penelusuran pada tahap pertanggungjawaban. PANGI dirancang untuk menata alur tersebut menjadi satu proses digital yang terdokumentasi dan dapat dipantau dari awal hingga arsip.
1.2 Tujuan Dokumen
Menjelaskan ruang lingkup dan tujuan aplikasi PANGI.
Menjabarkan kebutuhan fungsional dan nonfungsional secara terstruktur.
Menjadi referensi bersama bagi pemilik proses, pengembang, penguji, dan pimpinan.
Menetapkan batasan implementasi serta kriteria penerimaan sistem.
Menyediakan rancangan ERD dan rancangan UI/UX sebagai dasar desain tahap implementasi awal.
1.3 Ruang Lingkup Sistem
PANGI adalah aplikasi administrasi perjalanan dinas yang mendukung proses pengajuan usulan, persetujuan berjenjang, verifikasi dokumen, pemeriksaan anggaran, penerbitan dokumen penugasan, pencatatan pembayaran, pelaporan hasil perjalanan, verifikasi pertanggungjawaban, dan pengarsipan digital. Sistem difokuskan untuk proses internal Poltekkes Kemenkes Manado dan belum mencakup integrasi pembayaran bank secara otomatis.
1.4 Definisi dan Singkatan
Istilah	Definisi
PANGI	Perdin Aman, Nggak Drama, Gampang, Inovatif.
SPPD	Surat Perintah Perjalanan Dinas.
Surat Tugas	Dokumen penugasan resmi untuk perjalanan dinas.
PPK	Pejabat Pembuat Komitmen / pihak yang memeriksa kegiatan dan anggaran.
Unit Pengusul	Pegawai atau unit kerja yang mengajukan perjalanan dinas.
LPJ	Laporan pertanggungjawaban perjalanan dinas beserta bukti pendukung.
Pertanggungjawaban	Laporan hasil perjalanan dan bukti biaya sesuai ketentuan.
1.5 Referensi Dokumen
Dokumen SRS Aplikasi PANGI versi 2.0.
SOP perjalanan dinas internal yang menjadi dasar proses bisnis existing.
Alur keuangan perjalanan dinas yang dipakai sebagai acuan modul keuangan dan LPJ.
2. Deskripsi Umum Sistem
2.1 Sasaran Bisnis
Mempercepat siklus pengajuan dan persetujuan perjalanan dinas.
Meningkatkan akurasi dokumen dan kelengkapan data.
Menyediakan rekam jejak status dan histori tindakan setiap aktor.
Memudahkan verifikasi pertanggungjawaban dan pengarsipan dokumen.
Menyediakan data monitoring untuk pimpinan dan unit pengelola.
2.2 Aktor Pengguna dan Hak Akses Ringkas
Aktor	Peran Utama	Hak Akses Ringkas
Pegawai / Unit Pengusul	Membuat usulan perjalanan dinas dan menyerahkan laporan hasil	Buat, ubah, dan lihat status usulan milik sendiri
Atasan Langsung	Memeriksa urgensi, tujuan, dan kesesuaian penugasan	Setujui / tolak usulan dengan catatan
Subbag Kepegawaian / SDM	Verifikasi administrasi dan menyiapkan dokumen penugasan	Verifikasi, minta revisi, dan menyiapkan konsep dokumen
PPK	Memeriksa kegiatan dan anggaran	Validasi anggaran dan beri catatan perbaikan
Direktur	Menyetujui penugasan akhir	Setujui / tolak penugasan
Bendahara / Keuangan	Memproses biaya perjalanan dan pertanggungjawaban	Catat pembayaran, verifikasi bukti biaya, dan arsip
Administrator Sistem	Mengelola referensi dan pengguna	Kelola master data, hak akses, dan parameter sistem
2.3 Ringkasan Alur Target (To-Be)
Pengusul membuat draft usulan perjalanan dinas dan melengkapinya dengan data dasar serta lampiran awal.
Atasan langsung memberi keputusan awal berupa persetujuan atau penolakan.
SDM memverifikasi aspek administrasi dan menyiapkan dokumen penugasan.
PPK memeriksa kesesuaian kegiatan dan anggaran, lalu memberi rekomendasi.
Direktur memberi persetujuan final sehingga dokumen Surat Tugas dan SPPD dapat diterbitkan.
Bendahara menyusun rincian biaya, mencatat pembayaran uang muka, dan menyiapkan monitoring keuangan.
Setelah perjalanan selesai, pelaksana mengirim LPJ, laporan hasil, dan bukti biaya.
Keuangan memverifikasi LPJ, mencatat pembayaran sisa, lalu menutup proses dan mengarsipkan dokumen.
2.4 Prinsip Solusi Target
Satu data usulan untuk seluruh siklus perjalanan dinas.
Setiap tahapan memiliki status, timestamp, dan jejak audit.
Dokumen pendukung tersimpan digital dan tervalidasi kelengkapannya.
Persetujuan dan penolakan disertai catatan alasan.
Dashboard memudahkan monitoring backlog, SLA, dan progress proses.
3. Kebutuhan Fungsional
Kebutuhan fungsional dirumuskan dalam bentuk capability sistem yang wajib tersedia pada versi awal aplikasi PANGI. Requirement dikelompokkan per domain agar lebih mudah dibaca dan dilacak saat desain maupun pengujian.
3.1 Use Case Inti
Kode	Use Case	Aktor Utama	Output Utama
UC-01	Pengusul membuat dan mengirim usulan perjalanan dinas	Pegawai / Unit Pengusul	Draft usulan tersimpan atau dikirim ke atasan
UC-02	Atasan memutuskan setuju / tolak usulan	Atasan Langsung	Keputusan awal tercatat dengan catatan
UC-03	SDM memverifikasi administrasi dan menyiapkan dokumen	SDM / Kepegawaian	Usulan valid untuk pemeriksaan anggaran
UC-04	PPK memeriksa anggaran dan memberi rekomendasi	PPK	Usulan diteruskan atau dikembalikan
UC-05	Direktur menyetujui atau menolak penugasan	Direktur	Status penugasan final
UC-06	Bendahara memproses pembayaran biaya perjalanan	Bendahara / Keuangan	Pembayaran tercatat
UC-07	Pengusul mengirim laporan hasil dan bukti pertanggungjawaban	Pegawai / Pelaksana	LPJ masuk antrian verifikasi
UC-08	Keuangan memverifikasi LPJ dan mengarsipkan dokumen	Keuangan	Status selesai dan arsip lengkap
3.2 Use Case Modul Keuangan
ID	Fitur	Aktor Utama	Output Utama
KEU-01	Penerimaan Dokumen Awal	Pegawai / Pelaksana; Bendahara / Keuangan	Surat Tugas dan SPPD diterima sebagai dasar proses biaya
KEU-02	Penyusunan Rincian Perjadin	Bendahara / Keuangan	Rincian biaya disusun sesuai komponen dan ketentuan SBM / at cost
KEU-03	Pembayaran Uang Muka Perjadin	Bendahara / Keuangan; Pegawai / Pelaksana	Uang muka dibayar berdasarkan rincian yang sudah final
KEU-04	Pertanggungjawaban Perjadin	Pegawai / Pelaksana; Bendahara / Keuangan	Dokumen bukti, laporan, dan lampiran LPJ diunggah
KEU-05	Pembayaran Sisa Perjadin	Bendahara / Keuangan; Pegawai / Pelaksana	Sisa pembayaran dilakukan setelah LPJ valid
KEU-06	Daftar Nominatif	Pengelola Keuangan / Bendahara	Rekap perjalanan dinas tersedia sebagai output akhir
A. Autentikasi, pengguna, dan master data
Kode	Modul	Deskripsi Kebutuhan
FR-01	Autentikasi & Otorisasi	Sistem harus menyediakan login berbasis akun pengguna dan kontrol akses sesuai peran (pegawai, atasan, SDM, PPK, direktur, bendahara, admin).
FR-02	Profil Pengguna	Sistem harus menyimpan data pegawai, unit kerja, jabatan, atasan langsung, dan status kepegawaian yang dibutuhkan dalam proses pengajuan.
FR-20	Master Data	Admin dapat mengelola data referensi seperti unit kerja, lokasi tujuan, jenis kegiatan, komponen biaya, template dokumen, dan tahun anggaran.
B. Usulan, persetujuan, dan dokumen penugasan
Kode	Modul	Deskripsi Kebutuhan
FR-03	Pembuatan Usulan	Pengusul dapat membuat usulan perjalanan dinas dengan mengisi tujuan, lokasi, tanggal, peserta, dasar penugasan, estimasi biaya, dan lampiran pendukung.
FR-04	Draft & Submit	Usulan dapat disimpan sebagai draft dan baru masuk proses setelah dikirim secara resmi.
FR-05	Persetujuan Atasan	Atasan dapat menyetujui atau menolak usulan. Penolakan wajib menyertakan alasan dan sistem mengirim notifikasi kepada pengusul.
FR-06	Verifikasi Administrasi	SDM dapat memeriksa kelengkapan administrasi, meminta revisi, serta menyiapkan konsep Surat Tugas dan SPPD berdasarkan data usulan.
FR-07	Pemeriksaan Anggaran	PPK dapat menilai kesesuaian kegiatan dan anggaran, memberi catatan revisi, atau meneruskan untuk persetujuan direktur.
FR-08	Persetujuan Direktur	Direktur dapat menyetujui atau menolak penugasan. Persetujuan menghasilkan status penugasan final.
FR-09	Penerbitan Dokumen	Sistem harus dapat menghasilkan dokumen Surat Tugas dan SPPD berdasarkan template resmi yang dapat diunduh atau dicetak.
C. Keuangan, pembayaran, dan pertanggungjawaban
Kode	Modul	Deskripsi Kebutuhan
FR-10	Pencatatan Pembayaran	Bendahara dapat mencatat komponen biaya perjalanan, tanggal pembayaran, nomor bukti, dan status penyaluran dana.
FR-11	Pelaporan Perjalanan	Setelah perjalanan selesai, pengusul dapat mengunggah laporan hasil, bukti biaya, tiket, kuitansi, dan dokumen pertanggungjawaban lainnya.
FR-12	Verifikasi Pertanggungjawaban	Bendahara / keuangan dapat memeriksa kelengkapan dan kesesuaian bukti, lalu menerima, mengembalikan untuk dilengkapi, atau menutup proses.
D. Monitoring, notifikasi, laporan, dan arsip
Kode	Modul	Deskripsi Kebutuhan
FR-13	Manajemen Status	Sistem harus menampilkan status usulan secara jelas, misalnya Draft, Menunggu Atasan, Revisi SDM, Menunggu PPK, Menunggu Direktur, Dibayar, Dalam Perjalanan, Menunggu LPJ, Perlu Perbaikan, Selesai, dan Ditolak.
FR-14	Jejak Audit	Sistem harus menyimpan histori tindakan: siapa, kapan, aksi apa, dan catatan yang diberikan di setiap tahap.
FR-15	Notifikasi	Sistem harus mengirim notifikasi dalam aplikasi untuk aksi penting seperti submit, persetujuan, penolakan, permintaan revisi, dan kelengkapan LPJ.
FR-16	Pencarian & Filter	Pengguna dapat mencari usulan berdasarkan nomor, nama pegawai, unit, tujuan, rentang tanggal, status, dan tahun anggaran.
FR-17	Dashboard Monitoring	Sistem menyediakan ringkasan jumlah usulan per status, keterlambatan proses, penggunaan anggaran, dan dokumen yang belum lengkap.
FR-18	Laporan	Sistem dapat menghasilkan laporan periodik perjalanan dinas per unit, per pegawai, per status, dan per periode anggaran.
FR-19	Arsip Digital	Sistem harus menyimpan seluruh dokumen dan lampiran dalam arsip digital yang dapat ditelusuri kembali berdasarkan metadata usulan.
3.3 Status Proses Utama
Status	Deskripsi
Draft	Usulan masih disusun oleh pengusul dan belum diajukan.
Menunggu Atasan	Usulan telah dikirim dan menunggu keputusan atasan langsung.
Revisi SDM	SDM meminta perbaikan kelengkapan administrasi.
Menunggu PPK	Usulan lulus verifikasi SDM dan menunggu pemeriksaan anggaran.
Menunggu Direktur	Usulan lulus pemeriksaan PPK dan menunggu persetujuan akhir.
Dokumen Terbit	Surat Tugas dan SPPD sudah dihasilkan.
Dibayar / Uang Muka	Pembayaran awal telah dicatat oleh bendahara.
Dalam Perjalanan	Pelaksanaan perjalanan dinas sedang berlangsung atau telah berjalan.
Menunggu LPJ	Pelaksana wajib mengunggah laporan dan bukti pertanggungjawaban.
Perlu Perbaikan	Dokumen LPJ atau data keuangan harus diperbaiki.
Selesai	Seluruh tahapan, verifikasi, dan arsip sudah lengkap.
Ditolak	Usulan ditolak pada salah satu level persetujuan.
4. Kebutuhan Nonfungsional
Kebutuhan nonfungsional berikut menjadi batas mutu minimum agar sistem layak digunakan sebagai aplikasi operasional institusi. Area utama yang diperhatikan adalah performa, keamanan, auditabilitas, kemudahan penggunaan, dan kepatuhan.
Kode	Kategori	Kebutuhan
NFR-01	Kinerja	Waktu respon halaman utama dan daftar data maksimal 3 detik pada kondisi normal untuk 100 pengguna aktif.
NFR-02	Keamanan	Password disimpan dalam bentuk hash yang aman; akses data dibatasi sesuai peran; dokumen sensitif hanya dapat diakses pengguna berwenang.
NFR-03	Auditabilitas	Setiap perubahan status dan data penting harus tercatat dalam audit trail yang tidak dapat diubah pengguna biasa.
NFR-04	Ketersediaan	Aplikasi ditargetkan tersedia minimal 95% pada jam kerja operasional.
NFR-05	Usability	Antarmuka harus responsif, konsisten, dan mudah dipahami oleh pengguna administrasi non-teknis.
NFR-06	Kompatibilitas	Aplikasi berjalan pada browser modern desktop dan mobile (Chrome, Edge, Firefox) tanpa instalasi khusus.
NFR-07	Pemeliharaan	Struktur kode modular dan terdokumentasi agar mudah dikembangkan untuk fitur lanjutan.
NFR-08	Backup	Database dan dokumen lampiran harus memiliki mekanisme backup berkala.
NFR-09	Integritas Data	Nomor usulan dan nomor dokumen harus unik; validasi wajib dilakukan sebelum status naik ke tahap berikutnya.
NFR-10	Kepatuhan	Format dokumen, bukti biaya, dan alur persetujuan harus menyesuaikan ketentuan internal institusi dan regulasi perjalanan dinas yang berlaku.
5. Aturan Bisnis, Data, dan Arsitektur
5.1 Aturan Bisnis Utama
Kode	Aturan Bisnis
BR-01	Usulan tidak dapat diajukan tanpa data tanggal, tujuan, dasar penugasan, dan lampiran minimal yang dipersyaratkan.
BR-02	Setiap tahap persetujuan hanya dapat dilakukan oleh pengguna dengan role yang sesuai.
BR-03	Penolakan wajib menyertakan alasan yang tersimpan permanen dalam histori.
BR-04	Dokumen Surat Tugas / SPPD hanya dapat diterbitkan setelah usulan lolos persetujuan direktur.
BR-05	Status Selesai hanya diberikan jika laporan hasil dan bukti pertanggungjawaban telah diverifikasi lengkap.
BR-06	Nomor dokumen harus unik per tahun anggaran / format yang ditetapkan.
BR-07	Lampiran yang diunggah harus dibatasi tipe file dan ukuran maksimal sesuai kebijakan admin.
BR-08	Jika ada permintaan revisi, pengusul wajib memperbarui data / lampiran sebelum proses dapat dilanjutkan.
5.2 Entitas Data Utama
Entitas	Atribut Kunci	Keterangan
User	user_id, nama, NIP, email, unit_id, role_id	Akun pengguna sistem
Unit Kerja	unit_id, nama_unit	Referensi unit, fakultas, atau bagian
Usulan Perjalanan	usulan_id, nomor_usulan, pengusul_id, tujuan, tanggal_mulai, tanggal_selesai, status	Data induk proses perjalanan dinas
Peserta Perjalanan	peserta_id, usulan_id, pegawai_id, peran_peserta	Daftar orang yang mengikuti perjalanan
Lampiran	lampiran_id, usulan_id, jenis_dokumen, file_path, uploaded_by	Dokumen pendukung dan bukti
Persetujuan	approval_id, usulan_id, approver_id, level, keputusan, catatan, waktu_keputusan	Riwayat persetujuan atau penolakan
Dokumen Penugasan	dokumen_id, usulan_id, nomor_surat_tugas, nomor_sppd, file_output	Dokumen resmi hasil generate sistem
Pembayaran	pembayaran_id, usulan_id, total_biaya, tanggal_bayar, nomor_bukti, status_bayar	Catatan biaya dan pembayaran
Laporan Perjalanan	laporan_id, usulan_id, ringkasan_hasil, tanggal_submit	Laporan hasil perjalanan
Audit Log	log_id, usulan_id, actor_id, aksi, timestamp, detail	Jejak audit tindakan pengguna
5.3 Komponen Logis Solusi
Komponen	Deskripsi
Frontend Web	Form usulan, daftar status, dashboard, notifikasi, manajemen master data, dan halaman laporan.
Backend / API	Workflow bisnis, validasi, persetujuan, generate dokumen, pengiriman notifikasi, dan layanan laporan.
Database Relasional	Penyimpanan data transaksi, master data, audit log, dan metadata dokumen.
File Storage	Penyimpanan lampiran, template dokumen, dan hasil generate dokumen resmi.
5.4 Rancangan Menu Minimum
Menu	Submenu / Fitur
Dashboard	Ringkasan status, notifikasi, statistik proses
Usulan Perjalanan	Buat usulan, daftar usulan, detail usulan, riwayat
Persetujuan	Antrian persetujuan atasan, PPK, direktur
Dokumen	Surat Tugas, SPPD, unduh atau cetak template
Keuangan	Pembayaran, verifikasi LPJ, monitoring pertanggungjawaban
Laporan	Rekap per periode, unit, pegawai, status
Master Data	Pegawai, unit, biaya, template, referensi lokasi, tahun anggaran
Administrasi Sistem	Pengguna, role, parameter aplikasi, backup dan log
Catatan implementasi arsitektur
Implementasi awal direkomendasikan menggunakan arsitektur aplikasi web tiga lapis: lapisan presentasi (UI web), lapisan logika aplikasi (backend / API), dan lapisan data (database + file storage). Pendekatan ini memudahkan pemeliharaan, pengamanan data, dan pengembangan bertahap.
6. Rancangan ERD
Rancangan ERD pada dokumen ini dibagi menjadi dua tingkat: (1) ERD inti yang mewakili entitas utama yang eksplisit pada SRS versi 2.0; dan (2) catatan desain database implementatif yang menambahkan master data serta tabel pendukung agar kebutuhan workflow, keuangan, dan monitoring dapat dijalankan dengan baik.
6.1 ERD Inti Aplikasi PANGI

Gambar 1. ERD inti aplikasi PANGI yang memetakan entitas utama dari siklus usulan, persetujuan, dokumen, pembayaran, LPJ, dan audit.
6.2 Catatan Desain Database Implementatif
Entitas Tambahan Rekomendasi	Tujuan
status_ref	Memisahkan status workflow ke tabel referensi agar status mudah diurutkan dan dikelola.
tahun_anggaran	Memastikan penomoran, filter laporan, dan validasi periode anggaran lebih rapi.
lokasi_tujuan & jenis_kegiatan	Menstandarkan referensi tujuan dan jenis kegiatan untuk pelaporan.
komponen_biaya & rincian_biaya	Mendukung rincian biaya per komponen, uang muka, dan realisasi akhir.
template_dokumen	Menyimpan versi template resmi Surat Tugas dan SPPD.
notifikasi	Mencatat notifikasi in-app untuk submit, approval, revisi, dan LPJ.
Skema awal masih mengikuti asumsi satu user memiliki satu role melalui users.role_id. Jika nanti satu akun perlu memiliki banyak role, relasi dapat diubah menjadi tabel user_roles.
Daftar nominatif dapat dihasilkan sebagai laporan atau view dari data usulan, peserta, rincian biaya, dan pembayaran.
Lampiran sebaiknya mendukung relasi ke usulan maupun LPJ agar dokumen awal dan dokumen pertanggungjawaban tetap tertelusur.
7. Rancangan UI/UX
Rancangan UI/UX di bawah ini bersifat medium fidelity dan dimaksudkan sebagai arah desain antarmuka pada fase implementasi. Visual mengikuti pola dashboard modern dengan fokus pada kejelasan status, kemudahan aksi, dan konsistensi antar peran pengguna.
7.1 Prinsip Desain UI
Prinsip	Implikasi Desain
Konsisten	Pola navigasi, warna aksi, badge status, dan layout kartu harus seragam antar halaman.
Status terlihat jelas	Pengguna harus langsung memahami posisi proses, backlog, dan aksi berikutnya.
Berorientasi tugas	Aksi utama seperti Submit, Setujui, Minta Revisi, Verifikasi LPJ, dan Bayar Sisa ditonjolkan.
Mudah dipelajari	Label menu, judul halaman, dan istilah mengikuti bahasa administrasi yang lazim di institusi.
Siap mobile responsif	Layout harus tetap dapat digunakan pada layar tablet dan mobile untuk kebutuhan monitoring.
7.2 Struktur Navigasi per Peran
Peran	Menu Utama
Pegawai / Pengusul	Dashboard, Usulan Perjalanan, Dokumen, Laporan pribadi
Atasan	Dashboard, Persetujuan, Detail usulan, Histori keputusan
SDM / Kepegawaian	Dashboard, Persetujuan, Dokumen, Master referensi tertentu
PPK	Dashboard, Persetujuan, Monitoring anggaran
Direktur	Dashboard, Persetujuan final, Ringkasan eksekutif
Bendahara / Keuangan	Dashboard, Keuangan, Verifikasi LPJ, Laporan, Daftar nominatif
Administrator	Seluruh menu termasuk Master Data dan Administrasi Sistem
7.3 Katalog Layar Utama
Layar	Aktor	Tujuan
Login	Semua pengguna	Autentikasi dan pemilihan peran akses ke sistem
Dashboard Admin	Admin / Pimpinan / unit pengelola	Ringkasan backlog, status, aktivitas terbaru, dan quick actions
Form Usulan	Pegawai / Pengusul	Pengisian data perjalanan, peserta, lampiran, dan submit ke atasan
Detail Usulan & Persetujuan	Atasan, SDM, PPK, Direktur	Review detail usulan, dokumen, timeline, dan pengambilan keputusan
Modul Keuangan & LPJ	Bendahara / Keuangan	Rincian biaya, pembayaran, checklist LPJ, dan penyelesaian transaksi
Catatan rancangan UI/UX
Mockup berikut berfungsi sebagai acuan layout, hirarki informasi, dan penempatan aksi utama. Warna, ikon, dan komponen dapat diperkaya kembali pada fase desain final tanpa mengubah alur inti.

7.4 Mockup Login

Gambar 2. Rancangan halaman login PANGI dengan pilihan peran, field kredensial, dan tombol aksi utama.
Layout menempatkan kartu login di tengah untuk memfokuskan pengguna pada autentikasi.
Elemen akses utama dibuat ringkas: peran, email / NIP, password, dan tombol masuk.

7.5 Mockup Dashboard Admin

Gambar 3. Rancangan dashboard admin untuk monitoring backlog, statistik proses, aktivitas, dan quick actions.
Dashboard menonjolkan metrik utama dan aktivitas terbaru agar unit pengelola cepat membaca kondisi proses.
Quick actions disediakan untuk mempercepat akses ke modul operasional yang paling sering digunakan.

7.6 Mockup Form Usulan

Gambar 4. Rancangan form usulan perjalanan dinas dengan progress step, data utama, peserta, lampiran, dan ringkasan draft.
Form dibagi menjadi area input utama dan panel ringkasan di sisi kanan.
Validasi minimum ditampilkan sebelum usulan dikirim ke atasan.

7.7 Mockup Detail Usulan & Persetujuan

Gambar 5. Rancangan layar detail usulan untuk review dokumen, timeline persetujuan, dan aksi keputusan.
Informasi inti, dokumen, dan timeline approval ditempatkan dalam satu tampilan untuk mengurangi perpindahan halaman.
Aksi Setujui dan Tolak / Minta Revisi dibuat sangat jelas pada level approver.

7.8 Mockup Modul Keuangan & LPJ

Gambar 6. Rancangan layar keuangan untuk rincian biaya, pembayaran, checklist LPJ, dan proses pembayaran sisa.
Layar keuangan memisahkan area perhitungan biaya dan area verifikasi dokumen LPJ.
Aksi Verifikasi LPJ dan Proses Pembayaran Sisa menjadi fokus utama operator keuangan.

8. Kriteria Penerimaan, Roadmap, dan Risiko
8.1 Minimum Acceptance Criteria
Kode	Kriteria
AC-01	Pengusul dapat membuat usulan baru, menyimpan draft, dan submit tanpa error.
AC-02	Atasan, PPK, dan direktur dapat melihat detail usulan dan memberi keputusan dengan catatan.
AC-03	Sistem menolak pengguna yang tidak berwenang mengakses tahapan tertentu.
AC-04	Surat Tugas dan SPPD dapat dihasilkan dari data usulan yang telah disetujui.
AC-05	Bendahara dapat mencatat pembayaran serta memverifikasi LPJ hingga status selesai.
AC-06	Histori status dan audit trail tampil lengkap pada detail usulan.
AC-07	Dokumen yang belum lengkap dapat dikembalikan untuk perbaikan dan sistem merekam alasannya.
AC-08	Dashboard dan laporan menampilkan data yang konsisten dengan transaksi yang tersimpan.
8.2 Rekomendasi Tahap Pengembangan
Tahap	Ruang Lingkup
Fase 1	Master data, pembuatan usulan, persetujuan atasan, verifikasi SDM, persetujuan PPK / direktur.
Fase 2	Generate dokumen, pembayaran, dashboard, notifikasi, dan laporan dasar.
Fase 3	Pertanggungjawaban lengkap, arsip digital, statistik lanjutan, dan integrasi lain bila dibutuhkan.
8.3 Risiko dan Catatan Implementasi
Kode	Catatan
R-01	Perlu penetapan format resmi nomor Surat Tugas dan SPPD sebelum go-live.
R-02	Perlu kepastian daftar lampiran wajib untuk tiap jenis perjalanan atau kegiatan.
R-03	Perlu keputusan apakah tanda tangan digital masuk fase awal atau fase lanjutan.
R-04	Perlu validasi kebutuhan integrasi dengan sistem kepegawaian atau keuangan yang sudah ada.
R-05	Perlu penyiapan kebijakan perubahan proses dan pelatihan pengguna saat implementasi.
Penutup
Dokumen SRS versi 3.0 ini dapat digunakan sebagai acuan terpadu untuk menyelaraskan kebutuhan bisnis, perancangan data, dan rancangan antarmuka aplikasi PANGI. Pada fase desain teknis berikutnya, tim dapat menurunkan dokumen ini menjadi spesifikasi API, skema database fisik, detail validasi, rancangan hak akses, serta backlog implementasi per sprint.
