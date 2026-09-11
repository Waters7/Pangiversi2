@extends('app')

@section('title', 'Persetujuan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Persetujuan</h1>
            <p class="text-xs text-slate-400 mt-0.5">Alur berjenjang: atasan langsung → SDM → PPK → direktur</p>
        </div>
    </div>

    <x-flash />

    {{-- Tab antrian vs seluruh usulan --}}
    <div class="flex gap-2 mb-5">
        <a href="{{ route('persetujuan', ['tab' => 'antrian']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $tab === 'antrian' ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            Menunggu Keputusan Saya
            @if ($jumlahAntrian > 0)
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ $tab === 'antrian' ? 'bg-white/25' : 'bg-red-100 text-red-600' }}">{{ $jumlahAntrian }}</span>
            @endif
        </a>
        <a href="{{ route('persetujuan', ['tab' => 'semua']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $tab !== 'antrian' ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            Semua Usulan
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('persetujuan') }}">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
        <div class="flex flex-col sm:flex-row gap-3">

            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Cari no. usulan, kegiatan, lokasi, instansi..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>

            <select name="status" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-[160px]">
                <option value="">Semua Status</option>
                @foreach ($statusOptions as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(request('status') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="hidden" name="tab" value="{{ $tab }}">

            <button type="submit"
                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                Filter
            </button>

            @if(request('search') || request('status'))
                <a href="{{ route('persetujuan') }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                    Reset
                </a>
            @endif

        </div>
    </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Persetujuan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ $usulan->total() }} data
                </span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3.5">Usulan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Pemohon</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Periode</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-50">
                    @foreach ($usulan as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800 text-xs">{{ $item->no_usulan }}</p>
                                <p class="text-xs text-slate-400">{{ $item->created_at?->translatedFormat('d M Y') ?? '—' }}</p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-slate-700">{{ $item->user?->nama ?? '—' }}</p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm text-slate-700">{{ $item->kategoriPerjadin?->nama ?? $item->kegiatan?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $item->lokasi }}</p>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <p class="text-xs font-semibold text-slate-700">
                                    {{ $item->periode }}
                                </p>
                                <p class="text-xs text-slate-400">{{ $item->durasi }} hari</p>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center">
                                    <x-status-badge :usulan="$item" />
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center">
                                    <a href="{{ route('persetujuan.detail', $item->no_usulan) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold transition border border-slate-200 hover:border-teal-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Lihat
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

</div>

@endsection