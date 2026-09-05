@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7"
     x-data="{
        showForm: {{ $errors->any() ? 'true' : 'false' }},
        mode: 'tambah',
        form: { id: null, tahun: {{ now()->year }}, pagu: 0, is_aktif: false },
        buka(mode, data = null) {
            this.mode = mode;
            this.form = data
                ? { ...data }
                : { id: null, tahun: {{ now()->year }}, pagu: 0, is_aktif: false };
            this.showForm = true;
        }
     }">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Tahun Anggaran</h1>
            <p class="text-xs text-slate-400 mt-0.5">Periode anggaran beserta pagu dan realisasinya</p>
        </div>
        <button @click="buka('tambah')"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Tahun Anggaran
        </button>
    </div>

    <x-master-nav />

    {{-- Ringkasan tahun aktif --}}
    @if ($aktif)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tahun Anggaran Aktif</p>
                    <p class="text-2xl font-bold text-slate-800 mt-0.5">{{ $aktif->tahun }}</p>
                </div>
                <div class="grid grid-cols-3 gap-6 text-right">
                    <div>
                        <p class="text-xs text-slate-400">Pagu</p>
                        <p class="text-sm font-bold text-slate-700">Rp {{ number_format($aktif->pagu, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Realisasi</p>
                        <p class="text-sm font-bold text-amber-600">Rp {{ number_format($aktif->realisasi, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Sisa Pagu</p>
                        <p class="text-sm font-bold text-emerald-600">Rp {{ number_format($aktif->sisa_pagu, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
            @php
                $persen = $aktif->pagu > 0 ? min(100, round($aktif->realisasi / $aktif->pagu * 100, 1)) : 0;
            @endphp
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-teal-500 transition-all" style="width: {{ $persen }}%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-2">{{ $persen }}% pagu terserap</p>
        </div>
    @endif

    {{-- Form Tambah / Ubah --}}
    <div x-show="showForm" x-transition x-cloak class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <h3 class="font-bold text-slate-800 text-sm mb-4"
            x-text="mode === 'tambah' ? 'Tambah Tahun Anggaran' : 'Ubah Tahun Anggaran'"></h3>

        <form method="POST"
              :action="mode === 'tambah'
                        ? '{{ route('master.tahun-anggaran.store') }}'
                        : '{{ url('master/tahun-anggaran') }}/' + form.id">
            @csrf
            <template x-if="mode === 'ubah'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tahun <span class="text-red-500">*</span></label>
                    <input type="number" name="tahun" x-model="form.tahun" required min="2000" max="2100"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('tahun') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pagu Anggaran (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="pagu" x-model="form.pagu" required min="0" step="1000000"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('pagu') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 mt-4">
                <label class="flex items-center gap-2 text-sm text-slate-600 select-none cursor-pointer">
                    <input type="checkbox" name="is_aktif" value="1" x-model="form.is_aktif"
                           class="w-4 h-4 rounded border-slate-300 text-teal-500 focus:ring-teal-400">
                    Jadikan tahun anggaran aktif
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

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Tahun Anggaran
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $tahunAnggaran->total() }} data</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-28">Tahun</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Pagu</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Realisasi</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Sisa Pagu</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-24">Usulan</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-24">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5 w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($tahunAnggaran as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-4 font-bold text-slate-800">{{ $item->tahun }}</td>
                            <td class="px-4 py-4 text-right text-slate-700">Rp {{ number_format($item->pagu, 0, ',', '.') }}</td>
                            <td class="px-4 py-4 text-right text-amber-600 font-semibold">Rp {{ number_format($item->realisasi, 0, ',', '.') }}</td>
                            <td class="px-4 py-4 text-right text-emerald-600 font-semibold">Rp {{ number_format($item->sisa_pagu, 0, ',', '.') }}</td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-600">{{ $item->usulan_count }}</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->is_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    @unless ($item->is_aktif)
                                        <form method="POST" action="{{ route('master.tahun-anggaran.aktifkan', $item) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-emerald-100 text-slate-600 hover:text-emerald-700 flex items-center justify-center transition" title="Jadikan tahun aktif">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endunless
                                    <button @click="buka('ubah', {{ Js::from($item->only(['id', 'tahun', 'pagu', 'is_aktif'])) }})"
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Ubah">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form method="POST" action="{{ route('master.tahun-anggaran.destroy', $item) }}"
                                          x-data
                                          @submit.prevent="if (confirm('Hapus tahun anggaran {{ $item->tahun }}?')) $el.submit()">
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
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    <p class="text-sm text-slate-400">Belum ada tahun anggaran</p>
                                    <button @click="buka('tambah')" class="text-sm text-teal-600 font-semibold hover:underline">+ Tambah Tahun Pertama</button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$tahunAnggaran" />
    </div>

</div>

@endsection
