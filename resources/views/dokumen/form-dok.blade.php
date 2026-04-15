@use('Illuminate\Support\Facades\Storage')
@extends('app')

@section('content')

@php $dokumen = $usulan->dokumen->last(); @endphp

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Banner Selesai --}}
    @if($usulan->status === 'selesai')
        <div class="mb-5 flex items-center gap-3 px-5 py-3 bg-purple-50 border border-purple-200 rounded-xl">
            <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-purple-700 font-semibold">
                Usulan ini telah <strong>Selesai</strong>.
                @if(auth()->user()->isAdmin())
                    Sebagai administrator, Anda tetap dapat mengunggah ulang dokumen.
                @else
                    Dokumen tidak dapat diubah lagi.
                @endif
            </p>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dokumen') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Upload Dokumen</h1>
            <p class="text-xs text-slate-400 mt-0.5">Upload dokumen pertanggungjawaban perjalanan dinas per seksi</p>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-5 bg-teal-50 border border-teal-200 rounded-xl px-5 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm text-teal-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT: Setiap seksi punya form sendiri --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- ========== 1. DOKUMEN PENUGASAN ========== --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="penugasan">
                <fieldset @disabled($usulan->status === 'selesai' && !auth()->user()->isAdmin())>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    {{-- Header --}}
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Dokumen Penugasan</h3>
                                <p class="text-xs text-slate-400">Dokumen resmi penugasan perjalanan dinas</p>
                            </div>
                        </div>
                        @if($dokumen && $dokumen->surat_tugas && $dokumen->sppd)
                            <span class="text-xs font-semibold text-teal-600 bg-teal-50 border border-teal-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Sudah diupload
                            </span>
                        @endif
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Surat Tugas --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Tugas <span class="text-red-500">*</span>
                            </label>
                            @if($dokumen && $dokumen->surat_tugas)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->surat_tugas) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->surat_tugas))
                                        <a href="{{ asset('storage/' . $dokumen->surat_tugas) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="surat_tugas" accept=".pdf"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100
                                          {{ $errors->has('surat_tugas') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('surat_tugas')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF — maks. 2 MB</p>
                            @enderror
                        </div>

                        {{-- SPPD --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                SPPD (Surat Perintah Perjalanan Dinas) <span class="text-red-500">*</span>
                            </label>
                            @if($dokumen && $dokumen->sppd)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->sppd) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->sppd))
                                        <a href="{{ asset('storage/' . $dokumen->sppd) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="sppd" accept=".pdf"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100
                                          {{ $errors->has('sppd') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('sppd')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF — maks. 2 MB</p>
                            @enderror
                        </div>

                    </div>

                    {{-- Footer / Submit --}}
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <button type="submit"
                                class="px-5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Simpan Dokumen Penugasan
                        </button>
                    </div>
                </div>
                </fieldset>
            </form>

            {{-- ========== 2. DOKUMEN TRANSPORTASI ========== --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="transportasi">
                <fieldset @disabled($usulan->status === 'selesai' && !auth()->user()->isAdmin())>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M12 19V5M5 12l7-7 7 7"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Dokumen Transportasi</h3>
                                <p class="text-xs text-slate-400">Bukti perjalanan dan tiket</p>
                            </div>
                        </div>
                        @if($dokumen && $dokumen->boarding_pass && $dokumen->faktur)
                            <span class="text-xs font-semibold text-teal-600 bg-teal-50 border border-teal-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Sudah diupload
                            </span>
                        @endif
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Boarding Pass --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Boarding Pass</label>
                            @if($dokumen && $dokumen->boarding_pass)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->boarding_pass) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->boarding_pass))
                                        <a href="{{ asset('storage/' . $dokumen->boarding_pass) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="boarding_pass" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100
                                          {{ $errors->has('boarding_pass') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('boarding_pass')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 2 MB</p>
                            @enderror
                        </div>

                        {{-- Tiket / Invoice --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Tiket / Invoice
                            </label>
                            @if($dokumen && $dokumen->faktur)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->faktur) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->faktur))
                                        <a href="{{ asset('storage/' . $dokumen->faktur) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="faktur" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100
                                          {{ $errors->has('faktur') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('faktur')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 2 MB</p>
                            @enderror
                        </div>

                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <button type="submit"
                                class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Simpan Dokumen Transportasi
                        </button>
                    </div>
                </div>
                </fieldset>
            </form>

            {{-- ========== 3. DOKUMEN AKOMODASI & BIAYA ========== --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="akomodasi">
                <fieldset @disabled($usulan->status === 'selesai' && !auth()->user()->isAdmin())>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Dokumen Akomodasi & Biaya</h3>
                                <p class="text-xs text-slate-400">Bukti penginapan dan pengeluaran lokal</p>
                            </div>
                        </div>
                        @if($dokumen && ($dokumen->bill_hotel || $dokumen->kwintasi))
                            <span class="text-xs font-semibold text-teal-600 bg-teal-50 border border-teal-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Sudah diupload
                            </span>
                        @endif
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Bill Hotel --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Bill Hotel</label>
                            @if($dokumen && $dokumen->bill_hotel)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->bill_hotel) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->bill_hotel))
                                        <a href="{{ asset('storage/' . $dokumen->bill_hotel) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="bill_hotel" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100
                                          {{ $errors->has('bill_hotel') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('bill_hotel')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 2 MB</p>
                            @enderror
                        </div>

                        {{-- Kwitansi Lokal --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kwitansi Lokal</label>
                            @if($dokumen && $dokumen->kwintasi)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->kwintasi) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->kwintasi))
                                        <a href="{{ asset('storage/' . $dokumen->kwintasi) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="kwintasi" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100
                                          {{ $errors->has('kwintasi') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('kwintasi')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 2 MB</p>
                            @enderror
                        </div>

                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <button type="submit"
                                class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Simpan Dokumen Akomodasi
                        </button>
                    </div>
                </div>
                </fieldset>
            </form>

            {{-- ========== 4. LAPORAN PERJALANAN ========== --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="laporan">
                <fieldset @disabled($usulan->status === 'selesai' && !auth()->user()->isAdmin())>
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Laporan Perjalanan</h3>
                                <p class="text-xs text-slate-400">Laporan hasil dan pertanggungjawaban perjalanan</p>
                            </div>
                        </div>
                        @if($dokumen && $dokumen->laporan_hasil)
                            <span class="text-xs font-semibold text-teal-600 bg-teal-50 border border-teal-200 px-2.5 py-1 rounded-full flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Sudah diupload
                            </span>
                        @endif
                    </div>

                    <div class="p-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Laporan Hasil Perjalanan <span class="text-red-500">*</span>
                            </label>
                            @if($dokumen && $dokumen->laporan_hasil)
                                <div class="mb-2 flex items-center gap-2 px-3 py-2 bg-violet-50 border border-violet-200 rounded-lg text-xs text-violet-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span class="truncate">{{ basename($dokumen->laporan_hasil) }}</span>
                                    @if(Storage::disk('public')->exists($dokumen->laporan_hasil))
                                        <a href="{{ asset('storage/' . $dokumen->laporan_hasil) }}" target="_blank" class="ml-auto shrink-0 font-semibold underline">Lihat</a>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 mb-1.5">Ganti file (opsional):</p>
                            @endif
                            <input type="file" name="laporan_hasil" accept=".pdf"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-violet-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100
                                          {{ $errors->has('laporan_hasil') ? 'border-red-400 bg-red-50' : 'border-slate-200' }}">
                            @error('laporan_hasil')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1">Format: PDF — maks. 5 MB</p>
                            @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <button type="submit"
                                class="px-5 py-2 bg-violet-500 hover:bg-violet-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Simpan Laporan Perjalanan
                        </button>
                    </div>
                </div>
                </fieldset>
            </form>

        </div>

        {{-- RIGHT: Sidebar --}}
        <div class="space-y-5">

            {{-- Info Perjalanan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-slate-800 text-sm">Info Perjalanan</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-xs text-slate-400">Nomor Usulan</p>
                        <p class="font-semibold text-slate-800">{{ $usulan->no_usulan }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Kegiatan</p>
                        <p class="font-semibold text-slate-800">{{ $usulan->kegiatan->nama }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Tujuan</p>
                        <p class="font-semibold text-slate-800">{{ $usulan->lokasi }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Periode</p>
                        <p class="font-semibold text-slate-800">{{ $usulan->periode }}</p>
                    </div>
                </div>
            </div>

            {{-- Checklist Dokumen --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Kelengkapan Dokumen</h3>
                <div class="space-y-2.5 text-sm">
                    @php
                        $checklistDocs = [
                            ['label' => 'Surat Tugas', 'key' => 'surat_tugas', 'required' => true],
                            ['label' => 'SPPD', 'key' => 'sppd', 'required' => true],
                            ['label' => 'Boarding Pass', 'key' => 'boarding_pass', 'required' => false],
                            ['label' => 'Tiket / Invoice', 'key' => 'faktur', 'required' => false],
                            ['label' => 'Bill Hotel', 'key' => 'bill_hotel', 'required' => false],
                            ['label' => 'Kwitansi Lokal', 'key' => 'kwintasi', 'required' => false],
                            ['label' => 'Laporan Hasil', 'key' => 'laporan_hasil', 'required' => true],
                        ];
                    @endphp
                    @foreach($checklistDocs as $doc)
                        @php
                            $hasFile = $dokumen && $dokumen->{$doc['key']};
                            $hasError = $errors->has($doc['key']);
                        @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 text-xs">
                                {{ $doc['label'] }}
                                @if($doc['required']) <span class="text-red-400">*</span> @endif
                            </span>
                            @if($hasError)
                                <span class="text-xs text-red-500 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">Error</span>
                            @elseif($hasFile)
                                <span class="text-xs text-teal-600 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Sudah
                                </span>
                            @else
                                <span class="text-xs text-slate-300 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded-full">Belum</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>

@endsection