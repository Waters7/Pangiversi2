<?php

use App\Http\Controllers\UsulanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

// Usulan Routes
Route::get('/usulan', [UsulanController::class,'create'])->name('usulan.create');
Route::get('/list-usulan', [UsulanController::class, 'index'])->name('usulan.list');




Route::get('/persetujuan', function () {
    return view('persetujuan.list-persetujuan');
})->name('persetujuan');
Route::get('/persetujuan/usl-2025-001', function () {
    return view('persetujuan.detail-usulan');
})->name('persetujuan.detail');

Route::get('/dokumen', function () {
    return view('dokumen.dokumen');
})->name('dokumen');

Route::get('/keuangan', function () {
    return view('keuangan.keuangan');
})->name('keuangan');

Route::get('/laporan', function () {
    return view('laporan');
})->name('laporan');

Route::get('/master', function () {
    return view('master-data');
})->name('master');

Route::get('/administrasi', function () {
    return view('administrasi-sistem');
})->name('administrasi');

Route::get('/register', function () {
    return view('register');
})->name('register');

Route::get('/login', function () {
    return view('login');
})->name('login');