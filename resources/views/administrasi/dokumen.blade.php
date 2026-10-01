@extends('app')

@section('title', 'Dokumen Output')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Dokumen Output';
        $subjudulHalaman = 'Tampilan dan elemen tiap dokumen yang dicetak aplikasi';
    @endphp
    @include('administrasi.partials.kepala')

    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-5 items-start">

        {{-- ── Pilihan dokumen ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Dokumen Cetak</h3>
                <p class="text-xs text-slate-400 mt-0.5">Pilih dokumen untuk mengatur tampilannya</p>
            </div>
            <div class="p-2 space-y-0.5">
                @foreach ($daftar as $dokumen)
                    <a href="{{ route('administrasi.dokumen', ['dokumen' => $dokumen->value]) }}"
                       class="block px-3 py-2.5 rounded-xl transition border
                              {{ $dokumen === $terpilih ? 'bg-teal-50 border-teal-100' : 'border-transparent hover:bg-slate-50' }}">
                        <span class="block text-sm font-semibold text-slate-800">{{ $dokumen->label() }}</span>
                        <span class="block text-[11px] text-slate-400 mt-0.5 leading-relaxed">{{ $dokumen->keterangan() }}</span>
                    </a>
                @endforeach
            </div>
            <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/60">
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Perubahan berlaku pada cetakan berikutnya. Dokumen yang sudah terlanjur diunduh tidak ikut berubah.
                </p>
            </div>
        </div>

        {{-- ── Pengaturan dokumen terpilih ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" x-data>
            <form method="POST" action="{{ route('administrasi.dokumen.simpan', $terpilih->value) }}">
                @csrf @method('PUT')

                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 text-sm">{{ $terpilih->label() }}</h3>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Orientasi {{ $terpilih->orientasiLabel() }}</span>
                            @if ($sudahDiatur)
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">Sudah diubah</span>
                            @else
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">Tampilan bawaan</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-1">{{ $terpilih->keterangan() }}</p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        @if ($sudahDiatur)
                            <button type="button" @click="$dispatch('buka-bawaan-dokumen')"
                                    class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                                Kembalikan Bawaan
                            </button>
                        @endif
                        <button type="submit" class="px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                            Simpan Tampilan
                        </button>
                    </div>
                </div>

                {{-- Tampilan halaman --}}
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/60">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Tampilan Halaman</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="kertas" class="block text-xs font-semibold text-slate-600 mb-1">Ukuran kertas</label>
                            <select name="kertas" id="kertas"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-white border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                @foreach ($pilihanKertas as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected(old('kertas', $atur->kertas()) === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Satuan kerja mencetak di folio; ubah hanya bila mesin cetaknya berbeda.</p>
                        </div>
                        <div>
                            <label for="huruf" class="block text-xs font-semibold text-slate-600 mb-1">Ukuran huruf dasar (pt)</label>
                            <input type="number" name="huruf" id="huruf" step="0.5" min="6" max="16"
                                   value="{{ old('huruf', $atur->huruf()) }}"
                                   class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-[11px] text-slate-400 mt-1">Bawaan {{ $terpilih->hurufBawaan() }} pt. Memperbesar huruf dapat menambah halaman.</p>
                        </div>
                        <div>
                            @if ($terpilih->punyaKop())
                                <label for="lebar_kop" class="block text-xs font-semibold text-slate-600 mb-1">Lebar kop surat (%)</label>
                                <input type="number" name="lebar_kop" id="lebar_kop" min="40" max="100"
                                       value="{{ old('lebar_kop', $atur->lebarKop()) }}"
                                       class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                <p class="text-[11px] text-slate-400 mt-1">Bawaan 82% dari lebar ruang cetak.</p>
                            @else
                                <p class="text-xs text-slate-400 leading-relaxed sm:pt-6">Dokumen ini tidak berkop surat — judulnya langsung tercetak di kepala halaman.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Elemen --}}
                <div class="px-6 py-5 border-b border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Elemen yang Ikut Tercetak</h4>
                    <p class="text-xs text-slate-400 mb-3">Hilangkan centang untuk menyembunyikan elemen itu dari dokumen.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        @foreach ($terpilih->elemen() as $kode => $tentang)
                            <label class="flex items-start gap-3 px-4 py-3 rounded-xl border border-slate-100 hover:bg-slate-50 cursor-pointer transition">
                                <input type="checkbox" name="elemen[{{ $kode }}]" value="1"
                                       @checked(old('elemen.'.$kode, $atur->tampil($kode)))
                                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800">{{ $tentang['label'] }}</span>
                                    <span class="block text-xs text-slate-400 leading-relaxed mt-0.5">{{ $tentang['keterangan'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Teks --}}
                <div class="px-6 py-5">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Teks Baku</h4>
                    <p class="text-xs text-slate-400 mb-3">Dikosongkan berarti kembali memakai teks bawaan.</p>
                    <div class="space-y-4">
                        @foreach ($terpilih->teks() as $kode => $tentang)
                            <div>
                                <label for="teks-{{ $kode }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $tentang['label'] }}</label>
                                @if ($tentang['panjang'])
                                    <textarea name="teks[{{ $kode }}]" id="teks-{{ $kode }}" rows="4"
                                              class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition leading-relaxed">{{ old('teks.'.$kode, $atur->teks($kode)) }}</textarea>
                                @else
                                    <input type="text" name="teks[{{ $kode }}]" id="teks-{{ $kode }}" maxlength="2000"
                                           value="{{ old('teks.'.$kode, $atur->teks($kode)) }}"
                                           class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                @endif
                                <p class="text-[11px] text-slate-400 mt-1">{{ $tentang['keterangan'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Penanda tanda tangan elektronik SRIKANDI dan nomor naskah selalu tercetak — keduanya tidak dapat dimatikan.
                    </p>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition shrink-0">
                        Simpan Tampilan
                    </button>
                </div>
            </form>

            @if ($sudahDiatur)
                <x-modal-konfirmasi
                    nama="bawaan-dokumen"
                    judul="Kembalikan tampilan ke bawaan?"
                    :aksi="route('administrasi.dokumen.bawaan', $terpilih->value)"
                    metode="DELETE"
                    tombol="Ya, Kembalikan"
                    warna="amber"
                    ikon="peringatan">
                    <p>Seluruh pengaturan tampilan <strong class="text-slate-700">{{ $terpilih->label() }}</strong> dihapus dan dokumen kembali tercetak seperti bawaan aplikasi.</p>
                </x-modal-konfirmasi>
            @endif
        </div>
    </div>
</div>
@endsection
