<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <x-ikon-tab />
    <title>Verifikasi Dokumen — PANGI</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">

    @php
        $bendahara = $jenis === 'bendahara';
        $pelaksana = $jenis === 'pelaksana';
        $perNominatif = $jenis === 'nominatif';

        $usulan = $bendahara ? $keuangan?->usulan : $daftar?->usulan;

        // Daftar nominatif menaungi seluruh usulan pada satu surat tugas,
        // jadi nomor perjadinnya lebih dari satu.
        $noPerjadin = $perNominatif
            ? \App\Models\Usulan::where('no_tugas', $nominatif?->no_tugas)->pluck('no_usulan')
            : collect([$usulan?->no_usulan])->filter();

        $sah = match ($jenis) {
            'bendahara' => $keuangan !== null,
            'nominatif' => $nominatif !== null,
            default => $daftar !== null,
        };

        $penandatangan = match ($jenis) {
            'bendahara' => $keuangan?->pembayarBendahara,
            'pelaksana' => $daftar?->peserta,
            'nominatif' => $nominatif?->ppk,
            default => $daftar?->ppk,
        };

        $peranPenandatangan = match ($jenis) {
            'bendahara' => 'Bendahara Pengeluaran',
            'pelaksana' => 'Pelaksana Perjalanan Dinas',
            default => 'Pejabat Pembuat Komitmen',
        };

        $kodeSah = match ($jenis) {
            'bendahara' => $keuangan?->kode_konfirmasi_bayar,
            'pelaksana' => $daftar?->kode_konfirmasi,
            'nominatif' => $nominatif?->kode_verifikasi,
            default => $daftar?->kode_verifikasi,
        };

        $labelKode = match ($jenis) {
            'bendahara' => 'Nomor Konfirmasi Pembayaran',
            'pelaksana' => 'Kode Konfirmasi',
            default => 'Kode Verifikasi',
        };

        $waktu = match ($jenis) {
            'bendahara' => $keuangan?->dikonfirmasi_bayar_at,
            'pelaksana' => $daftar?->disetujui_pegawai_at,
            'nominatif' => $nominatif?->ditandatangani_at,
            default => $daftar?->ditandatangani_at,
        };

        $labelWaktu = match ($jenis) {
            'bendahara' => 'Tanggal Konfirmasi Pembayaran',
            'pelaksana' => 'Tanggal Konfirmasi',
            'nominatif' => 'Ditandatangani PPK Pada',
            default => 'Tanggal Verifikasi',
        };

        $keterangan = match ($jenis) {
            'bendahara' => 'Pembayaran perjalanan dinas ini telah dilunasi dan tercatat dalam sistem',
            'pelaksana' => 'Nominal telah dikonfirmasi pelaksana dan tercatat dalam sistem',
            'nominatif' => 'Daftar nominatif ini sah ditandatangani PPK dan tercatat dalam sistem',
            default => 'Tanda tangan PPK sah dan tercatat dalam sistem',
        };

        $labelPenandatangan = match ($jenis) {
            'bendahara' => 'Dikonfirmasi Oleh',
            'pelaksana' => 'Dikonfirmasi Oleh',
            default => 'Diverifikasi Oleh',
        };
    @endphp

    <div class="w-full max-w-md">

        <img src="{{ asset('images/pangi-logo.png') }}" alt="PANGI"
             class="w-56 h-auto mx-auto mb-5 block">
        <p class="text-center text-xs text-slate-500 -mt-3 mb-5">Poltekkes Kemenkes Manado</p>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

            @if ($sah)
                <div class="px-6 py-5 bg-emerald-50 border-b border-emerald-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold text-emerald-800">Dokumen Terverifikasi</p>
                        <p class="text-xs text-emerald-700">{{ $keterangan }}</p>
                    </div>
                </div>

                <dl class="px-6 py-5 space-y-4 text-sm">
                    @if ($perNominatif)
                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Nomor Surat Tugas</dt>
                            <dd class="font-bold text-slate-800 mt-0.5">{{ $nominatif?->no_tugas ?? '—' }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
                            Nomor Pengajuan Perjalanan Dinas
                        </dt>
                        <dd class="font-bold text-slate-800 mt-0.5">
                            @forelse ($noPerjadin as $no)
                                <span class="block">{{ $no }}</span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $labelKode }}</dt>
                        <dd class="font-mono font-bold text-slate-800 mt-0.5">{{ $kodeSah }}</dd>
                    </div>
                    <div @if ($perNominatif) hidden @endif>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Tanggal Pengajuan</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ optional($daftar?->diajukan_at ?? $usulan?->created_at)->translatedFormat('d F Y') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $labelWaktu }}</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ $waktu?->translatedFormat('d F Y, H:i') ?? '—' }} WITA
                        </dd>
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $labelPenandatangan }}</dt>
                        <dd class="text-slate-700 mt-0.5">
                            {{ $penandatangan?->nama ?? '—' }}
                            <span class="block text-xs text-slate-400">{{ $peranPenandatangan }}</span>
                        </dd>
                    </div>
                </dl>
            @else
                <div class="px-6 py-5 bg-red-50 border-b border-red-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold text-red-800">Dokumen Tidak Terverifikasi</p>
                        <p class="text-xs text-red-700">Kode tidak dikenali atau tanda tangannya telah dicabut</p>
                    </div>
                </div>

                <div class="px-6 py-5 text-sm">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Kode yang Diperiksa</p>
                    <p class="font-mono font-bold text-slate-800 mt-0.5 break-all">{{ $kode }}</p>
                    <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                        Pastikan kode diketik dengan benar. Bila dokumen fisik Anda memuat kode ini namun
                        hasilnya tidak sah, hubungi bagian keuangan Poltekkes Kemenkes Manado.
                    </p>
                </div>
            @endif

        </div>

        <p class="text-center text-xs text-slate-400 mt-5">
            Halaman verifikasi resmi Sistem Administrasi Perjalanan Dinas PANGI
        </p>

    </div>

</body>
</html>
