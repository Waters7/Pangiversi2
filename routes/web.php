<?php

use App\Http\Controllers\AdministrasiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\UsulanController;
use Illuminate\Support\Facades\Route;

// ── Guest Routes ──
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    // Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    // Route::post('/register', [AuthController::class, 'register']);
});

// ── Authenticated Routes ──
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Usulan — semua role bisa akses
    Route::get('/usulan', [UsulanController::class, 'create'])->name('usulan.create');
    Route::post('/usulan', [UsulanController::class, 'store'])->name('usulan.store');
    Route::get('/list-usulan', [UsulanController::class, 'index'])->name('usulan.list');
    Route::get('/usulan/{usulan:no_usulan}', [UsulanController::class, 'show'])->name('usulan.show');
    Route::get('/usulan/{usulan:no_usulan}/edit', [UsulanController::class, 'edit'])->name('usulan.edit');
    Route::put('/usulan/{usulan:no_usulan}', [UsulanController::class, 'update'])->name('usulan.update');
    Route::delete('/usulan/{usulan:no_usulan}', [UsulanController::class, 'destroy'])->name('usulan.destroy');

    // Dokumen — semua role bisa akses
    Route::prefix('dokumen')->group(function () {
        Route::get('/', [DokumenController::class, 'index'])->name('dokumen');
        Route::get('/{usulan:no_usulan}', [DokumenController::class, 'show'])->name('dokumen.show');
        Route::post('/{usulan:no_usulan}', [DokumenController::class, 'store'])->name('dokumen.store');
    });

    // PPK + Administrator only
    Route::middleware('role:ppk,administrator')->group(function () {
        Route::prefix('persetujuan')->group(function () {
            Route::get('/', [PersetujuanController::class, 'index'])->name('persetujuan');
            Route::get('/dokumen/{path}', [PersetujuanController::class, 'dokumen'])->name('persetujuan.dokumen')->where('path', '.*');
            Route::get('/{usulan:no_usulan}', [PersetujuanController::class, 'show'])->name('persetujuan.detail');
            Route::get('/{usulan:no_usulan}/export', [PersetujuanController::class, 'export'])->name('persetujuan.export');
            Route::put('/{usulan:no_usulan}/approve', [PersetujuanController::class, 'setuju'])->name('persetujuan.approve');
            Route::put('/{usulan:no_usulan}/revoke', [PersetujuanController::class, 'batalkan'])->name('persetujuan.revoke');
            Route::put('/{usulan:no_usulan}/reject', [PersetujuanController::class, 'tolak'])->name('persetujuan.reject');
        });

        Route::prefix('keuangan')->group(function () {
            Route::get('/', [KeuanganController::class, 'index'])->name('keuangan');
            Route::get('/{usulan:no_usulan}', [KeuanganController::class, 'show'])->name('keuangan.detail');
            Route::post('/{usulan:no_usulan}/rincian', [KeuanganController::class, 'storeRincian'])->name('keuangan.rincian.store');
            Route::put('/{usulan:no_usulan}/rincian/{rincian}', [KeuanganController::class, 'updateRincian'])->name('keuangan.rincian.update');
            Route::delete('/{usulan:no_usulan}/rincian/{rincian}', [KeuanganController::class, 'destroyRincian'])->name('keuangan.rincian.destroy');
            Route::post('/{usulan:no_usulan}/bayar-uang-muka', [KeuanganController::class, 'bayarUangMuka'])->name('keuangan.bayar-uang-muka');
            Route::post('/{usulan:no_usulan}/bayar-sisa', [KeuanganController::class, 'bayarSisa'])->name('keuangan.bayar-sisa');
            Route::put('/{usulan:no_usulan}/koreksi-status', [KeuanganController::class, 'koreksiStatus'])->name('keuangan.koreksi-status');
        });

        Route::prefix('laporan')->group(function () {
            Route::get('/', [LaporanController::class, 'index'])->name('laporan');
            Route::get('/export', [LaporanController::class, 'export'])->name('laporan.export');
            Route::get('/{usulan:no_usulan}', [LaporanController::class, 'show'])->name('laporan.show');
            Route::get('/{usulan:no_usulan}/export', [LaporanController::class, 'exportDetail'])->name('laporan.export-detail');
        });

        Route::get('/master', [MasterDataController::class, 'index'])->name('master');
    });

    // Administrator only
    Route::middleware('role:administrator')->prefix('administrasi')->group(function () {
        Route::get('/', [AdministrasiController::class, 'index'])->name('administrasi');
        Route::post('/', [AdministrasiController::class, 'store'])->name('administrasi.store');
        Route::put('/{user}', [AdministrasiController::class, 'update'])->name('administrasi.update');
        Route::put('/{user}/password', [AdministrasiController::class, 'updatePassword'])->name('administrasi.password');
        Route::delete('/{user}', [AdministrasiController::class, 'destroy'])->name('administrasi.destroy');
    });

    Route::middleware('role:administrator')->prefix('kegiatan')->group(function () {
        Route::get('/', [KegiatanController::class, 'index'])->name('kegiatan.index');
        Route::post('/', [KegiatanController::class, 'store'])->name('kegiatan.store');
        Route::put('/{kegiatan}', [KegiatanController::class, 'update'])->name('kegiatan.update');
        Route::delete('/{kegiatan}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');
    });
});
