@extends('app')

@section('title', 'Jadwal Perjalanan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Jadwal Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Pegawai yang akan berangkat — jadwal pasti maupun yang masih dalam pengajuan</p>
        </div>

        {{-- Rekap bulanan untuk Tim SDM dan pimpinan --}}
        <form method="GET" action="{{ route('jadwal-perjalanan.ekspor') }}" class="flex items-center gap-2 shrink-0">
            <input type="hidden" name="kepastian" value="{{ $kepastian }}">
            <input type="hidden" name="search" value="{{ $search }}">

            <select name="bulan"
                    class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                @forelse ($bulanTersedia as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($nilai === ($bulan ?? now()->format('Y-m')))>{{ $label }}</option>
                @empty
                    <option value="{{ now()->format('Y-m') }}">{{ now()->translatedFormat('F Y') }}</option>
                @endforelse
            </select>

            <button type="submit"
                    class="flex items-center gap-2 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export Rekap
            </button>
        </form>
    </div>

    {{-- Halaman ini memang tidak memuat biaya maupun berkas pertanggungjawaban. --}}
    <div class="mb-5 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <p>Daftar memuat identitas peserta, tujuan, dan waktu keberangkatan. Nominal biaya serta dokumen pertanggungjawaban tidak ditampilkan.</p>
    </div>

    {{-- Ringkasan kartu mengikuti bulan berjalan: begitu bulannya berganti,
         angkanya ikut berpindah tanpa perlu disaring manual. --}}
    <div class="flex items-baseline justify-between mb-2.5">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Ringkasan Bulan Berjalan</p>
        <p class="text-xs font-bold text-teal-700">{{ $labelBulanIni }}</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-2xl font-bold text-emerald-700">{{ $totalFix }}</p>
            <p class="text-xs text-slate-400">Jadwal Sudah Fix</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
            <p class="text-2xl font-bold text-amber-700">{{ $totalSementara }}</p>
            <p class="text-xs text-slate-400">Masih Diajukan</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalPegawai }}</p>
                <p class="text-xs text-slate-400">Pegawai Berangkat</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalPerjalanan }}</p>
                <p class="text-xs text-slate-400">Perjalanan Dinas</p>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('jadwal-perjalanan') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, NIP, atau kota tujuan..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <select name="kepastian"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="semua" @selected($kepastian === 'semua')>Semua kepastian</option>
                    <option value="fix" @selected($kepastian === 'fix')>Sudah fix</option>
                    <option value="sementara" @selected($kepastian === 'sementara')>Masih diajukan</option>
                </select>
                <select name="periode"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="mendatang" @selected($periode === 'mendatang')>Akan berangkat</option>
                    <option value="berlalu" @selected($periode === 'berlalu')>Sudah berlalu</option>
                    <option value="semua" @selected($periode === 'semua')>Semua periode</option>
                </select>
                <select name="bulan"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">Semua bulan</option>
                    @foreach ($bulanTersedia as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($bulan === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
                @if ($search || $bulan)
                    <a href="{{ route('jadwal-perjalanan') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Keberangkatan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $peserta->count() }} pegawai</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-16">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Nama Pegawai</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">NIP</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Unit Kerja</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Kota Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-52">Periode Keberangkatan</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-28">Peran</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-36">Kepastian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($peserta as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-4 text-center text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-4 font-semibold text-slate-800">
                                {{ $item->nama }}
                                @if ($item->jabatan)
                                    <span class="block text-xs font-normal text-slate-400">{{ $item->jabatan }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-500">{{ $item->nip ?? '—' }}</td>
                            <td class="px-4 py-4 text-slate-500">{{ $item->user?->unit?->nama ?? '—' }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ $item->usulan?->lokasi ?? '—' }}</td>
                            <td class="px-4 py-4 text-slate-600">
                                @if ($item->usulan)
                                    {{ \Carbon\Carbon::parse($item->usulan->tanggal_mulai)->translatedFormat('d M Y') }}
                                    <span class="text-slate-300">—</span>
                                    {{ \Carbon\Carbon::parse($item->usulan->tanggal_selesai)->translatedFormat('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->peran === 'ketua' ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $item->peran_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                @php $statusUsulan = \App\Enums\StatusUsulan::dari($item->usulan?->status); @endphp
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full {{ $statusUsulan->sudahFix() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $statusUsulan->sudahFix() ? 'Fix' : 'Diajukan' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    <p class="text-sm text-slate-400">Belum ada pegawai yang dijadwalkan berangkat</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
