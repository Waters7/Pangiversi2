@extends('app')

@section('title', 'Transport Lokal')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Penggantian Transport Lokal</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Nominalnya dari Daftar Pengeluaran Riil yang sudah ditandatangani PPK, ditransfer
            terpisah dari pelunasan. Yang belum dibayarkan di sini tetap ikut pada pelunasan.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-bold text-amber-700 truncate">Rp {{ number_format($totalMenunggu, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 font-medium">Menunggu dibayarkan</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-bold text-emerald-700 truncate">Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-500 font-medium">Terbayar pada saringan ini</p>
            </div>
        </div>
    </div>

    <x-tab-status
        :aksi="route('pembayaran.transport-lokal')"
        kunci="tahap"
        :terpilih="$tahap"
        :tab="[
            '' => ['label' => 'Semua', 'jumlah' => array_sum($jumlah)],
            'menunggu' => ['label' => 'Menunggu Dibayar', 'jumlah' => $jumlah['menunggu'], 'badge' => 'bg-amber-100 text-amber-700'],
            'terbayar' => ['label' => 'Terbayar', 'jumlah' => $jumlah['terbayar'], 'badge' => 'bg-emerald-100 text-emerald-700'],
        ]" />

    <x-saring-periode
        :aksi="route('pembayaran.transport-lokal')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['tahap' => $tahap, 'search' => $search]" />

    <form method="GET" action="{{ route('pembayaran.transport-lokal') }}" class="mb-5">
        <input type="hidden" name="tahap" value="{{ $tahap }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="flex gap-2">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari no. usulan atau nama pelaksana..."
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
            @if ($search)
                <a href="{{ route('pembayaran.transport-lokal', ['tahap' => $tahap, 'tahun' => $tahun, 'bulan' => $bulan]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Pelaksana</th>
                        <th class="px-5 py-3 font-semibold">No. Perjadin</th>
                        <th class="px-5 py-3 font-semibold text-right">Nominal</th>
                        <th class="px-5 py-3 font-semibold">Tanggal Bayar</th>
                        <th class="px-5 py-3 font-semibold">Bukti</th>
                        <th class="px-5 py-3 font-semibold text-right w-40">Aksi</th>
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
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $item->usulan?->lokasi }}</p>
                                    @if ($item->usulan?->user?->punyaRekening())
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            {{ $item->usulan->user->nama_bank }}
                                            <span class="font-mono">{{ $item->usulan->user->nomor_rekening }}</span>
                                        </p>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5 font-mono text-xs text-slate-600">{{ $item->usulan?->no_usulan ?? '—' }}</td>

                                <td class="px-5 py-3.5 text-right font-bold text-slate-800 whitespace-nowrap">
                                    Rp {{ number_format($item->total_riil, 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    @if ($item->sudahDibayar())
                                        <p class="text-slate-700 font-medium">{{ $item->dibayar_at->translatedFormat('d M Y') }}</p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $item->pembayar?->nama }}</p>
                                    @else
                                        <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                            Belum dibayar
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    @if ($item->bukti_bayar)
                                        <a href="{{ route('berkas.lihat', $item->bukti_bayar) }}"
                                           target="_blank" rel="noopener"
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

                                <td class="px-5 py-3.5 text-right">
                                    @can('mencatat-pembayaran')
                                        @if ($item->sudahDibayar())
                                            <x-batal-pembayaran
                                                :aksi="route('pembayaran.batal-transport', $item)"
                                                :nama="'batal-transport-'.$item->id"
                                                judul="Batalkan pencatatan penggantian transport lokal?"
                                                tombol="Batalkan"
                                                :ringkas="($item->peserta?->nama ?? '—').' · Rp '.number_format($item->total_riil, 0, ',', '.')" />
                                        @else
                                            <x-bayar-transport :daftar="$item" />
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Tidak ada penggantian pada saringan ini</p>
                                <p class="text-xs text-slate-400 mt-1.5 max-w-md mx-auto">
                                    Daftar muncul setelah PPK menandatangani daftar pengeluaran riil pelaksana.
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
