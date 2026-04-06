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
            <h1 class="text-xl font-bold text-slate-800">Rincian Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Detail lengkap usulan perjalanan dinas Anda</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- DATA DASAR --}}
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

                <div class="p-6 space-y-4 text-sm">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Jenis Kegiatan</p>
                            <p class="font-semibold">Rapat Koordinasi Nasional</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Dasar Penugasan</p>
                            <p class="font-semibold">Undangan No. 123/SET/2026</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Lokasi Tujuan</p>
                            <p class="font-semibold">Jakarta</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Instansi Tujuan</p>
                            <p class="font-semibold">Kementerian Kesehatan RI</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-slate-500">Tanggal Mulai</p>
                            <p class="font-semibold">10 Januari 2026</p>
                        </div>

                        <div>
                            <p class="text-slate-500">Tanggal Selesai</p>
                            <p class="font-semibold">12 Januari 2026</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-slate-500">Uraian Tujuan</p>
                        <p class="font-semibold">
                            Menghadiri rapat koordinasi nasional terkait implementasi program kesehatan daerah.
                        </p>
                    </div>

                </div>
            </div>

            {{-- PESERTA --}}
            <div class="flex flex-col gap-3">

                <!-- Card 1 -->
                <div class="flex items-center gap-4 bg-white border border-slate-100 rounded-xl px-4 py-3 hover:border-slate-200 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-teal-50 text-teal-700 flex items-center justify-center text-sm font-semibold shrink-0">
                    AF
                    </div>
                    <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">Ahmad Fauzi</p>
                    <p class="text-xs text-slate-400 truncate">Kepala Bidang · NIP 198812312010</p>
                    </div>
                    <span class="text-xs font-semibold bg-teal-50 text-teal-700 px-3 py-1 rounded-full shrink-0">
                    Pengusul
                    </span>
                    <button
                    onclick="openPesertaModal('Ahmad Fauzi','Kepala Bidang','198812312010','Dinas Kesehatan','Gol IV/a')"
                    class="text-xs font-semibold text-slate-600 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors shrink-0">
                    Detail
                    </button>
                </div>

                <!-- Card 2 -->
                <div class="flex items-center gap-4 bg-white border border-slate-100 rounded-xl px-4 py-3 hover:border-slate-200 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center text-sm font-semibold shrink-0">
                    SR
                    </div>
                    <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">Siti Rahma</p>
                    <p class="text-xs text-slate-400 truncate">Staff Program</p>
                    </div>
                    <button
                    onclick="openPesertaModal('Siti Rahma','Staff Program','-','Dinas Kesehatan','III/b')"
                    class="text-xs font-semibold text-slate-600 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors shrink-0">
                    Detail
                    </button>
                </div>

            </div>

            {{-- LAMPIRAN --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                        <p class="text-xs text-slate-400">Dokumen pendukung perjalanan dinas</p>
                    </div>
                </div>

                <div class="p-6 space-y-3 text-sm">

                    <div class="flex justify-between">
                        <span>Surat Undangan</span>
                        <a href="#" class="text-blue-600 font-semibold">Lihat</a>
                    </div>
                    
                    <div class="flex justify-between">
                        <span>Surat Disposisi</span>
                        <a href="#" class="text-blue-600 font-semibold">Lihat</a>
                    </div>

                    <div class="flex justify-between">
                        <span>TOR</span>
                        <a href="#" class="text-blue-600 font-semibold">Lihat</a>
                    </div>


                    <div class="flex justify-between">
                        <span>Dokumen Pendukung</span>
                        <a href="#" class="text-blue-600 font-semibold">Lihat</a>
                    </div>

                </div>
            </div>

        </div>

        {{-- RIGHT SIDEBAR --}}
        <div class="space-y-5">

            {{-- TIMELINE PERSETUJUAN --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    </div>
                    <div>
                    <h3 class="font-bold text-slate-800 text-sm">Timeline Persetujuan</h3>
                    </div>
                </div>

                <div class="space-y-4 text-sm">

                    {{-- STEP 1 --}}
                    <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-teal-500 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                    </div>
                    <div class="pb-4">
                        <p class="font-semibold text-slate-800">Submit Usulan</p>
                        <p class="text-xs text-slate-500">10 Jan 2026</p>
                    </div>
                    </div>

                    {{-- STEP 2 --}}
                    <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                    </div>
                    <div class="pb-4">
                        <p class="font-semibold text-slate-800">Persetujuan Atasan</p>
                        <p class="text-xs text-slate-500">Menunggu...</p>
                    </div>
                    </div>

                    {{-- STEP 3 --}}
                    <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="flex flex-col items-center">
                            <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            </div>
                            <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                        </div>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">Verifikasi SDM/SUBBAG KEPEGAWAIAN</p>
                        <p class="text-xs text-slate-500">Menunggu...</p>
                    </div>
                    </div>

                    {{-- STEP 4 --}}
                    <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        <div class="w-0.5 h-8 bg-slate-200 my-1"></div>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">PPK</p>
                        <p class="text-xs text-slate-500">Menunggu...</p>
                    </div>
                    </div>

                    {{-- STEP 5 --}}
                    <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-6 h-6 rounded-full bg-slate-300 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        </div>
                        
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">Direktur</p>
                        <p class="text-xs text-slate-500">Menunggu...</p>
                    </div>
                    </div>

                </div>
            </div>

            {{-- STATUS & BIAYA --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-4">

                <div>
                    <p class="text-xs text-slate-500">Status</p>
                    <span class="px-3 py-1 text-xs rounded-full bg-amber-100 text-amber-700">
                    Draft
                    </span>
                </div>

                <div>
                    <p class="text-xs text-slate-500">Estimasi Biaya</p>
                    <p class="text-2xl font-bold text-violet-600">
                    Rp 5.250.000
                    </p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex flex-col gap-2">

                    {{-- Unduh --}}
                    <a href="#"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-sm font-semibold
                            bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        Unduh Dokumen
                    </a>

                    {{-- SETUJU --}}
                    <button class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold
                                    bg-teal-50 text-teal-700 border border-teal-100 hover:bg-teal-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Setujui & Teruskan
                    </button>

                    {{-- TOLAK --}}
                    <button class="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold
                                    bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Tolak / Revisi
                    </button>

                </div>
            </div>

        </div>

    </div>

</div>


{{-- MODAL DETAIL PESERTA --}}
<div id="modalPeserta" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden items-center justify-center z-50">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">

        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-slate-800">Detail Peserta</h3>
            <button onclick="closePesertaModal()">✕</button>
        </div>

        <div class="p-6 space-y-3 text-sm">

            <div>
                <p class="text-slate-500">Nama</p>
                <p class="font-semibold" id="p_nama"></p>
            </div>

            <div>
                <p class="text-slate-500">Jabatan</p>
                <p class="font-semibold" id="p_jabatan"></p>
            </div>

            <div>
                <p class="text-slate-500">NIP</p>
                <p class="font-semibold" id="p_nip"></p>
            </div>

            <div>
                <p class="text-slate-500">Unit Kerja</p>
                <p class="font-semibold" id="p_unit"></p>
            </div>

            <div>
                <p class="text-slate-500">Golongan</p>
                <p class="font-semibold" id="p_gol"></p>
            </div>

        </div>

        <div class="px-6 py-4 border-t">
            <button onclick="closePesertaModal()" 
                class="w-full py-2 bg-slate-100 rounded-xl">
                Tutup
            </button>
        </div>

    </div>

</div>

@vite('resources/js/detail-usulan.js')
@endsection