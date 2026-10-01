@extends('app')

@section('title', 'Berkas Pertanggungjawaban')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Berkas Pertanggungjawaban';
        $subjudulHalaman = 'Berkas yang ditagih sesudah perjalanan, untuk tiap jalur pengajuan';
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

    <div class="mb-5 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
        <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <p class="text-xs text-slate-500 leading-relaxed">
            Centangan di sini menentukan apa yang diminta formulir Dokumen Perdin, apa yang dihitung
            pada checklist kelengkapan, dan apa yang ditagihkan ke pelaksana. Usulan yang sudah berjalan
            ikut mengikuti pengaturan baru, jadi berkas yang dicabut tidak lagi menahan penyelesaiannya.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        @foreach ($jalur as $jenis)
            @php $dipilih = collect($terpilih[$jenis->value])->pluck('value')->all(); @endphp

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" x-data>
                <form method="POST" action="{{ route('administrasi.berkas-lpj.simpan', $jenis->value) }}">
                    @csrf @method('PUT')

                    <div class="px-5 py-4 border-b border-slate-100">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 text-sm">{{ $jenis->label() }}</h3>
                            @if ($sudahDiatur[$jenis->value])
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">Sudah diubah</span>
                            @else
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">Bawaan</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ $jenis->keterangan() }}</p>
                    </div>

                    <div class="p-4 space-y-2">
                        @foreach ($berkas as $item)
                            <label class="flex items-start gap-3 px-3.5 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 cursor-pointer transition">
                                <input type="checkbox" name="berkas[{{ $item->value }}]" value="{{ $item->value }}"
                                       @checked(in_array($item->value, $dipilih, true))
                                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800">
                                        {{ $item->label() }}
                                        @if ($item->bersyarat())
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">· bersyarat</span>
                                        @endif
                                    </span>
                                    <span class="block text-xs text-slate-400 leading-relaxed mt-0.5">{{ $item->keterangan() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between gap-2">
                        @if ($sudahDiatur[$jenis->value])
                            <button type="button" @click="$dispatch('buka-bawaan-{{ $jenis->value }}')"
                                    class="text-xs font-bold text-slate-500 hover:text-slate-700 underline underline-offset-2">
                                Kembalikan bawaan
                            </button>
                        @else
                            <span class="text-xs text-slate-400">Mengikuti bawaan aplikasi</span>
                        @endif

                        <button type="submit" class="px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                            Simpan
                        </button>
                    </div>
                </form>

                @if ($sudahDiatur[$jenis->value])
                    <x-modal-konfirmasi
                        nama="bawaan-{{ $jenis->value }}"
                        judul="Kembalikan berkas jalur &quot;{{ $jenis->label() }}&quot; ke bawaan?"
                        :aksi="route('administrasi.berkas-lpj.bawaan', $jenis->value)"
                        metode="DELETE"
                        tombol="Ya, Kembalikan"
                        warna="amber"
                        ikon="peringatan">
                        <p>Daftar berkas jalur ini kembali seperti bawaan aplikasi, dan checklist kelengkapan ikut menyesuaikan.</p>
                    </x-modal-konfirmasi>
                @endif
            </div>
        @endforeach
    </div>

    <p class="text-xs text-slate-400 mt-5 leading-relaxed">
        Berkas <strong>bersyarat</strong> hanya ditagih bila memang ada: nota transportasi untuk ruas yang
        bernominal, dan bukti biaya penyelenggaraan bila pelaksana menyatakan ada. Mematikannya berarti
        berkas itu tidak pernah ditagih sama sekali.
    </p>
</div>
@endsection
