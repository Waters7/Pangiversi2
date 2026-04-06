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
            <h1 class="text-xl font-bold text-slate-800">Upload Dokumen</h1>
            <p class="text-xs text-slate-400 mt-0.5">Upload seluruh dokumen pertanggungjawaban perjalanan dinas</p>
        </div>
    </div>

    <form action="#" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- Pilih Perjalanan Dinas --}}
        @include('dokumen.list-pejadin')

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- LEFT: Form Upload --}}
            <div class="xl:col-span-2 space-y-5">

                {{-- Dokumen Penugasan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
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

                    <div class="p-6 space-y-5">

                        {{-- Surat Tugas --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Tugas <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="file" name="surat_tugas" id="surat_tugas" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                              file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                        {{-- SPPD --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                SPPD (Surat Perintah Perjalanan Dinas) <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="sppd" id="sppd" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                    </div>
                </div>

                {{-- Dokumen Transportasi --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
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

                    <div class="p-6 space-y-5">

                        {{-- Boarding Pass --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Boarding Pass
                            </label>
                            <input type="file" name="boarding_pass" id="boarding_pass" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                        {{-- Tiket / Invoice --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Tiket / Invoice <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="tiket_invoice" id="tiket_invoice" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                    </div>
                </div>

                {{-- Dokumen Akomodasi & Biaya --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
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

                    <div class="p-6 space-y-5">

                        {{-- Bill Hotel --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Bill Hotel
                            </label>
                            <input type="file" name="bill_hotel" id="bill_hotel" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                        {{-- Kwitansi Lokal --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Kwitansi Lokal
                            </label>
                            <input type="file" name="kwitansi_lokal" id="kwitansi_lokal" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                        </div>

                    </div>
                </div>

                {{-- Laporan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Laporan Perjalanan</h3>
                            <p class="text-xs text-slate-400">Laporan hasil dan pertanggungjawaban perjalanan</p>
                        </div>
                    </div>

                    <div class="p-6">

                        {{-- Laporan Hasil Perjalanan --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Laporan Hasil Perjalanan <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="laporan_hasil" id="laporan_hasil" accept=".pdf,.doc,.docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, DOCX — maks. 10 MB</p>
                        </div>

                    </div>
                </div>

            </div>

            {{-- RIGHT: Ringkasan & Aksi --}}
            <div class="space-y-5">

                {{-- Info Usulan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-4">
                    <h3 class="font-bold text-slate-800 text-sm">Info Perjalanan</h3>

                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-xs text-slate-400">Nomor Usulan</p>
                            <p class="font-semibold text-slate-800">USL-2025-001</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Kegiatan</p>
                            <p class="font-semibold text-slate-800">Rapat Koordinasi Nasional</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Tujuan</p>
                            <p class="font-semibold text-slate-800">Jakarta</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Periode</p>
                            <p class="font-semibold text-slate-800">15 Jan — 17 Jan 2025</p>
                        </div>
                    </div>
                </div>

                {{-- Checklist Dokumen --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 text-sm mb-4">Kelengkapan Dokumen</h3>
                    <div class="space-y-2.5 text-sm">

                        @php
                            $docs = [
                                ['label' => 'Surat Tugas', 'required' => true],
                                ['label' => 'SPPD', 'required' => true],
                                ['label' => 'Boarding Pass', 'required' => false],
                                ['label' => 'Tiket / Invoice', 'required' => true],
                                ['label' => 'Bill Hotel', 'required' => false],
                                ['label' => 'Kwitansi Lokal', 'required' => false],
                                ['label' => 'Laporan Hasil Perjalanan', 'required' => true],
                            ];
                        @endphp

                        @foreach($docs as $doc)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">
                                {{ $doc['label'] }}
                                @if($doc['required'])
                                    <span class="text-red-400 text-xs">*</span>
                                @endif
                            </span>
                            <span class="text-xs text-slate-300 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded-full">
                                Belum
                            </span>
                        </div>
                        @endforeach

                    </div>
                </div>

                {{-- Tombol Aksi --}}
                <div class="space-y-2">
                    <button type="submit"
                            class="w-full py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                        Simpan & Kirim Dokumen
                    </button>
                    <button type="button"
                            class="w-full py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                        Simpan Draft
                    </button>
                </div>

            </div>

        </div>

    </form>

</div>

@endsection