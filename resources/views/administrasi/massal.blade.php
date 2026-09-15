@extends('app')

@section('title', 'Impor & Ekspor Pengguna')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Impor & Ekspor Pengguna';
        $subjudulHalaman = 'Data pengguna massal lewat berkas CSV';
    @endphp
    @include('administrasi.partials.kepala')

    {{-- Flash Message --}}
    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Ekspor / impor massal --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Data Pengguna Massal</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    Unduh CSV untuk dipakai sebagai template, lalu unggah kembali. NIP menjadi kunci —
                    baris dengan NIP yang sudah ada akan diperbarui, bukan diduplikasi.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <a href="{{ route('administrasi.export') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    Export CSV
                </a>

                <form method="POST" action="{{ route('administrasi.import') }}" enctype="multipart/form-data"
                      x-data="{ namaBerkas: '' }" class="flex gap-2">
                    @csrf
                    <label class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition cursor-pointer whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                        <span x-text="namaBerkas || 'Pilih Berkas CSV'"></span>
                        <input type="file" name="berkas" accept=".csv,text/csv" required class="hidden"
                               @change="namaBerkas = $event.target.files[0]?.name ?? ''">
                    </label>
                    <button type="submit" x-show="namaBerkas" x-cloak
                            class="px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition whitespace-nowrap">
                        Impor
                    </button>
                </form>
            </div>
        </div>

        @error('berkas') <p class="text-red-500 text-xs mt-3">{{ $message }}</p> @enderror

        @if (session('impor_dilewati'))
            <div class="mt-4 px-4 py-3 bg-amber-50 border border-amber-100 rounded-xl">
                <p class="text-xs font-bold text-amber-800 mb-1.5">
                    {{ count(session('impor_dilewati')) }} baris dilewati
                </p>
                <ul class="text-xs text-amber-700 space-y-0.5 list-disc list-inside">
                    @foreach (array_slice(session('impor_dilewati'), 0, 10) as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
                @if (count(session('impor_dilewati')) > 10)
                    <p class="text-xs text-amber-600 mt-1.5">…dan {{ count(session('impor_dilewati')) - 10 }} baris lainnya.</p>
                @endif
            </div>
        @endif

        <p class="text-xs text-slate-400 mt-4 pt-4 border-t border-slate-100 leading-relaxed">
            Impor membaca kolom <span class="font-mono text-slate-500">nama, nip, email, no_hp, role, jabatan, unit_kode, atasan_nip, nama_bank, nomor_rekening, nama_rekening, password</span>.
            Sumber data dipisahkan lewat kontrak <span class="font-mono text-slate-500">SumberDataPegawai</span>, sehingga integrasi dengan aplikasi kepegawaian nanti cukup menambah satu implementasi baru.
        </p>
    </div>

</div>

@endsection
