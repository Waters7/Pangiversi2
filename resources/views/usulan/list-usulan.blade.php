@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Kelola dan pantau seluruh usulan perjalanan dinas Anda</p>
        </div>
        <a href="#"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Buat Usulan Baru
        </a>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
        <div class="flex flex-col sm:flex-row gap-3">

            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Cari dasar penugasan, tujuan, kota..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>

            <select class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-[160px]">
                <option value="">Semua Status</option>
                <option>Draft</option>
                <option>Diajukan</option>
                <option>Disetujui</option>
                <option>Ditolak</option>
                <option>Selesai</option>
            </select>

            <input type="month"
                   class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">

            <button type="button"
                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                Filter
            </button>

        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">12</p>
                <p class="text-xs text-slate-400">Total Usulan</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">3</p>
                <p class="text-xs text-slate-400">Menunggu</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">7</p>
                <p class="text-xs text-slate-400">Disetujui</p>
            </div>
        </div>

        

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 text-red-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">2</p>
                <p class="text-xs text-slate-400">Ditolak</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            </div>
            <div>
            <p class="text-2xl font-bold text-slate-800">5</p>
            <p class="text-xs text-slate-400">Selesai</p>
            </div>
        </div>

    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <p class="text-sm font-bold text-slate-700">
                Daftar Usulan
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">12 data</span>
            </p>
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                </svg>
                Terbaru di atas
            </div>
        </div>

        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-6 py-3.5">No. / Tanggal</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Kegiatan & Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Periode</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Peserta</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Estimasi</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">

                    {{-- Row 1: Diajukan --}}
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800 text-xs">USL-2025-001</p>
                            <p class="text-xs text-slate-400 mt-0.5">12 Jan 2025</p>
                        </td>
                        <td class="px-4 py-4 max-w-xs">
                            <p class="font-semibold text-slate-800 text-sm leading-snug">Rapat Koordinasi Nasional</p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Undangan Rakornas No. 012/KEMENKES/I/2025</p>
                            <div class="flex items-center gap-1 mt-1.5">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span class="text-xs text-slate-500">Jakarta · Kemenkes RI</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs font-semibold text-slate-700">15 Jan — 17 Jan 2025</p>
                            <p class="text-xs text-slate-400 mt-0.5">3 hari</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-1.5">
                                    <img src="https://ui-avatars.com/api/?name=Ahmad+Fauzi&background=14b8a6&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                    <img src="https://ui-avatars.com/api/?name=Siti+Rahma&background=6366f1&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                </div>
                                <span class="text-xs text-slate-500">2 orang</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-sm font-bold text-violet-600">Rp 4.500.000</p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">Diajukan</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Row 2: Draft --}}
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800 text-xs">USL-2025-002</p>
                            <p class="text-xs text-slate-400 mt-0.5">18 Jan 2025</p>
                        </td>
                        <td class="px-4 py-4 max-w-xs">
                            <p class="font-semibold text-slate-800 text-sm leading-snug">Pelatihan Manajemen Keuangan</p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">SK Direktur No. 045/DIR/I/2025</p>
                            <div class="flex items-center gap-1 mt-1.5">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span class="text-xs text-slate-500">Surabaya · Hotel Majapahit</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs font-semibold text-slate-700">22 Jan — 24 Jan 2025</p>
                            <p class="text-xs text-slate-400 mt-0.5">3 hari</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-1.5">
                                    <img src="https://ui-avatars.com/api/?name=Budi+Santoso&background=f59e0b&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                </div>
                                <span class="text-xs text-slate-500">1 orang</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs text-slate-400 italic">Belum diisi</p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">Draft</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-100 text-slate-600 hover:text-red-600 flex items-center justify-center transition" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- Row 3: Disetujui --}}
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800 text-xs">USL-2025-003</p>
                            <p class="text-xs text-slate-400 mt-0.5">25 Jan 2025</p>
                        </td>
                        <td class="px-4 py-4 max-w-xs">
                            <p class="font-semibold text-slate-800 text-sm leading-snug">Supervisi Lapangan Puskesmas</p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Surat Tugas No. 078/TU/I/2025</p>
                            <div class="flex items-center gap-1 mt-1.5">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span class="text-xs text-slate-500">Minahasa · Puskesmas Tondano</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs font-semibold text-slate-700">28 Jan — 29 Jan 2025</p>
                            <p class="text-xs text-slate-400 mt-0.5">2 hari</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-1.5">
                                    <img src="https://ui-avatars.com/api/?name=Dewi+Lestari&background=14b8a6&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                    <img src="https://ui-avatars.com/api/?name=Hendra+P&background=8b5cf6&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                    <img src="https://ui-avatars.com/api/?name=Rini+S&background=ec4899&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                </div>
                                <span class="text-xs text-slate-500">3 orang</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-sm font-bold text-violet-600">Rp 2.800.000</p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-teal-100 text-teal-700">Disetujui</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Row 4: Ditolak --}}
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800 text-xs">USL-2025-004</p>
                            <p class="text-xs text-slate-400 mt-0.5">02 Feb 2025</p>
                        </td>
                        <td class="px-4 py-4 max-w-xs">
                            <p class="font-semibold text-slate-800 text-sm leading-snug">Workshop Digitalisasi Layanan</p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Undangan Workshop No. 033/DINKES/II/2025</p>
                            <div class="flex items-center gap-1 mt-1.5">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span class="text-xs text-slate-500">Bandung · Grand Preanger</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs font-semibold text-slate-700">10 Feb — 12 Feb 2025</p>
                            <p class="text-xs text-slate-400 mt-0.5">3 hari</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-1.5">
                                    <img src="https://ui-avatars.com/api/?name=Ahmad+Fauzi&background=14b8a6&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                </div>
                                <span class="text-xs text-slate-500">1 orang</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-sm font-bold text-violet-600">Rp 5.200.000</p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-700">Ditolak</span>
                            <p class="text-xs text-red-400 mt-1 max-w-[120px] mx-auto line-clamp-1" title="Anggaran tidak tersedia bulan ini">
                                Anggaran tidak tersedia
                            </p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 flex items-center justify-center transition" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Row 5: Selesai --}}
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800 text-xs">USL-2025-005</p>
                            <p class="text-xs text-slate-400 mt-0.5">10 Feb 2025</p>
                        </td>
                        <td class="px-4 py-4 max-w-xs">
                            <p class="font-semibold text-slate-800 text-sm leading-snug">Bimtek Akreditasi RS</p>
                            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Undangan Bimtek No. 099/KARS/II/2025</p>
                            <div class="flex items-center gap-1 mt-1.5">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                <span class="text-xs text-slate-500">Makassar · Hotel Aryaduta</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-xs font-semibold text-slate-700">14 Feb — 16 Feb 2025</p>
                            <p class="text-xs text-slate-400 mt-0.5">3 hari</p>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <div class="flex -space-x-1.5">
                                    <img src="https://ui-avatars.com/api/?name=Siti+Rahma&background=6366f1&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                    <img src="https://ui-avatars.com/api/?name=Hendra+P&background=8b5cf6&color=fff&size=32" class="w-6 h-6 rounded-full ring-2 ring-white">
                                    <div class="w-6 h-6 rounded-full ring-2 ring-white bg-slate-300 flex items-center justify-center text-xs font-bold text-slate-600">+2</div>
                                </div>
                                <span class="text-xs text-slate-500">4 orang</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-sm font-bold text-violet-600">Rp 8.750.000</p>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700">Selesai</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="#" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-teal-100 text-slate-600 hover:text-teal-700 flex items-center justify-center transition" title="Lihat Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="md:hidden divide-y divide-slate-100">

            {{-- Card 1: Diajukan --}}
            <div class="p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-slate-800 text-sm">Rapat Koordinasi Nasional</p>
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Undangan Rakornas No. 012/KEMENKES/I/2025</p>
                    </div>
                    <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full shrink-0 bg-amber-100 text-amber-700">Diajukan</span>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        Jakarta
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        15–17 Jan 2025
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        2 peserta
                    </div>
                    <span class="text-xs font-bold text-violet-600">Rp 4.500.000</span>
                </div>
                <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                    <a href="#" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-teal-100 text-slate-700 hover:text-teal-700 text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Detail
                    </a>
                </div>
            </div>

            {{-- Card 2: Draft --}}
            <div class="p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-slate-800 text-sm">Pelatihan Manajemen Keuangan</p>
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">SK Direktur No. 045/DIR/I/2025</p>
                    </div>
                    <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full shrink-0 bg-slate-100 text-slate-600">Draft</span>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        Surabaya
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        22–24 Jan 2025
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        1 peserta
                    </div>
                    <span class="text-xs text-slate-400 italic">Estimasi belum diisi</span>
                </div>
                <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                    <a href="#" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-teal-100 text-slate-700 hover:text-teal-700 text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Detail
                    </a>
                    <a href="#" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-700 text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                    <button type="button" class="flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-red-100 text-slate-700 hover:text-red-600 text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                        Hapus
                    </button>
                </div>
            </div>

        </div>

        {{-- Pagination (dummy) --}}
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">Menampilkan 1–5 dari 12 data</p>
            <div class="flex items-center gap-1">
                <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-400 flex items-center justify-center" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <button class="w-8 h-8 rounded-lg bg-teal-500 text-white text-xs font-bold flex items-center justify-center">1</button>
                <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 flex items-center justify-center transition">2</button>
                <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 flex items-center justify-center transition">3</button>
                <button class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 flex items-center justify-center transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
        </div>

    </div>

</div>

@endsection