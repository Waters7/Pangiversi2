@extends('app')

@section('title', 'List Laporan Perjalanan Dinas')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">List Laporan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Laporan dari seluruh perjalanan dinas yang sudah berlaku
            </p>
        </div>
        <a href="{{ route('dokumen') }}" class="text-xs font-bold text-teal-600 hover:text-teal-700">← Kembali ke Dokumen</a>
    </div>

    <x-flash />

    <x-kotak-cari :rute="route('dokumen.laporan.index')" :nilai="$cari"
                  petunjuk="Cari no. usulan, no. surat tugas, tujuan, atau nama pelaksana" />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $usulan->total() }}</p>
                <p class="text-xs text-slate-400">Total Perjalanan</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $jumlahBelumSelesai }}</p>
                <p class="text-xs text-slate-400">Laporan Belum Selesai</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <th class="px-4 py-3 font-semibold">No. Usulan</th>
                        <th class="px-4 py-3 font-semibold">Tujuan</th>
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 font-semibold">Hasil</th>
                        <th class="px-4 py-3 font-semibold text-center">Laporan</th>
                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($usulan as $item)
                        @php
                            $laporan = $item->laporan;
                            $selesai = $laporan?->sudahSelesai() === true;
                            // Terkunci setelah perjalanannya ditutup; sejak itu
                            // berkasnya sudah divalidasi dan dibayarkan.
                            $bolehUbah = $laporan ? $laporan->bolehDisunting() : $item->status !== 'selesai';
                            $statusLaporan = $laporan?->status() ?? \App\Enums\StatusLaporanPerjadin::Draf;
                        @endphp

                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3.5 font-mono text-xs font-semibold text-slate-700">{{ $item->no_usulan }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $item->lokasi }}</td>
                            <td class="px-4 py-3.5 text-slate-500 text-xs whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($item->tanggal_mulai)->translatedFormat('d M') }}–{{ \Carbon\Carbon::parse($item->tanggal_selesai)->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($laporan?->statusHasil)
                                    <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $laporan->statusHasil->badge }}">
                                        {{ $laporan->statusHasil->nama }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $statusLaporan->badge() }}">
                                    {{ $statusLaporan->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('dokumen.laporan.show', $item->no_usulan) }}"
                                       class="text-xs font-bold text-slate-500 hover:text-teal-600">Lihat</a>

                                    {{-- Tautan ubah disembunyikan bila tak lagi berwenang,
                                         supaya tidak menjanjikan sesuatu yang berujung 403. --}}
                                    @if ($bolehUbah)
                                        <a href="{{ route('dokumen.laporan.edit', $item->no_usulan) }}"
                                           class="text-xs font-bold text-teal-600 hover:text-teal-700">Edit</a>
                                    @else
                                        <span class="text-xs text-slate-300" title="{{ $statusLaporan->keterangan() }}">Terkunci</span>
                                    @endif

                                    @if ($selesai)
                                        <a href="{{ route('dokumen.laporan.cetak', $item->no_usulan) }}"
                                           class="text-xs font-bold text-slate-500 hover:text-teal-600">Unduh</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <svg class="w-10 h-10 text-slate-200 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <p class="text-sm text-slate-400 mt-2">Belum ada perjalanan dinas yang perlu dilaporkan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$usulan" />
    </div>
</div>

@endsection
