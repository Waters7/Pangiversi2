@extends('app')

@section('title', 'Status Hasil Laporan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7"
     x-data="{
        showForm: {{ $errors->any() ? 'true' : 'false' }},
        mode: 'tambah',
        form: { id: null, nama: '', keterangan: '', urutan: '', is_aktif: true },
        buka(mode, data = null) {
            this.mode = mode;
            this.form = data
                ? { ...data }
                : { id: null, nama: '', keterangan: '', urutan: '', is_aktif: true };
            this.showForm = true;
        }
     }">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Status Hasil Laporan</h1>
            <p class="text-xs text-slate-400 mt-0.5">Pilihan hasil yang dicapai pada laporan perjalanan dinas</p>
        </div>
        <button @click="buka('tambah')"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Status
        </button>
    </div>

    <x-master-nav />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalStatus }}</p>
                <p class="text-xs text-slate-400">Total Status</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalAktif }}</p>
                <p class="text-xs text-slate-400">Ditawarkan pada Laporan</p>
            </div>
        </div>
    </div>

    {{-- Form Tambah / Ubah --}}
    <div x-show="showForm" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <h3 class="font-bold text-slate-800 text-sm mb-4"
            x-text="mode === 'tambah' ? 'Tambah Status Hasil' : 'Ubah Status Hasil'"></h3>

        <form method="POST"
              :action="mode === 'tambah'
                        ? '{{ route('master.status-hasil.store') }}'
                        : '{{ url('master/status-hasil') }}/' + form.id">
            @csrf
            <template x-if="mode === 'ubah'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Status <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" x-model="form.nama" required placeholder="Selesai dikerjakan"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('nama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Keterangan</label>
                    <input type="text" name="keterangan" x-model="form.keterangan" placeholder="Penjelasan singkat, boleh dikosongkan"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('keterangan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Urutan Tampil</label>
                    <input type="number" name="urutan" x-model="form.urutan" min="1" max="999" placeholder="otomatis"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <p class="text-xs text-slate-400 mt-1">Kosongkan untuk ditaruh paling bawah.</p>
                    @error('urutan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 mt-4">
                <label class="flex items-center gap-2 text-sm text-slate-600 select-none cursor-pointer">
                    <input type="checkbox" name="is_aktif" value="1" x-model="form.is_aktif"
                           class="w-4 h-4 rounded border-slate-300 text-teal-500 focus:ring-teal-400">
                    Tawarkan pada formulir laporan
                </label>
                <div class="flex gap-2">
                    <button type="button" @click="showForm = false"
                            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Batal</button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan</button>
                </div>
            </div>
        </form>
    </div>

    <form method="GET" action="{{ route('master.status-hasil') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama status..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
            @if ($search)
                <a href="{{ route('master.status-hasil') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Status
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $status->total() }} data</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-16">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Nama Status</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Keterangan</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-24">Dipakai</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-24">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($status as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-4 text-center text-xs font-semibold text-slate-400">
                                {{ ($status->currentPage() - 1) * $status->perPage() + $loop->iteration }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->badge }}">{{ $item->nama }}</span>
                            </td>
                            <td class="px-4 py-4 text-slate-500 text-xs">{{ $item->keterangan ?? '—' }}</td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $item->laporan_count }}</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->is_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="buka('ubah', {{ Js::from($item->only(['id', 'nama', 'keterangan', 'urutan', 'is_aktif'])) }})"
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Ubah">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form method="POST" action="{{ route('master.status-hasil.destroy', $item) }}"
                                          x-data
                                          @submit.prevent="if (confirm('Hapus status {{ addslashes($item->nama) }}?')) $el.submit()">
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
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p class="text-sm text-slate-400">Belum ada status hasil</p>
                                <button @click="buka('tambah')" class="text-sm text-teal-600 font-semibold hover:underline mt-1">+ Tambah Status Pertama</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$status" />
    </div>

</div>

@endsection
