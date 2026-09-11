@extends('app')

@section('title', 'Status Konfirmasi Laporan Perjadin')

@section('content')

@php
    use App\Enums\StatusLaporanPerjadin;

    $urutan = [
        StatusLaporanPerjadin::MenungguKonfirmasi,
        StatusLaporanPerjadin::PerluRevisi,
        StatusLaporanPerjadin::Dikonfirmasi,
        StatusLaporanPerjadin::Selesai,
        StatusLaporanPerjadin::Draf,
    ];

    $total = array_sum($jumlah);

    $ikon = [
        StatusLaporanPerjadin::MenungguKonfirmasi->value => 'bg-amber-100 text-amber-700',
        StatusLaporanPerjadin::PerluRevisi->value => 'bg-red-100 text-red-700',
        StatusLaporanPerjadin::Dikonfirmasi->value => 'bg-emerald-100 text-emerald-700',
        StatusLaporanPerjadin::Selesai->value => 'bg-blue-100 text-blue-700',
        StatusLaporanPerjadin::Draf->value => 'bg-slate-100 text-slate-600',
    ];
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Status Konfirmasi Laporan Perjadin</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Sampai di mana tiap laporan perjalanan dinas — dari disusun sampai ditandatangani
        </p>
    </div>

    <x-flash />

    {{-- Kartu ringkasan: klik untuk menyaring daftar di bawahnya. --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ($urutan as $s)
            @php $aktif = $status === $s; @endphp
            <a href="{{ route('laporan-perjadin.status', ['status' => $s->value]) }}"
               class="bg-white rounded-2xl border shadow-sm p-4 transition hover:shadow-md
                      {{ $aktif ? 'border-teal-400 ring-2 ring-teal-100' : 'border-slate-100' }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $ikon[$s->value] }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            @switch($s)
                                @case(StatusLaporanPerjadin::Dikonfirmasi)
                                    <path d="M5 13l4 4L19 7"/>
                                    @break
                                @case(StatusLaporanPerjadin::PerluRevisi)
                                    <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>
                                    @break
                                @case(StatusLaporanPerjadin::MenungguKonfirmasi)
                                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                                    @break
                                @default
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            @endswitch
                        </svg>
                    </span>
                    <span class="text-2xl font-bold text-slate-800">{{ $jumlah[$s->value] }}</span>
                </div>
                <p class="text-xs font-bold text-slate-700 mt-3 leading-tight">{{ $s->label() }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">{{ $s->keterangan() }}</p>
            </a>
        @endforeach
    </div>

    {{-- Batang proporsi, supaya sekali lihat tahu seberapa jauh keseluruhan. --}}
    @if ($total > 0)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <p class="text-sm font-bold text-slate-800">{{ $total }} perjalanan dinas berlaku</p>
                <p class="text-xs text-slate-400">
                    {{ $jumlah[StatusLaporanPerjadin::Dikonfirmasi->value] }} dikonfirmasi
                    ({{ round($jumlah[StatusLaporanPerjadin::Dikonfirmasi->value] / $total * 100) }}%)
                </p>
            </div>
            <div class="flex h-3 rounded-full overflow-hidden bg-slate-100">
                @foreach ($urutan as $s)
                    @if ($jumlah[$s->value] > 0)
                        <span class="{{ explode(' ', $ikon[$s->value])[0] }}"
                              style="width: {{ $jumlah[$s->value] / $total * 100 }}%"
                              title="{{ $s->label() }}: {{ $jumlah[$s->value] }}"></span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <x-kotak-cari :rute="route('laporan-perjadin.status')" :nilai="$cari" :sembunyi="['status' => request('status')]"
                  petunjuk="Cari nama pelaksana, no. usulan, no. surat tugas, atau tujuan" />

    @if ($status)
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-bold text-slate-700">
                Laporan pada tahap
                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $status->badge() }}">{{ $status->label() }}</span>
            </p>
            <a href="{{ route('laporan-perjadin.status') }}" class="text-xs font-bold text-teal-600 hover:text-teal-700">
                Tampilkan semua
            </a>
        </div>
    @endif

    @include('laporan-pimpinan.partials.tabel', ['kosong' => 'Tidak ada laporan pada tahap ini'])
</div>

@endsection
