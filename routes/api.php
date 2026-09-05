<?php

use App\Http\Controllers\Api\DashboardEksekutifController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API PANGI
|--------------------------------------------------------------------------
|
| Jalur baca-saja untuk aplikasi lain. Seluruhnya dijaga token bersama dan
| dibatasi kekerapannya — sekencang apa pun aplikasi pemanggil menyegarkan
| layarnya, basis data PANGI tidak ikut terbebani.
|
| Diberi versi (v1) sejak awal supaya bentuk balasannya kelak dapat berubah
| tanpa mematikan aplikasi yang sudah terlanjur memakainya.
|
*/

Route::middleware(['token.api', 'throttle:60,1'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('dashboard-eksekutif')->name('dashboard-eksekutif.')->group(function (): void {
            Route::get('/', [DashboardEksekutifController::class, 'index'])->name('index');
            Route::get('/ringkasan', [DashboardEksekutifController::class, 'ringkasan'])->name('ringkasan');
            Route::get('/realisasi', [DashboardEksekutifController::class, 'realisasi'])->name('realisasi');
            Route::get('/pegawai', [DashboardEksekutifController::class, 'pegawai'])->name('pegawai');
            Route::get('/tahun-anggaran', [DashboardEksekutifController::class, 'tahunAnggaran'])->name('tahun-anggaran');
        });
    });
