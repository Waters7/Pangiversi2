@extends('app')

@section('title', 'Jejak Audit')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Jejak Audit</h1>
        <p class="text-xs text-slate-400 mt-0.5">Riwayat seluruh tindakan pengguna — siapa, kapan, aksi apa, dan catatannya</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ number_format($totalLog, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400">Total Catatan Audit</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ number_format($logHariIni, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400">Tindakan Hari Ini</p>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('audit-log') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="relative md:col-span-2">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari deskripsi, pelaku, atau no. usulan..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <select name="aksi"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">Semua aksi</option>
                    @foreach ($aksiOptions as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($aksi === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="dari" value="{{ $dari }}" title="Dari tanggal"
                       class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <input type="date" name="sampai" value="{{ $sampai }}" title="Sampai tanggal"
                       class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Terapkan Filter</button>
                @if ($search || $aksi || $dari || $sampai)
                    <a href="{{ route('audit-log') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
                @endif
            </div>
        </div>
        <input type="hidden" name="peran" value="{{ $peran }}">
    </form>

    {{-- Dikelompokkan per peran: menelusuri "siapa melakukan apa" biasanya
         dimulai dari perannya, bukan dari nama orangnya. --}}
    <x-tab-status
        :aksi="route('audit-log')"
        kunci="peran"
        :terpilih="$peran"
        :tab="collect(['' => ['label' => 'Semua Peran', 'jumlah' => $totalLog]])
                ->merge($perPeran)
                ->all()" />

    {{-- Tabel log --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Catatan Audit
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $logs->total() }} data</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-44">Waktu</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-28">Aksi</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Deskripsi</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-44">Pelaku</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-36">Usulan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/60 transition align-top">
                            <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ $log->created_at->translatedFormat('d M Y') }}
                                <span class="block text-slate-400">{{ $log->created_at->format('H:i:s') }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $log->aksi_badge }}">{{ $log->aksi_label }}</span>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-slate-700">{{ $log->deskripsi }}</p>
                                @if ($log->adaPerubahanStatus())
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $log->status_lama_label }} → <span class="font-semibold text-slate-600">{{ $log->status_baru_label }}</span></p>
                                @endif
                                @if ($log->catatan)
                                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-2 py-1 mt-1.5 inline-block">{{ $log->catatan }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-700">{{ $log->pelaku?->nama ?? 'Sistem' }}</p>
                                <p class="text-xs text-slate-400">{{ $log->ip_address ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($log->usulan)
                                    <a href="{{ route('usulan.show', $log->usulan->no_usulan) }}"
                                       class="text-xs font-semibold text-teal-600 hover:underline">{{ $log->usulan->no_usulan }}</a>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                                    </svg>
                                    <p class="text-sm text-slate-400">Tidak ada catatan audit yang cocok</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$logs" />
    </div>

</div>

@endsection
