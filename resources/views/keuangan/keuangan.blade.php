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
            <h1 class="text-xl font-bold text-slate-800">Keuangan & LPJ</h1>
            <p class="text-xs text-slate-400 mt-0.5">Rincian biaya, pembayaran, verifikasi bukti, dan daftar nominatif</p>
        </div>
    </div>

    {{-- ── Pilih Perjalanan Dinas + Peserta ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Pilih Perjalanan Dinas</h3>
                <p class="text-xs text-slate-400">Pilih perjalanan dinas yang akan dikelola keuangannya</p>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Nomor Usulan / Kegiatan <span class="text-red-500">*</span>
                    </label>
                    <select id="keu_pejadin_select"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        <option value="">— Pilih perjalanan dinas —</option>
                        <optgroup label="Sedang Berjalan">
                            <option value="1">USL-2025-001 — Rapat Koordinasi Nasional</option>
                            <option value="2">USL-2025-004 — Bimtek Pengelolaan Anggaran</option>
                        </optgroup>
                        <optgroup label="Selesai / Perlu LPJ">
                            <option value="3">USL-2024-089 — Workshop SDM</option>
                            <option value="4">USL-2024-075 — Studi Banding</option>
                            <option value="5">USL-2024-060 — Seminar Nasional Kesehatan</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <button type="button"
                            class="w-full px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Tampilkan
                    </button>
                </div>
            </div>

            {{-- Preview: Info Pejadin + Daftar Peserta --}}
            <div class="mt-5">

                {{-- Info singkat --}}
                <div class="p-4 bg-teal-50 border border-teal-100 rounded-xl mb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Nomor Usulan</p>
                            <p class="font-semibold text-slate-800">USL-2025-001</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Kegiatan</p>
                            <p class="font-semibold text-slate-800">Rapat Koordinasi Nasional</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Tujuan</p>
                            <p class="font-semibold text-slate-800">Jakarta</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Periode</p>
                            <p class="font-semibold text-slate-800">15–17 Jan 2025</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── UANG MUKA: STATE BELUM DIBAYAR ── --}}
    <div class="mb-6">
        <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Konfirmasi Pembayaran Uang Muka</h3>
                        <p class="text-xs text-slate-400">Transfer 80% dari estimasi biaya sebelum keberangkatan</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum Dibayar
                </span>
            </div>
            <div class="p-6">
                {{-- Kalkulasi --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <p class="text-xs text-slate-500 mb-1">Total Estimasi</p>
                        <p class="text-xl font-bold text-slate-800">Rp 8.750.000</p>
                    </div>
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                        <p class="text-xs text-amber-600 mb-1 font-medium">80% — Uang Muka</p>
                        <p class="text-xl font-bold text-amber-700">Rp 7.000.000</p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <p class="text-xs text-slate-500 mb-1">Sisa Bayar (20%)</p>
                        <p class="text-xl font-bold text-slate-600">Rp 1.750.000</p>
                    </div>
                </div>
                {{-- Form Upload --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transfer <span class="text-red-500">*</span></label>
                        <input type="date"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Bukti Transfer <span class="text-red-500">*</span></label>
                        <input type="text" placeholder="cth. BM-250115-01"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                        <label class="flex items-center gap-2 w-full px-3.5 py-2.5 border border-dashed border-slate-300 rounded-xl text-sm text-slate-500 cursor-pointer hover:border-teal-400 hover:bg-teal-50/30 transition bg-white">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                            </svg>
                            <span class="truncate">Pilih file (PDF/JPG)</span>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                        </label>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button"
                            class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition flex items-center gap-2 shadow-sm shadow-amber-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Konfirmasi Pembayaran Uang Muka
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── UANG MUKA: STATE SUDAH DIBAYAR (fase berjalan) ── --}}
    <div class="mb-6">
        <div class="bg-white rounded-2xl border-2 border-teal-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-teal-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Pembayaran Uang Muka</h3>
                        <p class="text-xs text-slate-400">Detail bukti transfer uang muka 80% — fase <strong>Sedang Berjalan</strong></p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 text-teal-700 border border-teal-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Sudah Dibayar
                </span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Tanggal Transfer</p>
                        <p class="text-sm font-semibold text-slate-800">20/01/2025</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Nomor Bukti</p>
                        <p class="text-sm font-semibold text-slate-800 font-mono">BM-250120-04</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Nominal Ditransfer (80%)</p>
                        <p class="text-sm font-bold text-teal-700">Rp 7.200.000</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Bukti Transfer</p>
                        <a href="#" class="text-sm text-teal-600 font-semibold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13A9 9 0 1112 3"/>
                            </svg>
                            Lihat File
                        </a>
                    </div>
                </div>
                {{-- Notice: perjalanan masih berjalan --}}
                <div class="mt-4 flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4m0 4h.01"/>
                    </svg>
                    <span>Uang muka telah dibayarkan. Rincian LPJ dan verifikasi pembayaran sisa akan tersedia setelah perjalanan selesai.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── UANG MUKA: STATE SUDAH DIBAYAR (fase selesai, tanpa notice) ── --}}
    <div class="mb-6">
        <div class="bg-white rounded-2xl border-2 border-teal-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-teal-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Pembayaran Uang Muka</h3>
                        <p class="text-xs text-slate-400">Detail bukti transfer uang muka 80% — fase <strong>Selesai</strong></p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 text-teal-700 border border-teal-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Sudah Dibayar
                </span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Tanggal Transfer</p>
                        <p class="text-sm font-semibold text-slate-800">03/12/2024</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Nomor Bukti</p>
                        <p class="text-sm font-semibold text-slate-800 font-mono">BM-241203-01</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Nominal Ditransfer (80%)</p>
                        <p class="text-sm font-bold text-teal-700">Rp 6.000.000</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Bukti Transfer</p>
                        <a href="#" class="text-sm text-teal-600 font-semibold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13A9 9 0 1112 3"/>
                            </svg>
                            Lihat File
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── SISA BAYAR: STATE BELUM DIBAYAR ── --}}
    <div class="mb-6">
        <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Konfirmasi Pembayaran Sisa</h3>
                        <p class="text-xs text-slate-400">Transfer sisa 20% setelah LPJ diverifikasi dan perjalanan selesai</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum Dibayar
                </span>
            </div>
            <div class="p-6">
                {{-- Kalkulasi --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <p class="text-xs text-slate-500 mb-1">Total Realisasi</p>
                        <p class="text-xl font-bold text-slate-800">Rp 7.900.000</p>
                    </div>
                    <div class="bg-teal-50 border border-teal-100 rounded-xl p-4 text-center">
                        <p class="text-xs text-teal-600 mb-1 font-medium">Uang Muka Dibayar (80%)</p>
                        <p class="text-xl font-bold text-teal-700">Rp 7.000.000</p>
                    </div>
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                        <p class="text-xs text-amber-600 mb-1 font-medium">Sisa yang Harus Dibayar</p>
                        <p class="text-xl font-bold text-amber-700">Rp 900.000</p>
                    </div>
                </div>
                {{-- Form Upload --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transfer <span class="text-red-500">*</span></label>
                        <input type="date"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Bukti Transfer <span class="text-red-500">*</span></label>
                        <input type="text" placeholder="cth. BS-250120-01"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                        <label class="flex items-center gap-2 w-full px-3.5 py-2.5 border border-dashed border-slate-300 rounded-xl text-sm text-slate-500 cursor-pointer hover:border-teal-400 hover:bg-teal-50/30 transition bg-white">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                            </svg>
                            <span class="truncate">Pilih file (PDF/JPG)</span>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                        </label>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button"
                            class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition flex items-center gap-2 shadow-sm shadow-amber-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Konfirmasi Pembayaran Sisa
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── SISA BAYAR: STATE SUDAH DIBAYAR SEPENUHNYA ── --}}
    <div class="mb-6">
        <div class="bg-white rounded-2xl border-2 border-emerald-300 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-emerald-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Pembayaran Lunas</h3>
                        <p class="text-xs text-slate-400">Seluruh biaya perjalanan dinas telah dibayarkan</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sudah Dibayar Sepenuhnya
                </span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Tanggal Pelunasan</p>
                        <p class="text-sm font-semibold text-slate-800">08/04/2026</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Nomor Bukti</p>
                        <p class="text-sm font-semibold text-slate-800 font-mono">BS-260408-02</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Total Dibayarkan</p>
                        <p class="text-sm font-bold text-emerald-700">Rp 7.900.000</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-0.5">Bukti Transfer</p>
                        <a href="#" class="text-sm text-emerald-600 font-semibold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13A9 9 0 1112 3"/>
                            </svg>
                            Lihat File
                        </a>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-4 bg-emerald-50 border border-emerald-100 rounded-xl text-sm text-emerald-700">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Pembayaran telah lunas. Uang muka <strong>Rp 7.000.000</strong> dan sisa bayar <strong>Rp 900.000</strong> telah berhasil ditransfer. LPJ dinyatakan selesai.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Stat Cards (hanya tampil saat fase selesai) ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">8,75 jt</p>
                <p class="text-xs text-slate-500 font-medium">Total estimasi</p>
                <p class="text-xs text-slate-400">rincian awal perjalanan</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">80%</p>
                <p class="text-xs text-slate-500 font-medium">Uang muka</p>
                <p class="text-xs text-slate-400">siap dibayar saat dokumen final</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">7,90 jt</p>
                <p class="text-xs text-slate-500 font-medium">Realisasi</p>
                <p class="text-xs text-slate-400">menunggu verifikasi akhir</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">20%</p>
                <p class="text-xs text-slate-500 font-medium">Sisa bayar</p>
                <p class="text-xs text-slate-400">setelah LPJ lengkap</p>
            </div>
        </div>
    </div>

    {{-- ── Main Content (hanya tampil saat fase selesai) ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT --}}
        <div class="xl:col-span-2 space-y-5">
            {{-- Rincian Biaya Perjalanan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M9 7h8"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Rincian Biaya Perjalanan</h3>
                            <p class="text-xs text-slate-400">Input komponen biaya yang digunakan</p>
                        </div>
                    </div>
                    <button type="button"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Tambah Komponen
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Komponen</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3 w-24">Qty</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3 w-40">Tarif (Rp)</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3 w-36">Total</th>
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @php
                            $komponenRows = [
                                ['nama' => 'Transport pesawat', 'qty' => 2,    'tarif' => 1800000],
                                ['nama' => 'Hotel',             'qty' => 3,    'tarif' => 650000],
                                ['nama' => 'Uang harian',       'qty' => 4,    'tarif' => 350000],
                                ['nama' => 'Transport lokal',   'qty' => 1,    'tarif' => 500000],
                                ['nama' => 'Lain-lain',         'qty' => null, 'tarif' => 1300000],
                            ];
                            $grandTotal = 0;
                            @endphp
                            @foreach ($komponenRows as $row)
                            @php
                            $rowTotal = ($row['qty'] && $row['tarif']) ? $row['qty'] * $row['tarif'] : ($row['tarif'] ?? 0);
                            $grandTotal += $rowTotal;
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-2.5">
                                    <input type="text" value="{{ $row['nama'] }}" placeholder="Nama komponen"
                                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">
                                </td>
                                <td class="px-4 py-2.5">
                                    <input type="number" value="{{ $row['qty'] ?? '' }}" placeholder="—" min="1"
                                            class="w-20 px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">
                                </td>
                                <td class="px-4 py-2.5">
                                    <input type="number" value="{{ $row['tarif'] ?? '' }}" placeholder="0"
                                            class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">
                                </td>
                                <td class="px-4 py-2.5 text-sm font-semibold text-slate-700">
                                    Rp {{ number_format($rowTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button type="button"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition mx-auto">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50">
                                <td colspan="3" class="px-6 py-3 text-sm font-bold text-slate-700 text-right">Total Estimasi</td>
                                <td class="px-4 py-3 text-sm font-bold text-teal-700">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            {{-- Riwayat Pembayaran --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Riwayat Pembayaran</h3>
                            <p class="text-xs text-slate-400">Rekam jejak transaksi keuangan perjalanan</p>
                        </div>
                    </div>
                    <button type="button"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Catat Pembayaran
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Tanggal</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jenis</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nomor Bukti</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-3.5 text-slate-600">28/03/2026</td>
                                <td class="px-4 py-3.5 text-slate-700 font-medium">Uang muka</td>
                                <td class="px-4 py-3.5 text-slate-500 font-mono text-xs">BM-240328-01</td>
                                <td class="px-4 py-3.5 text-slate-700 font-semibold">Rp 7.000.000</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Berhasil
                                    </span>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-3.5 text-slate-600">05/04/2026</td>
                                <td class="px-4 py-3.5 text-slate-700 font-medium">Sisa bayar</td>
                                <td class="px-4 py-3.5 text-slate-400 text-xs">—</td>
                                <td class="px-4 py-3.5 text-slate-700 font-semibold">Rp 1.750.000</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Menunggu LPJ
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- RIGHT --}}
        <div class="space-y-5">

            {{-- Checklist LPJ --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Checklist LPJ</h3>
                        <p class="text-xs text-slate-400">Kelengkapan dokumen pertanggungjawaban</p>
                    </div>
                </div>

                @php
                $dokumenLPJ = [
                    ['nama' => 'Surat Tugas',              'status' => 'lengkap'],
                    ['nama' => 'SPPD',                     'status' => 'lengkap'],
                    ['nama' => 'Boarding pass',            'status' => 'lengkap'],
                    ['nama' => 'Tiket / invoice',          'status' => 'lengkap'],
                    ['nama' => 'Bill hotel',               'status' => 'perlu_upload'],
                    ['nama' => 'Kwitansi lokal',           'status' => 'lengkap'],
                    ['nama' => 'Laporan hasil perjalanan', 'status' => 'lengkap'],
                ];
                @endphp

                <div class="space-y-2">
                    @foreach ($dokumenLPJ as $dok)
                    <div class="flex items-center justify-between py-2.5 px-3 rounded-xl {{ $dok['status'] === 'lengkap' ? 'bg-teal-50/60' : 'bg-amber-50/60' }}">
                        <div class="flex items-center gap-2.5">
                            @if ($dok['status'] === 'lengkap')
                            <div class="w-5 h-5 rounded-full bg-teal-500 flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            @else
                            <div class="w-5 h-5 rounded-full bg-amber-400 flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path d="M12 9v4m0 4h.01"/>
                                </svg>
                            </div>
                            @endif
                            <span class="text-sm text-slate-700 font-medium">{{ $dok['nama'] }}</span>
                        </div>
                        <span class="text-xs font-semibold {{ $dok['status'] === 'lengkap' ? 'text-teal-600' : 'text-amber-600' }}">
                            {{ $dok['status'] === 'lengkap' ? 'Lengkap' : 'Perlu upload' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Tombol Aksi --}}
            <div class="space-y-3">
                <button type="button"
                        class="w-full py-3 bg-white border-2 border-teal-500 text-teal-600 hover:bg-teal-50 text-sm font-bold rounded-xl transition">
                    Verifikasi LPJ
                </button>
                <button type="button"
                        class="w-full py-3 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                    Proses Pembayaran Sisa
                </button>
                <button type="button"
                        class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                    Simpan Rincian Biaya
                </button>
            </div>

        </div>
    </div>

</div>

@endsection
