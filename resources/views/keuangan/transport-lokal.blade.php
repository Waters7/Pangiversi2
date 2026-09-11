@extends('app')

@section('title', 'Keuangan — Transport Lokal')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Transport Lokal</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Dinyatakan pelaksana pada Daftar Pengeluaran Riil (Lampiran IX), di luar rincian biaya.
            Dibayarkan sebagai penggantian saat pelunasan.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
                </svg>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-800">Rp {{ number_format($totalTerkunci, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400">Sudah ditandatangani — wajib diganti</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-800">Rp {{ number_format($totalBerjalan, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400">Masih berjalan — nominalnya dapat berubah</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('keuangan.transport-lokal') }}" class="mb-5">
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="cari" value="{{ $cari }}"
                   placeholder="Cari nama pelaksana, no. usulan, atau no. surat tugas"
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit"
                    class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                Search
            </button>
            @if ($cari)
                <a href="{{ route('keuangan.transport-lokal') }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">
                    Reset
                </a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Pelaksana</th>
                        <th class="px-5 py-3 font-semibold">No. Usulan</th>
                        <th class="px-5 py-3 font-semibold">Ruas &amp; Nota</th>
                        <th class="px-5 py-3 font-semibold text-right">Total</th>
                        <th class="px-5 py-3 font-semibold text-center w-36">Validasi</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold w-28"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftar as $item)
                        <tr class="hover:bg-slate-50/60 transition align-top">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $item->peserta?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $item->peserta?->nip }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ $item->usulan?->no_usulan ?? '—' }}
                                <span class="block text-xs text-slate-400 mt-0.5">{{ $item->usulan?->no_tugas }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @php
                                    // Nota per ruas ditautkan dari usulannya, dicocokkan
                                    // lewat kunci sumber baris daftar riil.
                                    $nota = $item->usulan?->notaTransport?->keyBy('urutan') ?? collect();
                                @endphp

                                @forelse ($item->rincian as $baris)
                                    @php
                                        $urutan = (int) str_replace('nota:', '', (string) $baris->kunci_sumber);
                                        $bukti = $nota->get($urutan)?->bukti;
                                    @endphp

                                    <div class="flex items-center gap-2 mb-0.5">
                                        <p class="text-xs text-slate-600">
                                            {{ $baris->uraian }}
                                            <span class="text-slate-400">· Rp {{ number_format($baris->nominal, 0, ',', '.') }}</span>
                                        </p>

                                        @if ($bukti)
                                            <a href="{{ Storage::url($bukti) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 bg-white border border-slate-200 hover:border-teal-300 text-slate-500 hover:text-teal-700 text-[10px] font-bold rounded transition"
                                               title="Buka nota {{ $baris->uraian }} di tab baru">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                                </svg>
                                                Nota
                                            </a>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-xs text-slate-400">Belum dirinci</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-800">
                                Rp {{ number_format($item->total_riil, 0, ',', '.') }}
                            </td>

                            {{-- Diperiksa tim keuangan sebelum berjalan ke pelaksana
                                 lalu ke PPK. --}}
                            <td class="px-5 py-3.5 text-center">
                                @if ($item->sudah_ditandatangani)
                                    <span class="text-[11px] text-slate-400">Sudah final</span>
                                @elseif ($item->divalidasi_at)
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                            Sudah divalidasi
                                        </span>
                                        @can('mengelola-biaya')
                                            {{-- Mencabut validasi menahan berkas lagi, jadi ditanya ulang sekali. --}}
                                            <x-konfirmasi-validasi
                                                :nama="'validasi-transport-'.$item->id"
                                                :aksi="route('daftar-riil.batal-validasi', [$item->usulan, $item->peserta])"
                                                metode="DELETE"
                                                :tervalidasi="true"
                                                :komponen="'Transport lokal '.($item->peserta?->nama ?? '')"
                                                :nominal="(float) $item->total_riil" />
                                        @endcan
                                    </div>
                                @else
                                    @can('mengelola-biaya')
                                        {{-- Validasi menyatakan notanya sudah diperiksa dan dapat
                                             menggerakkan berkas ke pelaksana, jadi ditanya ulang sekali. --}}
                                        <x-konfirmasi-validasi
                                            :nama="'validasi-transport-'.$item->id"
                                            :aksi="route('daftar-riil.validasi', [$item->usulan, $item->peserta])"
                                            metode="PUT"
                                            :tervalidasi="false"
                                            :komponen="'Transport lokal '.($item->peserta?->nama ?? '')"
                                            :nominal="(float) $item->total_riil" />
                                    @else
                                        <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                                            Belum diperiksa
                                        </span>
                                    @endcan
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->status_badge }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('daftar-riil.show', $item->usulan) }}"
                                   class="text-xs font-semibold text-teal-600 hover:underline">Detail →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Belum ada transport lokal tercatat</p>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Nominalnya muncul begitu pelaksana mengisi nota transportasi pada menu Dokumen.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($daftar->hasPages())
        <div class="mt-5">{{ $daftar->links() }}</div>
    @endif

</div>

@endsection
