
@extends('keuangan.keuangan-detail')

@section('state')

@include('keuangan.form-keuangan')

{{-- ── UANG MUKA: SUDAH DIBAYAR ── --}}
<div class="mb-6">
    <div class="bg-white rounded-2xl border-2 border-teal-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-teal-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Uang Muka Sudah Dibayar</h3>
                    <p class="text-xs text-slate-400">Menunggu LPJ & pembayaran sisa 20%</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 text-teal-700 border border-teal-200 text-xs font-bold rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Sudah Dibayar
            </span>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Tanggal Transfer</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $usulan->keuangan->tanggal_transfer?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Nominal Ditransfer (80%)</p>
                    <p class="text-sm font-bold text-teal-700">Rp {{ number_format($usulan->keuangan->uang_muka, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Bukti Transfer</p>
                    @if($usulan->keuangan->dokumenKeuangan?->transfer_uang_muka)
                        <a href="{{ asset('storage/' . $usulan->keuangan->dokumenKeuangan->transfer_uang_muka) }}" target="_blank"
                           class="text-sm text-teal-600 font-semibold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13A9 9 0 1112 3"/></svg>
                            Lihat File
                        </a>
                    @else
                        <span class="text-sm text-slate-400">—</span>
                    @endif
                </div>
            </div>
            <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <span>Uang muka telah dibayarkan. Pastikan dokumen LPJ sudah lengkap sebelum memproses pembayaran sisa.</span>
            </div>
        </div>
    </div>
</div>

{{-- ── FORM BAYAR SISA ── --}}
<div class="mb-6">
    <div class="bg-white rounded-2xl border-2 border-blue-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-blue-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Proses Pembayaran Sisa</h3>
                    <p class="text-xs text-slate-400">Transfer sisa 20% setelah LPJ lengkap & terverifikasi</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-center">
                    <p class="text-xs text-blue-600 mb-1 font-medium">20% — Sisa Bayar</p>
                    <p class="text-xl font-bold text-blue-700">Rp {{ number_format($usulan->keuangan->sisa, 0, ',', '.') }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-xs text-slate-500 mb-1">Total Keseluruhan</p>
                    <p class="text-xl font-bold text-slate-800">Rp {{ number_format($usulan->keuangan->total, 0, ',', '.') }}</p>
                </div>
            </div>
            <form action="{{ route('keuangan.bayar-sisa', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Pelunasan <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_pelunasan" required value="{{ old('tanggal_pelunasan') }}"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('tanggal_pelunasan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Upload Bukti Pelunasan <span class="text-red-500">*</span></label>
                        <input type="file" name="bukti_pelunasan" required accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition
                                      file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700">
                        @error('bukti_pelunasan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit"
                            class="px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold rounded-xl transition flex items-center gap-2 shadow-sm shadow-blue-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Konfirmasi Pelunasan Sisa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
