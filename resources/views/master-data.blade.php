@extends('app')

@section('title', 'Master Data')

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
            <h1 class="text-xl font-bold text-slate-800">Master Data</h1>
            <p class="text-xs text-slate-400 mt-0.5">Kelola seluruh data usulan, keuangan, dokumen, dan pegawai PANGI</p>
        </div>
    </div>

    {{-- ── Stat Overview ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php
        $stats = [
            ['label' => 'Total Pegawai',      'value' => $totalPegawai,   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'bg' => 'bg-teal-50', 'text' => 'text-teal-600'],
            ['label' => 'Usulan Perdin',       'value' => $totalUsulan,    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
            ['label' => 'Transaksi Keuangan',  'value' => $totalKeuangan,  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
            ['label' => 'Dokumen Terupload',   'value' => $totalDokumen,   'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'bg' => 'bg-rose-50', 'text' => 'text-rose-600'],
        ];
        @endphp
        @foreach ($stats as $s)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="w-9 h-9 rounded-xl {{ $s['bg'] }} flex items-center justify-center mb-2.5">
                <svg class="w-4 h-4 {{ $s['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="{{ $s['icon'] }}"/>
                </svg>
            </div>
            <p class="text-xl font-bold text-slate-800">{{ number_format($s['value']) }}</p>
            <p class="text-xs text-slate-500">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Tabs ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        {{-- Tab Navigation --}}
        @php
        $tabItems = [
            ['key' => 'usulan',    'label' => 'Usulan Perdin',      'icon' => '📝', 'count' => $totalUsulan],
            ['key' => 'keuangan',  'label' => 'Keuangan',           'icon' => '💰', 'count' => $totalKeuangan],
            ['key' => 'dokumen',   'label' => 'Dokumen',            'icon' => '📄', 'count' => $totalDokumen],
            ['key' => 'pegawai',   'label' => 'Pegawai',            'icon' => '👤', 'count' => $totalPegawai],
        ];
        @endphp
        <div class="border-b border-slate-100 px-6 flex gap-1 overflow-x-auto">
            @foreach ($tabItems as $t)
            <a href="{{ route('master', ['tab' => $t['key']]) }}"
               class="flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition whitespace-nowrap -mb-px
                      {{ $tab === $t['key'] ? 'border-teal-500 text-teal-700 bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50' }}">
                <span class="text-base">{{ $t['icon'] }}</span>
                <span>{{ $t['label'] }}</span>
                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full leading-none
                      {{ $tab === $t['key'] ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-500' }}">{{ number_format($t['count']) }}</span>
            </a>
            @endforeach
        </div>

        {{-- Filter Bar --}}
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <form method="GET" action="{{ route('master') }}" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Cari data..." value="{{ $search }}"
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition bg-white">
                </div>
                @if (in_array($tab, ['usulan', 'keuangan']))
                <select name="status" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition min-w-[160px]">
                    @if ($tab === 'usulan')
                    <option value="">Semua Status</option>
                    @foreach (\App\Enums\StatusUsulan::options() as $nilai => $label)
                        <option value="{{ $nilai }}" {{ $statusFilter === $nilai ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                    @else
                    <option value="">Semua Status</option>
                    <option value="belum bayar" {{ $statusFilter === 'belum bayar' ? 'selected' : '' }}>Belum Bayar</option>
                    <option value="bayar sebagian" {{ $statusFilter === 'bayar sebagian' ? 'selected' : '' }}>Bayar Sebagian</option>
                    <option value="lunas" {{ $statusFilter === 'lunas' ? 'selected' : '' }}>Lunas</option>
                    @endif
                </select>
                @endif
                <button type="submit"
                        class="flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
                @if ($search || $statusFilter)
                <a href="{{ route('master', ['tab' => $tab]) }}"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Reset
                </a>
                @endif
            </form>
        </div>

        {{-- ═══════════ TAB: Usulan Perdin ═══════════ --}}
        @if ($tab === 'usulan')
        <div>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Seluruh Usulan Perjalanan Dinas <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">{{ $usulanList->total() }}</span></p>
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
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($usulanList as $u)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $u->no_usulan }}</td>
                            <td class="px-4 py-3.5 text-slate-800 font-medium">{{ $u->kegiatan?->nama ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $u->user?->nama ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $u->lokasi }}</td>
                            <td class="px-4 py-3.5 text-slate-500 text-xs">{{ $u->periode }}</td>
                            <td class="px-4 py-3.5 font-semibold text-slate-700">
                                @if ($u->keuangan && $u->keuangan->total > 0)
                                    Rp {{ number_format($u->keuangan->total, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <x-status-badge :usulan="$u" />
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('usulan.show', $u->no_usulan) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    <p class="text-sm text-slate-400">Tidak ada data usulan ditemukan</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ═══════════ TAB: Keuangan ═══════════ --}}
        @if ($tab === 'keuangan')
        <div>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Riwayat Transaksi Keuangan <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">{{ $keuanganList->total() }}</span></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">No. Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kegiatan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Total</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Uang Muka</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Sisa Bayar</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($keuanganList as $k)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $k->usulan?->no_usulan ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-slate-700 font-medium">{{ $k->usulan?->kegiatan?->nama ?? '—' }}</td>
                            <td class="px-4 py-3.5 font-semibold text-slate-800">Rp {{ number_format($k->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-slate-600">Rp {{ number_format($k->uang_muka, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-slate-600">Rp {{ number_format($k->sisa, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5">
                                @if ($k->status === 'lunas')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Lunas</span>
                                @elseif ($k->status === 'bayar sebagian')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Bayar Sebagian</span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 border border-red-100 text-xs font-semibold rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Belum Bayar</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('keuangan.detail', $k->usulan?->no_usulan) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1"/></svg>
                                    <p class="text-sm text-slate-400">Tidak ada data keuangan ditemukan</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ═══════════ TAB: Dokumen ═══════════ --}}
        @if ($tab === 'dokumen')
        <div>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Seluruh Dokumen Terupload <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">{{ $dokumenList->total() }}</span></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">No. Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Pemohon</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Jenis Dokumen</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Kelengkapan</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php
                        $dokumenLabels = [
                            'surat_tugas' => 'Surat Tugas',
                            'rundown' => 'Rundown',
                            'dokumen_pendukung' => 'Dok. Pendukung',
                            'sppd' => 'SPPD',
                            'boarding_pass' => 'Boarding Pass',
                            'faktur' => 'Faktur',
                            'kwintasi' => 'Kwitansi',
                            'bill_hotel' => 'Bill Hotel',
                            'laporan_hasil' => 'Laporan Hasil',
                        ];
                        @endphp
                        @forelse ($dokumenList as $d)
                        @php
                            $uploadedFields = collect($dokumenLabels)->filter(fn ($label, $key) => $d->$key !== null);
                            $totalFields = count($dokumenLabels);
                            $uploadedCount = $uploadedFields->count();
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-mono text-xs font-bold text-teal-700">{{ $d->usulan?->no_usulan ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $d->usulan?->user?->nama ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($uploadedFields as $key => $label)
                                    <span class="px-2 py-0.5 bg-teal-50 text-teal-700 border border-teal-100 text-[10px] font-semibold rounded-full">{{ $label }}</span>
                                    @endforeach
                                    @if ($uploadedCount === 0)
                                    <span class="text-xs text-slate-400">Belum ada file</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden max-w-[80px]">
                                        <div class="h-full rounded-full {{ $uploadedCount === $totalFields ? 'bg-teal-500' : ($uploadedCount > 0 ? 'bg-amber-400' : 'bg-slate-300') }}"
                                             style="width: {{ $totalFields > 0 ? round($uploadedCount / $totalFields * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500 font-semibold">{{ $uploadedCount }}/{{ $totalFields }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('dokumen.show', $d->usulan?->no_usulan) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Lihat
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <p class="text-sm text-slate-400">Tidak ada dokumen ditemukan</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ═══════════ TAB: Pegawai ═══════════ --}}
        @if ($tab === 'pegawai')
        <div>
            <div class="px-6 py-3 flex items-center justify-between border-b border-slate-100">
                <p class="text-sm font-bold text-slate-600">Data Pegawai <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full ml-1">{{ $pegawai->total() }}</span></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-16">No</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Nama Lengkap</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Email</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Total Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($pegawai as $i => $p)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3 text-slate-400 text-xs">{{ $pegawai->firstItem() + $i }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($p->nama, 0, 1)) }}
                                    </div>
                                    <span class="font-semibold text-slate-800">{{ $p->nama }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $p->email }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">{{ $p->usulan_count }} usulan</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $p->created_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p class="text-sm text-slate-400">Tidak ada pegawai ditemukan</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-slate-100">
            @if ($tab === 'usulan')
                {{ $usulanList->links() }}
            @elseif ($tab === 'keuangan')
                {{ $keuanganList->links() }}
            @elseif ($tab === 'dokumen')
                {{ $dokumenList->links() }}
            @elseif ($tab === 'pegawai')
                {{ $pegawai->links() }}
            @endif
        </div>

    </div>

</div>

@endsection
