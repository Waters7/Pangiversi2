@extends('app')

@section('title', 'Riwayat Pembayaran')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Riwayat Pembayaran</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Jurnal setiap pembayaran yang dicatat bendahara — uang muka, pelunasan, dan
            pembatalannya — dikelompokkan menurut bulan pembayarannya.
        </p>
    </div>

    {{-- Nilai bersih: pembatalan mengurangi, bukan menambah. --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-lg font-bold text-emerald-700 truncate">
                Rp {{ number_format($totalTerbayar, 0, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500 font-medium">Nilai bersih pada saringan ini</p>
        </div>
    </div>

    @php
        $tabJenis = ['' => ['label' => 'Semua', 'jumlah' => array_sum($jumlahJenis)]];

        foreach ($daftarJenis as $kunci => $teks) {
            $tabJenis[$kunci] = [
                'label' => $teks,
                'jumlah' => $jumlahJenis[$kunci] ?? 0,
                'badge' => match ($kunci) {
                    \App\Models\RiwayatPembayaran::JENIS_UANG_MUKA => 'bg-blue-100 text-blue-700',
                    \App\Models\RiwayatPembayaran::JENIS_PELUNASAN => 'bg-emerald-100 text-emerald-700',
                    default => 'bg-red-100 text-red-700',
                },
            ];
        }
    @endphp

    <x-tab-status
        :aksi="route('pembayaran.riwayat')"
        kunci="jenis"
        :terpilih="$jenis"
        :tab="$tabJenis" />

    <x-saring-periode
        :aksi="route('pembayaran.riwayat')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['jenis' => $jenis, 'search' => $search]" />

    <form method="GET" action="{{ route('pembayaran.riwayat') }}" class="mb-5">
        <input type="hidden" name="jenis" value="{{ $jenis }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="flex gap-2">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari no. usulan atau nama pelaksana..."
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
            @if ($search)
                <a href="{{ route('pembayaran.riwayat', ['jenis' => $jenis, 'tahun' => $tahun, 'bulan' => $bulan]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Tanggal</th>
                        <th class="px-5 py-3 font-semibold">Perjalanan</th>
                        <th class="px-5 py-3 font-semibold">Jenis</th>
                        <th class="px-5 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-5 py-3 font-semibold">Dicatat Oleh</th>
                        <th class="px-5 py-3 font-semibold text-right w-24">Bukti</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($riwayat as $periode => $kelompok)
                        <tr class="bg-slate-100/70">
                            <td colspan="6" class="px-5 py-2">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $periode }}</span>
                                <span class="ml-2 text-[11px] font-bold text-slate-400">
                                    {{ $kelompok->count() }} transaksi ·
                                    Rp {{ number_format($kelompok->sum(fn ($b) => $b->nilaiArus()), 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>

                        @foreach ($kelompok as $baris)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap text-slate-600">
                                    {{ $baris->tanggal?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-slate-800">{{ $baris->usulan?->no_usulan ?? '—' }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $baris->usulan?->user?->nama ?? '—' }}</p>
                                    @if ($baris->catatan)
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $baris->catatan }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full {{ $baris->jenis_badge }}">
                                        {{ $baris->jenis_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold whitespace-nowrap {{ $baris->pembatalan() ? 'text-red-600' : 'text-slate-800' }}">
                                    {{ $baris->pembatalan() ? '−' : '' }}Rp {{ number_format($baris->nominal, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    {{ $baris->pencatat?->nama ?? 'Sistem' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if ($baris->bukti)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($baris->bukti) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-teal-600 hover:text-teal-700">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Belum ada pembayaran pada saringan ini</p>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Riwayat terisi sendiri setiap kali bendahara mencatat uang muka atau pelunasan.
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
