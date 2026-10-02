@extends('app')

@section('title', 'Nomor Surat')

@section('content')

@php
    $namaBulan = collect(range(1, 12))->mapWithKeys(fn ($b) => [$b => \Carbon\Carbon::create(null, $b, 1)->translatedFormat('F')]);
    $saringan = array_filter(['tahun' => $tahun, 'bulan' => $bulan, 'search' => $search], fn ($nilai) => filled($nilai));

    // Nomor resmi boleh berganti baris hanya sesudah garis miring, supaya
    // tiap bagiannya ("F.XXX.8", "2471") tetap utuh terbaca.
    $pecah = fn (string $nomor) => str_replace('/', '/<wbr>', e($nomor));
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Nomor Surat</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Nomor SPD dan surat tugas yang tercatat di PANGI per bulan — untuk dicocokkan arsiparis dengan buku agenda surat keluar
            </p>
        </div>
        <a href="{{ route('audit-log.nomor-surat.ekspor', $saringan) }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Ekspor Excel
        </a>
    </div>

    {{-- Saringan --}}
    <form method="GET" action="{{ route('audit-log.nomor-surat') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select name="tahun"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @foreach ($tahunTersedia as $pilihan)
                        <option value="{{ $pilihan }}" @selected($pilihan === $tahun)>Tahun {{ $pilihan }}</option>
                    @endforeach
                </select>
                <select name="bulan"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">Semua bulan</option>
                    @foreach ($namaBulan as $nomor => $nama)
                        <option value="{{ $nomor }}" @selected($nomor === $bulan)>{{ $nama }}</option>
                    @endforeach
                </select>
                <div class="relative md:col-span-2">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nomor SPD, surat tugas, nama, NIP, atau tujuan..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Tampilkan</button>
                @if ($bulan || $search)
                    <a href="{{ route('audit-log.nomor-surat', ['tahun' => $tahun]) }}"
                       class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['label' => 'Nomor SPD', 'nilai' => $jumlahSpd, 'warna' => 'bg-teal-100 text-teal-700'],
            ['label' => 'Surat Tugas', 'nilai' => $jumlahSuratTugas, 'warna' => 'bg-indigo-100 text-indigo-700'],
            ['label' => 'SPD Belum Bertanda Tangan', 'nilai' => $belumBertandaTangan, 'warna' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Baris Register', 'nilai' => $jumlahBaris, 'warna' => 'bg-slate-100 text-slate-700'],
        ] as $kartu)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-2xl font-bold text-slate-800">{{ number_format($kartu['nilai'], 0, ',', '.') }}</p>
                <p class="text-xs mt-1"><span class="inline-block px-2 py-0.5 rounded-full font-semibold {{ $kartu['warna'] }}">{{ $kartu['label'] }}</span></p>
            </div>
        @endforeach
    </div>

    <p class="mb-4 text-[11px] text-slate-400 leading-relaxed">
        <strong class="text-slate-500">No. SPD (Aplikasi)</strong> terbit saat SPD dibuat di PANGI;
        <strong class="text-slate-500">No. SPD Bertanda Tangan</strong> adalah nomor dari SPD yang sudah ditandatangani, yang disalin pelaksana pada usulannya.
        Tanggal mengikuti tanggal surat SPD, atau tanggal usulan dicatat bila perjalanannya tidak memakai SPD aplikasi.
    </p>

    {{-- Per bulan --}}
    <div class="space-y-5">
        @forelse ($perBulan as $kunciBulan => $isi)
            @php $judulBulan = \Carbon\Carbon::parse($kunciBulan.'-01')->translatedFormat('F Y'); @endphp

            <section class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-slate-800">{{ $judulBulan }}</h2>
                    <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold">
                        <span class="px-2.5 py-1 rounded-full bg-teal-50 text-teal-700">{{ $isi->whereNotNull('no_spd')->count() }} SPD</span>
                        <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700">{{ $isi->pluck('no_tugas')->filter()->unique()->count() }} surat tugas</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                                <th class="px-4 py-3 w-10">No</th>
                                <th class="px-4 py-3 w-24">Tanggal</th>
                                <th class="px-4 py-3">No. SPD (Aplikasi)</th>
                                <th class="px-4 py-3">No. SPD Bertanda Tangan</th>
                                <th class="px-4 py-3">No. Surat Tugas</th>
                                <th class="px-4 py-3">Pelaksana</th>
                                <th class="px-4 py-3">Tujuan &amp; Waktu</th>
                                <th class="px-4 py-3 w-32">Usulan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($isi->sortBy(fn ($b) => $b['tanggal']->format('Ymd'))->values() as $i => $baris)
                                <tr class="align-top hover:bg-slate-50/60 transition">
                                    <td class="px-4 py-3 text-xs text-slate-400">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $baris['tanggal']->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        @if ($baris['no_spd'])
                                            <span class="font-mono text-xs font-semibold text-slate-800 whitespace-nowrap">{{ $baris['no_spd'] }}</span>
                                        @else
                                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Tanpa SPD aplikasi</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($baris['no_spd_ttd'])
                                            <span class="font-mono text-xs text-slate-700">{!! $pecah($baris['no_spd_ttd']) !!}</span>
                                        @elseif ($baris['sumber'] === \App\Services\RegisterNomorSurat::SUMBER_SPD)
                                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Belum dicatat</span>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($baris['no_tugas'])
                                            <span class="font-mono text-xs text-slate-700">{!! $pecah($baris['no_tugas']) !!}</span>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-700">{{ $baris['nama'] }}</p>
                                        @if ($baris['nip'])
                                            <p class="text-[11px] text-slate-400 font-mono">{{ $baris['nip'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600">
                                        <p>{{ $baris['tujuan'] ?? '—' }}</p>
                                        @if ($baris['berangkat'])
                                            <p class="text-[11px] text-slate-400 mt-0.5">
                                                {{ $baris['berangkat']->translatedFormat('d M Y') }}@if ($baris['kembali'] && ! $baris['kembali']->isSameDay($baris['berangkat'])) — {{ $baris['kembali']->translatedFormat('d M Y') }}@endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($baris['no_usulan'])
                                            @can('melihat-semua-usulan')
                                                <a href="{{ route('usulan.show', $baris['no_usulan']) }}" class="text-xs font-semibold text-teal-600 hover:underline whitespace-nowrap">{{ $baris['no_usulan'] }}</a>
                                            @else
                                                <span class="text-xs font-semibold text-slate-700 whitespace-nowrap">{{ $baris['no_usulan'] }}</span>
                                            @endcan
                                            <p class="text-[11px] text-slate-400">{{ $baris['status'] }}</p>
                                        @else
                                            <span class="text-[11px] text-slate-400">Belum dipakai</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-12 text-center">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <p class="text-sm text-slate-400 mt-2">Belum ada nomor surat pada periode ini</p>
            </div>
        @endforelse
    </div>

</div>

@endsection
