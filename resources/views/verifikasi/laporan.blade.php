<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <x-ikon-tab />
    <title>Verifikasi Laporan Perjalanan Dinas — PANGI</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">

    @php
        // Halaman ini dituju QR pada dokumen laporan. Dua sisi tanda tangan
        // memuat isi yang berbeda: pelaksana membawa identitas perjalanannya,
        // pimpinan membawa nama dan kapan ia mengonfirmasi.
        $pimpinan = $sisi === 'pimpinan';

        $noSurat = $usulan?->no_spd
            ?: ($usulan?->spd?->pelaksana_utama?->nomor_surat ?: $usulan?->no_tugas);

        $mulai = $usulan?->tanggal_mulai ? \Carbon\Carbon::parse($usulan->tanggal_mulai) : null;
        $selesai = $usulan?->tanggal_selesai ? \Carbon\Carbon::parse($usulan->tanggal_selesai) : null;

        $tanggalPerjadin = match (true) {
            ! $mulai => '—',
            ! $selesai || $selesai->isSameDay($mulai) => $mulai->translatedFormat('d F Y'),
            $selesai->isSameMonth($mulai) => $mulai->format('d').'–'.$selesai->translatedFormat('d F Y'),
            default => $mulai->translatedFormat('d F Y').' – '.$selesai->translatedFormat('d F Y'),
        };
    @endphp

    <div class="w-full max-w-md">

        <img src="{{ asset('images/pangi-logo.png') }}" alt="PANGI"
             class="w-56 h-auto mx-auto mb-5 block">
        <p class="text-center text-xs text-slate-500 -mt-3 mb-5">Poltekkes Kemenkes Manado</p>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            <div class="px-6 py-5 bg-emerald-50 border-b border-emerald-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-emerald-800">Laporan Perjalanan Dinas Terverifikasi</p>
                    <p class="text-xs text-emerald-700">
                        {{ $pimpinan
                            ? 'Dikonfirmasi dan ditandatangani pimpinan, tercatat dalam sistem'
                            : 'Ditandatangani pelaksana dan tercatat dalam sistem' }}
                    </p>
                </div>
            </div>

            <dl class="px-6 py-5 space-y-4 text-sm">
                @if ($pimpinan)
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nama Pimpinan</dt>
                        <dd class="font-bold text-slate-800 mt-0.5">{{ $laporan->pimpinan?->nama ?? '—' }}</dd>
                        <dd class="text-xs text-slate-400">
                            {{ $laporan->pimpinan?->jabatan ?: 'Pimpinan' }}
                            @if ($laporan->pimpinan?->nip) · NIP {{ $laporan->pimpinan->nip }} @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tanggal Konfirmasi Tanda Tangan</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ $laporan->dikonfirmasi_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                        </dd>
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Laporan yang Dikonfirmasi</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ $usulan?->user?->nama ?? '—' }}
                            <span class="block text-xs text-slate-400">{{ $usulan?->no_usulan }} · {{ $usulan?->lokasi }}</span>
                        </dd>
                    </div>
                @else
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nomor Surat</dt>
                        <dd class="font-bold text-slate-800 mt-0.5">{{ $noSurat ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tanggal Perjalanan Dinas</dt>
                        <dd class="text-slate-700 mt-0.5">{{ $tanggalPerjadin }}</dd>
                        <dd class="text-xs text-slate-400">{{ $usulan?->lokasi }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tanggal Pembuatan Laporan</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ $laporan->dikirim_at?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                        </dd>
                        @if ($laporan->diselesaikan_at && ! $laporan->diselesaikan_at->isSameDay($laporan->dikirim_at))
                            <dd class="text-xs text-slate-400">
                                Diselesaikan {{ $laporan->diselesaikan_at->translatedFormat('d F Y') }}
                            </dd>
                        @endif
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nama Pelaksana</dt>
                        <dd class="font-bold text-slate-800 mt-0.5">{{ $usulan?->user?->nama ?? '—' }}</dd>
                        @if ($usulan?->user?->nip)
                            <dd class="text-xs text-slate-400">NIP {{ $usulan->user->nip }}</dd>
                        @endif
                    </div>
                    @if ($laporan->sudahDikonfirmasi())
                        <div class="pt-4 border-t border-slate-100">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Konfirmasi Pimpinan</dt>
                            <dd class="text-slate-700 mt-0.5">
                                {{ $laporan->pimpinan?->nama }}
                                <span class="block text-xs text-slate-400">
                                    {{ $laporan->dikonfirmasi_at->translatedFormat('d F Y, H:i') }} WITA
                                </span>
                            </dd>
                        </div>
                    @endif
                @endif

                <div class="pt-4 border-t border-slate-100">
                    <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Kode Verifikasi</dt>
                    <dd class="font-mono font-bold text-slate-800 mt-0.5">{{ $kode }}</dd>
                </div>
            </dl>

        </div>

        <p class="text-center text-xs text-slate-400 mt-5">
            Halaman verifikasi resmi Sistem Administrasi Perjalanan Dinas PANGI
        </p>

    </div>

</body>
</html>
