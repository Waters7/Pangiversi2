
@extends('keuangan.keuangan-detail')


@section('state')

@include('keuangan.form-keuangan')

{{-- ── PEMBAYARAN LUNAS ── --}}
<div class="mb-6">
    <div class="bg-white rounded-2xl border-2 border-emerald-300 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-emerald-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Pembayaran Lunas</h3>
                    <p class="text-xs text-slate-400">Seluruh biaya perjalanan dinas telah dibayarkan</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Lunas
            </span>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Tanggal Uang Muka</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $usulan->keuangan->tanggal_transfer?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Tanggal Pelunasan</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $usulan->keuangan->tanggal_pelunasan?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Total Dibayarkan</p>
                    <p class="text-sm font-bold text-emerald-700">Rp {{ number_format($usulan->keuangan->total, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Bukti Transfer</p>
                    <div class="flex items-center gap-3">
                        @if($usulan->keuangan->dokumenKeuangan?->transfer_uang_muka)
                            <a href="{{ asset('storage/' . $usulan->keuangan->dokumenKeuangan->transfer_uang_muka) }}" target="_blank"
                               class="text-xs text-emerald-600 font-semibold hover:underline">UM</a>
                        @endif
                        @if($usulan->keuangan->dokumenKeuangan?->transfer_sisa)
                            <a href="{{ asset('storage/' . $usulan->keuangan->dokumenKeuangan->transfer_sisa) }}" target="_blank"
                               class="text-xs text-emerald-600 font-semibold hover:underline">Sisa</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-start gap-3 p-4 bg-emerald-50 border border-emerald-100 rounded-xl text-sm text-emerald-700">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Pembayaran telah lunas. Uang muka <strong>Rp {{ number_format($usulan->keuangan->uang_muka, 0, ',', '.') }}</strong> dan sisa bayar <strong>Rp {{ number_format($usulan->keuangan->sisa, 0, ',', '.') }}</strong> telah berhasil ditransfer.</span>
            </div>

            @can('mencatat-pembayaran')
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-batal-pembayaran
                        :aksi="route('keuangan.batal-pelunasan', $usulan->no_usulan)"
                        :nama="'batal-lunas-'.$usulan->id"
                        judul="Batalkan pencatatan pelunasan?"
                        :ringkas="$usulan->no_usulan.' · Rp '.number_format($usulan->keuangan->nilaiPelunasan(), 0, ',', '.')" />
                    <p class="text-xs text-slate-400">Kode konfirmasi bendahara pada dokumen ikut dicabut.</p>
                </div>
            @endcan
        </div>
    </div>
</div>

@endsection

