@use('App\Services\KabarPerjalanan')

@extends('app')

@section('title', 'News Feed')

@section('content')

@php
    $tautan = fn (array $ubah) => route('dashboard-eksekutif.news-feed', array_filter(
        array_merge(['jenis' => $jenis, 'rentang' => $hari === KabarPerjalanan::RENTANG_BAWAAN ? null : $hari], $ubah),
        fn ($nilai) => filled($nilai),
    ));
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">News Feed</h1>
        <p class="text-xs text-slate-400 mt-0.5">Kabar siapa saja yang akan dan sedang melakukan perjalanan dinas, serta tindak lanjut yang dijadwalkan</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-6 max-w-6xl">

        {{-- Lini masa --}}
        <div class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div class="flex flex-wrap gap-2">
                    @foreach ([null => 'Semua', KabarPerjalanan::JENIS_PERJALANAN => 'Perjalanan', KabarPerjalanan::JENIS_TINDAK_LANJUT => 'Tindak Lanjut'] as $nilai => $label)
                        <a href="{{ $tautan(['jenis' => $nilai ?: null]) }}"
                           class="px-4 py-2 rounded-full text-sm font-bold transition
                                  {{ (string) $jenis === (string) $nilai ? 'bg-teal-500 text-white shadow-sm shadow-teal-200' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-full p-1">
                    @foreach (KabarPerjalanan::RENTANG as $pilihan)
                        <a href="{{ $tautan(['rentang' => $pilihan === KabarPerjalanan::RENTANG_BAWAAN ? null : $pilihan]) }}"
                           class="px-3 py-1 rounded-full text-xs font-semibold transition
                                  {{ $hari === $pilihan ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ $pilihan }} hari
                        </a>
                    @endforeach
                </div>
            </div>

            @forelse ($kelompok as $kunci => $daftar)
                <div class="mb-6">
                    <div class="sticky top-0 z-10 -mx-1 px-1 py-2 bg-slate-50/90 backdrop-blur">
                        <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wide
                                     {{ $kunci === KabarPerjalanan::KELOMPOK_TERLAMBAT ? 'text-red-600' : 'text-slate-500' }}">
                            <span class="w-2 h-2 rounded-full {{ $kunci === KabarPerjalanan::KELOMPOK_TERLAMBAT ? 'bg-red-500' : 'bg-teal-500' }}"></span>
                            {{ KabarPerjalanan::labelKelompok($kunci) }}
                        </span>
                    </div>

                    <div class="space-y-4 mt-2">
                        @foreach ($daftar as $item)
                            @php $usulan = $item['usulan']; @endphp

                            <article class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                                <header class="px-5 pt-4 flex items-start gap-3">
                                    <x-avatar :nama="$item['penulis']['nama']" :foto="$item['penulis']['foto']" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm leading-snug">
                                            <span class="font-bold text-slate-800">{{ $item['penulis']['nama'] }}</span>
                                            @if ($item['jenis'] === KabarPerjalanan::JENIS_PERJALANAN)
                                                <span class="text-slate-500">
                                                    {{ $item['berlangsung'] ? 'sedang dalam perjalanan dinas' : 'akan melakukan perjalanan dinas' }}@if ($item['peserta']->count() > 1) bersama {{ $item['peserta']->count() - 1 }} rekan @endif
                                                </span>
                                            @else
                                                <span class="text-slate-500">menjadwalkan tindak lanjut</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5 truncate">
                                            {{ $item['penulis']['keterangan'] ?? 'Pegawai' }}
                                            ·
                                            @if ($item['jenis'] === KabarPerjalanan::JENIS_PERJALANAN)
                                                diajukan {{ $usulan->created_at?->diffForHumans() }}
                                            @else
                                                dari perjalanan ke {{ $usulan->lokasi }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($item['jenis'] === KabarPerjalanan::JENIS_PERJALANAN)
                                        <span class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full
                                                     {{ $item['berlangsung'] ? 'bg-teal-500 text-white' : ($item['pasti'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">
                                            {{ $item['berlangsung'] ? 'Sedang bertugas' : ($item['pasti'] ? 'Terjadwal' : 'Masih diajukan') }}
                                        </span>
                                    @else
                                        <span class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full {{ $item['tindak']->status->badge() }}">
                                            {{ $item['tindak']->status->label() }}
                                        </span>
                                    @endif
                                </header>

                                @if ($item['jenis'] === KabarPerjalanan::JENIS_PERJALANAN)
                                    @if (filled($usulan->uraian))
                                        <p class="px-5 pt-3 text-sm text-slate-700 leading-relaxed">{{ $usulan->uraian }}</p>
                                    @endif

                                    {{-- Lampiran kabar: tujuan dan waktunya --}}
                                    <div class="mx-5 mt-3 rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50 via-white to-white p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-teal-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-teal-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path d="M12 21s-7-5.5-7-11a7 7 0 1114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-800">{{ $usulan->lokasi ?: 'Tujuan belum diisi' }}</p>
                                                @if (filled($usulan->instansi))
                                                    <p class="text-xs text-slate-500">{{ $usulan->instansi }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-600">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                                {{ $item['mulai']->translatedFormat('d M') }}@if (! $item['selesai']->isSameDay($item['mulai'])) – {{ $item['selesai']->translatedFormat('d M Y') }}@else {{ $item['mulai']->translatedFormat('Y') }}@endif
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-600">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                                {{ $item['lama'] }} hari
                                            </span>
                                            @if ($usulan->kategoriPerjadin)
                                                <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-600">{{ $usulan->kategoriPerjadin->nama }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="px-5 pt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-teal-600">
                                        <span>#{{ $usulan->jalur()->name }}</span>
                                        @if ($usulan->kegiatan)
                                            <span class="text-slate-400 font-medium truncate max-w-full">{{ $usulan->kegiatan->nama }}</span>
                                        @endif
                                    </div>

                                    <footer class="mt-3 px-5 py-3 border-t border-slate-50 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="flex -space-x-2">
                                                @foreach ($item['peserta']->take(5) as $orang)
                                                    <x-avatar :nama="$orang->nama" :foto="$orang->user?->url_foto" ukuran="sm" class="ring-2 ring-white" />
                                                @endforeach
                                            </div>
                                            <span class="text-xs text-slate-500 truncate">
                                                {{ max(1, $item['peserta']->count()) }} pelaksana
                                                @if ($item['peserta']->count() > 5) · +{{ $item['peserta']->count() - 5 }} lainnya @endif
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <span class="text-xs font-bold {{ $item['berlangsung'] ? 'text-teal-600' : 'text-slate-700' }}">{{ $item['waktu'] }}</span>
                                            @can('melihat-semua-usulan')
                                                <a href="{{ route('usulan.show', $usulan->no_usulan) }}" class="text-xs font-semibold text-teal-600 hover:underline">Lihat</a>
                                            @endcan
                                        </div>
                                    </footer>
                                @else
                                    @php $tindak = $item['tindak']; @endphp

                                    <p class="px-5 pt-3 text-sm text-slate-700 leading-relaxed">{{ $tindak->uraian }}</p>

                                    <div class="mx-5 mt-3 rounded-xl border p-4 flex items-center gap-4
                                                {{ $item['terlambat'] ? 'border-red-100 bg-red-50/60' : 'border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-white' }}">
                                        <div class="w-14 shrink-0 rounded-xl bg-white border text-center overflow-hidden {{ $item['terlambat'] ? 'border-red-200' : 'border-indigo-200' }}">
                                            <p class="text-[10px] font-bold uppercase py-0.5 text-white {{ $item['terlambat'] ? 'bg-red-500' : 'bg-indigo-500' }}">{{ $item['target']->translatedFormat('M') }}</p>
                                            <p class="text-xl font-bold text-slate-800 leading-tight py-1">{{ $item['target']->format('d') }}</p>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-400">Target selesai</p>
                                            <p class="text-sm font-bold text-slate-800">{{ $item['target']->translatedFormat('l, d F Y') }}</p>
                                            @if (filled($tindak->penanggung_jawab))
                                                <p class="text-xs text-slate-500 mt-0.5">Penanggung jawab: <span class="font-semibold text-slate-700">{{ $tindak->penanggung_jawab }}</span></p>
                                            @endif
                                        </div>
                                    </div>

                                    <footer class="mt-3 px-5 py-3 border-t border-slate-50 flex items-center justify-between gap-3">
                                        <span class="text-xs text-slate-500 truncate">
                                            Perjalanan {{ $usulan->no_usulan }} · {{ \Carbon\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d M Y') }}
                                        </span>
                                        <span class="text-xs font-bold shrink-0 {{ $item['terlambat'] ? 'text-red-600' : 'text-indigo-600' }}">{{ $item['waktu'] }}</span>
                                    </footer>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-14 text-center">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-teal-50 text-teal-500 flex items-center justify-center">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M4 11a9 9 0 019 9M4 4a16 16 0 0116 16"/><circle cx="5" cy="19" r="1.5"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-600 mt-3">Belum ada kabar dalam {{ $hari }} hari ke depan</p>
                    <p class="text-xs text-slate-400 mt-1">Perjalanan dinas dan tindak lanjut yang dijadwalkan akan muncul di sini.</p>
                </div>
            @endforelse

            @if ($jumlah >= 60)
                <p class="text-center text-xs text-slate-400">Menampilkan kabar terdekat. Persempit rentang hari untuk melihat lebih rinci.</p>
            @endif
        </div>

        {{-- Panel samping --}}
        <aside class="order-first xl:order-last">
            <div class="xl:sticky xl:top-6 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Sekilas {{ $hari }} hari ke depan</p>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        @foreach ([
                            ['label' => 'Sedang bertugas', 'nilai' => $ringkasan['sedang_bertugas'], 'warna' => 'text-teal-600'],
                            ['label' => 'Akan berangkat', 'nilai' => $ringkasan['akan_berangkat'], 'warna' => 'text-slate-800'],
                            ['label' => 'Tindak lanjut jatuh tempo', 'nilai' => $ringkasan['tindak_lanjut'], 'warna' => 'text-indigo-600'],
                            ['label' => 'Lewat tenggat', 'nilai' => $ringkasan['terlambat'], 'warna' => 'text-red-600'],
                        ] as $angka)
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <p class="text-xl font-bold {{ $angka['warna'] }}">{{ $angka['nilai'] }}</p>
                                <p class="text-[11px] text-slate-500 leading-tight">{{ $angka['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-slate-400 mt-3">Pegawai dihitung per orang; tindak lanjut yang belum selesai saja.</p>
                </div>

                @if ($ringkasan['tujuan']->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Tujuan terbanyak</p>
                        <ol class="mt-3 space-y-2">
                            @foreach ($ringkasan['tujuan'] as $tujuan => $kali)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 rounded-md bg-teal-50 text-teal-600 text-[11px] font-bold flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                                        <span class="truncate text-slate-700">{{ $tujuan }}</span>
                                    </span>
                                    <span class="text-xs font-semibold text-slate-400 shrink-0">{{ $kali }} perjalanan</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                <a href="{{ route('dashboard-eksekutif') }}"
                   class="flex items-center justify-between gap-2 bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-4 text-sm font-semibold text-slate-600 hover:text-teal-700 transition">
                    Dashboard Utama
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
        </aside>
    </div>

</div>

@endsection
