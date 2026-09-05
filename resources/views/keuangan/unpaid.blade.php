
@extends('keuangan.keuangan-detail')

@section('state')

@include('keuangan.form-keuangan')

{{-- ── KONFIRMASI PEMBAYARAN UANG MUKA ── --}}
@if($usulan->keuangan->rincianBiaya->isNotEmpty())
<div class="mb-6">
    <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Konfirmasi Pembayaran Uang Muka</h3>
                    <p class="text-xs text-slate-400">Transfer 80% dari estimasi biaya sebelum keberangkatan</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum Dibayar
            </span>
        </div>
        <div class="p-6">
            {{-- Kalkulasi --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-500 mb-1">Total Estimasi</p>
                    <p class="text-xl font-bold text-slate-800">Rp {{ number_format($usulan->keuangan->total, 0, ',', '.') }}</p>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                    <p class="text-xs text-amber-600 mb-1 font-medium">80% — Uang Muka</p>
                    <p class="text-xl font-bold text-amber-700">Rp {{ number_format($usulan->keuangan->uang_muka, 0, ',', '.') }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-500 mb-1">Sisa Bayar</p>
                    <p class="text-xl font-bold text-slate-600">Rp {{ number_format($usulan->keuangan->sisa, 0, ',', '.') }}</p>
                </div>
            </div>
            {{-- Form Upload — bukti pembayaran hanya untuk bendahara --}}
            @cannot('mencatat-pembayaran')
                <p class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500">
                    Pencatatan pembayaran uang muka beserta bukti transfernya hanya dapat dilakukan bendahara.
                </p>
            @endcannot

            @can('mencatat-pembayaran')
            <form action="{{ route('keuangan.bayar-uang-muka', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transfer <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_transfer" required value="{{ old('tanggal_transfer') }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        @error('tanggal_transfer') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                        <input type="file" name="bukti_transfer" required accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition
                                      file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700">
                        @error('bukti_transfer') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit"
                            class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition flex items-center gap-2 shadow-sm shadow-amber-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Konfirmasi Pembayaran Uang Muka
                    </button>
                </div>
            </form>
            @endcan
        </div>
    </div>
</div>
@endif

@endsection

