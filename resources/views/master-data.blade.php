@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="masterData()">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Master Data</h1>
            <p class="text-xs text-slate-400 mt-0.5">Kelola seluruh referensi data, transaksi, dan aktivitas sistem PANGI</p>
        </div>
    </div>

    {{-- ── Stat Overview ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @php
        $stats = [
            ['label' => 'Total Pegawai',      'value' => '156',  'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'bg' => 'bg-teal-50', 'text' => 'text-teal-600'],
            ['label' => 'Unit Kerja',          'value' => '12',   'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'bg' => 'bg-blue-50', 'text' => 'text-blue-600'],
            ['label' => 'Jabatan',             'value' => '28',   'icon' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0', 'bg' => 'bg-violet-50', 'text' => 'text-violet-600'],
            ['label' => 'Usulan Perdin',       'value' => '87',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
            ['label' => 'Transaksi Keuangan',  'value' => '214',  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
            ['label' => 'Dokumen Terupload',   'value' => '342',  'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'bg' => 'bg-rose-50', 'text' => 'text-rose-600'],
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

    {{-- ── Tabs ── --}}
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
                    <span x-show="tab.count"
                          class="text-xs font-bold px-1.5 py-0.5 rounded-full leading-none"
                          :class="activeTab === tab.key ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-500'"
                          x-text="tab.count"></span>
                </button>
            </template>
        </div>

        {{-- Filter Bar --}}
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" placeholder="Cari data..." x-model="search"
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition bg-white">
                </div>
                <select class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-[140px]">
                    <option>Semua Status</option>
                    <option>Aktif</option>
                    <option>Non-aktif</option>
                </select>
                <button type="button"
                        class="flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
            </div>
        </div>

        {{-- ═══════════ TAB: Pegawai ═══════════ --}}
        <div x-show="activeTab === 'pegawai'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Data Pegawai <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">156</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Pegawai
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">NIP</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama Lengkap</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jabatan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Unit Kerja</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Golongan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $pegawai = [
                            ['nip' => '198501012010121001', 'nama' => 'Dr. Hendra Santoso, M.Kes', 'jabatan' => 'Kepala Bagian Keuangan', 'unit' => 'Bagian Keuangan', 'gol' => 'IV/a', 'status' => 'aktif'],
                            ['nip' => '199203152015042002', 'nama' => 'Siti Rahayu, S.Kep',        'jabatan' => 'Staf Keuangan',           'unit' => 'Bagian Keuangan', 'gol' => 'III/b', 'status' => 'aktif'],
                            ['nip' => '197802202005011003', 'nama' => 'Ahmad Fauzi, SE',            'jabatan' => 'Bendahara Pengeluaran',   'unit' => 'Bagian Keuangan', 'gol' => 'III/d', 'status' => 'aktif'],
                            ['nip' => '198011122004122005', 'nama' => 'Maria Lumenta, S.Pd',        'jabatan' => 'Kepala Sub Bagian SDM',   'unit' => 'Bagian SDM',      'gol' => 'III/c', 'status' => 'aktif'],
                            ['nip' => '199507082019031002', 'nama' => 'Ricky Pontoh, SKM',          'jabatan' => 'Staf SDM',                'unit' => 'Bagian SDM',      'gol' => 'III/a', 'status' => 'aktif'],
                            ['nip' => '196706151993031001', 'nama' => 'Prof. Dr. Samuel Roring',    'jabatan' => 'Direktur',                'unit' => 'Pimpinan',        'gol' => 'IV/d', 'status' => 'aktif'],
                            ['nip' => '197409222001122002', 'nama' => 'Ns. Debora Tumewu, M.Kep',   'jabatan' => 'Wakil Direktur I',       'unit' => 'Pimpinan',        'gol' => 'IV/b', 'status' => 'aktif'],
                            ['nip' => '198803052014041003', 'nama' => 'dr. Andi Kusuma',             'jabatan' => 'Dokter Fungsional',      'unit' => 'Jurusan Keperawatan', 'gol' => 'III/c', 'status' => 'nonaktif'],
                        ];
                        @endphp
                        @foreach ($pegawai as $p)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ $p['nip'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ substr($p['nama'], 0, 1) }}
                                    </div>
                                    <span class="font-semibold text-slate-800">{{ $p['nama'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $p['jabatan'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $p['unit'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $p['gol'] }}</td>
                            <td class="px-4 py-3">
                                @if ($p['status'] === 'aktif')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Aktif
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-500 border border-slate-200 text-xs font-semibold rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Non-aktif
                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Unit Kerja ═══════════ --}}
        <div x-show="activeTab === 'unit'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Data Unit Kerja <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">12</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Unit
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-16">No</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kode</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama Unit Kerja</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kepala Unit</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jml Pegawai</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $units = [
                            ['kode' => 'DIR', 'nama' => 'Pimpinan / Direktorat',          'kepala' => 'Prof. Dr. Samuel Roring',    'jml' => 4,  'status' => 'aktif'],
                            ['kode' => 'KEU', 'nama' => 'Bagian Keuangan',                'kepala' => 'Dr. Hendra Santoso, M.Kes', 'jml' => 18, 'status' => 'aktif'],
                            ['kode' => 'SDM', 'nama' => 'Bagian SDM & Kepegawaian',       'kepala' => 'Maria Lumenta, S.Pd',        'jml' => 12, 'status' => 'aktif'],
                            ['kode' => 'AKD', 'nama' => 'Bagian Akademik',                'kepala' => 'Drs. Budi Hartono, M.Si',   'jml' => 10, 'status' => 'aktif'],
                            ['kode' => 'UMU', 'nama' => 'Bagian Umum & Perlengkapan',     'kepala' => 'Ir. Tony Makarawung',        'jml' => 22, 'status' => 'aktif'],
                            ['kode' => 'JKP', 'nama' => 'Jurusan Keperawatan',            'kepala' => 'Ns. Debora Tumewu, M.Kep',  'jml' => 35, 'status' => 'aktif'],
                            ['kode' => 'JKB', 'nama' => 'Jurusan Kebidanan',              'kepala' => 'Dr. Grace Mosey, M.Keb',    'jml' => 28, 'status' => 'aktif'],
                            ['kode' => 'JGZ', 'nama' => 'Jurusan Gizi',                   'kepala' => 'Dr. Fenny Rompas, M.Gizi',  'jml' => 15, 'status' => 'aktif'],
                            ['kode' => 'JKL', 'nama' => 'Jurusan Kesehatan Lingkungan',   'kepala' => 'Dr. James Wowor, M.KL',     'jml' => 12, 'status' => 'aktif'],
                        ];
                        @endphp
                        @foreach ($units as $i => $u)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3 text-slate-400 text-xs">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs font-bold text-slate-600">{{ $u['kode'] }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $u['nama'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $u['kepala'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $u['jml'] }} orang</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Aktif
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Jabatan & Golongan ═══════════ --}}
        <div x-show="activeTab === 'jabatan'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Data Jabatan & Golongan <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">28</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Jabatan
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-16">No</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama Jabatan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Eselon / Level</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Golongan Minimal</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Uang Harian Perjadin</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $jabatanList = [
                            ['nama' => 'Direktur',                   'eselon' => 'Eselon II', 'gol' => 'IV/c', 'harian' => 'Rp 580.000'],
                            ['nama' => 'Wakil Direktur',             'eselon' => 'Eselon III','gol' => 'IV/a', 'harian' => 'Rp 530.000'],
                            ['nama' => 'Kepala Bagian',              'eselon' => 'Eselon III','gol' => 'III/d', 'harian' => 'Rp 480.000'],
                            ['nama' => 'Kepala Sub Bagian',          'eselon' => 'Eselon IV', 'gol' => 'III/b', 'harian' => 'Rp 430.000'],
                            ['nama' => 'Ketua Jurusan',              'eselon' => 'Eselon III','gol' => 'IV/a', 'harian' => 'Rp 480.000'],
                            ['nama' => 'Dosen / Fungsional Tertentu','eselon' => '—',         'gol' => 'III/a', 'harian' => 'Rp 380.000'],
                            ['nama' => 'Staf Administrasi',          'eselon' => '—',         'gol' => 'II/a', 'harian' => 'Rp 350.000'],
                            ['nama' => 'Bendahara Pengeluaran',      'eselon' => '—',         'gol' => 'III/a', 'harian' => 'Rp 380.000'],
                        ];
                        @endphp
                        @foreach ($jabatanList as $i => $j)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3 text-slate-400 text-xs">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $j['nama'] }}</td>
                            <td class="px-4 py-3">
                                @if ($j['eselon'] !== '—')
                                <span class="px-2.5 py-1 bg-violet-50 text-violet-700 border border-violet-100 text-xs font-semibold rounded-full">{{ $j['eselon'] }}</span>
                                @else
                                <span class="text-slate-400 text-xs">Non-eselon</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $j['gol'] }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $j['harian'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 transition" title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 transition" title="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Transaksi Keuangan ═══════════ --}}
        <div x-show="activeTab === 'transaksi'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Riwayat Transaksi Keuangan <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">214</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Tanggal</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">No. Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kegiatan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jenis</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $transaksi = [
                            ['tgl' => '05/04/2026', 'nomor' => 'USL-2024-089', 'kegiatan' => 'Workshop SDM',              'jenis' => 'Sisa Bayar',  'jumlah' => 'Rp 1.500.000', 'status' => 'berhasil'],
                            ['tgl' => '03/04/2026', 'nomor' => 'USL-2024-075', 'kegiatan' => 'Studi Banding',             'jenis' => 'Sisa Bayar',  'jumlah' => 'Rp 1.200.000', 'status' => 'berhasil'],
                            ['tgl' => '28/03/2026', 'nomor' => 'USL-2025-001', 'kegiatan' => 'Rapat Koordinasi Nasional', 'jenis' => 'Uang Muka',   'jumlah' => 'Rp 7.000.000', 'status' => 'berhasil'],
                            ['tgl' => '20/01/2025', 'nomor' => 'USL-2025-004', 'kegiatan' => 'Bimtek Pengelolaan',        'jenis' => 'Uang Muka',   'jumlah' => 'Rp 7.200.000', 'status' => 'berhasil'],
                            ['tgl' => '13/10/2024', 'nomor' => 'USL-2024-060', 'kegiatan' => 'Seminar Nasional',          'jenis' => 'Uang Muka',   'jumlah' => 'Rp 4.400.000', 'status' => 'berhasil'],
                            ['tgl' => '29/10/2024', 'nomor' => 'USL-2024-075', 'kegiatan' => 'Studi Banding',             'jenis' => 'Uang Muka',   'jumlah' => 'Rp 4.800.000', 'status' => 'berhasil'],
                            ['tgl' => '05/04/2026', 'nomor' => 'USL-2025-001', 'kegiatan' => 'Rapat Koordinasi Nasional', 'jenis' => 'Sisa Bayar',  'jumlah' => 'Rp 1.750.000', 'status' => 'pending'],
                        ];
                        @endphp
                        @foreach ($transaksi as $t)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 text-slate-500 text-xs">{{ $t['tgl'] }}</td>
                            <td class="px-4 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $t['nomor'] }}</td>
                            <td class="px-4 py-3.5 text-slate-700 font-medium">{{ $t['kegiatan'] }}</td>
                            <td class="px-4 py-3.5">
                                @if ($t['jenis'] === 'Uang Muka')
                                <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold rounded-full">{{ $t['jenis'] }}</span>
                                @else
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full">{{ $t['jenis'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-800">{{ $t['jumlah'] }}</td>
                            <td class="px-4 py-3.5">
                                @if ($t['status'] === 'berhasil')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Berhasil
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Usulan Perdin ═══════════ --}}
        <div x-show="activeTab === 'usulan'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Seluruh Usulan Perjalanan Dinas <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">87</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">No. Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kegiatan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Pemohon</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tujuan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Periode</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Estimasi</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $usulanAll = [
                            ['nomor' => 'USL-2025-001', 'kegiatan' => 'Rapat Koordinasi Nasional',     'pemohon' => 'Dr. Hendra Santoso',   'tujuan' => 'Jakarta',     'periode' => '15–17 Jan 2025', 'estimasi' => 'Rp 8.750.000', 'status' => 'disetujui'],
                            ['nomor' => 'USL-2025-004', 'kegiatan' => 'Bimtek Pengelolaan Anggaran',   'pemohon' => 'Ahmad Fauzi, SE',      'tujuan' => 'Surabaya',    'periode' => '20–22 Jan 2025', 'estimasi' => 'Rp 9.000.000', 'status' => 'berjalan'],
                            ['nomor' => 'USL-2024-089', 'kegiatan' => 'Workshop SDM',                  'pemohon' => 'Maria Lumenta, S.Pd',  'tujuan' => 'Makassar',    'periode' => '5–7 Des 2024',   'estimasi' => 'Rp 7.500.000', 'status' => 'selesai'],
                            ['nomor' => 'USL-2024-075', 'kegiatan' => 'Studi Banding',                 'pemohon' => 'Prof. Dr. Samuel R.',   'tujuan' => 'Yogyakarta',  'periode' => '1–3 Nov 2024',   'estimasi' => 'Rp 6.000.000', 'status' => 'selesai'],
                            ['nomor' => 'USL-2024-060', 'kegiatan' => 'Seminar Nasional Kesehatan',    'pemohon' => 'Ns. Debora Tumewu',    'tujuan' => 'Bandung',     'periode' => '15–16 Okt 2024', 'estimasi' => 'Rp 5.500.000', 'status' => 'selesai'],
                            ['nomor' => 'USL-2025-010', 'kegiatan' => 'Pelatihan Sistem Informasi',    'pemohon' => 'Ricky Pontoh, SKM',    'tujuan' => 'Manado',      'periode' => '10–11 Feb 2025', 'estimasi' => 'Rp 2.500.000', 'status' => 'menunggu'],
                            ['nomor' => 'USL-2025-012', 'kegiatan' => 'Monitoring & Evaluasi Program', 'pemohon' => 'dr. Andi Kusuma',      'tujuan' => 'Gorontalo',   'periode' => '18–20 Feb 2025', 'estimasi' => 'Rp 4.200.000', 'status' => 'ditolak'],
                        ];
                        @endphp
                        @foreach ($usulanAll as $u)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $u['nomor'] }}</td>
                            <td class="px-4 py-3.5 text-slate-800 font-medium">{{ $u['kegiatan'] }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $u['pemohon'] }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $u['tujuan'] }}</td>
                            <td class="px-4 py-3.5 text-slate-500 text-xs">{{ $u['periode'] }}</td>
                            <td class="px-4 py-3.5 font-semibold text-slate-700">{{ $u['estimasi'] }}</td>
                            <td class="px-4 py-3.5">
                                @if ($u['status'] === 'disetujui')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Disetujui</span>
                                @elseif ($u['status'] === 'berjalan')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Berjalan</span>
                                @elseif ($u['status'] === 'selesai')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Selesai</span>
                                @elseif ($u['status'] === 'menunggu')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Menunggu</span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 border border-red-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Ditolak</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Dokumen ═══════════ --}}
        <div x-show="activeTab === 'dokumen'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Seluruh Dokumen Terupload <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">342</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">No. Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jenis Dokumen</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama File</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Diupload Oleh</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tanggal Upload</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $dokumen = [
                            ['nomor' => 'USL-2025-001', 'jenis' => 'Surat Tugas',               'file' => 'ST-USL-2025-001.pdf',     'uploader' => 'Siti Rahayu',    'tgl' => '14/01/2025', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2025-001', 'jenis' => 'SPPD',                      'file' => 'SPPD-USL-2025-001.pdf',   'uploader' => 'Siti Rahayu',    'tgl' => '14/01/2025', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2025-001', 'jenis' => 'Boarding Pass',              'file' => 'BP-CGK-MDC-150125.jpg',   'uploader' => 'Dr. Hendra S.',  'tgl' => '18/01/2025', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2025-001', 'jenis' => 'Bill Hotel',                 'file' => 'bill-hotel-jkt.pdf',      'uploader' => 'Dr. Hendra S.',  'tgl' => '18/01/2025', 'status' => 'pending'],
                            ['nomor' => 'USL-2024-089', 'jenis' => 'Surat Tugas',               'file' => 'ST-USL-2024-089.pdf',     'uploader' => 'Maria Lumenta',  'tgl' => '03/12/2024', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2024-089', 'jenis' => 'Laporan Hasil Perjalanan',  'file' => 'LHP-USL-2024-089.pdf',    'uploader' => 'Maria Lumenta',  'tgl' => '10/12/2024', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2024-060', 'jenis' => 'Tiket / Invoice',           'file' => 'tiket-bdg-round.pdf',     'uploader' => 'Ns. Debora T.',  'tgl' => '12/10/2024', 'status' => 'terverifikasi'],
                            ['nomor' => 'USL-2024-060', 'jenis' => 'Kwitansi Lokal',            'file' => 'kwitansi-transport.jpg',   'uploader' => 'Ns. Debora T.',  'tgl' => '17/10/2024', 'status' => 'pending'],
                        ];
                        @endphp
                        @foreach ($dokumen as $d)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $d['nomor'] }}</td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">{{ $d['jenis'] }}</span>
                            </td>
                            <td class="px-4 py-3.5">
                                <a href="#" class="text-blue-600 hover:underline font-medium flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13A9 9 0 1112 3"/></svg>
                                    {{ $d['file'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $d['uploader'] }}</td>
                            <td class="px-4 py-3.5 text-slate-500 text-xs">{{ $d['tgl'] }}</td>
                            <td class="px-4 py-3.5">
                                @if ($d['status'] === 'terverifikasi')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Terverifikasi</span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════ TAB: Aktivitas Sistem ═══════════ --}}
        <div x-show="activeTab === 'aktivitas'" x-cloak>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Log Aktivitas Sistem <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">Terbaru</span></p>
                <button class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Log
                </button>
            </div>
            <div class="divide-y divide-slate-50">
                @php
                $aktivitas = [
                    ['waktu' => '10/04/2026 14:32', 'user' => 'Admin PANGI',              'aksi' => 'Login',                    'detail' => 'Berhasil login ke sistem',                          'tipe' => 'info'],
                    ['waktu' => '10/04/2026 14:28', 'user' => 'Siti Rahayu, S.Kep',       'aksi' => 'Upload Dokumen',           'detail' => 'Upload Surat Tugas — USL-2025-001',                 'tipe' => 'upload'],
                    ['waktu' => '10/04/2026 13:15', 'user' => 'Dr. Hendra Santoso',       'aksi' => 'Approve Usulan',           'detail' => 'Menyetujui USL-2025-010',                           'tipe' => 'approve'],
                    ['waktu' => '10/04/2026 11:40', 'user' => 'Ahmad Fauzi, SE',          'aksi' => 'Konfirmasi Uang Muka',     'detail' => 'Transfer 80% — USL-2025-001 (Rp 7.000.000)',        'tipe' => 'bayar'],
                    ['waktu' => '09/04/2026 16:20', 'user' => 'Maria Lumenta, S.Pd',      'aksi' => 'Buat Usulan',              'detail' => 'Membuat usulan USL-2025-015 — Workshop Kurikulum',  'tipe' => 'create'],
                    ['waktu' => '09/04/2026 15:05', 'user' => 'Prof. Dr. Samuel Roring',  'aksi' => 'Tolak Usulan',             'detail' => 'Menolak USL-2025-012 — Alasan: anggaran belum cukup', 'tipe' => 'reject'],
                    ['waktu' => '09/04/2026 10:30', 'user' => 'Ricky Pontoh, SKM',        'aksi' => 'Upload Dokumen',           'detail' => 'Upload Laporan Hasil Perjalanan — USL-2024-089',    'tipe' => 'upload'],
                    ['waktu' => '08/04/2026 09:00', 'user' => 'Sistem',                   'aksi' => 'Backup Database',          'detail' => 'Auto backup harian selesai — 245 MB',               'tipe' => 'system'],
                    ['waktu' => '07/04/2026 17:45', 'user' => 'Admin PANGI',              'aksi' => 'Tambah Pegawai',           'detail' => 'Menambahkan pegawai baru — Indra Manoppo, S.Kep',   'tipe' => 'create'],
                    ['waktu' => '07/04/2026 14:10', 'user' => 'Ns. Debora Tumewu',        'aksi' => 'Verifikasi LPJ',           'detail' => 'Verifikasi LPJ USL-2024-060 — Seminar Nasional',    'tipe' => 'approve'],
                ];
                @endphp
                @foreach ($aktivitas as $a)
                <div class="flex items-start gap-4 px-6 py-4 hover:bg-slate-50/60 transition">
                    {{-- Icon --}}
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5
                        @if ($a['tipe'] === 'approve') bg-teal-50
                        @elseif ($a['tipe'] === 'reject') bg-red-50
                        @elseif ($a['tipe'] === 'bayar') bg-emerald-50
                        @elseif ($a['tipe'] === 'upload') bg-blue-50
                        @elseif ($a['tipe'] === 'create') bg-violet-50
                        @elseif ($a['tipe'] === 'system') bg-slate-100
                        @else bg-slate-100
                        @endif">
                        @if ($a['tipe'] === 'approve')
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        @elseif ($a['tipe'] === 'reject')
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        @elseif ($a['tipe'] === 'bayar')
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1"/></svg>
                        @elseif ($a['tipe'] === 'upload')
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                        @elseif ($a['tipe'] === 'create')
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        @else
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                        @endif
                    </div>
                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-semibold text-slate-800">{{ $a['aksi'] }}</span>
                            <span class="text-xs text-slate-400">oleh</span>
                            <span class="text-sm font-medium text-slate-600">{{ $a['user'] }}</span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">{{ $a['detail'] }}</p>
                    </div>
                    {{-- Timestamp --}}
                    <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap">{{ $a['waktu'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500">Menampilkan 1–10 dari <span x-text="tabs.find(t => t.key === activeTab)?.count || '—'"></span> data</p>
            <div class="flex gap-1">
                <button class="px-3 py-1.5 text-xs font-semibold text-slate-400 bg-slate-100 rounded-lg cursor-not-allowed">Prev</button>
                <button class="px-3 py-1.5 text-xs font-semibold text-white bg-teal-500 rounded-lg">1</button>
                <button class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">2</button>
                <button class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">3</button>
                <button class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Next</button>
            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('masterData', () => ({
        activeTab: 'pegawai',
        search: '',
        tabs: [
            { key: 'pegawai',   label: 'Pegawai',            icon: '👤', count: 156 },
            { key: 'unit',      label: 'Unit Kerja',         icon: '🏢', count: 12 },
            { key: 'jabatan',   label: 'Jabatan & Golongan', icon: '📋', count: 28 },
            { key: 'transaksi', label: 'Transaksi Keuangan', icon: '💰', count: 214 },
            { key: 'usulan',    label: 'Usulan Perdin',      icon: '📝', count: 87 },
            { key: 'dokumen',   label: 'Dokumen',            icon: '📄', count: 342 },
            { key: 'aktivitas', label: 'Aktivitas Sistem',   icon: '🔔', count: null },
        ],
    }));
});
</script>

@endsection