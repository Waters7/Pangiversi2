@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="adminSistem()">

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
                <h1 class="text-xl font-bold text-slate-800">Administrasi Sistem</h1>
                <p class="text-xs text-slate-400 mt-0.5">Kelola pengguna, role, dan konfigurasi sistem PANGI</p>
            </div>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-teal-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">18</p>
            <p class="text-xs text-slate-500">Total Pengguna</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">5</p>
            <p class="text-xs text-slate-500">Role Tersedia</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">342</p>
            <p class="text-xs text-slate-500">Log Aktivitas</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </div>
            <p class="text-xl font-bold text-slate-800">v3.0</p>
            <p class="text-xs text-slate-500">Versi Sistem</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        {{-- Tab Navigation --}}
        <div class="border-b border-slate-100 px-6 flex gap-1 overflow-x-auto">
            <template x-for="tab in tabs" :key="tab.key">
                <button type="button"
                        @click="activeTab = tab.key"
                        :class="activeTab === tab.key
                            ? 'border-teal-500 text-teal-700 bg-teal-50/50'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                        class="flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition whitespace-nowrap -mb-px">
                    <span x-text="tab.icon" class="text-base"></span>
                    <span x-text="tab.label"></span>
                </button>
            </template>
        </div>

        {{-- ═══════ TAB: Manajemen Pengguna ═══════ --}}
        <div x-show="activeTab === 'pengguna'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" placeholder="Cari pengguna..." x-model="searchUser"
                               class="pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent w-56">
                    </div>
                    <select x-model="filterRole" class="px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        <option value="semua">Semua Role</option>
                        <option value="admin">Admin</option>
                        <option value="pimpinan">Pimpinan</option>
                        <option value="bendahara">Bendahara</option>
                        <option value="staf">Staf</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <button @click="showModal = 'tambah-user'" class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Pengguna
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Pengguna</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Email</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Role</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Unit Kerja</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Login Terakhir</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <template x-for="u in filteredUsers" :key="u.id">
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                             :class="u.role === 'admin' ? 'bg-teal-100 text-teal-700' : u.role === 'pimpinan' ? 'bg-violet-100 text-violet-700' : u.role === 'bendahara' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                             x-text="u.nama.charAt(0)"></div>
                                        <div>
                                            <p class="font-semibold text-slate-800 text-sm" x-text="u.nama"></p>
                                            <p class="text-xs text-slate-400" x-text="u.nip"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs" x-text="u.email"></td>
                                <td class="px-4 py-3">
                                    <div class="relative" x-data="{ editingRole: false }">
                                        <button @click="editingRole = !editingRole"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border cursor-pointer transition"
                                                :class="roleBadge(u.role)">
                                            <span x-text="u.role.charAt(0).toUpperCase() + u.role.slice(1)"></span>
                                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="editingRole" @click.outside="editingRole = false" x-cloak
                                             class="absolute top-full left-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 py-1 w-40">
                                            <template x-for="r in roles" :key="r">
                                                <button @click="gantiRole(u, r); editingRole = false"
                                                        class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-slate-50 transition flex items-center justify-between"
                                                        :class="u.role === r ? 'text-teal-700 bg-teal-50/50' : 'text-slate-600'">
                                                    <span x-text="r.charAt(0).toUpperCase() + r.slice(1)"></span>
                                                    <svg x-show="u.role === r" class="w-3.5 h-3.5 text-teal-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs" x-text="u.unit"></td>
                                <td class="px-4 py-3 text-slate-500 text-xs" x-text="u.lastLogin"></td>
                                <td class="px-4 py-3">
                                    <button @click="toggleStatus(u)" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border cursor-pointer transition"
                                            :class="u.aktif ? 'bg-teal-50 text-teal-700 border-teal-100' : 'bg-slate-100 text-slate-500 border-slate-200'">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="u.aktif ? 'bg-teal-500' : 'bg-slate-400'"></span>
                                        <span x-text="u.aktif ? 'Aktif' : 'Nonaktif'"></span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="editUser(u)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="resetPassword(u)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-amber-50 text-slate-500 hover:text-amber-600 transition" title="Reset Password">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                        </button>
                                        <button @click="confirmDelete(u, 'user')" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════ TAB: Manajemen Role ═══════ --}}
        <div x-show="activeTab === 'role'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Role & Hak Akses</p>
                <button @click="showModal = 'tambah-role'" class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Role
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-6">
                <template x-for="role in roleList" :key="role.nama">
                    <div class="border border-slate-100 rounded-2xl p-5 hover:shadow-md transition-all group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="role.bg">
                                    <span class="text-lg" x-text="role.icon"></span>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800" x-text="role.nama"></h3>
                                    <p class="text-xs text-slate-400" x-text="role.jumlahUser + ' pengguna'"></p>
                                </div>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                                <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button x-show="role.nama !== 'Admin'" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mb-3" x-text="role.deskripsi"></p>
                        <div class="space-y-1.5">
                            <template x-for="perm in role.permissions" :key="perm">
                                <div class="flex items-center gap-2 text-xs">
                                    <svg class="w-3.5 h-3.5 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    <span class="text-slate-600" x-text="perm"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ═══════ TAB: Manajemen Data ═══════ --}}
        <div x-show="activeTab === 'data'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <p class="text-sm font-bold text-slate-600">Manajemen Data Sistem</p>
                </div>
                <div class="flex items-center gap-2">
                    <select x-model="dataFilter" class="px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        <option value="semua">Semua Jenis</option>
                        <option value="usulan">Usulan Perdin</option>
                        <option value="transaksi">Transaksi</option>
                        <option value="dokumen">Dokumen</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">ID / Nomor</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jenis Data</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Deskripsi</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Dibuat Oleh</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tanggal</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <template x-for="d in filteredData" :key="d.id">
                            <tr class="hover:bg-slate-50/60 transition" :class="d.deleted ? 'opacity-40 line-through' : ''">
                                <td class="px-6 py-3 font-mono text-xs font-bold text-teal-700" x-text="d.nomor"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full border"
                                          :class="d.jenis === 'Usulan' ? 'bg-blue-50 text-blue-700 border-blue-100' : d.jenis === 'Transaksi' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-violet-50 text-violet-700 border-violet-100'"
                                          x-text="d.jenis"></span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 text-xs font-medium" x-text="d.deskripsi"></td>
                                <td class="px-4 py-3 text-slate-600 text-xs" x-text="d.dibuatOleh"></td>
                                <td class="px-4 py-3 text-slate-500 text-xs" x-text="d.tanggal"></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full border"
                                          :class="statusBadge(d.status)">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="statusDot(d.status)"></span>
                                        <span x-text="d.status"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div x-show="!d.deleted" class="flex items-center justify-center gap-1">
                                        <button @click="editData(d)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="batalkanData(d)" x-show="d.status !== 'Dibatalkan'" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-amber-50 text-slate-500 hover:text-amber-600 transition" title="Batalkan">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </button>
                                        <button @click="confirmDelete(d, 'data')" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                    <span x-show="d.deleted" class="text-xs text-red-400 font-semibold">Dihapus</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════ TAB: Log Aktivitas ═══════ --}}
        <div x-show="activeTab === 'log'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Log Aktivitas Sistem</p>
                <button @click="clearLog()" class="flex items-center gap-1.5 px-3.5 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Bersihkan Log
                </button>
            </div>
            <div class="divide-y divide-slate-50">
                <template x-for="log in logAktivitas" :key="log.id">
                    <div class="flex items-start gap-4 px-6 py-4 hover:bg-slate-50/60 transition">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5" :class="logIconBg(log.tipe)">
                            <svg x-show="log.tipe === 'login'" class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            <svg x-show="log.tipe === 'role'" class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <svg x-show="log.tipe === 'hapus'" class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <svg x-show="log.tipe === 'edit'" class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <svg x-show="log.tipe === 'batal'" class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            <svg x-show="log.tipe === 'system'" class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 010 4h-.09c-.658.003-1.25.396-1.51 1z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-slate-800" x-text="log.aksi"></span>
                                <span class="text-xs text-slate-400">oleh</span>
                                <span class="text-sm font-medium text-slate-600" x-text="log.user"></span>
                            </div>
                            <p class="text-xs text-slate-500" x-text="log.detail"></p>
                        </div>
                        <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap" x-text="log.waktu"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- ═══════ TAB: Konfigurasi ═══════ --}}
        <div x-show="activeTab === 'konfigurasi'" x-cloak>
            <div class="p-6 space-y-6">
                {{-- General --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09"/></svg>
                        Pengaturan Umum
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Nama Aplikasi</label>
                            <input type="text" value="PANGI v3.0" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Nama Instansi</label>
                            <input type="text" value="Poltekkes Kemenkes Manado" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Email Admin</label>
                            <input type="email" value="admin@poltekkes-manado.ac.id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Tahun Anggaran</label>
                            <input type="text" value="2026" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Perjalanan Dinas --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1"/></svg>
                        Konfigurasi Perjalanan Dinas
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Persentase Uang Muka</label>
                            <div class="flex items-center gap-2">
                                <input type="number" value="80" class="w-24 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent text-center">
                                <span class="text-sm text-slate-500 font-semibold">%</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Persentase uang muka yang ditransfer sebelum perjalanan</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Batas Hari Pengajuan</label>
                            <div class="flex items-center gap-2">
                                <input type="number" value="7" class="w-24 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent text-center">
                                <span class="text-sm text-slate-500 font-semibold">hari sebelum keberangkatan</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Batas Hari LPJ</label>
                            <div class="flex items-center gap-2">
                                <input type="number" value="14" class="w-24 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent text-center">
                                <span class="text-sm text-slate-500 font-semibold">hari setelah kembali</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Max Upload File</label>
                            <div class="flex items-center gap-2">
                                <input type="number" value="10" class="w-24 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent text-center">
                                <span class="text-sm text-slate-500 font-semibold">MB</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Notifikasi --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        Notifikasi & Email
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Notifikasi Email Usulan Baru</p>
                                <p class="text-xs text-slate-400">Kirim email ke pimpinan saat ada usulan perdin baru</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-10 h-5 bg-slate-300 rounded-full peer-checked:bg-teal-500 transition"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Notifikasi Persetujuan</p>
                                <p class="text-xs text-slate-400">Kirim email ke pemohon saat usulan disetujui/ditolak</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-10 h-5 bg-slate-300 rounded-full peer-checked:bg-teal-500 transition"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Notifikasi Pembayaran</p>
                                <p class="text-xs text-slate-400">Kirim email konfirmasi setelah transfer uang muka / sisa bayar</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-10 h-5 bg-slate-300 rounded-full peer-checked:bg-teal-500 transition"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition"></div>
                            </div>
                        </label>
                        <label class="flex items-center justify-between p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Reminder LPJ</p>
                                <p class="text-xs text-slate-400">Kirim pengingat otomatis jika LPJ belum diselesaikan</p>
                            </div>
                            <div class="relative">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-10 h-5 bg-slate-300 rounded-full peer-checked:bg-teal-500 transition"></div>
                                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Database --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Maintenance Database
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition text-left">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Backup Database</p>
                                <p class="text-xs text-slate-400">Terakhir: 08/04/2026</p>
                            </div>
                        </button>
                        <button class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition text-left">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Restore Database</p>
                                <p class="text-xs text-slate-400">Pulihkan dari backup</p>
                            </div>
                        </button>
                        <button class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl hover:bg-red-50 transition text-left">
                            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Clear Cache</p>
                                <p class="text-xs text-slate-400">Bersihkan data cache</p>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Save Button --}}
                <div class="flex justify-end pt-2">
                    <button @click="simpanKonfigurasi()" class="flex items-center gap-2 px-6 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Simpan Pengaturan
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════ MODAL: Konfirmasi Hapus ═══════ --}}
    <div x-show="showModal === 'confirm-delete'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition>
        <div class="absolute inset-0 bg-black/40" @click="showModal = null"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 text-center mb-1">Konfirmasi Hapus</h3>
            <p class="text-sm text-slate-500 text-center mb-6" x-text="'Apakah Anda yakin ingin menghapus ' + (deleteTarget?.nama || deleteTarget?.deskripsi || 'data ini') + '? Tindakan ini tidak bisa dibatalkan.'"></p>
            <div class="flex gap-3">
                <button @click="showModal = null" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                <button @click="hapusKonfirmasi()" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl transition">Hapus</button>
            </div>
        </div>
    </div>

    {{-- ═══════ MODAL: Edit User ═══════ --}}
    <div x-show="showModal === 'edit-user'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition>
        <div class="absolute inset-0 bg-black/40" @click="showModal = null"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-bold text-slate-800">Edit Pengguna</h3>
                <button @click="showModal = null" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Nama Lengkap</label>
                    <input type="text" x-model="editingUser.nama" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Email</label>
                    <input type="email" x-model="editingUser.email" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Role</label>
                        <select x-model="editingUser.role" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            <template x-for="r in roles" :key="r">
                                <option :value="r" x-text="r.charAt(0).toUpperCase() + r.slice(1)"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Unit Kerja</label>
                        <input type="text" x-model="editingUser.unit" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button @click="showModal = null" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                <button @click="simpanUser()" class="flex-1 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan</button>
            </div>
        </div>
    </div>

    {{-- ═══════ MODAL: Edit Data ═══════ --}}
    <div x-show="showModal === 'edit-data'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition>
        <div class="absolute inset-0 bg-black/40" @click="showModal = null"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-bold text-slate-800">Edit Data</h3>
                <button @click="showModal = null" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Nomor / ID</label>
                    <input type="text" x-model="editingData.nomor" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50" readonly>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Deskripsi</label>
                    <input type="text" x-model="editingData.deskripsi" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Status</label>
                    <select x-model="editingData.status" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                        <option>Aktif</option>
                        <option>Disetujui</option>
                        <option>Menunggu</option>
                        <option>Selesai</option>
                        <option>Dibatalkan</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button @click="showModal = null" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                <button @click="simpanData()" class="flex-1 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">Simpan</button>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminSistem', () => ({
        activeTab: 'pengguna',
        showModal: null,
        searchUser: '',
        filterRole: 'semua',
        dataFilter: 'semua',
        deleteTarget: null,
        deleteType: null,
        editingUser: {},
        editingData: {},

        tabs: [
            { key: 'pengguna',     label: 'Manajemen Pengguna', icon: '\uD83D\uDC65' },
            { key: 'role',         label: 'Role & Hak Akses',   icon: '\uD83D\uDD12' },
            { key: 'data',         label: 'Manajemen Data',     icon: '\uD83D\uDDC3\uFE0F' },
            { key: 'log',          label: 'Log Aktivitas',      icon: '\uD83D\uDCCB' },
            { key: 'konfigurasi',  label: 'Konfigurasi',        icon: '\u2699\uFE0F' },
        ],

        roles: ['admin', 'pimpinan', 'bendahara', 'staf', 'viewer'],

        users: [
            { id: 1,  nama: 'Admin PANGI',              nip: '—',                  email: 'admin@poltekkes.ac.id',     role: 'admin',     unit: 'IT',                 lastLogin: '10/04/2026 14:32', aktif: true },
            { id: 2,  nama: 'Prof. Dr. Samuel Roring',   nip: '196706151993031001',  email: 'samuel.r@poltekkes.ac.id',  role: 'pimpinan',  unit: 'Pimpinan',           lastLogin: '10/04/2026 09:15', aktif: true },
            { id: 3,  nama: 'Ns. Debora Tumewu, M.Kep',  nip: '197409222001122002',  email: 'debora.t@poltekkes.ac.id',  role: 'pimpinan',  unit: 'Pimpinan',           lastLogin: '09/04/2026 16:20', aktif: true },
            { id: 4,  nama: 'Dr. Hendra Santoso, M.Kes', nip: '198501012010121001',  email: 'hendra.s@poltekkes.ac.id',  role: 'pimpinan',  unit: 'Bagian Keuangan',    lastLogin: '10/04/2026 13:15', aktif: true },
            { id: 5,  nama: 'Ahmad Fauzi, SE',           nip: '197802202005011003',  email: 'ahmad.f@poltekkes.ac.id',   role: 'bendahara', unit: 'Bagian Keuangan',    lastLogin: '10/04/2026 11:40', aktif: true },
            { id: 6,  nama: 'Siti Rahayu, S.Kep',        nip: '199203152015042002',  email: 'siti.r@poltekkes.ac.id',    role: 'staf',      unit: 'Bagian Keuangan',    lastLogin: '10/04/2026 14:28', aktif: true },
            { id: 7,  nama: 'Maria Lumenta, S.Pd',       nip: '198011122004122005',  email: 'maria.l@poltekkes.ac.id',   role: 'staf',      unit: 'Bagian SDM',         lastLogin: '09/04/2026 15:05', aktif: true },
            { id: 8,  nama: 'Ricky Pontoh, SKM',         nip: '199507082019031002',  email: 'ricky.p@poltekkes.ac.id',   role: 'staf',      unit: 'Bagian SDM',         lastLogin: '09/04/2026 10:30', aktif: true },
            { id: 9,  nama: 'dr. Andi Kusuma',           nip: '198803052014041003',  email: 'andi.k@poltekkes.ac.id',    role: 'staf',      unit: 'Jur. Keperawatan',   lastLogin: '05/04/2026 08:45', aktif: false },
            { id: 10, nama: 'Dr. Grace Mosey, M.Keb',    nip: '198205152008012004',  email: 'grace.m@poltekkes.ac.id',   role: 'staf',      unit: 'Jur. Kebidanan',     lastLogin: '08/04/2026 14:10', aktif: true },
            { id: 11, nama: 'Dr. Fenny Rompas, M.Gizi',  nip: '197910102006042003',  email: 'fenny.r@poltekkes.ac.id',   role: 'staf',      unit: 'Jur. Gizi',          lastLogin: '07/04/2026 11:30', aktif: true },
            { id: 12, nama: 'Ir. Tony Makarawung',       nip: '197106082000031005',  email: 'tony.m@poltekkes.ac.id',    role: 'staf',      unit: 'Bagian Umum',        lastLogin: '06/04/2026 09:00', aktif: true },
            { id: 13, nama: 'Drs. Budi Hartono, M.Si',   nip: '196903151997031002',  email: 'budi.h@poltekkes.ac.id',    role: 'viewer',    unit: 'Bagian Akademik',    lastLogin: '04/04/2026 10:15', aktif: true },
            { id: 14, nama: 'Dr. James Wowor, M.KL',     nip: '198107202009121001',  email: 'james.w@poltekkes.ac.id',   role: 'staf',      unit: 'Jur. Kes. Lingkungan', lastLogin: '03/04/2026 14:45', aktif: true },
            { id: 15, nama: 'Indra Manoppo, S.Kep',      nip: '199901102023011001',  email: 'indra.m@poltekkes.ac.id',   role: 'viewer',    unit: 'Jur. Keperawatan',   lastLogin: '—',                aktif: true },
        ],

        get filteredUsers() {
            return this.users.filter(u => {
                const matchRole = this.filterRole === 'semua' || u.role === this.filterRole;
                const matchSearch = !this.searchUser || u.nama.toLowerCase().includes(this.searchUser.toLowerCase()) || u.email.toLowerCase().includes(this.searchUser.toLowerCase()) || u.nip.includes(this.searchUser);
                return matchRole && matchSearch;
            });
        },

        roleList: [
            { nama: 'Admin',      icon: '\uD83D\uDEE1\uFE0F', bg: 'bg-teal-50',    jumlahUser: 1, deskripsi: 'Akses penuh ke seluruh fitur sistem termasuk manajemen pengguna dan konfigurasi.',            permissions: ['Manajemen Pengguna', 'Manajemen Role', 'Konfigurasi Sistem', 'Hapus & Edit Semua Data', 'Lihat Log Aktivitas', 'Backup & Restore'] },
            { nama: 'Pimpinan',   icon: '\uD83D\uDC51', bg: 'bg-violet-50',  jumlahUser: 3, deskripsi: 'Menyetujui/menolak usulan perjalanan dinas dan melihat laporan.',                                   permissions: ['Approve/Reject Usulan', 'Lihat Semua Laporan', 'Lihat Dashboard', 'Export Laporan'] },
            { nama: 'Bendahara',  icon: '\uD83D\uDCB0', bg: 'bg-emerald-50', jumlahUser: 1, deskripsi: 'Mengelola keuangan perjalanan dinas, konfirmasi pembayaran, dan LPJ.',                               permissions: ['Konfirmasi Uang Muka', 'Input Komponen Biaya', 'Verifikasi LPJ', 'Lihat Laporan Keuangan', 'Export Keuangan'] },
            { nama: 'Staf',       icon: '\uD83D\uDC64', bg: 'bg-blue-50',    jumlahUser: 8, deskripsi: 'Membuat usulan perjalanan dinas, upload dokumen, dan melihat status.',                                permissions: ['Buat Usulan', 'Upload Dokumen', 'Lihat Status Usulan', 'Lihat Riwayat Sendiri'] },
            { nama: 'Viewer',     icon: '\uD83D\uDC41\uFE0F', bg: 'bg-slate-100',  jumlahUser: 2, deskripsi: 'Hanya dapat melihat data tanpa bisa mengubah atau membuat data baru.',                           permissions: ['Lihat Dashboard', 'Lihat Daftar Usulan', 'Lihat Laporan Terbatas'] },
        ],

        dataList: [
            { id: 1,  nomor: 'USL-2025-001', jenis: 'Usulan',    deskripsi: 'Rapat Koordinasi Nasional — Jakarta',           dibuatOleh: 'Dr. Hendra Santoso',   tanggal: '10/01/2025', status: 'Disetujui', deleted: false },
            { id: 2,  nomor: 'USL-2025-004', jenis: 'Usulan',    deskripsi: 'Bimtek Pengelolaan Anggaran — Surabaya',         dibuatOleh: 'Ahmad Fauzi, SE',      tanggal: '15/01/2025', status: 'Aktif',      deleted: false },
            { id: 3,  nomor: 'USL-2025-010', jenis: 'Usulan',    deskripsi: 'Pelatihan Sistem Informasi — Manado',            dibuatOleh: 'Ricky Pontoh, SKM',    tanggal: '01/02/2025', status: 'Menunggu',   deleted: false },
            { id: 4,  nomor: 'USL-2025-012', jenis: 'Usulan',    deskripsi: 'Monitoring & Evaluasi Program — Gorontalo',      dibuatOleh: 'dr. Andi Kusuma',      tanggal: '10/02/2025', status: 'Dibatalkan', deleted: false },
            { id: 5,  nomor: 'TRX-2026-028', jenis: 'Transaksi', deskripsi: 'Uang Muka 80% — USL-2025-001 (Rp 7.000.000)',    dibuatOleh: 'Ahmad Fauzi, SE',      tanggal: '14/01/2025', status: 'Selesai',    deleted: false },
            { id: 6,  nomor: 'TRX-2026-029', jenis: 'Transaksi', deskripsi: 'Sisa Bayar 20% — USL-2025-001 (Rp 1.750.000)',   dibuatOleh: 'Ahmad Fauzi, SE',      tanggal: '20/01/2025', status: 'Menunggu',   deleted: false },
            { id: 7,  nomor: 'TRX-2026-030', jenis: 'Transaksi', deskripsi: 'Uang Muka 80% — USL-2025-004 (Rp 7.200.000)',    dibuatOleh: 'Ahmad Fauzi, SE',      tanggal: '18/01/2025', status: 'Selesai',    deleted: false },
            { id: 8,  nomor: 'DOK-2025-045', jenis: 'Dokumen',   deskripsi: 'Surat Tugas — USL-2025-001',                     dibuatOleh: 'Siti Rahayu, S.Kep',   tanggal: '14/01/2025', status: 'Aktif',      deleted: false },
            { id: 9,  nomor: 'DOK-2025-046', jenis: 'Dokumen',   deskripsi: 'SPPD — USL-2025-001',                            dibuatOleh: 'Siti Rahayu, S.Kep',   tanggal: '14/01/2025', status: 'Aktif',      deleted: false },
            { id: 10, nomor: 'DOK-2025-050', jenis: 'Dokumen',   deskripsi: 'Boarding Pass — USL-2025-001',                   dibuatOleh: 'Dr. Hendra Santoso',   tanggal: '18/01/2025', status: 'Aktif',      deleted: false },
            { id: 11, nomor: 'USL-2024-089', jenis: 'Usulan',    deskripsi: 'Workshop SDM — Makassar',                        dibuatOleh: 'Maria Lumenta, S.Pd',  tanggal: '01/12/2024', status: 'Selesai',    deleted: false },
            { id: 12, nomor: 'USL-2024-075', jenis: 'Usulan',    deskripsi: 'Studi Banding — Yogyakarta',                     dibuatOleh: 'Prof. Dr. Samuel R.',  tanggal: '15/10/2024', status: 'Selesai',    deleted: false },
        ],

        get filteredData() {
            return this.dataList.filter(d => {
                if (this.dataFilter === 'semua') return true;
                if (this.dataFilter === 'usulan') return d.jenis === 'Usulan';
                if (this.dataFilter === 'transaksi') return d.jenis === 'Transaksi';
                if (this.dataFilter === 'dokumen') return d.jenis === 'Dokumen';
                return true;
            });
        },

        logAktivitas: [
            { id: 1,  waktu: '10/04/2026 14:35', user: 'Admin PANGI',           aksi: 'Ganti Role',           detail: 'Mengubah role Ricky Pontoh dari Viewer ke Staf',                  tipe: 'role' },
            { id: 2,  waktu: '10/04/2026 14:32', user: 'Admin PANGI',           aksi: 'Login',                detail: 'Berhasil login ke sistem',                                        tipe: 'login' },
            { id: 3,  waktu: '10/04/2026 13:15', user: 'Dr. Hendra Santoso',    aksi: 'Edit Data',            detail: 'Mengubah deskripsi USL-2025-001',                                 tipe: 'edit' },
            { id: 4,  waktu: '10/04/2026 11:40', user: 'Ahmad Fauzi, SE',       aksi: 'Edit Transaksi',       detail: 'Update status TRX-2026-029 menjadi Selesai',                      tipe: 'edit' },
            { id: 5,  waktu: '09/04/2026 16:20', user: 'Admin PANGI',           aksi: 'Batalkan Data',        detail: 'Membatalkan USL-2025-012 — Monitoring & Evaluasi',                tipe: 'batal' },
            { id: 6,  waktu: '09/04/2026 15:05', user: 'Admin PANGI',           aksi: 'Hapus Dokumen',        detail: 'Menghapus DOK-2025-043 — Draft kwitansi duplikat',                tipe: 'hapus' },
            { id: 7,  waktu: '09/04/2026 10:30', user: 'Admin PANGI',           aksi: 'Reset Password',       detail: 'Reset password untuk akun Ricky Pontoh, SKM',                     tipe: 'system' },
            { id: 8,  waktu: '08/04/2026 17:00', user: 'Admin PANGI',           aksi: 'Tambah Pengguna',      detail: 'Menambahkan pengguna baru — Indra Manoppo, S.Kep (Viewer)',       tipe: 'edit' },
            { id: 9,  waktu: '08/04/2026 09:00', user: 'Sistem',                aksi: 'Backup Database',      detail: 'Auto backup harian selesai — 245 MB',                             tipe: 'system' },
            { id: 10, waktu: '07/04/2026 14:10', user: 'Admin PANGI',           aksi: 'Ganti Role',           detail: 'Mengubah role Dr. Hendra Santoso dari Staf ke Pimpinan',          tipe: 'role' },
        ],

        roleBadge(role) {
            const map = {
                admin:     'bg-teal-50 text-teal-700 border-teal-100',
                pimpinan:  'bg-violet-50 text-violet-700 border-violet-100',
                bendahara: 'bg-emerald-50 text-emerald-700 border-emerald-100',
                staf:      'bg-blue-50 text-blue-700 border-blue-100',
                viewer:    'bg-slate-100 text-slate-600 border-slate-200',
            };
            return map[role] || 'bg-slate-100 text-slate-600 border-slate-200';
        },

        statusBadge(status) {
            const map = {
                'Aktif':      'bg-teal-50 text-teal-700 border-teal-100',
                'Disetujui':  'bg-teal-50 text-teal-700 border-teal-100',
                'Menunggu':   'bg-amber-50 text-amber-700 border-amber-100',
                'Selesai':    'bg-slate-100 text-slate-600 border-slate-200',
                'Dibatalkan': 'bg-red-50 text-red-600 border-red-100',
            };
            return map[status] || 'bg-slate-100 text-slate-600 border-slate-200';
        },

        statusDot(status) {
            const map = {
                'Aktif':      'bg-teal-500',
                'Disetujui':  'bg-teal-500',
                'Menunggu':   'bg-amber-500',
                'Selesai':    'bg-slate-400',
                'Dibatalkan': 'bg-red-500',
            };
            return map[status] || 'bg-slate-400';
        },

        logIconBg(tipe) {
            const map = {
                login:  'bg-teal-50',
                role:   'bg-violet-50',
                hapus:  'bg-red-50',
                edit:   'bg-amber-50',
                batal:  'bg-orange-50',
                system: 'bg-slate-100',
            };
            return map[tipe] || 'bg-slate-100';
        },

        gantiRole(user, newRole) {
            const oldRole = user.role;
            user.role = newRole;
            this.toast(`Role ${user.nama} diubah dari ${oldRole} ke ${newRole}`, 'teal');
        },

        toggleStatus(user) {
            user.aktif = !user.aktif;
            this.toast(`${user.nama} ${user.aktif ? 'diaktifkan' : 'dinonaktifkan'}`, user.aktif ? 'teal' : 'slate');
        },

        editUser(user) {
            this.editingUser = { ...user };
            this.showModal = 'edit-user';
        },

        simpanUser() {
            const idx = this.users.findIndex(u => u.id === this.editingUser.id);
            if (idx !== -1) {
                this.users[idx] = { ...this.editingUser };
                this.toast(`Data ${this.editingUser.nama} berhasil disimpan`, 'teal');
            }
            this.showModal = null;
        },

        resetPassword(user) {
            this.toast(`Password ${user.nama} berhasil direset. Email notifikasi terkirim.`, 'amber');
        },

        editData(d) {
            this.editingData = { ...d };
            this.showModal = 'edit-data';
        },

        simpanData() {
            const idx = this.dataList.findIndex(x => x.id === this.editingData.id);
            if (idx !== -1) {
                this.dataList[idx].deskripsi = this.editingData.deskripsi;
                this.dataList[idx].status = this.editingData.status;
                this.toast(`Data ${this.editingData.nomor} berhasil diperbarui`, 'teal');
            }
            this.showModal = null;
        },

        batalkanData(d) {
            d.status = 'Dibatalkan';
            this.toast(`${d.nomor} berhasil dibatalkan`, 'amber');
        },

        confirmDelete(target, type) {
            this.deleteTarget = target;
            this.deleteType = type;
            this.showModal = 'confirm-delete';
        },

        hapusKonfirmasi() {
            if (this.deleteType === 'user') {
                this.users = this.users.filter(u => u.id !== this.deleteTarget.id);
                this.toast(`Pengguna ${this.deleteTarget.nama} berhasil dihapus`, 'red');
            } else if (this.deleteType === 'data') {
                const idx = this.dataList.findIndex(x => x.id === this.deleteTarget.id);
                if (idx !== -1) this.dataList[idx].deleted = true;
                this.toast(`${this.deleteTarget.nomor} berhasil dihapus`, 'red');
            }
            this.showModal = null;
        },

        clearLog() {
            this.logAktivitas = [];
            this.toast('Log aktivitas berhasil dibersihkan', 'red');
        },

        simpanKonfigurasi() {
            this.toast('Pengaturan berhasil disimpan', 'teal');
        },

        toast(message, color = 'teal') {
            const colors = {
                teal:  'bg-teal-600',
                amber: 'bg-amber-600',
                red:   'bg-red-600',
                slate: 'bg-slate-700',
            };
            const el = document.createElement('div');
            el.className = `fixed bottom-6 right-6 ${colors[color] || colors.teal} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg z-[60] flex items-center gap-2`;
            el.innerHTML = `<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg> ${message}`;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        },
    }));
});
</script>

@endsection