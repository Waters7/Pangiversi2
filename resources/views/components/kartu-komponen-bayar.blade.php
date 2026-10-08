@props(['ringkasan'])

{{-- Kartu ringkasan komponen yang dibayarkan, untuk bendahara: dari mana
     uang muka dan sisa bayar tersusun, dan apa yang belum ikut terhitung. --}}
@php
    $rupiah = fn (float $nilai) => 'Rp '.number_format($nilai, 0, ',', '.');
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6" data-kartu-komponen-bayar>
    <div class="rounded-xl border border-slate-200 p-3.5">
        <p class="text-[11px] font-semibold text-slate-500">Uang harian</p>
        <p class="text-base font-bold text-slate-800 tabular-nums">{{ $rupiah($ringkasan['uang_harian']) }}</p>
        <p class="text-[11px] text-slate-400 mt-1 leading-snug">
            80% di muka {{ $rupiah($ringkasan['uang_harian_di_muka']) }} · 20% pelunasan {{ $rupiah($ringkasan['uang_harian_pelunasan']) }}
        </p>
    </div>

    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3.5">
        <p class="text-[11px] font-semibold text-amber-700">Dibayarkan lewat uang muka</p>
        <p class="text-base font-bold text-amber-800 tabular-nums">{{ $rupiah($ringkasan['di_muka']) }}</p>
        <p class="text-[11px] text-amber-700/80 mt-1">{{ $ringkasan['jumlah_di_muka'] }} komponen selain uang harian</p>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-3.5">
        <p class="text-[11px] font-semibold text-blue-700">Penggantian saat pelunasan</p>
        <p class="text-base font-bold text-blue-800 tabular-nums">{{ $rupiah($ringkasan['penggantian']) }}</p>
        <p class="text-[11px] text-blue-700/80 mt-1">{{ $ringkasan['jumlah_penggantian'] }} komponen dibayar pelaksana dahulu</p>
    </div>

    <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-3.5">
        <p class="text-[11px] font-semibold text-indigo-700">Transport lokal</p>
        <p class="text-base font-bold text-indigo-800 tabular-nums">{{ $rupiah($ringkasan['transport_lokal']) }}</p>
        <p class="text-[11px] text-indigo-700/80 mt-1">Daftar riil, dibayar saat pelunasan</p>
    </div>

    <div class="rounded-xl border p-3.5 {{ $ringkasan['menunggu']->isNotEmpty() ? 'border-red-200 bg-red-50/60' : 'border-slate-200' }}">
        <p class="text-[11px] font-semibold {{ $ringkasan['menunggu']->isNotEmpty() ? 'text-red-700' : 'text-slate-500' }}">Menunggu validasi</p>
        <p class="text-base font-bold tabular-nums {{ $ringkasan['menunggu']->isNotEmpty() ? 'text-red-800' : 'text-slate-800' }}">{{ $rupiah($ringkasan['nominal_menunggu']) }}</p>
        <p class="text-[11px] mt-1 {{ $ringkasan['menunggu']->isNotEmpty() ? 'text-red-700/80' : 'text-slate-400' }}">
            {{ $ringkasan['menunggu']->count() }} baris — belum dihitung
        </p>
    </div>
</div>

@if ($ringkasan['belum_dikonfirmasi'] > 0)
    <p class="mb-5 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
        Cara bayar {{ $ringkasan['belum_dikonfirmasi'] }} komponen belum dikonfirmasi tim keuangan — sementara dihitung masuk uang muka.
        Lihat tabel <strong>Status Pembayaran Komponen</strong>.
    </p>
@endif
