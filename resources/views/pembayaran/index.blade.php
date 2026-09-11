@extends('app')

@section('title', 'Pembayaran')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Pembayaran Perjalanan Dinas</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Pantau tahap pembayaran tiap perjalanan dinas — dari yang menunggu transfer sampai lunas
        </p>
    </div>

    {{-- Ringkasan nilai --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        @php
            $kartu = [
                ['Total Anggaran', $nilai['anggaran'], 'bg-slate-100 text-slate-700', 'text-slate-800'],
                ['Sudah Dibayarkan', $nilai['terbayar'], 'bg-emerald-50 text-emerald-700', 'text-emerald-700'],
                ['Belum Dibayarkan', $nilai['sisa'], 'bg-amber-50 text-amber-700', 'text-amber-700'],
            ];
        @endphp

        @foreach ($kartu as [$judul, $angka, $gayaIkon, $gayaAngka])
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl {{ $gayaIkon }} flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-bold {{ $gayaAngka }} truncate">Rp {{ number_format($angka, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 font-medium">{{ $judul }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tahap pembayaran --}}
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
        @foreach ($daftarTahap as $kunci => $label)
            <a href="{{ route('pembayaran', ['tahap' => $kunci, 'search' => $search, 'tahun' => $tahun, 'bulan' => $bulan]) }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                      {{ $tahap === $kunci
                            ? 'bg-teal-500 text-white shadow-sm shadow-teal-200'
                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
                <span class="text-xs font-bold px-2 py-0.5 rounded-full
                             {{ $tahap === $kunci ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">
                    {{ $jumlah[$kunci] }}
                </span>
            </a>
        @endforeach
    </div>

    <x-saring-periode
        :aksi="route('pembayaran')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['tahap' => $tahap, 'search' => $search]" />

    {{-- Pencarian --}}
    <form method="GET" action="{{ route('pembayaran') }}" class="mb-5">
        <input type="hidden" name="tahap" value="{{ $tahap }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari no. usulan, pegawai, atau kota tujuan..."
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Search</button>
            @if ($search)
                <a href="{{ route('pembayaran', ['tahap' => $tahap, 'tahun' => $tahun, 'bulan' => $bulan]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
            @endif
        </div>
    </form>

    {{-- Daftar --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                {{ $daftarTahap[$tahap] }}
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ $usulan->total() }} perjadin
                </span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-6 py-3.5">Pegawai</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Perjalanan</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Anggaran</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Uang Muka</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Sisa 20%</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Status</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3.5">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-50">
                    @php $bulanBerjalan = null; @endphp

                    @forelse ($usulan as $item)
                        @php $k = $item->keuangan; @endphp

                        {{-- Judul bulan disisipkan tiap kali bulan keberangkatannya
                             berganti, agar bendahara membaca daftarnya per periode. --}}
                        @php
                            $mulai = $item->tanggal_mulai ? \Carbon\Carbon::parse($item->tanggal_mulai) : null;
                            $bulanBaris = $mulai?->format('Y-m');
                        @endphp

                        @if ($bulanBaris !== $bulanBerjalan)
                            @php $bulanBerjalan = $bulanBaris; @endphp
                            <tr class="bg-slate-100/70">
                                <td colspan="7" class="px-6 py-2">
                                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">
                                        {{ $mulai?->translatedFormat('F Y') ?? 'Tanpa Tanggal' }}
                                    </span>
                                </td>
                            </tr>
                        @endif

                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <x-avatar :nama="$item->user?->nama" :foto="$item->user?->url_foto" ukuran="sm" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-800 truncate">{{ $item->user?->nama ?? '—' }}</p>
                                        <p class="text-xs text-slate-400 truncate">{{ $item->user?->unit?->nama ?? '—' }}</p>

                                        {{-- Rekening tujuan transfer, agar bendahara tidak
                                             berpindah layar saat hendak membayar. --}}
                                        @if ($item->user?->punyaRekening())
                                            <p class="text-xs text-slate-500 truncate mt-0.5">
                                                {{ $item->user->nama_bank }}
                                                <span class="font-mono">{{ $item->user->nomor_rekening }}</span>
                                            </p>
                                            <p class="text-[11px] text-slate-400 truncate">a.n. {{ $item->user->nama_rekening }}</p>
                                        @else
                                            <p class="text-xs font-semibold text-amber-600 truncate mt-0.5">Rekening belum dilengkapi</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <p class="font-semibold text-slate-700">{{ $item->no_usulan }}</p>
                                <p class="text-xs text-slate-500">{{ $item->lokasi }} · {{ $item->periode }}</p>
                                @if ($item->kategoriPerjadin)
                                    <span class="inline-block mt-1 text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->kategoriPerjadin->badge }}">
                                        {{ $item->kategoriPerjadin->nama }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-4 text-right font-semibold text-slate-800 whitespace-nowrap">
                                Rp {{ number_format($k?->total ?? 0, 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <p class="{{ $k?->uangMukaTerbayar() ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                                    Rp {{ number_format($k?->uang_muka ?? 0, 0, ',', '.') }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ $k?->tanggal_transfer?->translatedFormat('d M Y') ?? 'belum ditransfer' }}
                                </p>
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <p class="{{ $k?->sudahLunas() ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                                    Rp {{ number_format($k?->sisa ?? 0, 0, ',', '.') }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ $k?->tanggal_pelunasan?->translatedFormat('d M Y') ?? 'belum dilunasi' }}
                                </p>
                            </td>

                            <td class="px-4 py-4 text-center">
                                <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $k?->status_badge ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ $k?->status_label ?? 'Belum Ada' }}
                                </span>

                                {{-- Kelengkapan berkas menentukan boleh tidaknya dilunasi --}}
                                @if ($item->berkas_lengkap)
                                    <span class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700"
                                          title="Seluruh berkas pertanggungjawaban sudah diunggah">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Dokumen lengkap
                                    </span>
                                @else
                                    <span class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700"
                                          title="Kurang: {{ implode(', ', $item->berkas_kurang) }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                                        </svg>
                                        Kurang {{ count($item->berkas_kurang) }} berkas
                                    </span>
                                @endif

                                @if ($k?->sudahDikonfirmasiBayar())
                                    <p class="text-[11px] font-mono text-emerald-700 mt-1">{{ $k->kode_konfirmasi_bayar }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('keuangan.detail', $item->no_usulan) }}"
                                       title="Kelola pembayaran"
                                       class="p-2 rounded-lg hover:bg-teal-50 text-slate-400 hover:text-teal-600 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('keuangan.cetak-rincian', $item->no_usulan) }}"
                                       title="Cetak rincian biaya"
                                       class="p-2 rounded-lg hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center">
                                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                                </svg>
                                <p class="text-sm text-slate-400">Tidak ada perjalanan dinas pada tahap ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usulan->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $usulan->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
