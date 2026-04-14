@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="administrasi()">

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
                <p class="text-xs text-slate-400 mt-0.5">Kelola pengguna, role, dan akses sistem PANGI</p>
            </div>
        </div>
        <button @click="showAddModal = true" type="button"
                class="flex items-center gap-2 px-4 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Tambah Pengguna
        </button>
    </div>

    {{-- Flash Message --}}
    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Stat Cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php
        $stats = [
            ['label' => 'Total Pengguna',  'value' => $totalUsers,   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
            ['label' => 'Administrator',   'value' => $totalAdmin,   'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'bg' => 'bg-red-50', 'text' => 'text-red-600'],
            ['label' => 'PPK',             'value' => $totalPPK,     'icon' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0', 'bg' => 'bg-blue-50', 'text' => 'text-blue-600'],
            ['label' => 'Pegawai',         'value' => $totalPegawai, 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'bg' => 'bg-teal-50', 'text' => 'text-teal-600'],
        ];
        @endphp
        @foreach ($stats as $s)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl {{ $s['bg'] }} flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 {{ $s['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="{{ $s['icon'] }}"/>
                </svg>
            </div>
            <p class="text-xl font-bold text-slate-800">{{ $s['value'] }}</p>
            <p class="text-xs text-slate-500">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── User Table Card ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        {{-- Filter Bar --}}
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <form method="GET" action="{{ route('administrasi') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Cari nama, email, atau NIP..." value="{{ $search }}"
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition bg-white">
                </div>
                <select name="role" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-[160px]">
                    <option value="">Semua Role</option>
                    <option value="administrator" {{ $roleFilter === 'administrator' ? 'selected' : '' }}>Administrator</option>
                    <option value="ppk" {{ $roleFilter === 'ppk' ? 'selected' : '' }}>PPK</option>
                    <option value="pegawai" {{ $roleFilter === 'pegawai' ? 'selected' : '' }}>Pegawai</option>
                </select>
                <button type="submit" class="flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
                @if ($search || $roleFilter)
                <a href="{{ route('administrasi') }}" class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Reset
                </a>
                @endif
            </form>
        </div>

        {{-- Table Header --}}
        <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
            <p class="text-sm font-bold text-slate-600">Daftar Pengguna <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">{{ $users->total() }}</span></p>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-14">No</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">NIP</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama Lengkap</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Email</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Role</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Usulan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Terdaftar</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($users as $i => $user)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3 text-slate-400 text-xs">{{ $users->firstItem() + $i }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $user->nip }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                                    {{ $user->role === 'administrator' ? 'bg-red-100 text-red-700' : ($user->role === 'ppk' ? 'bg-blue-100 text-blue-700' : 'bg-teal-100 text-teal-700') }}">
                                    {{ strtoupper(substr($user->nama, 0, 1)) }}
                                </div>
                                <span class="font-semibold text-slate-800">{{ $user->nama }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->role === 'administrator')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 border border-red-100 text-xs font-semibold rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Administrator
                            </span>
                            @elseif ($user->role === 'ppk')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                                PPK
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Pegawai
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">{{ $user->usulan_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                {{-- Edit --}}
                                <button @click="openEdit({{ json_encode(['id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'nip' => $user->nip, 'role' => $user->role]) }})"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit Pengguna">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                {{-- Password --}}
                                <button @click="openPassword({{ $user->id }}, '{{ addslashes($user->nama) }}')"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-amber-50 text-slate-500 hover:text-amber-700 transition" title="Ganti Password">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                </button>
                                {{-- Delete --}}
                                <button @click="openDelete({{ $user->id }}, '{{ addslashes($user->nama) }}')"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus Pengguna">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <p class="text-sm text-slate-400">Tidak ada pengguna ditemukan</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-slate-100">
            {{ $users->links() }}
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Tambah Pengguna                                    --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" x-show="showAddModal" x-transition.opacity @click="showAddModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" x-show="showAddModal" x-transition.scale.origin.center @click.away="showAddModal = false">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Tambah Pengguna Baru</h3>
                <button @click="showAddModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('administrasi.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" required placeholder="Masukkan nama lengkap"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">NIP <span class="text-red-500">*</span></label>
                    <input type="text" name="nip" required placeholder="Masukkan NIP"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required placeholder="email@poltekkes.ac.id"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Role <span class="text-red-500">*</span></label>
                    <select name="role" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <option value="pegawai">Pegawai</option>
                        <option value="ppk">PPK</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi password"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click.prevent="showAddModal = false" type="button"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Simpan
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Edit Pengguna                                      --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" x-show="showEditModal" x-transition.opacity @click="showEditModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" x-show="showEditModal" x-transition.scale.origin.center @click.away="showEditModal = false">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Edit Pengguna</h3>
                <button @click="showEditModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="`{{ url('administrasi') }}/${editUser.id}`" method="POST" class="px-6 py-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" required x-model="editUser.nama"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">NIP <span class="text-red-500">*</span></label>
                    <input type="text" name="nip" required x-model="editUser.nip"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required x-model="editUser.email"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Role <span class="text-red-500">*</span></label>
                    <select name="role" required x-model="editUser.role" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <option value="pegawai">Pegawai</option>
                        <option value="ppk">PPK</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click.prevent="showEditModal = false" type="button"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            Simpan Perubahan
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Ganti Password                                     --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div x-show="showPasswordModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" x-show="showPasswordModal" x-transition.opacity @click="showPasswordModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md" x-show="showPasswordModal" x-transition.scale.origin.center @click.away="showPasswordModal = false">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Ganti Password</h3>
                    <p class="text-xs text-slate-400 mt-0.5" x-text="'Untuk pengguna: ' + passwordUserName"></p>
                </div>
                <button @click="showPasswordModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="`{{ url('administrasi') }}/${passwordUserId}/password`" method="POST" class="px-6 py-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password Baru <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi password baru"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click.prevent="showPasswordModal = false" type="button"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            Ubah Password
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Konfirmasi Hapus                                   --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" x-show="showDeleteModal" x-transition.opacity @click="showDeleteModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm" x-show="showDeleteModal" x-transition.scale.origin.center @click.away="showDeleteModal = false">
            <div class="px-6 py-6 text-center">
                <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Hapus Pengguna?</h3>
                <p class="text-sm text-slate-500 mb-6">Pengguna <strong x-text="deleteUserName" class="text-slate-700"></strong> akan dihapus permanen beserta seluruh data terkait.</p>
                <div class="flex gap-3 justify-center">
                    <button @click="showDeleteModal = false" type="button"
                            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">Batal</button>
                    <form :action="`{{ url('administrasi') }}/${deleteUserId}`" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl transition">
                            Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('administrasi', () => ({
        showAddModal: false,
        showEditModal: false,
        showPasswordModal: false,
        showDeleteModal: false,
        editUser: { id: null, nama: '', email: '', nip: '', role: 'pegawai' },
        passwordUserId: null,
        passwordUserName: '',
        deleteUserId: null,
        deleteUserName: '',

        openEdit(user) {
            this.editUser = { ...user };
            this.showEditModal = true;
        },
        openPassword(id, name) {
            this.passwordUserId = id;
            this.passwordUserName = name;
            this.showPasswordModal = true;
        },
        openDelete(id, name) {
            this.deleteUserId = id;
            this.deleteUserName = name;
            this.showDeleteModal = true;
        },
    }));
});
</script>
@endpush

@endsection
