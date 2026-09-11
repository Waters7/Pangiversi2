@extends('app')

@section('title', 'Detail Keuangan')

@section('content')

    <div class="flex-1 px-4 md:px-8 py-7">


        {{-- Page Header --}}
        <div class="mb-6 flex items-center gap-3">
            <a href="{{ route('keuangan') }}"
            class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Keuangan & LPJ</h1>
                <p class="text-xs text-slate-400 mt-0.5">Rincian biaya, pembayaran, verifikasi bukti, dan daftar nominatif</p>
            </div>
        </div>

        <x-mode-lihat-keuangan />

        {{-- Banner Selesai --}}
        @if($usulan->status === 'selesai')
            <div class="mb-5 flex items-center gap-3 px-5 py-3 bg-purple-50 border border-purple-200 rounded-xl">
                <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm text-purple-700 font-semibold">
                    Usulan ini telah <strong>Selesai</strong>.
                    @if(auth()->user()->isAdmin())
                        Sebagai administrator, Anda tetap dapat melakukan koreksi.
                    @else
                        Data keuangan tidak dapat diubah lagi.
                    @endif
                </p>
            </div>
        @endif

        @php
            $keuangan = $usulan->keuangan;
            $statusColor = match($keuangan->status) {
                'lunas' => 'emerald',
                'bayar sebagian' => 'blue',
                default => 'amber',
            };
            $statusLabel = match($keuangan->status) {
                'lunas' => 'Lunas',
                'bayar sebagian' => 'Bayar Sebagian',
                default => 'Belum Bayar',
            };
        @endphp

        {{-- Info Usulan --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-4 flex flex-wrap items-center gap-x-6 gap-y-2">
            <div>
                <p class="text-xs text-slate-400">No. Usulan</p>
                <p class="text-sm font-bold text-slate-800 font-mono">{{ $usulan->no_usulan }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Pengusul</p>
                <p class="text-sm font-semibold text-slate-700">{{ $usulan->user->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Tujuan</p>
                <p class="text-sm font-semibold text-slate-700">{{ $usulan->tujuan }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Periode</p>
                <p class="text-sm font-semibold text-slate-700">{{ $usulan->periode }}</p>
            </div>

            {{-- Rekening tujuan transfer ditampilkan di sini supaya bendahara
                 tidak perlu membuka profil pegawai saat hendak membayar. --}}
            <div>
                <p class="text-xs text-slate-400">Rekening Pelaksana</p>
                @if ($usulan->user?->punyaRekening())
                    <p class="text-sm font-semibold text-slate-700">{{ $usulan->user->nama_bank }}</p>
                    <p class="text-xs font-mono text-slate-500">{{ $usulan->user->nomor_rekening }} a.n. {{ $usulan->user->nama_rekening }}</p>
                @else
                    <p class="text-sm font-semibold text-amber-700">Belum dilengkapi</p>
                    <p class="text-xs text-amber-600">Pelaksana perlu mengisinya pada menu Profil.</p>
                @endif
            </div>
            <div class="ml-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-{{ $statusColor }}-50 text-{{ $statusColor }}-700 border border-{{ $statusColor }}-200 text-xs font-bold rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-{{ $statusColor }}-500"></span> {{ $statusLabel }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 font-medium">Total Estimasi</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 font-medium">Uang Muka</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 font-medium">Sisa Bayar</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800">{{ $keuangan->rincianBiaya->count() }}</p>
                    <p class="text-xs text-slate-500 font-medium">Komponen Biaya</p>
                </div>
            </div>
        </div> 

        @yield('state')

    </div>

@endsection

