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
