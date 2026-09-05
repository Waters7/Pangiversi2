<?php

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
