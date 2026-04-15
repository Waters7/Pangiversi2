@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl text-sm text-teal-700">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                @if(auth()->user()->isAdmin())
                    Kelola seluruh usulan perjalanan dinas semua pegawai
                @else
                    Kelola dan pantau seluruh usulan perjalanan dinas Anda
                @endif
            </p>
        </div>
        <a href="{{ route('usulan.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Buat Usulan Baru
        </a>
    </div>

    {{-- Filter & Search Bar --}}
    <form method="GET" action="{{ route('usulan.list') }}">
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
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                <option value="diajukan" @selected(request('status') === 'diajukan')>Diajukan</option>
                <option value="menunggu" @selected(request('status') === 'menunggu')>Menunggu</option>
                <option value="disetujui" @selected(request('status') === 'disetujui')>Disetujui</option>
                <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
            </select>

            <button type="submit"
                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                Filter
            </button>

            @if(request('search') || request('status'))
                <a href="{{ route('usulan.list') }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                    Reset
                </a>
            @endif

        </div>
    </div>
    </form>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 mb-5">

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalUsulan }}</p>
                <p class="text-xs text-slate-400">Total Usulan</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $menunggu }}</p>
                <p class="text-xs text-slate-400">Menunggu</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $disetujui }}</p>
                <p class="text-xs text-slate-400">Disetujui</p>
            </div>
        </div>


        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 text-red-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $ditolak }}</p>
                <p class="text-xs text-slate-400">Ditolak</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            </div>
            <div>
            <p class="text-2xl font-bold text-slate-800">{{ $selesai }}</p>
            <p class="text-xs text-slate-400">Selesai</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $draft }}</p>
                <p class="text-xs text-slate-400">Draft</p>
            </div>
        </div>
    </div>


    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <p class="text-sm font-bold text-slate-700">
                Daftar Usulan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">12 data</span>
            </p>
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                </svg>
                Terbaru di atas
            </div>
        </div>

        {{-- Table (all screen sizes) --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">No. / Tanggal</th>
                        @if(auth()->user()->isAdmin())
                            <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Pemohon</th>
                        @endif
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Kegiatan & Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 hidden sm:table-cell">Periode</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">

                    @foreach ($usulan as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-4 text-center text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-4">
                                <p class="font-bold text-slate-800 text-xs whitespace-nowrap">{{ $item->no_usulan }}</p>
                                <p class="text-xs text-slate-400 mt-0.5 whitespace-nowrap">12 Jan 2025</p>
                            </td>
                            @if(auth()->user()->isAdmin())
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800 text-xs whitespace-nowrap">{{ $item->user->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $item->user->nip ?? '-' }}</p>
                                </td>
                            @endif
                            <td class="px-4 py-4 max-w-[160px] sm:max-w-xs">
                                <p class="font-semibold text-slate-800 text-xs sm:text-sm leading-snug line-clamp-2">{{ $item->kegiatan->nama }}</p>
                                <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ $item->no_tugas }}</p>
                                <div class="flex items-center gap-1 mt-1.5">
                                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                    </svg>
                                    <span class="text-xs text-slate-500 line-clamp-1">{{ $item->lokasi }}</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5 sm:hidden">{{ $item->periode }} · {{ $item->durasi }} hari</p>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap hidden sm:table-cell">
                                <p class="text-xs font-semibold text-slate-700">{{ $item->periode }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $item->durasi }} hari</p>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap {{ $item->status_badge }}">
                                    {{ $item->status_text }}
                                </span>
                                @if($item->status === 'ditolak' && $item->catatan)
                                    <p class="text-xs text-red-400 mt-1 max-w-[120px] mx-auto line-clamp-2" title="{{ $item->catatan }}">
                                        {{ $item->catatan }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('usulan.show', $item) }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if(in_array($item->status, ['draft', 'ditolak']) || auth()->user()->isAdmin())
                                        <a href="{{ route('usulan.edit', $item) }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Edit">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @else
                                        <span class="w-7 h-7 sm:w-8 sm:h-8"></span>
                                    @endif
                                    @if(in_array($item->status, ['draft', 'ditolak']) || auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('usulan.destroy', $item) }}"
                                              x-data
                                              @submit.prevent="if(confirm('Hapus usulan {{ $item->no_usulan }}?\nTindakan ini tidak dapat dibatalkan.')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 flex items-center justify-center transition" title="Hapus">
                                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="w-7 h-7 sm:w-8 sm:h-8"></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-4">
            <p class="text-xs text-slate-400">
                Menampilkan {{ $usulan->firstItem() ?? 0 }}–{{ $usulan->lastItem() ?? 0 }} dari {{ $usulan->total() }} data
            </p>
            <div class="flex items-center gap-1">
                {{-- Prev --}}
                @if ($usulan->onFirstPage())
                    <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </span>
                @else
                    <a href="{{ $usulan->previousPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                @endif

                {{-- Page numbers --}}
                @foreach ($usulan->getUrlRange(max(1, $usulan->currentPage() - 2), min($usulan->lastPage(), $usulan->currentPage() + 2)) as $page => $url)
                    @if ($page === $usulan->currentPage())
                        <span class="w-8 h-8 rounded-lg bg-teal-500 text-white text-xs font-bold flex items-center justify-center">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 flex items-center justify-center transition">{{ $page }}</a>
                    @endif
                @endforeach

                {{-- Next --}}
                @if ($usulan->hasMorePages())
                    <a href="{{ $usulan->nextPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                @else
                    <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </span>
                @endif
            </div>
        </div>

    </div>

</div>

@endsection