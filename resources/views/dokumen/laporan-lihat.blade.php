@extends('app')

@section('title', 'Laporan Perjalanan Dinas')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dokumen.laporan.index') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Laporan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }}</p>
        </div>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            @if ($laporan->sudahSelesai())
                <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                    Unduh Dokumen
                </a>
            @endif
            @if ($laporan->bolehDisunting())
                <a href="{{ route('dokumen.laporan.edit', $usulan->no_usulan) }}"
                   class="px-4 py-2 border border-slate-200 bg-white text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition">
                    Edit Laporan
                </a>
            @endif
        </div>
    </div>

    <x-flash />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-5">

            @include('dokumen.partials.isi-laporan')
        </div>

        <div class="space-y-5">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-3">Hasil yang Dicapai</h3>
                @if ($laporan->statusHasil)
                    <span class="inline-block text-sm font-bold px-3 py-1.5 rounded-full {{ $laporan->statusHasil->badge }}">
                        {{ $laporan->statusHasil->nama }}
                    </span>
                    @if ($laporan->statusHasil->keterangan)
                        <p class="text-xs text-slate-400 mt-2">{{ $laporan->statusHasil->keterangan }}</p>
                    @endif
                @else
                    <p class="text-sm text-slate-400">Belum dipilih.</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Keterangan</h3>
                @include('dokumen.partials.keterangan-laporan')
            </div>
        </div>
    </div>
</div>

@endsection
