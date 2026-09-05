@extends('app')

@section('title', 'Laporan — List Daftar Riil')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">List Daftar Riil</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Daftar pengeluaran riil seluruh pelaksana. Berkas dapat diunduh setelah
            ditandatangani pelaksana dan PPK.
        </p>
    </div>

    <form method="GET" action="{{ route('laporan.daftar-riil') }}" class="mb-5">
        <input type="hidden" name="tahap" value="{{ $tahap }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="cari" value="{{ $cari }}"
                   placeholder="Cari nama pelaksana, no. usulan, atau no. surat tugas"
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit"
                    class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                Cari
            </button>
            @if ($cari)
                <a href="{{ route('laporan.daftar-riil', ['tahap' => $tahap, 'tahun' => $tahun, 'bulan' => $bulan]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">
                    Reset
                </a>
            @endif
        </div>
    </form>

    @php
        $tabTahap = ['' => ['label' => 'Semua', 'jumlah' => array_sum($jumlah)]];

        foreach ($labelTahap as $kunci => $teks) {
            $tabTahap[$kunci] = [
                'label' => $teks,
                'jumlah' => $jumlah[$kunci] ?? 0,
                'badge' => match ($kunci) {
                    'keuangan' => 'bg-blue-100 text-blue-700',
                    'pelaksana' => 'bg-amber-100 text-amber-700',
                    'disanggah' => 'bg-red-100 text-red-700',
                    'ppk' => 'bg-teal-100 text-teal-700',
                    default => 'bg-emerald-100 text-emerald-700',
                },
            ];
        }
    @endphp

    <x-tab-status
        :aksi="route('laporan.daftar-riil')"
        kunci="tahap"
        :terpilih="$tahap"
        :tab="$tabTahap" />

    <x-saring-periode
        :aksi="route('laporan.daftar-riil')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['tahap' => $tahap, 'cari' => $cari]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Pelaksana</th>
                        <th class="px-5 py-3 font-semibold">No. Usulan</th>
                        <th class="px-5 py-3 font-semibold">Surat Tugas</th>
                        <th class="px-5 py-3 font-semibold text-right">Total Riil</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold w-32"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftar as $periode => $kelompok)
                        <tr class="bg-slate-100/70">
                            <td colspan="6" class="px-5 py-2">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $periode }}</span>
                                <span class="ml-2 text-[11px] font-bold text-slate-400">
                                    {{ $kelompok->count() }} berkas ·
                                    Rp {{ number_format($kelompok->sum(fn ($b) => $b['berkas']->total_riil), 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>

                        @foreach ($kelompok as $baris)
                        @php $item = $baris['berkas']; @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $item->peserta?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $item->peserta?->nip }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $item->usulan?->no_usulan ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $item->usulan?->no_tugas ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-800">
                                Rp {{ number_format($item->total_riil, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->status_badge }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($item->sudah_ditandatangani)
                                    <a href="{{ route('daftar-riil.cetak', [$item->usulan, $item->peserta]) }}"
                                       class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                        </svg>
                                        Unduh
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">Belum lengkap</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Tidak ada daftar pada saringan ini</p>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Daftar muncul begitu pelaksana mengisi nota transportasinya.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>


</div>

@endsection
