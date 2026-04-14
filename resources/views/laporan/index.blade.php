@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Pusat Laporan</h1>
                <p class="text-xs text-slate-400 mt-0.5">Rekap seluruh laporan perjalanan dinas & anggaran</p>
            </div>
        </div>
        <a href="{{ route('laporan.export', request()->query()) }}"
           class="hidden sm:flex items-center gap-2 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Unduh CSV
        </a>
    </div>

    {{-- ── Stat Cards ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['total_pejadin'] }}</p>
                <p class="text-xs text-slate-500 font-medium">Total Pejadin</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($stats['total_anggaran'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 font-medium">Total Anggaran</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($stats['total_terbayar'], 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 font-medium">Total Terbayar</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div class="flex items-center gap-3">
                <div>
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ $stats['lunas'] }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 ml-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> {{ $stats['sebagian'] }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 ml-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> {{ $stats['belum'] }}
                    </span>
                </div>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">Status Bayar</p>
            </div>
        </div>
    </div>

    {{-- ── Filter & Search ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-6">
        <form method="GET" action="{{ route('laporan') }}" class="px-6 py-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari no. usulan, pengusul, tujuan..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition bg-white">
                </div>
                <select name="status_keuangan"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-40">
                    <option value="">Semua Status Bayar</option>
                    <option value="belum bayar" {{ $status === 'belum bayar' ? 'selected' : '' }}>Belum Bayar</option>
                    <option value="bayar sebagian" {{ $status === 'bayar sebagian' ? 'selected' : '' }}>Bayar Sebagian</option>
                    <option value="lunas" {{ $status === 'lunas' ? 'selected' : '' }}>Lunas</option>
                </select>
                <input type="month" name="bulan" value="{{ $bulan }}" placeholder="Bulan"
                       class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <button type="submit"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                    Filter
                </button>
                @if($search || $status || $bulan)
                    <a href="{{ route('laporan') }}"
                       class="px-4 py-2.5 border border-slate-200 bg-white text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ═══ TABEL LAPORAN PEJADIN ═══ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-sm">Rekap Laporan Perjalanan Dinas</h2>
                    <p class="text-xs text-slate-400">{{ $usulan->total() }} data ditemukan</p>
                </div>
            </div>
            {{-- Mobile download --}}
            <a href="{{ route('laporan.export', request()->query()) }}"
               class="sm:hidden flex items-center gap-1.5 px-3 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-10">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Pengusul</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kegiatan & Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Periode</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Total Anggaran</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Uang Muka</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Sisa</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($usulan as $i => $item)
                        @php
                            $keu = $item->keuangan;
                            $statusConfig = match($keu?->status) {
                                'lunas'           => ['label' => 'Lunas',          'class' => 'bg-emerald-50 text-emerald-700'],
                                'bayar sebagian'  => ['label' => 'Sebagian',       'class' => 'bg-blue-50 text-blue-700'],
                                'belum bayar'     => ['label' => 'Belum Bayar',    'class' => 'bg-amber-50 text-amber-700'],
                                default           => ['label' => 'Belum Ada',      'class' => 'bg-slate-100 text-slate-500'],
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4 text-slate-500 font-medium">{{ $usulan->firstItem() + $i }}</td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2.5">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($item->user?->nama ?? 'U') }}&background=14b8a6&color=fff&size=32"
                                         class="w-7 h-7 rounded-full shrink-0" alt="">
                                    <div>
                                        <p class="font-semibold text-slate-800 text-xs">{{ $item->user?->nama ?? '—' }}</p>
                                        <p class="text-xs text-slate-400 font-mono">{{ $item->no_usulan }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-xs font-semibold text-slate-700">{{ $item->kegiatan?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $item->lokasi }} — {{ $item->instansi }}</p>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <p class="text-xs font-semibold text-slate-700">{{ $item->tanggal_mulai_formatted }}</p>
                                <p class="text-xs text-slate-400">{{ $item->durasi }} hari</p>
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <p class="text-xs font-bold text-slate-800">Rp {{ number_format($keu?->total ?? 0, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <p class="text-xs font-semibold text-teal-700">Rp {{ number_format($keu?->uang_muka ?? 0, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <p class="text-xs font-semibold text-slate-600">Rp {{ number_format($keu?->sisa ?? 0, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('laporan.show', $item->no_usulan) }}"
                                       class="p-2 rounded-lg hover:bg-teal-50 text-slate-400 hover:text-teal-600 transition" title="Lihat Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('laporan.export-detail', $item->no_usulan) }}"
                                       class="p-2 rounded-lg hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition" title="Unduh CSV">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm text-slate-400">Belum ada data laporan perjalanan dinas.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- TFOOT: Totals --}}
                @if($usulan->isNotEmpty())
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    @php
                        $pageTotal = $usulan->sum(fn ($u) => $u->keuangan?->total ?? 0);
                        $pageUM = $usulan->sum(fn ($u) => $u->keuangan?->uang_muka ?? 0);
                        $pageSisa = $usulan->sum(fn ($u) => $u->keuangan?->sisa ?? 0);
                    @endphp
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-right text-xs font-bold text-slate-600 uppercase">Total halaman ini</td>
                        <td class="px-4 py-3 text-right text-xs font-bold text-slate-800">Rp {{ number_format($pageTotal, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-xs font-bold text-teal-700">Rp {{ number_format($pageUM, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-xs font-bold text-slate-600">Rp {{ number_format($pageSisa, 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Pagination --}}
        @if($usulan->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $usulan->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
