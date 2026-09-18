<?php

use App\Models\LogApi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat kelengkapan berkas dijalankan sekali sehari pada jam kerja.
// Tenggang dan jedanya diatur pada menu Administrasi Sistem.
Schedule::command('pangi:pengingat-dokumen')
    ->dailyAt('08:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

// Pengiriman data ke aplikasi tujuan: diperiksa tiap menit, dikirim hanya
// saat jadwal pada menu Integrasi Data jatuh tempo.
Schedule::command('pangi:kirim-integrasi')
    ->everyMinute()
    ->withoutOverlapping();

// Catatan permintaan API yang sudah lewat masa simpannya dibersihkan tiap malam.
Schedule::call(fn () => LogApi::where('created_at', '<', now()->subDays(LogApi::SIMPAN_HARI))->delete())
    ->dailyAt('01:00')
    ->timezone('Asia/Makassar')
    ->name('bersihkan-log-api');
