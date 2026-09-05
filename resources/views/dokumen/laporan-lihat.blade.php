@extends('app')

@section('title', 'Laporan Perjalanan Dinas')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dokumen.laporan.index') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Laporan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }}</p>
        </div>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            @if ($laporan->sudahSelesai())
                <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                    Unduh Dokumen
                </a>
            @endif
            @if ($laporan->bolehDisunting())
                <a href="{{ route('dokumen.laporan.edit', $usulan->no_usulan) }}"
                   class="px-4 py-2 border border-slate-200 bg-white text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition">
                    Edit Laporan
                </a>
            @endif
        </div>
    </div>

    <x-flash />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-5">

            {{-- Dasar pelaksanaan diturunkan dari surat tugas dan maksud
                 perjalanan, jadi tidak pernah berbeda dari yang tercatat. --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <h3 class="font-bold text-slate-800 text-sm mb-2">Dasar Pelaksanaan</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $laporan->dasarPelaksanaan() }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">Uraian Kegiatan per Hari</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($laporan->kegiatan as $kegiatan)
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-teal-600 mb-1">
                                {{ $kegiatan->tanggal?->translatedFormat('l, d F Y') ?? 'Hari ke-'.$kegiatan->urutan }}
                            </p>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ $kegiatan->uraian }}</p>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-sm text-slate-400 text-center">Belum ada uraian kegiatan.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">Rencana Tindak Lanjut</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($laporan->tindakLanjut as $tindak)
                        <div class="px-6 py-4 flex flex-wrap items-start gap-x-4 gap-y-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $tindak->uraian }}</p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-400">
                                    @if ($tindak->penanggung_jawab)
                                        <span>PJ: {{ $tindak->penanggung_jawab }}</span>
                                    @endif
                                    @if ($tindak->target_selesai)
                                        <span class="{{ $tindak->terlambat() ? 'font-bold text-red-600' : '' }}">
                                            Target {{ $tindak->target_selesai->translatedFormat('d M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full shrink-0 {{ $tindak->status->badge() }}">
                                {{ $tindak->status->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-sm text-slate-400 text-center">Belum ada rencana tindak lanjut.</p>
                    @endforelse
                </div>
            </div>

            @if (filled($laporan->kesimpulan))
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <h3 class="font-bold text-slate-800 text-sm mb-2">Kesimpulan dan Saran</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $laporan->kesimpulan }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-5">
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
                <dl class="space-y-3 text-xs">
                    <div>
                        <dt class="text-slate-400">Status Laporan</dt>
                        <dd class="font-semibold text-slate-700">
                            {{ $laporan->sudahSelesai() ? 'Selesai' : 'Belum diselesaikan' }}
                        </dd>
                    </div>
                    @if ($laporan->sudahSelesai())
                        <div>
                            <dt class="text-slate-400">Diselesaikan</dt>
                            <dd class="font-semibold text-slate-700">
                                {{ $laporan->diselesaikan_at->translatedFormat('d F Y H:i') }} WITA
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-slate-400">Pelaksana</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->user?->nama }}</dd>
                    </div>
                    @unless ($laporan->bolehDisunting())
                        <div>
                            <dt class="text-slate-400">Penyuntingan</dt>
                            <dd class="font-semibold text-slate-700">
                                Terkunci — perjalanan sudah ditutup.
                            </dd>
                        </div>
                    @endunless
                </dl>
            </div>
        </div>
    </div>
</div>

@endsection
