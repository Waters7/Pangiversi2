<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');
Route::get('/usulan', function () {
    return view('usulan.add-usulan');
})->name('usulan.create');
Route::get('/list-usulan', function () {
    return view('usulan.list-usulan');
})->name('usulan.list');
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
    return view('keuangan');
})->name('keuangan');

Route::get('/register', function () {
    return view('register');
})->name('register');

Route::get('/login', function () {
    return view('login');
})->name('login');