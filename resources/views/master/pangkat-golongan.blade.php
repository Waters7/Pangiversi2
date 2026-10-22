@extends('app')

@section('title', 'Pangkat & Golongan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7"
     x-data="{
        showForm: {{ $errors->any() ? 'true' : 'false' }},
        mode: @js(old('_method') === 'PUT' ? 'ubah' : 'tambah'),
        form: @js(['id' => old('id'), 'kode' => old('kode', ''), 'tulisan' => old('tulisan', ''), 'kelompok' => old('kelompok', ''), 'urutan' => old('urutan', '')]),
        buka(mode, data = null) {
            this.mode = mode;
            this.form = data
                ? { ...data }
                : { id: null, kode: '', tulisan: '', kelompok: '', urutan: '' };
            this.showForm = true;
        }
     }">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Pangkat &amp; Golongan</h1>
            <p class="text-xs text-slate-400 mt-0.5">Pilihan pangkat dan golongan pada data pegawai dan Surat Perjalanan Dinas</p>
        </div>
        <button @click="buka('tambah')"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Pangkat &amp; Golongan
        </button>
    </div>

    <x-master-nav />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 15l-3.5 2 1-4-3-2.7 4-.3L12 6l1.5 4 4 .3-3 2.7 1 4z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $totalGolongan }}</p>
                <p class="text-xs text-slate-400">Pilihan Pangkat &amp; Golongan</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h10"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $daftarKelompok->count() }}</p>
                <p class="text-xs text-slate-400">Kelompok</p>
            </div>
        </div>
    </div>

    {{-- Form Tambah / Ubah --}}
    <div x-show="showForm" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <h3 class="font-bold text-slate-800 text-sm mb-4"
            x-text="mode === 'tambah' ? 'Tambah Pangkat & Golongan' : 'Ubah Pangkat & Golongan'"></h3>

        <form method="POST"
              :action="mode === 'tambah'
                        ? '{{ route('master.pangkat-golongan.store') }}'
                        : '{{ url('master/pangkat-golongan') }}/' + form.id">
            @csrf
            <template x-if="mode === 'ubah'">
                <input type="hidden" name="_method" value="PUT">
            </template>
            <input type="hidden" name="id" :value="form.id">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kode <span class="text-red-500">*</span></label>
                    <input type="text" name="kode" x-model="form.kode" required maxlength="8" placeholder="cth: OS"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <p class="text-xs text-slate-400 mt-1">Maks. 8 karakter, tanpa spasi — disimpan pada data pegawai.</p>
                    @error('kode') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tulisan pada SPD <span class="text-red-500">*</span></label>
                    <input type="text" name="tulisan" x-model="form.tulisan" required maxlength="100" placeholder="cth: Outsourcing"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('tulisan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kelompok <span class="text-red-500">*</span></label>
                    <input type="text" name="kelompok" x-model="form.kelompok" required maxlength="60" list="daftar-kelompok" placeholder="cth: Non-ASN"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <datalist id="daftar-kelompok">
                        @foreach ($daftarKelompok as $kelompok)
                            <option value="{{ $kelompok }}"></option>
                        @endforeach
                    </datalist>
                    <p class="text-xs text-slate-400 mt-1">Judul pengelompokan pada kotak pilih.</p>
                    @error('kelompok') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Urutan Tampil</label>
                    <input type="number" name="urutan" x-model="form.urutan" min="1" max="999" placeholder="otomatis"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <p class="text-xs text-slate-400 mt-1">Kosongkan untuk ditaruh paling bawah.</p>
                    @error('urutan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <p x-show="mode === 'ubah'" class="mt-3 text-xs text-slate-500">
                Kode yang diganti ikut diganti pada pegawai yang memakainya. SPD yang sudah terbit tetap memuat tulisan lamanya.
            </p>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" @click="showForm = false"
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Batal</button>
                <button type="submit"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan</button>
            </div>
        </form>
    </div>

    <form method="GET" action="{{ route('master.pangkat-golongan') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari kode, tulisan, atau kelompok..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
            @if ($search)
                <a href="{{ route('master.pangkat-golongan') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Pangkat &amp; Golongan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $golongan->total() }} data</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-16">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Kode</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Tulisan pada SPD</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Kelompok</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-28">Pegawai</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($golongan as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 text-center text-xs font-semibold text-slate-400">
                                {{ ($golongan->currentPage() - 1) * $golongan->perPage() + $loop->iteration }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700">{{ $item->kode }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->tulisan }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $item->kelompok }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $item->pengguna_count }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="buka('ubah', {{ Js::from($item->only(['id', 'kode', 'tulisan', 'kelompok', 'urutan'])) }})"
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Ubah">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    @can('menghapus-master-data')
                                        <form method="POST" action="{{ route('master.pangkat-golongan.destroy', $item) }}"
                                              x-data
                                              @submit.prevent="if (confirm({{ Js::from('Hapus '.$item->tulisan.'?') }})) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 flex items-center justify-center transition" title="Hapus">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p class="text-sm text-slate-400">Belum ada pangkat dan golongan</p>
                                <button @click="buka('tambah')" class="text-sm text-teal-600 font-semibold hover:underline mt-1">+ Tambah yang pertama</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$golongan" />
    </div>

</div>

@endsection
