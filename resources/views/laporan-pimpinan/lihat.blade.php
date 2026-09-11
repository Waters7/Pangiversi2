@extends('app')

@section('title', 'Konfirmasi Laporan Perjadin')

@section('content')

@php $statusLaporan = $laporan->status(); @endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('laporan-perjadin.index') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Laporan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $usulan->no_usulan }} · {{ $usulan->user?->nama }} · {{ $usulan->lokasi }}
            </p>
        </div>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $statusLaporan->badge() }}">
                {{ $statusLaporan->label() }}
            </span>
            @if ($laporan->sudahSelesai())
                <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                    Unduh Dokumen
                </a>
            @endif
        </div>
    </div>

    <x-flash />

    @if ($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3">
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-5">
            @include('dokumen.partials.isi-laporan')
        </div>

        <div class="space-y-5">

            {{-- Keputusan pimpinan: hanya untuk laporan yang sedang menunggu. --}}
            @if ($laporan->sudahDikirim())
                <div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm p-5"
                     x-data="{ mode: null }">
                    <h3 class="font-bold text-slate-800 text-sm">Keputusan Pimpinan</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Dikirim {{ $usulan->user?->nama }} pada
                        {{ $laporan->dikirim_at->translatedFormat('d F Y H:i') }} WITA.
                        Konfirmasi Anda membuka pelunasan pembayaran perjalanan ini.
                    </p>

                    @php $bolehTandaTangan = auth()->user()->isDirektur(); @endphp

                    @unless ($bolehTandaTangan)
                        <p class="mt-3 text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                            Laporan hanya ditandatangani <strong>Direktur Poltekkes Kemenkes Manado</strong>.
                            Anda dapat membaca dan mengembalikannya untuk revisi.
                        </p>
                    @endunless

                    <div class="grid {{ $bolehTandaTangan ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 mt-4">
                        @if ($bolehTandaTangan)
                        <button type="button" @click="mode = 'konfirmasi'"
                                :class="mode === 'konfirmasi' ? 'ring-2 ring-emerald-300' : ''"
                                class="px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                            Konfirmasi &amp; Tanda Tangan
                        </button>
                        @endif
                        <button type="button" @click="mode = 'kembalikan'"
                                :class="mode === 'kembalikan' ? 'ring-2 ring-red-300' : ''"
                                class="px-3 py-2.5 border border-red-200 bg-white text-red-700 hover:bg-red-50 text-xs font-bold rounded-xl transition">
                            Kembalikan untuk Revisi
                        </button>
                    </div>

                    @if ($bolehTandaTangan)
                    <form x-show="mode === 'konfirmasi'" x-cloak x-transition method="POST"
                          action="{{ route('laporan-perjadin.konfirmasi', $usulan->no_usulan) }}"
                          class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                        @csrf @method('PUT')
                        <label for="catatan-konfirmasi" class="block text-xs font-semibold text-slate-700">
                            Catatan (opsional)
                        </label>
                        <textarea name="catatan" id="catatan-konfirmasi" rows="3" maxlength="1000"
                                  class="w-full px-3 py-2 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition resize-none"
                                  placeholder="Apresiasi atau catatan untuk pelaksana…">{{ old('catatan') }}</textarea>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Tanda tangan Anda terbit sebagai QR pada dokumen laporan, memuat nama
                            dan tanggal konfirmasi, dan laporan terkunci sejak itu.
                        </p>
                        <button type="submit"
                                class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                            Konfirmasi Laporan Ini
                        </button>
                    </form>
                    @endif

                    <form x-show="mode === 'kembalikan'" x-cloak x-transition method="POST"
                          action="{{ route('laporan-perjadin.kembalikan', $usulan->no_usulan) }}"
                          class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                        @csrf @method('PUT')
                        <label for="catatan-revisi" class="block text-xs font-semibold text-slate-700">
                            Arahan revisi <span class="text-red-500">*</span>
                        </label>
                        <textarea name="catatan" id="catatan-revisi" rows="4" maxlength="1000" required
                                  class="w-full px-3 py-2 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-red-300 focus:border-transparent transition resize-none"
                                  placeholder="Bagian mana yang perlu diperbaiki pelaksana…">{{ old('catatan') }}</textarea>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Laporan dibuka kembali untuk pelaksana beserta arahan ini, dan ia
                            mengirim ulang setelah memperbaikinya.
                        </p>
                        <button type="submit"
                                class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition">
                            Kembalikan ke Pelaksana
                        </button>
                    </form>
                </div>
            @elseif ($laporan->sudahDikonfirmasi())
                <div class="bg-emerald-50 rounded-2xl border border-emerald-200 p-5">
                    <h3 class="font-bold text-emerald-800 text-sm">Sudah Dikonfirmasi</h3>
                    <p class="text-xs text-emerald-700 mt-1 leading-relaxed">
                        Ditandatangani {{ $laporan->pimpinan?->nama }} pada
                        {{ $laporan->dikonfirmasi_at->translatedFormat('d F Y H:i') }} WITA.
                    </p>
                    @unless ($usulan->keuangan?->sudahLunas())
                        <form method="POST" action="{{ route('laporan-perjadin.batal-konfirmasi', $usulan->no_usulan) }}"
                              class="mt-3"
                              onsubmit="return confirm('Cabut konfirmasi? Laporan kembali menunggu keputusan dan QR pimpinan yang sudah tercetak tidak lagi sah.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-xs font-bold text-emerald-800 underline hover:text-emerald-900">
                                Cabut konfirmasi
                            </button>
                        </form>
                    @else
                        <p class="text-[11px] text-emerald-600 mt-2">
                            Pelunasan sudah dibayarkan atas dasar konfirmasi ini.
                        </p>
                    @endunless
                </div>
            @elseif ($laporan->perluRevisi())
                <div class="bg-red-50 rounded-2xl border border-red-200 p-5">
                    <h3 class="font-bold text-red-800 text-sm">Sedang Direvisi Pelaksana</h3>
                    <p class="text-xs text-red-700 mt-1 leading-relaxed">
                        Dikembalikan {{ $laporan->dikembalikan_at->translatedFormat('d F Y H:i') }} WITA.
                        Anda akan diberi tahu setelah dikirim ulang.
                    </p>
                </div>
            @else
                <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-700 text-sm">Belum Dikirim</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        {{ $statusLaporan->keterangan() }} Keputusan baru dapat diambil setelah
                        pelaksana mengirimnya.
                    </p>
                </div>
            @endif

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-3">Hasil yang Dicapai</h3>
                @if ($laporan->statusHasil)
                    <span class="inline-block text-sm font-bold px-3 py-1.5 rounded-full {{ $laporan->statusHasil->badge }}">
                        {{ $laporan->statusHasil->nama }}
                    </span>
                    @if ($laporan->statusHasil->keterangan)
                        <p class="text-xs text-slate-400 mt-2">{{ $laporan->statusHasil->keterangan }}</p>
                    @endif
                @else
                    <p class="text-sm text-slate-400">Belum dipilih.</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Keterangan</h3>
                @include('dokumen.partials.keterangan-laporan')
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-3">Perjalanan Dinas</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-slate-400">Surat Tugas</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->no_tugas ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Tanggal</dt>
                        <dd class="font-semibold text-slate-700">
                            {{ \Carbon\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d M') }}–{{ \Carbon\Carbon::parse($usulan->tanggal_selesai)->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Unit Kerja</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->user?->unit?->nama ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Pembayaran</dt>
                        <dd class="font-semibold text-slate-700">
                            {{ $usulan->keuangan?->sudahLunas() ? 'Sudah dilunasi' : 'Belum dilunasi' }}
                        </dd>
                    </div>
                </dl>
                <a href="{{ route('usulan.show', $usulan->no_usulan) }}"
                   class="inline-block mt-3 text-xs font-bold text-teal-600 hover:text-teal-700">
                    Lihat usulan perjadin →
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
