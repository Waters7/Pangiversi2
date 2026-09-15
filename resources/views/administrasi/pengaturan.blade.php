@extends('app')

@section('title', 'Pengaturan Sistem')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Pengaturan Sistem';
        $subjudulHalaman = 'Pengingat kelengkapan berkas dan kunci tanggal SPD';
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

    {{-- ── Pengingat kelengkapan berkas ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Pengingat Kelengkapan Berkas</h3>
                <p class="text-xs text-slate-400">
                    Notifikasi otomatis untuk pegawai yang belum mengunggah berkas pertanggungjawaban
                </p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $kandidatPengingat > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $kandidatPengingat }} perjadin belum lengkap
            </span>
        </div>

        <form method="POST" action="{{ route('administrasi.pengaturan.simpan') }}" class="p-6">
            @csrf
            @method('PUT')

            <label class="flex items-start gap-3 mb-5 cursor-pointer">
                <input type="checkbox" name="pengingat_aktif" value="1"
                       @checked(old('pengingat_aktif', $pengaturan['pengingat_dokumen_aktif']) == '1')
                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Aktifkan pengingat otomatis</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Bila dimatikan, penjadwal harian tetap berjalan namun tidak mengirim notifikasi apa pun.
                    </span>
                </span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Kirim setelah <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_hari" min="0" max="90" required
                               value="{{ old('pengingat_hari', $pengaturan['pengingat_dokumen_hari']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_hari') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">hari</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Dihitung sejak tanggal perjalanan berakhir.</p>
                    @error('pengingat_hari') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Diulang tiap <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_ulang" min="1" max="60" required
                               value="{{ old('pengingat_ulang', $pengaturan['pengingat_dokumen_ulang']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_ulang') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">hari</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Selama berkasnya masih belum lengkap.</p>
                    @error('pengingat_ulang') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Paling banyak <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_maksimal" min="0" max="20" required
                               value="{{ old('pengingat_maksimal', $pengaturan['pengingat_dokumen_maksimal']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_maksimal') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">kali</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Isi 0 bila ingin diingatkan terus-menerus.</p>
                    @error('pengingat_maksimal') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 mt-5 pt-5 border-t border-slate-100">
                <button type="submit" form="jalankan-pengingat"
                        class="px-5 py-2.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition">
                    Jalankan Sekarang
                </button>
                <button type="submit"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200">
                    Simpan Pengaturan
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('administrasi.pengingat') }}" id="jalankan-pengingat" class="hidden">
            @csrf
        </form>
    </div>

    {{-- Kunci tanggal dikeluarkan SPD — hanya super administrator yang melihat
         dan mengubahnya; Tim SDM yang berbagi halaman ini tidak. --}}
    @if (auth()->user()->isAdmin())
        @php $tanggalSpdTerbuka = ($pengaturan['tanggal_spd_terbuka'] ?? '0') === '1'; @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg {{ $tanggalSpdTerbuka ? 'bg-amber-50' : 'bg-slate-100' }} flex items-center justify-center">
                    <svg class="w-4 h-4 {{ $tanggalSpdTerbuka ? 'text-amber-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                        @if ($tanggalSpdTerbuka)
                            <path d="M8 11V7a4 4 0 017.5-2"/>
                        @else
                            <path d="M8 11V7a4 4 0 018 0v4"/>
                        @endif
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-slate-800 text-sm">Tanggal Dikeluarkan SPD</h3>
                    <p class="text-xs text-slate-400">
                        Siapa yang boleh menetapkan tanggal terbit SPD sendiri — untuk kasus tanggal mundur (backdate)
                    </p>
                </div>
                <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $tanggalSpdTerbuka ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $tanggalSpdTerbuka ? 'Terbuka untuk semua peran' : 'Terkunci' }}
                </span>
            </div>

            <form method="POST" action="{{ route('administrasi.tanggal-spd') }}" class="p-6"
                  x-data="{ terbuka: {{ $tanggalSpdTerbuka ? 'true' : 'false' }} }"
                  @submit.prevent="if (confirm(terbuka
                        ? 'Buka tanggal dikeluarkan SPD untuk seluruh peran? Semua pengguna dapat menerbitkan SPD dengan tanggal mundur sampai dikunci kembali.'
                        : 'Kunci kembali tanggal dikeluarkan SPD? Peran selain pimpinan akan mengikuti tanggal pembuatan.')) $el.submit()">
                @csrf
                @method('PUT')

                <p class="text-xs text-slate-500 leading-relaxed mb-4">
                    Bawaannya <strong>terkunci</strong>: pimpinan dan administrator dapat menyesuaikan tanggal terbit dengan
                    buku agenda, sedangkan peran lain otomatis mendapat tanggal pembuatan. Buka kuncinya hanya bila ada SPD yang
                    harus diterbitkan dengan tanggal lebih awal oleh pengguna biasa, lalu kunci kembali — setiap perubahan tercatat
                    pada jejak audit.
                </p>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="tanggal_spd_terbuka" value="1" x-model="terbuka"
                           class="mt-0.5 w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                    <span>
                        <span class="block text-sm font-semibold text-slate-700">Buka tanggal dikeluarkan untuk seluruh peran</span>
                        <span class="block text-xs text-slate-400 mt-0.5">
                            Selama terbuka, formulir SPD setiap pengguna menampilkan kolom tanggal yang dapat diisi sendiri.
                        </span>
                    </span>
                </label>

                <div class="flex justify-end mt-5 pt-5 border-t border-slate-100">
                    <button type="submit"
                            class="px-5 py-2.5 text-white text-sm font-semibold rounded-xl transition shadow-sm"
                            :class="terbuka ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-200' : 'bg-teal-500 hover:bg-teal-600 shadow-teal-200'"
                            x-text="terbuka ? 'Buka Tanggal SPD' : 'Kunci Tanggal SPD'">
                    </button>
                </div>
            </form>
        </div>
    @endif

</div>

@endsection
