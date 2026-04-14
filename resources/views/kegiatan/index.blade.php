@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="{ showTambah: false, editId: null, editNama: '' }">

    {{-- Flash Messages --}}
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
            <h1 class="text-xl font-bold text-slate-800">Administrasi Sistem</h1>
            <p class="text-xs text-slate-400 mt-0.5">Kelola pengguna dan jenis kegiatan</p>
        </div>
        <button @click="showTambah = !showTambah"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Kegiatan
        </button>
    </div>

    {{-- Sub Navigation --}}
    <div class="flex gap-2 mb-5">
        <a href="{{ route('administrasi') }}"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Pengguna
        </a>
        <a href="{{ route('kegiatan.index') }}"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition bg-teal-500 text-white shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Jenis Kegiatan
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalKegiatan }}</p>
                <p class="text-xs text-slate-400">Total Jenis Kegiatan</p>
            </div>
        </div>
    </div>

    {{-- Form Tambah (Collapsible) --}}
    <div x-show="showTambah" x-transition class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <h3 class="font-bold text-slate-800 text-sm mb-4">Tambah Jenis Kegiatan Baru</h3>
        <form method="POST" action="{{ route('kegiatan.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <div class="flex-1">
                <input type="text" name="nama" placeholder="Nama jenis kegiatan..." value="{{ old('nama') }}" required
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                @error('nama')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                    class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition whitespace-nowrap">
                Simpan
            </button>
        </form>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('kegiatan.index') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}"
                           placeholder="Cari jenis kegiatan..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <button type="submit"
                        class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                    Cari
                </button>
                @if($search)
                    <a href="{{ route('kegiatan.index') }}"
                       class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <p class="text-sm font-bold text-slate-700">
                Daftar Jenis Kegiatan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $kegiatan->total() }} data</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-16">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Nama Kegiatan</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-32">Digunakan</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($kegiatan as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-4 text-center text-xs font-semibold text-slate-400">
                                {{ ($kegiatan->currentPage() - 1) * $kegiatan->perPage() + $loop->iteration }}
                            </td>
                            <td class="px-4 py-4">
                                {{-- Display mode --}}
                                <template x-if="editId !== {{ $item->id }}">
                                    <p class="font-semibold text-slate-800">{{ $item->nama }}</p>
                                </template>

                                {{-- Edit mode --}}
                                <template x-if="editId === {{ $item->id }}">
                                    <form method="POST" action="{{ route('kegiatan.update', $item) }}" class="flex gap-2" id="editForm{{ $item->id }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="nama" x-model="editNama" required
                                               class="flex-1 px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                                            Simpan
                                        </button>
                                        <button type="button" @click="editId = null"
                                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                                            Batal
                                        </button>
                                    </form>
                                </template>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-600">
                                    {{ $item->usulan_count }} usulan
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    {{-- Edit --}}
                                    <template x-if="editId !== {{ $item->id }}">
                                        <button @click="editId = {{ $item->id }}; editNama = '{{ addslashes($item->nama) }}'"
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                    </template>
                                    {{-- Delete --}}
                                    <form method="POST" action="{{ route('kegiatan.destroy', $item) }}"
                                          x-data
                                          @submit.prevent="if(confirm('Hapus kegiatan &quot;{{ addslashes($item->nama) }}&quot;?\nTindakan ini tidak dapat dibatalkan.')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 flex items-center justify-center transition" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    <p class="text-sm text-slate-400">Belum ada jenis kegiatan</p>
                                    <button @click="showTambah = true"
                                            class="text-sm text-teal-600 font-semibold hover:underline">
                                        + Tambah Kegiatan Pertama
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($kegiatan->hasPages())
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-4">
            <p class="text-xs text-slate-400">
                Menampilkan {{ $kegiatan->firstItem() ?? 0 }}–{{ $kegiatan->lastItem() ?? 0 }} dari {{ $kegiatan->total() }} data
            </p>
            <div class="flex items-center gap-1">
                @if ($kegiatan->onFirstPage())
                    <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </span>
                @else
                    <a href="{{ $kegiatan->previousPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                @endif

                @foreach ($kegiatan->getUrlRange(max(1, $kegiatan->currentPage() - 2), min($kegiatan->lastPage(), $kegiatan->currentPage() + 2)) as $page => $url)
                    @if ($page === $kegiatan->currentPage())
                        <span class="w-8 h-8 rounded-lg bg-teal-500 text-white text-xs font-bold flex items-center justify-center">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 flex items-center justify-center transition">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($kegiatan->hasMorePages())
                    <a href="{{ $kegiatan->nextPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                @else
                    <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </span>
                @endif
            </div>
        </div>
        @endif

    </div>

</div>

@endsection
