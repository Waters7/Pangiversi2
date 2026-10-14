@extends('app')

@section('title', 'Surat Tugas & Dokumen')

@section('content')

@php
    // Nomor resmi boleh berganti baris hanya sesudah garis miring.
    $pecah = fn (string $nomor) => str_replace('/', '/<wbr>', e($nomor));
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Surat Tugas &amp; Dokumen</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Tiap nomor surat tugas yang tercatat di PANGI beserta berkasnya, siapa yang pertama mengunggah, dan usulan serta SPD yang memakainya
        </p>
    </div>

    {{-- Saringan --}}
    <form method="GET" action="{{ route('audit-log.surat-tugas') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <select name="tahun" aria-label="Tahun"
                        class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">Semua tahun</option>
                    @foreach ($tahunTersedia as $pilihan)
                        <option value="{{ $pilihan }}" @selected($pilihan === $tahun)>Tahun {{ $pilihan }}</option>
                    @endforeach
                </select>
                <div class="relative md:col-span-3">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $cari }}" placeholder="Cari nomor surat tugas, nomor usulan, atau nama pelaksana..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Tampilkan</button>
                @if ($tahun || $cari)
                    <a href="{{ route('audit-log.surat-tugas') }}"
                       class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
        @foreach ([
            ['label' => 'Nomor Surat Tugas', 'nilai' => $jumlahNomor, 'warna' => 'bg-indigo-100 text-indigo-700'],
            ['label' => 'Berdokumen', 'nilai' => $jumlahBerdokumen, 'warna' => 'bg-teal-100 text-teal-700'],
            ['label' => 'Dipakai Bersama', 'nilai' => $jumlahDipakaiBersama, 'warna' => 'bg-amber-100 text-amber-700'],
        ] as $kartu)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <p class="text-2xl font-bold text-slate-800">{{ number_format($kartu['nilai'], 0, ',', '.') }}</p>
                <p class="text-xs mt-1"><span class="inline-block px-2 py-0.5 rounded-full font-semibold {{ $kartu['warna'] }}">{{ $kartu['label'] }}</span></p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                        <th class="px-4 py-3 w-10">No</th>
                        <th class="px-4 py-3">No. Surat Tugas</th>
                        <th class="px-4 py-3">Dokumen</th>
                        <th class="px-4 py-3">Diunggah Pertama</th>
                        <th class="px-4 py-3">Dipakai Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($suratTugas as $i => $baris)
                        <tr class="align-top hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $suratTugas->firstItem() + $i }}</td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-semibold text-slate-800">{!! $pecah($baris['no_tugas']) !!}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($baris['dokumen'])
                                    <a href="{{ $baris['dokumen']['tautan'] }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-600 hover:underline">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        Lihat berkas
                                    </a>
                                    @if ($baris['jumlah_berkas'] > 1)
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $baris['jumlah_berkas'] }} salinan pada usulan &amp; SPD</p>
                                    @endif
                                @else
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Belum ada berkas</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <p class="font-semibold text-slate-700">{{ $baris['pengunggah'] ?? '—' }}</p>
                                {{-- Waktu berkas diunggah — sama dengan yang disebut tombol Cek. --}}
                                <p class="text-[11px] text-slate-400">{{ ($baris['dokumen']['waktu'] ?? $baris['pertama'])?->translatedFormat('d M Y, H:i') ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <ul class="space-y-1">
                                    @foreach ($baris['usulan'] as $usulan)
                                        <li>
                                            @can('melihat-semua-usulan')
                                                <a href="{{ route('usulan.show', $usulan) }}" class="font-semibold text-teal-600 hover:underline whitespace-nowrap">{{ $usulan->no_usulan }}</a>
                                            @else
                                                <span class="font-semibold text-slate-700 whitespace-nowrap">{{ $usulan->no_usulan }}</span>
                                            @endcan
                                            <span class="text-slate-500">· {{ $usulan->user?->nama ?? '—' }}</span>
                                        </li>
                                    @endforeach
                                    @foreach ($baris['spd'] as $spd)
                                        <li>
                                            <a href="{{ route('spd.show', $spd) }}" class="font-semibold text-indigo-600 hover:underline whitespace-nowrap">SPD</a>
                                            <span class="text-slate-500">· {{ $spd->pembuat?->nama ?? '—' }} — {{ $spd->ringkasan }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">Belum ada nomor surat tugas yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$suratTugas" />
    </div>

</div>

@endsection
