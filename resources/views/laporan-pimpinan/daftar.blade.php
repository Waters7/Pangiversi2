@extends('app')

@section('title', 'Daftar Laporan Perjadin')

@section('content')

@php
    use App\Enums\StatusLaporanPerjadin;

    // Tab meja pimpinan: yang menunggu didahulukan, lalu tahap lainnya.
    // "Semua" memakai nilai eksplisit karena tanpa parameter status halaman
    // ini justru membuka tab Menunggu.
    $tab = ['semua' => ['label' => 'Semua', 'jumlah' => array_sum($jumlah)]];
    foreach ([
        StatusLaporanPerjadin::MenungguKonfirmasi,
        StatusLaporanPerjadin::PerluRevisi,
        StatusLaporanPerjadin::Dikonfirmasi,
        StatusLaporanPerjadin::Selesai,
        StatusLaporanPerjadin::Draf,
    ] as $s) {
        $tab[$s->value] = ['label' => $s->label(), 'jumlah' => $jumlah[$s->value], 'badge' => $s->badge()];
    }

    $terpilih = $status?->value
        ?? (request()->has('status') ? 'semua' : StatusLaporanPerjadin::MenungguKonfirmasi->value);
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Daftar Laporan Perjadin</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Laporan perjalanan dinas seluruh pelaksana — yang menunggu konfirmasi Anda didahulukan
        </p>
    </div>

    <x-flash />

    @if ($jumlah[StatusLaporanPerjadin::MenungguKonfirmasi->value] > 0)
        <div class="mb-5 flex items-center gap-3 px-5 py-4 bg-amber-50 border border-amber-200 rounded-2xl">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            <p class="text-sm text-amber-800">
                <strong>{{ $jumlah[StatusLaporanPerjadin::MenungguKonfirmasi->value] }} laporan</strong>
                menunggu konfirmasi Anda. Pelunasan pembayaran pelaksananya baru dapat diproses setelah dikonfirmasi.
            </p>
        </div>
    @endif

    <x-kotak-cari :rute="route('laporan-perjadin.index')" :nilai="$cari" :sembunyi="['status' => request('status')]"
                  petunjuk="Cari nama pelaksana, no. usulan, no. surat tugas, atau tujuan" />

    <x-tab-status :aksi="route('laporan-perjadin.index')" :terpilih="$terpilih" :tab="$tab" />

    @include('laporan-pimpinan.partials.tabel', [
        'kosong' => $status === StatusLaporanPerjadin::MenungguKonfirmasi
            ? 'Tidak ada laporan yang menunggu konfirmasi Anda'
            : 'Tidak ada laporan pada tahap ini',
    ])
</div>

@endsection
