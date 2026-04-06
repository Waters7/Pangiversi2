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
                    <button type="button" id="keuPilihBtn"
                            class="w-full px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Tampilkan
                    </button>
                </div>
            </div>

            {{-- Preview: Info Pejadin + Daftar Peserta --}}
            <div id="keu_pejadin_info" class="hidden mt-5">

                {{-- Info singkat --}}
                <div class="p-4 bg-teal-50 border border-teal-100 rounded-xl mb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Nomor Usulan</p>
                            <p class="font-semibold text-slate-800" id="keu_info_nomor">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Kegiatan</p>
                            <p class="font-semibold text-slate-800" id="keu_info_kegiatan">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Tujuan</p>
                            <p class="font-semibold text-slate-800" id="keu_info_tujuan">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Periode</p>
                            <p class="font-semibold text-slate-800" id="keu_info_periode">—</p>
                        </div>
                    </div>
                </div>

                {{-- Daftar Peserta --}}
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Daftar Peserta</p>
                <div id="keu_peserta_list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {{-- diisi oleh JS --}}
                </div>

            </div>
        </div>
    </div>

    {{-- ── Konfirmasi Uang Muka (80%) ── --}}
    <div id="keu_uang_muka" class="hidden mb-6">

        {{-- State: Belum Dibayar --}}
        <div id="keu_um_belum" class="hidden">
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
                            <p class="text-xl font-bold text-slate-800" id="um_estimasi_total">—</p>
                        </div>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                            <p class="text-xs text-amber-600 mb-1 font-medium">80% — Uang Muka</p>
                            <p class="text-xl font-bold text-amber-700" id="um_80persen">—</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center">
                            <p class="text-xs text-slate-500 mb-1">Sisa Bayar (20%)</p>
                            <p class="text-xl font-bold text-slate-600" id="um_20persen">—</p>
                        </div>
                    </div>
                    {{-- Form Upload --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transfer <span class="text-red-500">*</span></label>
                            <input type="date" id="um_tgl"
                                   class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Bukti Transfer <span class="text-red-500">*</span></label>
                            <input type="text" id="um_bukti" placeholder="cth. BM-250115-01"
                                   class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                            <label class="flex items-center gap-2 w-full px-3.5 py-2.5 border border-dashed border-slate-300 rounded-xl text-sm text-slate-500 cursor-pointer hover:border-teal-400 hover:bg-teal-50/30 transition bg-white">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                                </svg>
                                <span id="um_file_name" class="truncate">Pilih file (PDF/JPG)</span>
                                <input type="file" id="um_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="document.getElementById('um_file_name').textContent = this.files[0] ? this.files[0].name : 'Pilih file (PDF/JPG)'">
                            </label>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <button type="button" id="konfirmasiUangMukaBtn"
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

        {{-- State: Sudah Dibayar --}}
        <div id="keu_um_sudah" class="hidden">
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
                            <p class="text-xs text-slate-400">Detail bukti transfer uang muka 80%</p>
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
                            <p class="text-sm font-semibold text-slate-800" id="um_paid_tgl">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Nomor Bukti</p>
                            <p class="text-sm font-semibold text-slate-800 font-mono" id="um_paid_bukti">—</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Nominal Ditransfer (80%)</p>
                            <p class="text-sm font-bold text-teal-700" id="um_paid_nominal">—</p>
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
                    <div id="keu_um_berjalan_notice" class="hidden mt-4 flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4m0 4h.01"/>
                        </svg>
                        <span>Uang muka telah dibayarkan. Rincian LPJ dan verifikasi pembayaran sisa akan tersedia setelah perjalanan selesai.</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Stat Cards ── --}}
    <div id="keu_stats" class="hidden grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

    {{-- ── Main Content ── --}}
    <div id="keu_main" class="hidden grid-cols-1 xl:grid-cols-3 gap-6">

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
                    <button type="button" id="tambahKomponenBtn"
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
                        <tbody id="komponenBody" class="divide-y divide-slate-50">
                            {{-- diisi oleh JS --}}
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50">
                                <td colspan="3" class="px-6 py-3 text-sm font-bold text-slate-700 text-right">Total Estimasi</td>
                                <td class="px-4 py-3 text-sm font-bold text-teal-700" id="grandTotal">Rp 0</td>
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
                    <button type="button" id="tambahPembayaranBtn"
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
                        <tbody id="pembayaranBody" class="divide-y divide-slate-50">
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
                    <div class="flex items-center justify-between py-2.5 px-3 rounded-xl
                        {{ $dok['status'] === 'lengkap' ? 'bg-teal-50/60' : 'bg-amber-50/60' }}">
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

<script>
    // ── Data pejadin (static sample) ─────────────────────────────────────
    // fase: 'berjalan' = sedang/belum berangkat, 'selesai' = sudah pulang (LPJ tersedia)
    // uangMuka.status: 'belum' | 'sudah'
    const keuData = {
        '1': {
            nomor: 'USL-2025-001', kegiatan: 'Rapat Koordinasi Nasional',
            tujuan: 'Jakarta', periode: '15–17 Jan 2025',
            estimasi: 8750000,
            fase: 'berjalan',
            uangMuka: { status: 'belum' },
            peserta: [
                { nama: 'Dr. Hendra Santoso, M.Kes', jabatan: 'Kepala Bagian Keuangan',  nip: '198501012010121001' },
                { nama: 'Siti Rahayu, S.Kep',        jabatan: 'Staf Keuangan',           nip: '199203152015042002' },
            ],
        },
        '2': {
            nomor: 'USL-2025-004', kegiatan: 'Bimtek Pengelolaan Anggaran',
            tujuan: 'Surabaya', periode: '20–22 Jan 2025',
            estimasi: 9000000,
            fase: 'berjalan',
            uangMuka: { status: 'sudah', tanggal: '20/01/2025', bukti: 'BM-250120-04', nominal: 7200000 },
            peserta: [
                { nama: 'Ahmad Fauzi, SE', jabatan: 'Bendahara Pengeluaran', nip: '197802202005011003' },
            ],
        },
        '3': {
            nomor: 'USL-2024-089', kegiatan: 'Workshop SDM',
            tujuan: 'Makassar', periode: '5–7 Des 2024',
            estimasi: 7500000,
            fase: 'selesai',
            uangMuka: { status: 'sudah', tanggal: '03/12/2024', bukti: 'BM-241203-01', nominal: 6000000 },
            peserta: [
                { nama: 'Maria Lumenta, S.Pd',  jabatan: 'Kepala Sub Bagian SDM', nip: '198011122004122005' },
                { nama: 'Ricky Pontoh, SKM',    jabatan: 'Staf SDM',              nip: '199507082019031002' },
                { nama: 'Yolanda Wenas, S.Kep', jabatan: 'Staf Keperawatan',      nip: '199212032020122001' },
            ],
        },
        '4': {
            nomor: 'USL-2024-075', kegiatan: 'Studi Banding',
            tujuan: 'Yogyakarta', periode: '1–3 Nov 2024',
            estimasi: 6000000,
            fase: 'selesai',
            uangMuka: { status: 'sudah', tanggal: '29/10/2024', bukti: 'BM-241029-02', nominal: 4800000 },
            peserta: [
                { nama: 'Prof. Dr. Samuel Roring', jabatan: 'Direktur', nip: '196706151993031001' },
            ],
        },
        '5': {
            nomor: 'USL-2024-060', kegiatan: 'Seminar Nasional Kesehatan',
            tujuan: 'Bandung', periode: '15–16 Okt 2024',
            estimasi: 5500000,
            fase: 'selesai',
            uangMuka: { status: 'sudah', tanggal: '13/10/2024', bukti: 'BM-241013-03', nominal: 4400000 },
            peserta: [
                { nama: 'Ns. Debora Tumewu, M.Kep', jabatan: 'Wakil Direktur I',   nip: '197409222001122002' },
                { nama: 'dr. Andi Kusuma',           jabatan: 'Dokter Fungsional',  nip: '198803052014041003' },
            ],
        },
    };

    // ── Default komponen rows ─────────────────────────────────────────────
    const defaultKomponen = [
        { nama: 'Transport pesawat', qty: 2,    tarif: 1800000 },
        { nama: 'Hotel',             qty: 3,    tarif: 650000  },
        { nama: 'Uang harian',       qty: 4,    tarif: 350000  },
        { nama: 'Transport lokal',   qty: 1,    tarif: 500000  },
        { nama: 'Lain-lain',         qty: null, tarif: 1300000 },
    ];

    let komponenRows = [];

    function formatRupiah(angka) {
        if (!angka || isNaN(angka)) return '—';
        return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
    }

    function renderKomponen() {
        const tbody = document.getElementById('komponenBody');
        tbody.innerHTML = '';
        let total = 0;

        komponenRows.forEach(function (row, i) {
            const rowTotal = (row.qty && row.tarif) ? row.qty * row.tarif : (row.tarif ?? 0);
            total += rowTotal;
            tbody.insertAdjacentHTML('beforeend',
                '<tr class="hover:bg-slate-50/60 transition">' +
                    '<td class="px-6 py-2.5">' +
                        '<input type="text" value="' + row.nama + '" placeholder="Nama komponen"' +
                               ' onchange="updateRow(' + i + ',\'nama\',this.value)"' +
                               ' class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">' +
                    '</td>' +
                    '<td class="px-4 py-2.5">' +
                        '<input type="number" value="' + (row.qty ?? '') + '" placeholder="—" min="1"' +
                               ' onchange="updateRow(' + i + ',\'qty\',this.value)"' +
                               ' class="w-20 px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">' +
                    '</td>' +
                    '<td class="px-4 py-2.5">' +
                        '<input type="number" value="' + (row.tarif ?? '') + '" placeholder="0"' +
                               ' onchange="updateRow(' + i + ',\'tarif\',this.value)"' +
                               ' class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400 transition bg-white">' +
                    '</td>' +
                    '<td class="px-4 py-2.5 text-sm font-semibold text-slate-700">' + formatRupiah(rowTotal) + '</td>' +
                    '<td class="px-4 py-2.5 text-center">' +
                        '<button type="button" onclick="hapusRow(' + i + ')"' +
                                ' class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition mx-auto">' +
                            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
                                '<path d="M6 18L18 6M6 6l12 12"/>' +
                            '</svg>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
        });

        document.getElementById('grandTotal').textContent = formatRupiah(total);
    }

    function updateRow(idx, field, val) {
        if (field === 'qty' || field === 'tarif') {
            komponenRows[idx][field] = val === '' ? null : parseFloat(val);
        } else {
            komponenRows[idx][field] = val;
        }
        renderKomponen();
    }

    function hapusRow(idx) {
        komponenRows.splice(idx, 1);
        renderKomponen();
    }

    window.updateRow = updateRow;
    window.hapusRow  = hapusRow;

    document.getElementById('tambahKomponenBtn').addEventListener('click', function () {
        komponenRows.push({ nama: '', qty: 1, tarif: 0 });
        renderKomponen();
    });

    // ── Helper: show/hide element ─────────────────────────────────────────
    function showEl(id)  { document.getElementById(id).classList.remove('hidden'); }
    function hideEl(id)  { document.getElementById(id).classList.add('hidden'); }
    function showGrid(id) {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        el.classList.add('grid');
    }
    function hideGrid(id) {
        const el = document.getElementById(id);
        el.classList.add('hidden');
        el.classList.remove('grid');
    }

    // ── Render uang muka section ──────────────────────────────────────────
    function renderUangMuka(d) {
        showEl('keu_uang_muka');
        const um = d.uangMuka;

        if (um.status === 'belum') {
            // tampilkan form konfirmasi
            showEl('keu_um_belum');
            hideEl('keu_um_sudah');
            document.getElementById('um_estimasi_total').textContent = formatRupiah(d.estimasi);
            document.getElementById('um_80persen').textContent       = formatRupiah(Math.round(d.estimasi * 0.8));
            document.getElementById('um_20persen').textContent       = formatRupiah(Math.round(d.estimasi * 0.2));
        } else {
            // tampilkan detail sudah bayar
            hideEl('keu_um_belum');
            showEl('keu_um_sudah');
            document.getElementById('um_paid_tgl').textContent    = um.tanggal;
            document.getElementById('um_paid_bukti').textContent  = um.bukti;
            document.getElementById('um_paid_nominal').textContent = formatRupiah(um.nominal);

            if (d.fase === 'berjalan') {
                showEl('keu_um_berjalan_notice');
            } else {
                hideEl('keu_um_berjalan_notice');
            }
        }
    }

    // ── Pilih pejadin ─────────────────────────────────────────────────────
    document.getElementById('keuPilihBtn').addEventListener('click', function () {
        const val   = document.getElementById('keu_pejadin_select').value;
        const info  = document.getElementById('keu_pejadin_info');

        // reset
        hideEl('keu_pejadin_info');
        hideEl('keu_uang_muka');
        hideGrid('keu_stats');
        hideGrid('keu_main');

        if (!val || !keuData[val]) { return; }

        const d = keuData[val];
        document.getElementById('keu_info_nomor').textContent    = d.nomor;
        document.getElementById('keu_info_kegiatan').textContent = d.kegiatan;
        document.getElementById('keu_info_tujuan').textContent   = d.tujuan;
        document.getElementById('keu_info_periode').textContent  = d.periode;

        // render daftar peserta
        document.getElementById('keu_peserta_list').innerHTML = d.peserta.map(function (p) {
            return '<div class="flex items-center gap-3 bg-white border border-slate-100 rounded-xl px-4 py-3">' +
                '<div class="w-9 h-9 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xs font-bold shrink-0">' +
                    p.nama.charAt(0) +
                '</div>' +
                '<div class="min-w-0">' +
                    '<p class="text-sm font-semibold text-slate-800 truncate">' + p.nama + '</p>' +
                    '<p class="text-xs text-slate-400">' + p.jabatan + ' · NIP ' + p.nip + '</p>' +
                '</div>' +
            '</div>';
        }).join('');

        showEl('keu_pejadin_info');

        // render seksi uang muka
        renderUangMuka(d);

        // rincian LPJ hanya untuk fase selesai
        if (d.fase === 'selesai') {
            if (komponenRows.length === 0) {
                komponenRows = defaultKomponen.map(function (k) { return Object.assign({}, k); });
            }
            renderKomponen();
            showGrid('keu_stats');
            showGrid('keu_main');
        }
    });

    // ── Konfirmasi uang muka (simulasi) ──────────────────────────────────
    document.getElementById('konfirmasiUangMukaBtn').addEventListener('click', function () {
        const tgl   = document.getElementById('um_tgl').value;
        const bukti = document.getElementById('um_bukti').value;
        const file  = document.getElementById('um_file').files[0];

        if (!tgl || !bukti || !file) {
            alert('Lengkapi tanggal transfer, nomor bukti, dan upload file bukti terlebih dahulu.');
            return;
        }

        const val = document.getElementById('keu_pejadin_select').value;
        const d   = keuData[val];

        // update data sementara (simulasi)
        d.uangMuka = {
            status: 'sudah',
            tanggal: tgl,
            bukti: bukti,
            nominal: Math.round(d.estimasi * 0.8),
        };

        renderUangMuka(d);
    });

    // ── Catat pembayaran (placeholder) ───────────────────────────────────
    document.getElementById('tambahPembayaranBtn').addEventListener('click', function () {
        alert('Form catat pembayaran akan tersedia di sini.');
    });
</script>

@endsection