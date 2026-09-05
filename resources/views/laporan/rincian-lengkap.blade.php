@extends('app')

@section('title', 'Rincian Biaya Lengkap')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Rincian Biaya Lengkap Tanda Tangan</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Rincian biaya perjalanan dinas yang sudah ditandatangani pelaksana dan PPK, serta
            daftar nominatifnya sudah diterima tim keuangan. Sejak itu dokumennya sah sebagai
            dasar pembayaran dan tidak berubah lagi.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-slate-800 leading-none">{{ $jumlahBerkas }}</p>
                <p class="text-xs text-slate-500 font-medium mt-1">Berkas lengkap pada saringan ini</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-bold text-slate-800 truncate">Rp {{ number_format($totalNilai, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 font-medium">Nilai rincian biayanya</p>
            </div>
        </div>
    </div>

    <x-saring-periode
        :aksi="route('laporan.rincian-lengkap')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['cari' => $cari]" />

    <form method="GET" action="{{ route('laporan.rincian-lengkap') }}" class="mb-5">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="flex gap-2">
            <input type="text" name="cari" value="{{ $cari }}"
                   placeholder="Cari no. perjadin, no. surat tugas, atau nama pelaksana..."
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Cari</button>
            @if ($cari)
                <a href="{{ route('laporan.rincian-lengkap', ['tahun' => $tahun, 'bulan' => $bulan]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Pelaksana</th>
                        <th class="px-5 py-3 font-semibold">No. Perjadin</th>
                        <th class="px-5 py-3 font-semibold">Surat Tugas</th>
                        <th class="px-5 py-3 font-semibold text-right">Nilai Rincian</th>
                        <th class="px-5 py-3 font-semibold">Tanda Tangan</th>
                        <th class="px-5 py-3 font-semibold text-right w-28">Cetak</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftar as $periode => $kelompok)
                        <tr class="bg-slate-100/70">
                            <td colspan="6" class="px-5 py-2">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $periode }}</span>
                                <span class="ml-2 text-[11px] font-bold text-slate-400">
                                    {{ $kelompok->count() }} berkas ·
                                    Rp {{ number_format($kelompok->sum(fn ($b) => $b['total']), 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>

                        @foreach ($kelompok as $baris)
                            @php
                                $item = $baris['berkas'];
                                $usulan = $baris['usulan'];
                            @endphp

                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-slate-800">{{ $item->peserta?->nama ?? $usulan?->user?->nama ?? '—' }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $usulan?->lokasi }}</p>
                                </td>

                                <td class="px-5 py-3.5 font-mono text-xs text-slate-600">{{ $usulan?->no_usulan ?? '—' }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-slate-500">{{ $usulan?->no_tugas ?? '—' }}</td>

                                <td class="px-5 py-3.5 text-right font-bold text-slate-800 whitespace-nowrap">
                                    Rp {{ number_format($baris['total'], 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                        Lengkap
                                    </span>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        PPK {{ $item->ppk?->nama ?? '—' }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ $baris['tanggal']?->translatedFormat('d M Y, H:i') }}
                                    </p>
                                </td>

                                <td class="px-5 py-3.5 text-right">
                                    @if ($usulan)
                                        <a href="{{ route('keuangan.cetak-rincian', $usulan->no_usulan) }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                                                <rect x="6" y="14" width="12" height="8"/>
                                            </svg>
                                            Cetak
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Belum ada rincian biaya yang lengkap tanda tangannya</p>
                                <p class="text-xs text-slate-400 mt-1.5 max-w-md mx-auto leading-relaxed">
                                    Berkas muncul di sini setelah pelaksana dan PPK menandatangani kedua dokumennya,
                                    dan daftar nominatif surat tugasnya sudah diterima tim keuangan.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
