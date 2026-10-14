@extends('app')

@section('title', 'Pagu per Kode Akun')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7"
     x-data="{
        form: { id_akun_pembiayaan: {{ Js::from(old('id_akun_pembiayaan', '')) }}, id_kategori_pembiayaan: {{ Js::from(old('id_kategori_pembiayaan', '')) }}, pagu: {{ Js::from(old('pagu', '')) }} },
        ubah(data) {
            this.form = { ...data };
            this.$refs.formulir.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
     }">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('master.tahun-anggaran') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">← Tahun Anggaran</a>
            <h1 class="text-xl font-bold text-slate-800 mt-1">Pagu per Kode Akun — TA {{ $tahunAnggaran->tahun }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Angka maksimal tiap mata anggaran perjalanan dinas beserta sumber dananya. Pagu tahun anggaran adalah jumlah seluruhnya:
                <span class="font-semibold text-slate-600">Rp {{ number_format($tahunAnggaran->pagu, 0, ',', '.') }}</span>.
            </p>
        </div>
    </div>

    <x-master-nav />

    {{-- Tambah atau ubah pagu satu akun --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5" x-ref="formulir">
        <h3 class="font-bold text-slate-800 text-sm mb-1">Tetapkan Pagu Akun</h3>
        <p class="text-xs text-slate-400 mb-4">Akun yang sudah berpagu diperbarui angkanya. Kode akun yang belum ada ditambahkan dulu pada Master Data → Akun Pembiayaan.</p>

        <form method="POST" action="{{ route('master.tahun-anggaran.pagu-akun.simpan', $tahunAnggaran) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label for="id_akun_pembiayaan" class="block text-xs font-semibold text-slate-600 mb-1.5">Kode Akun <span class="text-red-500">*</span></label>
                    <select id="id_akun_pembiayaan" name="id_akun_pembiayaan" x-model="form.id_akun_pembiayaan" required
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <option value="">Pilih kode akun</option>
                        @foreach ($pilihanAkun as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('id_akun_pembiayaan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="id_kategori_pembiayaan" class="block text-xs font-semibold text-slate-600 mb-1.5">Sumber Dana</label>
                    <select id="id_kategori_pembiayaan" name="id_kategori_pembiayaan" x-model="form.id_kategori_pembiayaan"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <option value="">Tanpa sumber dana</option>
                        @foreach ($pilihanSumber as $sumber)
                            <option value="{{ $sumber->id }}">{{ $sumber->kode }} — {{ $sumber->nama }}</option>
                        @endforeach
                    </select>
                    @error('id_kategori_pembiayaan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pagu" class="block text-xs font-semibold text-slate-600 mb-1.5">Pagu (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" id="pagu" name="pagu" x-model="form.pagu" required min="0" step="1"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('pagu') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" @click="form = { id_akun_pembiayaan: '', id_kategori_pembiayaan: '', pagu: '' }"
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Kosongkan</button>
                <button type="submit"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan Pagu</button>
            </div>
        </form>
    </div>

    {{-- Daftar pagu yang sudah ditetapkan --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Pagu yang Ditetapkan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{{ $paguAkun->count() }} akun</span>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Kode Akun</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Uraian Akun</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Sumber Dana</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Pagu</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($paguAkun as $baris)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700">{{ $baris->akun?->kode }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $baris->akun?->nama }}</td>
                            <td class="px-4 py-3 text-center text-xs font-bold text-slate-600">{{ $baris->sumberDana?->kode ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800 whitespace-nowrap">Rp {{ number_format($baris->pagu, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" title="Ubah pagu {{ $baris->akun?->kode }}"
                                            @click="ubah({{ Js::from(['id_akun_pembiayaan' => (string) $baris->id_akun_pembiayaan, 'id_kategori_pembiayaan' => (string) $baris->id_kategori_pembiayaan, 'pagu' => (int) $baris->pagu]) }})"
                                            class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    @can('menghapus-master-data')
                                        <form method="POST" action="{{ route('master.tahun-anggaran.pagu-akun.hapus', [$tahunAnggaran, $baris]) }}"
                                              onsubmit="return confirm('Hapus pagu akun {{ $baris->akun?->kode }}? Pagu tahun anggaran ikut berkurang.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Hapus pagu {{ $baris->akun?->kode }}"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 flex items-center justify-center transition">
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
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">
                                Belum ada pagu per akun. Selama kosong, pagu tahun anggaran diisi langsung pada daftar Tahun Anggaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-anggaran-per-akun :anggaran="$anggaran" judul="Pagu dan Realisasi TA {{ $tahunAnggaran->tahun }}" />

</div>

@endsection
