@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="laporanPage()">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Pusat Laporan</h1>
                <p class="text-xs text-slate-400 mt-0.5">Unduh laporan, rekap, dan dokumen perjalanan dinas</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="downloadAll()"
                    class="hidden sm:flex items-center gap-2 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Unduh Semua
            </button>
        </div>
    </div>

    {{-- ── Stat Cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-teal-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">24</p>
            <p class="text-xs text-slate-500">Laporan Tersedia</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">156</p>
            <p class="text-xs text-slate-500">Total Unduhan</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">Apr 2026</p>
            <p class="text-xs text-slate-500">Periode Aktif</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">87</p>
            <p class="text-xs text-slate-500">Perdin Tercatat</p>
        </div>
    </div>

    {{-- ── Filter & Period ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-6">
        <div class="px-6 py-4 border-b border-slate-100">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" placeholder="Cari laporan..." x-model="search"
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition bg-white">
                </div>
                <select x-model="filterKategori" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-40">
                    <option value="semua">Semua Kategori</option>
                    <option value="rekap">Rekap Perjalanan</option>
                    <option value="keuangan">Keuangan</option>
                    <option value="kepegawaian">Kepegawaian</option>
                    <option value="dokumen">Dokumen Perjadin</option>
                    <option value="sistem">Sistem</option>
                </select>
                <select x-model="filterFormat" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-32">
                    <option value="semua">Semua Format</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="csv">CSV (.csv)</option>
                    <option value="zip">ZIP Archive</option>
                </select>
                <div class="flex gap-2">
                    <input type="month" x-model="periodeDari"
                           class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <input type="month" x-model="periodeSampai"
                           class="px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Kategori Tabs ── --}}
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
        <template x-for="kat in kategoris" :key="kat.key">
            <button type="button" @click="activeKat = kat.key"
                    :class="activeKat === kat.key
                        ? 'bg-teal-500 text-white shadow-sm'
                        : 'bg-white text-slate-600 border border-slate-200 hover:border-teal-300 hover:text-teal-700'"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl transition whitespace-nowrap">
                <span x-text="kat.icon"></span>
                <span x-text="kat.label"></span>
                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full leading-none"
                      :class="activeKat === kat.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
                      x-text="kat.count"></span>
            </button>
        </template>
    </div>

    {{-- ═══════════ LAPORAN CARDS GRID ═══════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <template x-for="item in filteredLaporan" :key="item.id">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all group">
                {{-- Card Header --}}
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                             :class="item.iconBg">
                            <span class="text-lg" x-text="item.icon"></span>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full border"
                              :class="formatBadgeClass(item.format)" x-text="item.format.toUpperCase()"></span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800 mb-1 leading-snug" x-text="item.nama"></h3>
                    <p class="text-xs text-slate-500 leading-relaxed" x-text="item.deskripsi"></p>
                </div>
                {{-- Card Meta --}}
                <div class="px-5 py-3 border-t border-slate-50">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span x-text="item.tanggal"></span>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span x-text="item.ukuran"></span>
                            </span>
                        </div>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span x-text="item.downloads + 'x'"></span>
                        </span>
                    </div>
                </div>
                {{-- Card Actions --}}
                <div class="px-5 pb-5 pt-1 flex gap-2">
                    <button @click="unduhLaporan(item)"
                            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Unduh
                    </button>
                    <button @click="previewLaporan(item)"
                            class="flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Preview
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- ═══════════ RIWAYAT UNDUHAN ═══════════ --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="text-sm font-bold text-slate-700">Riwayat Unduhan Terbaru</h2>
            </div>
            <button class="text-xs text-teal-600 hover:text-teal-700 font-semibold">Lihat Semua</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Nama Laporan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kategori</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Format</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tanggal Unduh</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Diunduh Oleh</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base">📊</span>
                                <span class="font-semibold text-slate-800">Rekap Perjalanan Dinas Q1 2026</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">Rekap Perjalanan</span></td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 text-xs font-semibold rounded-full">XLSX</span></td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs">10/04/2026 14:32</td>
                        <td class="px-4 py-3.5 text-slate-600">Admin PANGI</td>
                        <td class="px-4 py-3.5 text-center">
                            <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-600 transition mx-auto" title="Unduh ulang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base">💰</span>
                                <span class="font-semibold text-slate-800">Laporan Realisasi Anggaran Perdin Maret 2026</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 text-xs font-semibold rounded-full">Keuangan</span></td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-red-50 text-red-600 border border-red-100 text-xs font-semibold rounded-full">PDF</span></td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs">09/04/2026 10:15</td>
                        <td class="px-4 py-3.5 text-slate-600">Ahmad Fauzi, SE</td>
                        <td class="px-4 py-3.5 text-center">
                            <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-600 transition mx-auto" title="Unduh ulang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base">📋</span>
                                <span class="font-semibold text-slate-800">SPPD Lengkap — USL-2025-001</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold rounded-full">Dokumen Perjadin</span></td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-red-50 text-red-600 border border-red-100 text-xs font-semibold rounded-full">PDF</span></td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs">08/04/2026 16:45</td>
                        <td class="px-4 py-3.5 text-slate-600">Siti Rahayu, S.Kep</td>
                        <td class="px-4 py-3.5 text-center">
                            <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-600 transition mx-auto" title="Unduh ulang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base">📦</span>
                                <span class="font-semibold text-slate-800">Bundle Dokumen Perjadin — USL-2024-089</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold rounded-full">Dokumen Perjadin</span></td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-violet-50 text-violet-700 border border-violet-100 text-xs font-semibold rounded-full">ZIP</span></td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs">07/04/2026 09:20</td>
                        <td class="px-4 py-3.5 text-slate-600">Maria Lumenta, S.Pd</td>
                        <td class="px-4 py-3.5 text-center">
                            <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-600 transition mx-auto" title="Unduh ulang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base">👥</span>
                                <span class="font-semibold text-slate-800">Data Pegawai Aktif — Export April 2026</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-violet-50 text-violet-700 border border-violet-100 text-xs font-semibold rounded-full">Kepegawaian</span></td>
                        <td class="px-4 py-3.5"><span class="px-2.5 py-1 bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold rounded-full">CSV</span></td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs">05/04/2026 11:00</td>
                        <td class="px-4 py-3.5 text-slate-600">Admin PANGI</td>
                        <td class="px-4 py-3.5 text-center">
                            <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-600 transition mx-auto" title="Unduh ulang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('laporanPage', () => ({
        search: '',
        filterKategori: 'semua',
        filterFormat: 'semua',
        periodeDari: '',
        periodeSampai: '',
        activeKat: 'semua',

        kategoris: [
            { key: 'semua',       label: 'Semua',              icon: '📁', count: 24 },
            { key: 'rekap',       label: 'Rekap Perjalanan',   icon: '📊', count: 6 },
            { key: 'keuangan',    label: 'Keuangan',           icon: '💰', count: 5 },
            { key: 'kepegawaian', label: 'Kepegawaian',        icon: '👥', count: 4 },
            { key: 'dokumen',     label: 'Dokumen Perjadin',   icon: '📋', count: 7 },
            { key: 'sistem',      label: 'Sistem',             icon: '⚙️', count: 2 },
        ],

        laporan: [
            // ── Rekap Perjalanan ──
            { id: 1,  nama: 'Rekap Perjalanan Dinas Q1 2026',                   deskripsi: 'Ringkasan seluruh perjalanan dinas Januari–Maret 2026, termasuk jumlah pejadin, tujuan, dan status.',                               kategori: 'rekap',       format: 'xlsx', icon: '📊', iconBg: 'bg-teal-50',    tanggal: '01/04/2026', ukuran: '245 KB', downloads: 32 },
            { id: 2,  nama: 'Rekap Perjalanan Dinas Tahun 2025',                deskripsi: 'Laporan tahunan seluruh kegiatan perjalanan dinas tahun 2025.',                                                                      kategori: 'rekap',       format: 'xlsx', icon: '📊', iconBg: 'bg-teal-50',    tanggal: '05/01/2026', ukuran: '1.2 MB', downloads: 58 },
            { id: 3,  nama: 'Statistik Perjalanan per Unit Kerja',              deskripsi: 'Breakdown jumlah perjadin per unit kerja, frekuensi, dan rata-rata biaya.',                                                          kategori: 'rekap',       format: 'pdf',  icon: '📈', iconBg: 'bg-teal-50',    tanggal: '01/04/2026', ukuran: '890 KB', downloads: 14 },
            { id: 4,  nama: 'Rekap Tujuan Perjalanan Dinas',                    deskripsi: 'Data kota tujuan terpopuler, durasi perjalanan, dan distribusi wilayah.',                                                            kategori: 'rekap',       format: 'pdf',  icon: '🗺️', iconBg: 'bg-teal-50',    tanggal: '01/04/2026', ukuran: '650 KB', downloads: 9 },
            { id: 5,  nama: 'Daftar Surat Tugas Terbit Q1 2026',                deskripsi: 'Seluruh surat tugas yang diterbitkan pada periode Januari–Maret 2026.',                                                              kategori: 'rekap',       format: 'xlsx', icon: '📝', iconBg: 'bg-teal-50',    tanggal: '02/04/2026', ukuran: '180 KB', downloads: 6 },
            { id: 6,  nama: 'Rekapitulasi Peserta Perjadin',                    deskripsi: 'Daftar nama pegawai yang mengikuti perjalanan dinas beserta detail tugas.',                                                          kategori: 'rekap',       format: 'csv',  icon: '👥', iconBg: 'bg-teal-50',    tanggal: '01/04/2026', ukuran: '95 KB',  downloads: 11 },

            // ── Keuangan ──
            { id: 7,  nama: 'Laporan Realisasi Anggaran Perdin Maret 2026',     deskripsi: 'Detail realisasi anggaran perjalanan dinas bulan Maret 2026 termasuk uang muka dan sisa bayar.',                                     kategori: 'keuangan',    format: 'pdf',  icon: '💰', iconBg: 'bg-emerald-50',  tanggal: '05/04/2026', ukuran: '1.1 MB', downloads: 22 },
            { id: 8,  nama: 'Realisasi Anggaran Perjadin Tahunan 2025',         deskripsi: 'Laporan keuangan tahunan seluruh realisasi anggaran perjalanan dinas.',                                                               kategori: 'keuangan',    format: 'xlsx', icon: '📊', iconBg: 'bg-emerald-50',  tanggal: '10/01/2026', ukuran: '2.3 MB', downloads: 45 },
            { id: 9,  nama: 'Rincian Pembayaran Uang Muka 80%',                 deskripsi: 'Detail seluruh transfer uang muka 80% yang telah dikonfirmasi oleh bendahara.',                                                      kategori: 'keuangan',    format: 'xlsx', icon: '🏦', iconBg: 'bg-emerald-50',  tanggal: '01/04/2026', ukuran: '340 KB', downloads: 18 },
            { id: 10, nama: 'Laporan LPJ Perjalanan Dinas',                     deskripsi: 'Kompilasi Laporan Pertanggungjawaban (LPJ) seluruh perjadin yang sudah selesai.',                                                     kategori: 'keuangan',    format: 'pdf',  icon: '📑', iconBg: 'bg-emerald-50',  tanggal: '28/03/2026', ukuran: '3.8 MB', downloads: 30 },
            { id: 11, nama: 'Rekap Kwitansi & Bukti Bayar',                     deskripsi: 'Seluruh kwitansi, bukti transfer, dan invoice terkait perjalanan dinas.',                                                             kategori: 'keuangan',    format: 'zip',  icon: '🧾', iconBg: 'bg-emerald-50',  tanggal: '01/04/2026', ukuran: '15.6 MB', downloads: 8 },

            // ── Kepegawaian ──
            { id: 12, nama: 'Data Pegawai Aktif — Export April 2026',            deskripsi: 'Seluruh data pegawai aktif termasuk NIP, jabatan, golongan, dan unit kerja.',                                                        kategori: 'kepegawaian', format: 'csv',  icon: '👤', iconBg: 'bg-violet-50',   tanggal: '01/04/2026', ukuran: '120 KB', downloads: 15 },
            { id: 13, nama: 'Laporan Frekuensi Perjadin per Pegawai',           deskripsi: 'Jumlah perjalanan dinas per pegawai dalam setahun terakhir.',                                                                            kategori: 'kepegawaian', format: 'xlsx', icon: '📋', iconBg: 'bg-violet-50',   tanggal: '01/04/2026', ukuran: '210 KB', downloads: 12 },
            { id: 14, nama: 'Daftar Unit Kerja & Struktur Organisasi',          deskripsi: 'Data unit kerja, kepala unit, jumlah staf, dan struktur organisasi.',                                                                    kategori: 'kepegawaian', format: 'pdf',  icon: '🏢', iconBg: 'bg-violet-50',   tanggal: '15/03/2026', ukuran: '560 KB', downloads: 7 },
            { id: 15, nama: 'Jabatan & Tarif Uang Harian',                      deskripsi: 'Referensi jabatan beserta tarif uang harian perjalanan dinas sesuai ketentuan.',                                                      kategori: 'kepegawaian', format: 'pdf',  icon: '💼', iconBg: 'bg-violet-50',   tanggal: '01/01/2026', ukuran: '85 KB',  downloads: 20 },

            // ── Dokumen Perjadin ──
            { id: 16, nama: 'Bundle Dokumen — USL-2025-001 (Rakornas)',          deskripsi: 'Surat Tugas, SPPD, Boarding Pass, Bill Hotel, dan LHP dalam satu paket ZIP.',                                                        kategori: 'dokumen',     format: 'zip',  icon: '📦', iconBg: 'bg-blue-50',     tanggal: '20/01/2026', ukuran: '8.5 MB', downloads: 4 },
            { id: 17, nama: 'Bundle Dokumen — USL-2024-089 (Workshop SDM)',      deskripsi: 'Paket lengkap dokumen pertanggungjawaban Workshop SDM di Makassar.',                                                                     kategori: 'dokumen',     format: 'zip',  icon: '📦', iconBg: 'bg-blue-50',     tanggal: '12/12/2024', ukuran: '6.2 MB', downloads: 3 },
            { id: 18, nama: 'Template Surat Tugas',                             deskripsi: 'Template resmi surat tugas perjalanan dinas Poltekkes Kemenkes Manado.',                                                              kategori: 'dokumen',     format: 'pdf',  icon: '📄', iconBg: 'bg-blue-50',     tanggal: '01/01/2026', ukuran: '45 KB',  downloads: 67 },
            { id: 19, nama: 'Template SPPD',                                    deskripsi: 'Template Surat Perintah Perjalanan Dinas sesuai format standar.',                                                                        kategori: 'dokumen',     format: 'pdf',  icon: '📄', iconBg: 'bg-blue-50',     tanggal: '01/01/2026', ukuran: '52 KB',  downloads: 55 },
            { id: 20, nama: 'Template Laporan Hasil Perjalanan',                deskripsi: 'Format resmi Laporan Hasil Perjalanan (LHP) untuk dilengkapi setelah perjadin.',                                                      kategori: 'dokumen',     format: 'pdf',  icon: '📄', iconBg: 'bg-blue-50',     tanggal: '01/01/2026', ukuran: '38 KB',  downloads: 49 },
            { id: 21, nama: 'Formulir Kwitansi Perjalanan',                     deskripsi: 'Template formulir kwitansi untuk pengeluaran selama perjalanan dinas.',                                                               kategori: 'dokumen',     format: 'pdf',  icon: '📝', iconBg: 'bg-blue-50',     tanggal: '01/01/2026', ukuran: '30 KB',  downloads: 42 },
            { id: 22, nama: 'Checklist Kelengkapan Dokumen Perjadin',           deskripsi: 'Daftar periksa dokumen yang wajib dilengkapi untuk pertanggungjawaban.',                                                              kategori: 'dokumen',     format: 'pdf',  icon: '✅', iconBg: 'bg-blue-50',     tanggal: '01/01/2026', ukuran: '22 KB',  downloads: 38 },

            // ── Sistem ──
            { id: 23, nama: 'Log Aktivitas Sistem — Maret 2026',                deskripsi: 'Catatan seluruh aktivitas user di sistem PANGI selama bulan Maret 2026.',                                                             kategori: 'sistem',      format: 'csv',  icon: '🔔', iconBg: 'bg-slate-100',   tanggal: '01/04/2026', ukuran: '780 KB', downloads: 2 },
            { id: 24, nama: 'Backup Database — 08 April 2026',                  deskripsi: 'Backup otomatis database sistem PANGI terbaru.',                                                                                         kategori: 'sistem',      format: 'zip',  icon: '💾', iconBg: 'bg-slate-100',   tanggal: '08/04/2026', ukuran: '245 MB', downloads: 1 },
        ],

        get filteredLaporan() {
            return this.laporan.filter(item => {
                const matchKat = this.activeKat === 'semua' || item.kategori === this.activeKat;
                const matchSearch = !this.search || item.nama.toLowerCase().includes(this.search.toLowerCase()) || item.deskripsi.toLowerCase().includes(this.search.toLowerCase());
                const matchFormat = this.filterFormat === 'semua' || item.format === this.filterFormat;
                return matchKat && matchSearch && matchFormat;
            });
        },

        formatBadgeClass(format) {
            const map = {
                xlsx: 'bg-emerald-50 text-emerald-700 border-emerald-100',
                pdf:  'bg-red-50 text-red-600 border-red-100',
                csv:  'bg-slate-100 text-slate-600 border-slate-200',
                zip:  'bg-violet-50 text-violet-700 border-violet-100',
            };
            return map[format] || 'bg-slate-100 text-slate-600 border-slate-200';
        },

        unduhLaporan(item) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-6 right-6 bg-slate-800 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
            toast.innerHTML = `<svg class="w-4 h-4 text-teal-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> Mengunduh <strong>${item.nama}</strong>...`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },

        previewLaporan(item) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-6 right-6 bg-slate-800 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
            toast.innerHTML = `<svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> Membuka preview <strong>${item.nama}</strong>...`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },

        downloadAll() {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-6 right-6 bg-slate-800 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2';
            toast.innerHTML = `<svg class="w-4 h-4 text-teal-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> Mempersiapkan unduhan semua laporan...`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        },
    }));
});
</script>

@endsection