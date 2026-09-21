<?php

use App\Http\Controllers\AdministrasiController;
use App\Http\Controllers\AkunPembiayaanController;
use App\Http\Controllers\AsistenAiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BantuanController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\DaftarRiilController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardEksekutifController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\IntegrasiDataController;
use App\Http\Controllers\JadwalPerjalananController;
use App\Http\Controllers\KategoriPembiayaanController;
use App\Http\Controllers\KategoriPerjadinController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KomponenBiayaController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LaporanPerjadinController;
use App\Http\Controllers\LaporanPimpinanController;
use App\Http\Controllers\LokasiTujuanController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ModulDalamPengembanganController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PeranController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\PersetujuanPpkController;
use App\Http\Controllers\PesertaUsulanController;
use App\Http\Controllers\PetaPerjalananController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RincianSayaController;
use App\Http\Controllers\StatusHasilController;
use App\Http\Controllers\SuratPerjalananDinasController;
use App\Http\Controllers\TahunAnggaranController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Controllers\UsulanController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

// ── Guest Routes ──
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    // Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    // Route::post('/register', [AuthController::class, 'register']);
});

// ── Verifikasi publik dokumen (tujuan QR code) ──
Route::get('/verifikasi/{kode}', VerifikasiController::class)
    ->middleware('throttle:30,1')
    ->name('verifikasi.tampil');

// ── Authenticated Routes ──
Route::middleware('auth')->group(function () {
    // Berkas unggahan disajikan lewat aplikasi, bukan tautan simbolik
    // public/storage yang kerap tidak diikuti peladen hosting bersama.
    Route::get('/berkas/{path}', [BerkasController::class, 'lihat'])->name('berkas.lihat')->where('path', '.*');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Pegawai eksternal, mahasiswa, dan outsourcing: modulnya masih dikembangkan.
    Route::get('/dalam-pengembangan', ModulDalamPengembanganController::class)->name('modul.dalam-pengembangan');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('can:melihat-dashboard-eksekutif')->group(function () {
        Route::get('/dashboard-eksekutif', DashboardEksekutifController::class)->name('dashboard-eksekutif');

        // Fitur AI: wawasan otomatis dan agen tanya-jawab, keduanya baca-saja.
        Route::get('/asisten-ai/wawasan', [AsistenAiController::class, 'wawasan'])->name('asisten-ai.wawasan');
        Route::post('/asisten-ai/tanya', [AsistenAiController::class, 'tanya'])
            ->middleware('throttle:20,1')
            ->name('asisten-ai.tanya');
    });

    // Panel milik pengguna sendiri — terbuka untuk seluruh peran
    Route::prefix('profil')->name('profil.')->group(function () {
        Route::get('/', [ProfilController::class, 'index'])->name('index');
        Route::put('/password', [ProfilController::class, 'ubahPassword'])->name('password');
        Route::put('/rekening', [ProfilController::class, 'ubahRekening'])->name('rekening');
        Route::post('/foto', [ProfilController::class, 'ubahFoto'])->name('foto');
        Route::delete('/foto', [ProfilController::class, 'hapusFoto'])->name('foto.hapus');
    });

    // Surat Perjalanan Dinas — terbuka bagi seluruh peran, sama seperti usulan.
    // Rute pratinjau dan cetak ditaruh sebelum rute berparameter agar kata
    // "buat" dan "pratinjau" tidak terbaca sebagai id SPD.
    Route::get('/spd', [SuratPerjalananDinasController::class, 'index'])->name('spd.index');
    Route::get('/spd/buat', [SuratPerjalananDinasController::class, 'create'])->name('spd.create');
    Route::post('/spd', [SuratPerjalananDinasController::class, 'store'])->name('spd.store');
    Route::post('/spd/pratinjau', [SuratPerjalananDinasController::class, 'pratinjau'])->name('spd.pratinjau');
    Route::get('/spd/{spd}', [SuratPerjalananDinasController::class, 'show'])->name('spd.show');
    Route::get('/spd/{spd}/ubah', [SuratPerjalananDinasController::class, 'edit'])->name('spd.edit');
    Route::put('/spd/{spd}', [SuratPerjalananDinasController::class, 'update'])->name('spd.update');
    Route::get('/spd/{spd}/cetak', [SuratPerjalananDinasController::class, 'cetak'])->name('spd.cetak');
    Route::delete('/spd/{spd}', [SuratPerjalananDinasController::class, 'destroy'])->name('spd.destroy');

    // Usulan — seluruh peran berhak mengajukan perjalanan dinas
    Route::get('/usulan', [UsulanController::class, 'create'])->name('usulan.create');
    Route::post('/usulan', [UsulanController::class, 'store'])->name('usulan.store');

    Route::get('/list-usulan', [UsulanController::class, 'index'])->name('usulan.list');
    Route::get('/usulan/{usulan:no_usulan}', [UsulanController::class, 'show'])->name('usulan.show');
    Route::get('/usulan/{usulan:no_usulan}/edit', [UsulanController::class, 'edit'])->name('usulan.edit');
    Route::put('/usulan/{usulan:no_usulan}', [UsulanController::class, 'update'])->name('usulan.update');
    Route::delete('/usulan/{usulan:no_usulan}', [UsulanController::class, 'destroy'])->name('usulan.destroy');

    // Peserta perjalanan — dikelola pengusul dari halaman detail usulan
    Route::post('/usulan/{usulan:no_usulan}/peserta', [PesertaUsulanController::class, 'store'])->name('usulan.peserta.store');
    Route::delete('/usulan/{usulan:no_usulan}/peserta/{peserta}', [PesertaUsulanController::class, 'destroy'])->name('usulan.peserta.destroy');

    // Mengirim usulan draf untuk diverifikasi PPK
    Route::put('/usulan/{usulan:no_usulan}/ajukan', [UsulanController::class, 'ajukan'])->name('usulan.ajukan');

    // Konfirmasi kesediaan atas usulan yang dibuatkan orang lain
    Route::put('/usulan/{usulan:no_usulan}/konfirmasi', [UsulanController::class, 'konfirmasi'])->name('usulan.konfirmasi');
    Route::put('/usulan/{usulan:no_usulan}/batal-konfirmasi', [UsulanController::class, 'batalKonfirmasi'])->name('usulan.batal-konfirmasi');

    // Notifikasi in-app — semua peran
    Route::prefix('notifikasi')->name('notifikasi.')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index'])->name('index');
        Route::put('/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('baca-semua');
        Route::put('/{notifikasi}/baca', [NotifikasiController::class, 'baca'])->name('baca');
        Route::delete('/{notifikasi}', [NotifikasiController::class, 'destroy'])->name('destroy');
    });

    // Dokumen — semua peran bisa mengurus dokumen usulannya sendiri.
    // Rute tanpa parameter ditaruh lebih dulu agar kata "tindak-lanjut" tidak
    // terbaca sebagai nomor usulan.
    Route::prefix('dokumen')->group(function () {
        Route::get('/', [DokumenController::class, 'index'])->name('dokumen');

        Route::get('/laporan', [LaporanPerjadinController::class, 'index'])
            ->name('dokumen.laporan.index');

        Route::get('/tindak-lanjut', [LaporanPerjadinController::class, 'daftarTindakLanjut'])
            ->name('dokumen.tindak-lanjut');
        Route::put('/tindak-lanjut/{tindakLanjut}', [LaporanPerjadinController::class, 'ubahStatusTindakLanjut'])
            ->name('dokumen.tindak-lanjut.status');

        Route::get('/{usulan:no_usulan}', [DokumenController::class, 'show'])->name('dokumen.show');
        Route::get('/{usulan:no_usulan}/format-laporan', [DokumenController::class, 'formatLaporan'])
            ->name('dokumen.format-laporan');
        Route::post('/{usulan:no_usulan}', [DokumenController::class, 'store'])->name('dokumen.store');

        // Laporan perjalanan dinas disusun langsung di aplikasi.
        Route::get('/{usulan:no_usulan}/laporan', [LaporanPerjadinController::class, 'edit'])
            ->name('dokumen.laporan.edit');
        Route::get('/{usulan:no_usulan}/laporan/lihat', [LaporanPerjadinController::class, 'show'])
            ->name('dokumen.laporan.show');
        Route::put('/{usulan:no_usulan}/laporan', [LaporanPerjadinController::class, 'update'])
            ->name('dokumen.laporan.update');
        Route::put('/{usulan:no_usulan}/laporan/selesaikan', [LaporanPerjadinController::class, 'selesaikan'])
            ->name('dokumen.laporan.selesaikan');
        Route::put('/{usulan:no_usulan}/laporan/buka', [LaporanPerjadinController::class, 'bukaKembali'])
            ->name('dokumen.laporan.buka');
        Route::put('/{usulan:no_usulan}/laporan/kirim', [LaporanPerjadinController::class, 'kirim'])
            ->name('dokumen.laporan.kirim');
        Route::get('/{usulan:no_usulan}/laporan/cetak', [LaporanPerjadinController::class, 'cetak'])
            ->name('dokumen.laporan.cetak');
    });

    // Meja pimpinan: laporan perjalanan dinas yang dikirim pelaksana untuk
    // dikonfirmasi dan ditandatangani, atau dikembalikan untuk direvisi.
    Route::middleware('can:mengonfirmasi-laporan-perjadin')
        ->prefix('laporan-perjadin')->name('laporan-perjadin.')->group(function () {
            Route::get('/', [LaporanPimpinanController::class, 'index'])->name('index');
            Route::get('/status', [LaporanPimpinanController::class, 'status'])->name('status');
            Route::get('/tindak-lanjut', [LaporanPimpinanController::class, 'tindakLanjut'])->name('tindak-lanjut');
            Route::get('/{usulan:no_usulan}', [LaporanPimpinanController::class, 'show'])->name('show');
            Route::put('/{usulan:no_usulan}/konfirmasi', [LaporanPimpinanController::class, 'konfirmasi'])->name('konfirmasi');
            Route::delete('/{usulan:no_usulan}/konfirmasi', [LaporanPimpinanController::class, 'batalKonfirmasi'])->name('batal-konfirmasi');
            Route::put('/{usulan:no_usulan}/kembalikan', [LaporanPimpinanController::class, 'kembalikan'])->name('kembalikan');
        });

    // Meja kerja PPK: berkas yang menunggu verifikasi dan tanda tangannya.
    Route::middleware('can:menandatangani-daftar-riil')->prefix('persetujuan')->group(function () {
        Route::get('/rincian-biaya', [PersetujuanPpkController::class, 'rincianBiaya'])->name('persetujuan.rincian-biaya');
        Route::get('/daftar-riil', [PersetujuanPpkController::class, 'daftarRiil'])->name('persetujuan.daftar-riil');

        Route::get('/riwayat', [PersetujuanPpkController::class, 'riwayat'])->name('persetujuan.riwayat');

        Route::get('/nominatif', [PersetujuanPpkController::class, 'nominatif'])->name('persetujuan.nominatif');
        Route::get('/nominatif/{nominatif}', [PersetujuanPpkController::class, 'nominatifDetail'])->name('persetujuan.nominatif.detail');
        Route::put('/nominatif/{nominatif}/tanda-tangan', [PersetujuanPpkController::class, 'tandaTanganiNominatif'])->name('persetujuan.nominatif.tanda-tangan');
        Route::put('/nominatif/{nominatif}/kirim', [PersetujuanPpkController::class, 'kirimNominatif'])->name('persetujuan.nominatif.kirim');
    });

    // Persetujuan berjenjang — atasan langsung, PPK, pimpinan, super administrator
    Route::middleware('approver')->prefix('persetujuan')->group(function () {
        Route::get('/', [PersetujuanController::class, 'index'])->name('persetujuan');
        Route::get('/dokumen/{path}', [PersetujuanController::class, 'dokumen'])->name('persetujuan.dokumen')->where('path', '.*');
        Route::get('/{usulan:no_usulan}', [PersetujuanController::class, 'show'])->name('persetujuan.detail');
        Route::get('/{usulan:no_usulan}/export', [PersetujuanController::class, 'export'])->name('persetujuan.export');
        Route::put('/{usulan:no_usulan}/approve', [PersetujuanController::class, 'setuju'])->name('persetujuan.approve');
        Route::put('/{usulan:no_usulan}/revoke', [PersetujuanController::class, 'batalkan'])->name('persetujuan.revoke');
        Route::put('/{usulan:no_usulan}/reject', [PersetujuanController::class, 'tolak'])->name('persetujuan.reject');
        Route::put('/{usulan:no_usulan}/revisi', [PersetujuanController::class, 'revisi'])->name('persetujuan.revisi');
    });

    // Jadwal keberangkatan — Tim SDM melihat siapa yang berangkat, tanpa rincian
    Route::middleware('can:melihat-jadwal-perjalanan')->group(function () {
        Route::get('/jadwal-perjalanan', [JadwalPerjalananController::class, 'index'])
            ->name('jadwal-perjalanan');
        Route::get('/jadwal-perjalanan/ekspor', [JadwalPerjalananController::class, 'ekspor'])
            ->name('jadwal-perjalanan.ekspor');

        // Peta kota tujuan: dalam kota & sekitarnya, atau luar kota.
        Route::get('/jadwal-perjalanan/peta/{jenis}', PetaPerjalananController::class)
            ->whereIn('jenis', ['dalam-kota', 'luar-kota'])
            ->name('jadwal-perjalanan.peta');
    });

    // Menu Pembayaran khusus bendahara: daftar tahap dan jurnal riwayatnya.
    Route::middleware('can:melihat-pembayaran')->group(function () {
        Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('pembayaran');
        Route::get('/pembayaran/riwayat', [PembayaranController::class, 'riwayat'])->name('pembayaran.riwayat');

        // Penggantian transport lokal dibayarkan dan dicatat sendiri,
        // sebab nominalnya berasal dari daftar riil, bukan rincian biaya.
        Route::get('/pembayaran/transport-lokal', [PembayaranController::class, 'transportLokal'])->name('pembayaran.transport-lokal');
        Route::post('/pembayaran/transport-lokal/{daftar}', [PembayaranController::class, 'bayarTransport'])->name('pembayaran.bayar-transport');
        Route::put('/pembayaran/transport-lokal/{daftar}/batal', [PembayaranController::class, 'batalBayarTransport'])->name('pembayaran.batal-transport');
    });

    // Keuangan — dibuka oleh peran yang berhak melihat data keuangan.
    // Pemisahan input biaya dan pencatatan bukti bayar ditegakkan di controller.
    Route::middleware('can:melihat-keuangan')->prefix('keuangan')->group(function () {
        Route::get('/', [KeuanganController::class, 'index'])->name('keuangan');

        // Didaftarkan sebelum rute {usulan} agar tidak terbaca sebagai nomor usulan.
        Route::get('/transport-lokal', [KeuanganController::class, 'transportLokal'])->name('keuangan.transport-lokal');
        Route::get('/{usulan:no_usulan}', [KeuanganController::class, 'show'])->name('keuangan.detail');
        Route::get('/{usulan:no_usulan}/cetak-rincian', [KeuanganController::class, 'cetakRincian'])->name('keuangan.cetak-rincian');
        Route::post('/{usulan:no_usulan}/rincian', [KeuanganController::class, 'storeRincian'])->name('keuangan.rincian.store');
        Route::put('/{usulan:no_usulan}/rincian/{rincian}', [KeuanganController::class, 'updateRincian'])->name('keuangan.rincian.update');
        Route::delete('/{usulan:no_usulan}/rincian/{rincian}', [KeuanganController::class, 'destroyRincian'])->name('keuangan.rincian.destroy');

        // Nominal yang datang dari berkas pelaksana diperiksa satu per satu.
        Route::put('/{usulan:no_usulan}/rincian/{rincian}/validasi', [KeuanganController::class, 'validasiRincian'])->name('keuangan.rincian.validasi');
        Route::delete('/{usulan:no_usulan}/rincian/{rincian}/validasi', [KeuanganController::class, 'batalValidasiRincian'])->name('keuangan.rincian.batal-validasi');
        Route::post('/{usulan:no_usulan}/bayar-uang-muka', [KeuanganController::class, 'bayarUangMuka'])->name('keuangan.bayar-uang-muka');
        Route::post('/{usulan:no_usulan}/bayar-sisa', [KeuanganController::class, 'bayarSisa'])->name('keuangan.bayar-sisa');

        // Pembatalan pembayaran: menambah baris jurnal, bukan menghapus jejaknya.
        Route::put('/{usulan:no_usulan}/batal-uang-muka', [KeuanganController::class, 'batalUangMuka'])->name('keuangan.batal-uang-muka');
        Route::put('/{usulan:no_usulan}/batal-pelunasan', [KeuanganController::class, 'batalPelunasan'])->name('keuangan.batal-pelunasan');
        Route::put('/{usulan:no_usulan}/koreksi-status', [KeuanganController::class, 'koreksiStatus'])->name('keuangan.koreksi-status');
    });

    // Panduan penggunaan — terbuka untuk seluruh peran
    Route::get('/panduan', PanduanController::class)->name('panduan');
    Route::get('/panduan/unduh', [PanduanController::class, 'unduh'])->name('panduan.unduh');

    // Saluran bantuan — terbuka bagi seluruh peran: siapa pun dapat
    // mengalami kendala, dan administrator menjawab di halaman yang sama.
    Route::prefix('bantuan')->name('bantuan.')->group(function () {
        Route::get('/', [BantuanController::class, 'index'])->name('index');
        Route::post('/', [BantuanController::class, 'store'])->name('store');
        Route::get('/{obrolan}', [BantuanController::class, 'show'])->name('show');
        Route::post('/{obrolan}/balas', [BantuanController::class, 'balas'])->name('balas');
        Route::put('/{obrolan}/selesai', [BantuanController::class, 'selesaikan'])->name('selesai');
    });

    // Berkas keuangan milik pengguna sendiri, dipisah menurut jenis dokumennya
    Route::prefix('rincian-saya')->name('rincian-saya.')->group(function () {
        Route::get('/daftar-riil', [RincianSayaController::class, 'daftarRiil'])->name('daftar-riil');
        Route::get('/rincian-biaya', [RincianSayaController::class, 'rincianBiaya'])->name('rincian-biaya');
    });

    // Tanggapan pelaksana atas daftar pengeluaran riilnya
    Route::prefix('daftar-riil')->name('daftar-riil.')->group(function () {
        Route::put('/{usulan:no_usulan}/peserta/{peserta}/setuju/{jenis?}', [DaftarRiilController::class, 'setujuiPegawai'])->name('setuju');
        Route::put('/{usulan:no_usulan}/peserta/{peserta}/sanggah/{jenis?}', [DaftarRiilController::class, 'sanggah'])->name('sanggah');
    });

    // Daftar pengeluaran riil per peserta — nominalnya dari nota pelaksana,
    // diverifikasi dan ditandatangani PPK. Tidak lagi bermenu sendiri: PPK
    // mengaksesnya lewat Persetujuan, Tim SDM lewat Laporan.
    Route::middleware('can:melihat-keuangan')->prefix('daftar-riil')->name('daftar-riil.')->group(function () {
        Route::get('/{usulan:no_usulan}', [DaftarRiilController::class, 'show'])->name('show');
        Route::get('/{usulan:no_usulan}/peserta/{peserta}/cetak', [DaftarRiilController::class, 'cetak'])->name('cetak');

        Route::middleware('can:mengelola-biaya')->group(function () {
            Route::put('/{usulan:no_usulan}/peserta/{peserta}', [DaftarRiilController::class, 'simpan'])->name('simpan');

            // Transport lokal diperiksa tim keuangan sebelum berjalan ke
            // pelaksana lalu ke PPK.
            Route::put('/{usulan:no_usulan}/peserta/{peserta}/validasi', [DaftarRiilController::class, 'validasi'])->name('validasi');
            Route::delete('/{usulan:no_usulan}/peserta/{peserta}/validasi', [DaftarRiilController::class, 'batalValidasi'])->name('batal-validasi');
        });

        // Tim keuangan yang memeriksa nominal lalu mengirimkan kedua dokumen
        // — rincian biaya dan daftar riil — kepada pelaksana untuk disanggah
        // atau ditandatangani. PPK baru menandatangani setelahnya.
        Route::middleware('can:mengelola-biaya')->group(function () {
            Route::put('/{usulan:no_usulan}/peserta/{peserta}/kirim-pegawai', [DaftarRiilController::class, 'kirimKePegawai'])->name('kirim-pegawai');
        });

        Route::middleware('can:menandatangani-daftar-riil')->group(function () {
            Route::put('/{usulan:no_usulan}/peserta/{peserta}/tanda-tangan/{jenis?}', [DaftarRiilController::class, 'tandaTangani'])->name('tanda-tangan');
            Route::delete('/{usulan:no_usulan}/peserta/{peserta}/tanda-tangan/{jenis?}', [DaftarRiilController::class, 'batalTandaTangan'])->name('batal-tanda-tangan');

            // Alih-alih menandatangani, PPK dapat mengembalikannya untuk diperbaiki.
            Route::put('/{usulan:no_usulan}/peserta/{peserta}/kembalikan/{jenis?}', [DaftarRiilController::class, 'kembalikan'])->name('kembalikan');
        });
    });

    // Arsip daftar riil dan daftar nominatif. Terbuka juga bagi Tim SDM, yang
    // mengarsipkannya tanpa berhak membaca rekap anggaran. Didaftarkan lebih
    // dulu agar tidak tertangkap rute {usulan} di bawahnya.
    Route::middleware('can:melihat-arsip-perjadin')->prefix('laporan')->group(function () {
        Route::get('/daftar-riil', [LaporanController::class, 'daftarRiil'])->name('laporan.daftar-riil');

        // Rincian biaya yang tanda tangannya sudah lengkap — dokumen yang
        // sah sebagai dasar pembayaran dan tidak berubah lagi.
        Route::get('/rincian-lengkap', [LaporanController::class, 'rincianLengkap'])->name('laporan.rincian-lengkap');
        Route::get('/nominatif', [LaporanController::class, 'nominatif'])->name('laporan.nominatif');

        // Cetak dan penetapan akun pembiayaan terbuka bagi seluruh pembaca
        // arsip: PPK, tim keuangan, maupun Tim SDM.
        Route::get('/nominatif/{nominatif}/cetak', [LaporanController::class, 'cetakNominatif'])->name('laporan.nominatif.cetak');
        Route::put('/nominatif/{nominatif}/akun', [LaporanController::class, 'tetapkanAkun'])->name('laporan.nominatif.akun');
    });

    // Laporan dan rekap
    Route::middleware('can:melihat-laporan')->prefix('laporan')->group(function () {
        Route::get('/', [LaporanController::class, 'index'])->name('laporan');
        Route::get('/export-excel', [LaporanController::class, 'exportExcel'])->name('laporan.export-excel');

        Route::get('/{usulan:no_usulan}', [LaporanController::class, 'show'])->name('laporan.show');
    });

    // Ringkasan master data — dapat dibaca seluruh peran back office
    Route::middleware('can:melihat-laporan')->get('/master', [MasterDataController::class, 'index'])->name('master');

    // Master data referensi — hanya super administrator
    Route::middleware('can:mengelola-master-data')->prefix('master')->name('master.')->group(function () {
        Route::get('/unit-kerja', [UnitKerjaController::class, 'index'])->name('unit-kerja');
        Route::post('/unit-kerja', [UnitKerjaController::class, 'store'])->name('unit-kerja.store');
        Route::put('/unit-kerja/{unitKerja}', [UnitKerjaController::class, 'update'])->name('unit-kerja.update');
        Route::delete('/unit-kerja/{unitKerja}', [UnitKerjaController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('unit-kerja.destroy');

        Route::get('/lokasi', [LokasiTujuanController::class, 'index'])->name('lokasi');
        Route::post('/lokasi', [LokasiTujuanController::class, 'store'])->name('lokasi.store');
        Route::put('/lokasi/{lokasi}', [LokasiTujuanController::class, 'update'])->name('lokasi.update');
        Route::delete('/lokasi/{lokasi}', [LokasiTujuanController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('lokasi.destroy');

        Route::get('/kategori-perjadin', [KategoriPerjadinController::class, 'index'])->name('kategori-perjadin');
        Route::post('/kategori-perjadin', [KategoriPerjadinController::class, 'store'])->name('kategori-perjadin.store');
        Route::put('/kategori-perjadin/{kategoriPerjadin}', [KategoriPerjadinController::class, 'update'])->name('kategori-perjadin.update');
        Route::delete('/kategori-perjadin/{kategoriPerjadin}', [KategoriPerjadinController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('kategori-perjadin.destroy');

        Route::get('/kategori-pembiayaan', [KategoriPembiayaanController::class, 'index'])->name('kategori-pembiayaan');
        Route::post('/kategori-pembiayaan', [KategoriPembiayaanController::class, 'store'])->name('kategori-pembiayaan.store');
        Route::put('/kategori-pembiayaan/{kategoriPembiayaan}', [KategoriPembiayaanController::class, 'update'])->name('kategori-pembiayaan.update');
        Route::delete('/kategori-pembiayaan/{kategoriPembiayaan}', [KategoriPembiayaanController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('kategori-pembiayaan.destroy');

        Route::get('/akun-pembiayaan', [AkunPembiayaanController::class, 'index'])->name('akun-pembiayaan');
        Route::post('/akun-pembiayaan', [AkunPembiayaanController::class, 'store'])->name('akun-pembiayaan.store');
        Route::put('/akun-pembiayaan/{akunPembiayaan}', [AkunPembiayaanController::class, 'update'])->name('akun-pembiayaan.update');
        Route::delete('/akun-pembiayaan/{akunPembiayaan}', [AkunPembiayaanController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('akun-pembiayaan.destroy');

        Route::get('/status-hasil', [StatusHasilController::class, 'index'])->name('status-hasil');
        Route::post('/status-hasil', [StatusHasilController::class, 'store'])->name('status-hasil.store');
        Route::put('/status-hasil/{statusHasil}', [StatusHasilController::class, 'update'])->name('status-hasil.update');
        Route::delete('/status-hasil/{statusHasil}', [StatusHasilController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('status-hasil.destroy');

        Route::get('/komponen-biaya', [KomponenBiayaController::class, 'index'])->name('komponen-biaya');
        Route::post('/komponen-biaya', [KomponenBiayaController::class, 'store'])->name('komponen-biaya.store');
        Route::put('/komponen-biaya/{komponenBiaya}', [KomponenBiayaController::class, 'update'])->name('komponen-biaya.update');
        Route::delete('/komponen-biaya/{komponenBiaya}', [KomponenBiayaController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('komponen-biaya.destroy');

        Route::get('/tahun-anggaran', [TahunAnggaranController::class, 'index'])->name('tahun-anggaran');
        Route::post('/tahun-anggaran', [TahunAnggaranController::class, 'store'])->name('tahun-anggaran.store');
        Route::put('/tahun-anggaran/{tahunAnggaran}', [TahunAnggaranController::class, 'update'])->name('tahun-anggaran.update');
        Route::put('/tahun-anggaran/{tahunAnggaran}/aktifkan', [TahunAnggaranController::class, 'aktifkan'])->name('tahun-anggaran.aktifkan');
        Route::delete('/tahun-anggaran/{tahunAnggaran}', [TahunAnggaranController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('tahun-anggaran.destroy');
    });

    Route::middleware('can:mengelola-master-data')->prefix('kegiatan')->group(function () {
        Route::get('/', [KegiatanController::class, 'index'])->name('kegiatan.index');
        Route::post('/', [KegiatanController::class, 'store'])->name('kegiatan.store');
        Route::put('/{kegiatan}', [KegiatanController::class, 'update'])->name('kegiatan.update');
        Route::delete('/{kegiatan}', [KegiatanController::class, 'destroy'])->middleware('can:menghapus-master-data')->name('kegiatan.destroy');
    });

    // Jejak audit
    Route::middleware('can:melihat-jejak-audit')->get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

    // Manajemen pengguna — Tim SDM dan super administrator
    Route::middleware('can:mengelola-pengguna')->prefix('administrasi')->group(function () {
        // Tiga halaman bermenu: pengguna, impor/ekspor, dan pengaturan sistem.
        Route::get('/', [AdministrasiController::class, 'index'])->name('administrasi');
        Route::get('/impor-ekspor', [AdministrasiController::class, 'massal'])->name('administrasi.massal');
        Route::get('/pengaturan', [AdministrasiController::class, 'pengaturan'])->name('administrasi.pengaturan');

        // Integrasi Data (super administrator): token API, pemantauan
        // permintaan API, dan pengiriman data terjadwal ke aplikasi tujuan.
        Route::get('/integrasi', [IntegrasiDataController::class, 'index'])->name('administrasi.integrasi');
        Route::put('/integrasi/jadwal', [IntegrasiDataController::class, 'simpanJadwal'])->name('administrasi.integrasi.jadwal');
        Route::post('/integrasi/kirim', [IntegrasiDataController::class, 'kirimSekarang'])->name('administrasi.integrasi.kirim');

        // Peran & Hak Akses (super administrator): tambah peran dan atur
        // menu yang boleh dilihat, diubah, dan dihapus tiap peran.
        Route::middleware('can:mengelola-peran')->group(function () {
            Route::get('/peran', [PeranController::class, 'index'])->name('administrasi.peran');
            Route::post('/peran', [PeranController::class, 'store'])->name('administrasi.peran.store');
            Route::put('/peran/{peran}', [PeranController::class, 'update'])->name('administrasi.peran.update');
            Route::delete('/peran/{peran}', [PeranController::class, 'destroy'])->name('administrasi.peran.destroy');
        });

        Route::get('/export', [AdministrasiController::class, 'export'])->name('administrasi.export');
        Route::post('/import', [AdministrasiController::class, 'import'])->name('administrasi.import');

        // Pengingat kelengkapan berkas pertanggungjawaban
        Route::put('/pengaturan', [AdministrasiController::class, 'simpanPengaturan'])->name('administrasi.pengaturan.simpan');
        Route::post('/pengingat', [AdministrasiController::class, 'jalankanPengingat'])->name('administrasi.pengingat');

        // Kunci tanggal dikeluarkan SPD — super administrator saja, dijaga di pengontrol.
        Route::put('/tanggal-spd', [AdministrasiController::class, 'simpanKunciTanggalSpd'])->name('administrasi.tanggal-spd');

        // Token API dashboard eksekutif (super administrator)
        Route::post('/token-api', [AdministrasiController::class, 'buatTokenApi'])->name('administrasi.token-api.buat');
        Route::delete('/token-api', [AdministrasiController::class, 'cabutTokenApi'])->name('administrasi.token-api.cabut');

        // Kunci API Anthropic untuk asisten AI dashboard (super administrator)
        Route::put('/kunci-anthropic', [AdministrasiController::class, 'simpanKunciAnthropic'])->name('administrasi.kunci-anthropic.simpan');
        Route::delete('/kunci-anthropic', [AdministrasiController::class, 'hapusKunciAnthropic'])->name('administrasi.kunci-anthropic.hapus');

        Route::post('/', [AdministrasiController::class, 'store'])->name('administrasi.store');
        Route::put('/{user}', [AdministrasiController::class, 'update'])->name('administrasi.update');
        Route::put('/{user}/password', [AdministrasiController::class, 'updatePassword'])->name('administrasi.password');
        Route::delete('/{user}', [AdministrasiController::class, 'destroy'])->middleware('can:menghapus-pengguna')->name('administrasi.destroy');
    });
});
