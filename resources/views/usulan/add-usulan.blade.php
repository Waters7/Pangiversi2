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
                                    class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('jenis_kegiatan_id') ? 'border-red-500' : 'border-slate-200' }}"
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
                                   class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('dasar_penugasan') ? 'border-red-500' : 'border-slate-200' }}"
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
                                        class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('lokasi_tujuan_id') ? 'border-red-500' : 'border-slate-200' }}"
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
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_mulai') ? 'border-red-500' : 'border-slate-200' }}"
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
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_selesai') ? 'border-red-500' : 'border-slate-200' }}"
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
                                      class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition resize-none border {{ $errors->has('keterangan') ? 'border-red-500' : 'border-slate-200' }}"
                                      placeholder="Jelaskan secara singkat maksud dan tujuan perjalanan dinas ini..."
                                      required>{{ old('keterangan') }}</textarea>
                            @error('keterangan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- STEP 2: Lampiran Dokumen --}}
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

                        {{-- Surat Tugas --}}
                        <div>
                            <label for="lampiran_undangan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Tugas <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="surat_tugas" id="lampiran_undangan"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('surat_tugas') ? 'border-red-500' : 'border-slate-200' }}"
                                   required>
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                            @error('surat_tugas')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Rundown Kegiatan (opsional) --}}
                        <div>
                            <label for="lampiran_tor" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Rundown Kegiatan
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
                                Surat tugas <strong>wajib</strong> dilampirkan sesuai dengan
                                tipe file yang diizinkan: PDF, JPG, PNG, DOC, DOCX. Maksimal ukuran per file: 5 MB.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            {{-- SIDEBAR: Aksi --}}
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-4">

                    {{-- Action Buttons --}}
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                        <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                        <button type="submit" name="action" value="submit"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Kirim ke Atasan
                        </button>

                        <button type="submit" name="action" value="draft"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Draft
                        </button>

                        <a href="{{ route('dashboard') }}"
                           class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Batal
                        </a>
                    </div>

                    {{-- Validation Summary --}}
                    @if($errors->any())
                        <div class="bg-red-50 rounded-2xl border border-red-100 p-4">
                            <p class="text-sm font-semibold text-red-700 mb-2">Harap perbaiki kesalahan berikut:</p>
                            <ul class="space-y-1">
                                @foreach($errors->all() as $error)
                                    <li class="text-xs text-red-600 flex items-start gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        {{ $error }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>
            </div>

        </div>

    </form>
</div>

@endsection
