@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Buat Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Lengkapi semua data sebelum mengajukan ke atasan</p>
        </div>
    </div>

    {{-- Main Form --}}
    <form action="" method="POST" enctype="multipart/form-data" id="formUsulan">
        @csrf

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-5">

                {{-- STEP 1: Data Dasar Perjalanan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Data Dasar Perjalanan</h3>
                            <p class="text-xs text-slate-400">Informasi utama perjalanan dinas</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Jenis Kegiatan --}}
                        <div>
                            <label for="jenis_kegiatan_id" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Jenis Kegiatan <span class="text-red-500">*</span>
                            </label>
                            <select name="jenis_kegiatan_id" id="jenis_kegiatan_id"
                                    class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('jenis_kegiatan_id') border-red-500 @else border-slate-200 @enderror"
                                    required>
                                <option value="">-- Pilih Jenis Kegiatan --</option>
                                @foreach($jenisKegiatan ?? [] as $jk)
                                    <option value="{{ $jk->id }}" {{ old('jenis_kegiatan_id') == $jk->id ? 'selected' : '' }}>
                                        {{ $jk->nama }}
                                    </option>
                                @endforeach
                            </select>
                            @error('jenis_kegiatan_id')
                                <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Dasar Penugasan --}}
                        <div>
                            <label for="dasar_penugasan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Dasar Penugasan <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="dasar_penugasan" id="dasar_penugasan"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('dasar_penugasan') border-red-500 @else border-slate-200 @enderror"
                                   placeholder="cth: Undangan Rapat No. 123/..., SK Direktur No. ..."
                                   value="{{ old('dasar_penugasan') }}" required>
                            <p class="text-xs text-slate-400 mt-1">Nomor surat undangan, SK, atau surat tugas dasar</p>
                            @error('dasar_penugasan')
                                <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Lokasi Tujuan + Instansi --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="lokasi_tujuan_id" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Lokasi / Kota Tujuan <span class="text-red-500">*</span>
                                </label>
                                <select name="lokasi_tujuan_id" id="lokasi_tujuan_id"
                                        class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('lokasi_tujuan_id') border-red-500 @else border-slate-200 @enderror"
                                        required>
                                    <option value="">-- Pilih Kota --</option>
                                    @foreach($lokasiTujuan ?? [] as $lok)
                                        <option value="{{ $lok->id }}" {{ old('lokasi_tujuan_id') == $lok->id ? 'selected' : '' }}>
                                            {{ $lok->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('lokasi_tujuan_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="instansi_tujuan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Instansi / Tempat Tujuan
                                </label>
                                <input type="text" name="instansi_tujuan" id="instansi_tujuan"
                                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"
                                       placeholder="cth: Kemenkes RI, Hotel Grand..."
                                       value="{{ old('instansi_tujuan') }}">
                            </div>
                        </div>

                        {{-- Tanggal Mulai & Selesai --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tanggal_mulai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Mulai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai"
                                       class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('tanggal_mulai') border-red-500 @else border-slate-200 @enderror"
                                       value="{{ old('tanggal_mulai') }}" required>
                                @error('tanggal_mulai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tanggal_selesai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Selesai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai"
                                       class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('tanggal_selesai') border-red-500 @else border-slate-200 @enderror"
                                       value="{{ old('tanggal_selesai') }}" required>
                                @error('tanggal_selesai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Keterangan --}}
                        <div>
                            <label for="keterangan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Uraian Tujuan Perjalanan <span class="text-red-500">*</span>
                            </label>
                            <textarea name="keterangan" id="keterangan" rows="3"
                                      class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition resize-none @error('keterangan') border-red-500 @else border-slate-200 @enderror"
                                      placeholder="Jelaskan secara singkat maksud dan tujuan perjalanan dinas ini..."
                                      required>{{ old('keterangan') }}</textarea>
                            @error('keterangan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Estimasi Biaya --}}
                        <div>
                            <label for="estimasi_biaya" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Estimasi Total Biaya (Rp)
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-500 font-medium">Rp</span>
                                <input type="number" name="estimasi_biaya" id="estimasi_biaya"
                                       class="w-full pl-10 pr-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('estimasi_biaya') border-red-500 @else border-slate-200 @enderror"
                                       placeholder="0"
                                       min="0"
                                       value="{{ old('estimasi_biaya') }}">
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Isi perkiraan anggaran yang dibutuhkan (opsional, akan dihitung detail oleh Bendahara)</p>
                            @error('estimasi_biaya')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- STEP 2: Peserta Perjalanan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Peserta Perjalanan</h3>
                            <p class="text-xs text-slate-400">Tambahkan seluruh peserta yang ikut serta</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">

                        {{-- Pengusul sendiri --}}
                        <div class="flex items-center gap-3 p-3.5 rounded-xl bg-teal-50 border border-teal-100">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=14b8a6&color=fff&size=64"
                                 class="w-9 h-9 rounded-full shrink-0" alt="avatar"/>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ auth()->user()->name ?? 'Nama Anda' }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ auth()->user()->unit->nama ?? 'Unit Kerja' }} · NIP: {{ auth()->user()->nip ?? '-' }}</p>
                            </div>
                            <span class="text-xs font-bold text-teal-700 bg-teal-100 px-2.5 py-1 rounded-full shrink-0">Pengusul</span>
                        </div>

                        {{-- Peserta Lainnya --}}
                        <div id="pesertaContainer" class="space-y-3">
                            {{-- Peserta items akan ditambahkan di sini --}}
                        </div>

                        {{-- Button Tambah Peserta --}}
                        <button type="button" id="tambahPesertaBtn"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 border border-dashed border-blue-300 bg-blue-50 text-blue-700 text-sm font-semibold rounded-xl hover:bg-blue-100 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Tambah Peserta Lainnya
                        </button>

                    </div>
                </div>

                {{-- Modal Tambah Peserta --}}
                <div id="pesertaModal" 
                        class="fixed inset-0 backdrop-blur-sm bg-black/40 z-50 items-center justify-center p-4 hidden flex">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                        {{-- Modal Header --}}
                        <div class="sticky top-0 bg-white px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="font-bold text-slate-800">Tambah Peserta Baru</h2>
                                    <p class="text-xs text-slate-400">Lengkapi data peserta perjalanan dinas</p>
                                </div>
                            </div>
                            <button type="button" id="closePesertaModal" class="text-slate-400 hover:text-slate-600 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Modal Body --}}
                        <div class="p-6 space-y-4" id="pesertaFormContainer">
                            {{-- Form fields akan diisi oleh JavaScript --}}
                        </div>

                        {{-- Modal Footer --}}
                        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center gap-3">
                            <button type="button" id="cancelPesertaBtn"
                                    class="flex-1 px-4 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                                Batal
                            </button>
                            <button type="button" id="savePesertaBtn"
                                    class="flex-1 px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-xl transition">
                                Simpan Peserta
                            </button>
                        </div>
                    </div>
                </div>

                {{-- STEP 3: Lampiran Dokumen --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                            <p class="text-xs text-slate-400">Upload dokumen pendukung perjalanan dinas</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Surat Undangan / Disposisi (wajib) --}}
                        <div>
                            <label for="lampiran_undangan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Undangan / Disposisi <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="lampiran_undangan" id="lampiran_undangan"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition @error('lampiran_undangan') border-red-500 @else border-slate-200 @enderror"
                                   required>
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                            @error('lampiran_undangan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- TOR / KAK (opsional) --}}
                        <div>
                            <label for="lampiran_tor" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                TOR / Kerangka Acuan Kegiatan
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional)</span>
                            </label>
                            <input type="file" name="lampiran_tor" id="lampiran_tor"
                                   accept=".pdf,.doc,.docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, DOCX — maks. 5 MB</p>
                        </div>

                        {{-- Dokumen lain (opsional) --}}
                        <div>
                            <label for="lampiran_lain" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Dokumen Lainnya
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional, maks. 3 file)</span>
                            </label>
                            <input type="file" name="lampiran_lain[]" id="lampiran_lain"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   multiple
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, JPG, PNG — maks. 5 MB per file</p>
                        </div>

                        {{-- Info --}}
                        <div class="flex items-start gap-3 p-3.5 bg-blue-50 rounded-xl border border-blue-100">
                            <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <p class="text-xs text-blue-700">
                                Surat undangan atau disposisi <strong>wajib</strong> dilampirkan sesuai BR-01. 
                                Tipe file yang diizinkan: PDF, JPG, PNG, DOC, DOCX. Maksimal ukuran per file: 5 MB.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            {{-- SIDEBAR: Ringkasan Anggaran & Aksi --}}
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-4">
                    
                    {{-- Card Ringkasan Anggaran --}}
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Ringkasan Anggaran</h3>
                                <p class="text-xs text-slate-400">Estimasi biaya perjalanan</p>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            {{-- Estimasi Biaya Display --}}
                            <div>
                                <p class="text-xs font-medium text-slate-500 mb-2">Total Estimasi</p>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-bold text-violet-600" id="displayEstimasiBiaya">Rp 0</span>
                                </div>
                            </div>

                            {{-- Info Box --}}
                            <div class="p-3.5 bg-violet-50 rounded-xl border border-violet-100">
                                <p class="text-xs text-violet-700">
                                    <strong>Catatan:</strong> Estimasi akan disesuaikan oleh Bendahara berdasarkan rincian biaya komponen perjalanan (transportasi, akomodasi, konsumsi).
                                </p>
                            </div>

                            {{-- Summary Items --}}
                            <div class="space-y-2 pt-2 border-t border-slate-100">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Status Dokumen:</span>
                                    <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full">Draft</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Pengusul:</span>
                                    <span class="text-xs font-semibold text-slate-700">{{ auth()->user()->name ?? 'Anda' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="space-y-3">
                        <button type="submit" name="action" value="draft"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Draft
                        </button>

                        <button type="submit" name="action" value="submit"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Kirim ke Atasan
                        </button>

                        <a href="{{ route('dashboard') }}"
                           class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Batal
                        </a>
                    </div>

                </div>
            </div>

        </div>

        
    </form>
</div>


@vite('resources/js/usulan.js')

@endsection
