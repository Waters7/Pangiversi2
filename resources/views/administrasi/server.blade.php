@extends('app')

@section('title', 'Pemantauan Server — Administrasi Sistem')

@use('App\Models\CatatanPenyimpanan')
@use('App\Services\PemantauServer')
@use('Illuminate\Support\Carbon')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Pemantauan Server';
        $subjudulHalaman = 'Ukuran aplikasi di peladen dan peringatan WhatsApp saat aplikasi tidak dapat diakses';
        $ukuran = fn ($byte) => CatatanPenyimpanan::ukuranTerbaca($byte);
        $waktuLokal = fn (?string $iso) => $iso ? Carbon::parse($iso)->timezone(config('app.timezone')) : null;
    @endphp
    @include('administrasi.partials.kepala')

    <x-flash />

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── 1. Status server ── --}}
    @php
        $terakhirDicek = $waktuLokal($status['terakhir_dicek']);
        $gangguan = $status['status'] === PemantauServer::STATUS_GANGGUAN;
    @endphp
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-status-server>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $gangguan ? 'bg-red-50' : 'bg-emerald-50' }} flex items-center justify-center">
                <svg class="w-4 h-4 {{ $gangguan ? 'text-red-600' : 'text-emerald-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Status Server</h3>
                <p class="text-xs text-slate-400">Situs, basis data, dan penyimpanan diperiksa tiap menit oleh cron pemantau</p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full
                {{ ! $terakhirDicek ? 'bg-slate-100 text-slate-500' : ($gangguan ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700') }}">
                @if (! $terakhirDicek)
                    Belum pernah diperiksa
                @elseif ($gangguan)
                    Gangguan sejak {{ $waktuLokal($status['gagal_sejak'])?->translatedFormat('d M, H:i') }}
                @else
                    Normal
                @endif
            </span>
        </div>

        <div class="p-6 space-y-4">
            @unless ($cronBerjalan)
                <div class="px-4 py-3.5 bg-amber-50 border border-amber-200 rounded-xl">
                    <p class="text-sm font-bold text-amber-800">
                        {{ $terakhirDicek ? 'Pemeriksaan otomatis berhenti — terakhir '.$terakhirDicek->diffForHumans() : 'Pemeriksaan otomatis belum berjalan' }}
                    </p>
                    <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                        Peringatan WhatsApp hanya terkirim bila cron berikut terpasang di peladen
                        (cPanel → <strong>Cron Jobs</strong> → tiap menit). Cron ini terpisah dari penjadwal
                        <span class="font-mono">schedule:run</span>, sebab penjadwal ikut berhenti saat basis data terputus.
                    </p>
                    <pre class="mt-2.5 px-3 py-2 bg-white border border-amber-200 rounded-lg text-[11px] font-mono text-slate-700 whitespace-pre-wrap break-all">{{ $perintahCron }}</pre>
                    <p class="text-[11px] text-amber-700 mt-1.5">
                        Bila cron memakai PHP versi lain, ganti <span class="font-mono">php</span> dengan jalur yang ditunjukkan
                        perintah <span class="font-mono">which php</span> di terminal hosting.
                    </p>
                </div>
            @endunless

            @if ($status['pemeriksaan'] !== [])
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach ($status['pemeriksaan'] as $hasil)
                        <div class="px-4 py-3 rounded-xl border {{ $hasil['lolos'] ? 'border-emerald-100 bg-emerald-50/50' : 'border-red-200 bg-red-50' }}">
                            <p class="text-xs font-bold {{ $hasil['lolos'] ? 'text-emerald-800' : 'text-red-800' }} flex items-center gap-1.5">
                                @if ($hasil['lolos'])
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                                {{ $hasil['label'] }}
                            </p>
                            <p class="text-[11px] {{ $hasil['lolos'] ? 'text-emerald-700' : 'text-red-700' }} mt-0.5 break-words">{{ $hasil['pesan'] }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-slate-400">
                    Diperiksa {{ $terakhirDicek?->translatedFormat('d M Y, H:i') }} ({{ $terakhirDicek?->diffForHumans() }}).
                    Peringatan dikirim setelah {{ config('pantau.ambang_gagal') }} kali gagal berturut-turut, lalu kabar susulan saat pulih.
                    Pemeriksaan dari terminal: <span class="font-mono">php artisan pangi:pantau-server</span>
                </p>
            @endif
        </div>
    </div>

    {{-- ── 2. Ukuran aplikasi ── --}}
    @php
        $rincian = $terbaru?->rincian ?? [];
        $total = (int) ($terbaru?->total_byte ?? 0);
        $persenKuota = $kuotaByte && $terbaru ? min(100, round($total / $kuotaByte * 100, 1)) : null;
        $warnaKuota = match (true) {
            $persenKuota === null => 'bg-teal-500',
            $persenKuota >= 90 => 'bg-red-500',
            $persenKuota >= 75 => 'bg-amber-500',
            default => 'bg-teal-500',
        };
        $puncakGrafik = max(1, (int) $grafik->max('total_byte'));
    @endphp
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-ukuran-aplikasi>
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-800 text-sm">Ukuran Aplikasi</h3>
                    <p class="text-xs text-slate-400">
                        @if ($terbaru)
                            Diukur {{ $terbaru->updated_at->translatedFormat('d M Y, H:i') }} dalam {{ number_format((float) ($rincian['lama_detik'] ?? 0), 1, ',', '.') }} detik · diukur otomatis tiap malam pukul 02.30 WITA
                        @else
                            Berkas unggahan, basis data, log, pustaka, dan kode aplikasi di peladen
                        @endif
                    </p>
                </div>
            </div>
            <form method="POST" action="{{ route('administrasi.server.ukur') }}" class="shrink-0"
                  x-data="{ mengukur: false }" @submit="mengukur = true">
                @csrf
                <button type="submit" :disabled="mengukur"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">
                    <svg class="w-3.5 h-3.5" :class="mengukur && 'animate-spin'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0018 17.6M18.4 9A7 7 0 006 6.4"/></svg>
                    <span x-text="mengukur ? 'Mengukur…' : 'Ukur Ulang'">Ukur Ulang</span>
                </button>
            </form>
        </div>

        @if (! $terbaru)
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-semibold text-slate-600">Ukuran aplikasi belum pernah diukur.</p>
                <p class="text-xs text-slate-400 mt-1">Tekan <strong>Ukur Ulang</strong> — penjelajahan seluruh folder dapat memakan beberapa detik.</p>
            </div>
        @else
            <div class="grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 border-b border-slate-100">
                <div class="px-6 py-4">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Total aplikasi</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ $ukuran($total) }}</p>
                    <p class="text-[11px] text-slate-400">{{ number_format(array_sum(array_column($rincian['kelompok'] ?? [], 'berkas')), 0, ',', '.') }} berkas</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Berkas unggahan</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ $ukuran($terbaru->unggahan_byte) }}</p>
                    <p class="text-[11px] text-slate-400">{{ number_format($rincian['kelompok']['unggahan']['berkas'] ?? 0, 0, ',', '.') }} berkas pengguna</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Basis data</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ ($rincian['basis_data']['terukur'] ?? false) || $terbaru->basis_data_byte > 0 ? $ukuran($terbaru->basis_data_byte) : '—' }}</p>
                    <p class="text-[11px] text-slate-400">{{ strtoupper($rincian['basis_data']['driver'] ?? '') }}{{ ($rincian['basis_data']['terukur'] ?? false) || $terbaru->basis_data_byte > 0 ? '' : ' · ukurannya tidak dapat dibaca di hosting ini' }}</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Pertumbuhan</p>
                    @if ($pertumbuhan['tiga_puluh_hari'])
                        <p class="text-2xl font-bold {{ $pertumbuhan['tiga_puluh_hari']['byte'] > 0 ? 'text-amber-600' : 'text-slate-800' }} mt-1">
                            {{ $pertumbuhan['tiga_puluh_hari']['byte'] >= 0 ? '+' : '−' }}{{ $ukuran(abs($pertumbuhan['tiga_puluh_hari']['byte'])) }}
                        </p>
                        <p class="text-[11px] text-slate-400">
                            sejak {{ $pertumbuhan['tiga_puluh_hari']['sejak'] }}
                            @if ($pertumbuhan['tujuh_hari'])
                                · 7 hari: {{ $pertumbuhan['tujuh_hari']['byte'] >= 0 ? '+' : '−' }}{{ $ukuran(abs($pertumbuhan['tujuh_hari']['byte'])) }}
                            @endif
                        </p>
                    @else
                        <p class="text-sm font-bold text-slate-400 mt-1">Belum ada pembanding</p>
                        <p class="text-[11px] text-slate-400">tampil setelah dua hari pengukuran</p>
                    @endif
                </div>
            </div>

            <div class="p-6 space-y-6">
                {{-- Kuota hosting --}}
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-2">
                        <div>
                            <p class="text-xs font-bold text-slate-600">Pemakaian kuota hosting</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                @if ($persenKuota !== null)
                                    {{ $ukuran($total) }} dari {{ $ukuran($kuotaByte) }} ({{ str_replace('.', ',', (string) $persenKuota) }}%) — kuota akun juga terpakai surel dan situs lain di akun yang sama.
                                @else
                                    Isi kuota paket hosting untuk melihat persentasenya.
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('administrasi.server.kuota') }}" class="flex items-center gap-2 shrink-0">
                            @csrf
                            @method('PUT')
                            <label for="kuota_gb" class="text-[11px] font-semibold text-slate-500">Kuota (GB)</label>
                            <input id="kuota_gb" type="number" name="kuota_gb" step="0.1" min="0.1"
                                   value="{{ old('kuota_gb', $kuotaByte ? round($kuotaByte / 1024 / 1024 / 1024, 1) : '') }}" placeholder="cth. 10"
                                   class="w-24 px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            <button type="submit" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-lg transition">Simpan</button>
                        </form>
                    </div>
                    @if ($persenKuota !== null)
                        <div class="h-3 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $warnaKuota }}" style="width: {{ max(1, $persenKuota) }}%"></div>
                        </div>
                        @if ($persenKuota >= 90)
                            <p class="text-xs font-semibold text-red-600 mt-1.5">Kuota hampir habis — unggahan pengguna akan gagal bila kuota penuh. Bersihkan log atau tambah kuota hosting.</p>
                        @endif
                    @endif
                </div>

                {{-- Rincian per kelompok --}}
                <div>
                    <p class="text-xs font-bold text-slate-600 mb-3">Rincian per kelompok</p>
                    <div class="space-y-2.5">
                        @foreach (collect($rincian['kelompok'] ?? [])->sortByDesc('byte') as $kunci => $isi)
                            @continue($isi['byte'] === 0)
                            <div class="grid grid-cols-1 sm:grid-cols-[14rem_minmax(0,1fr)_6rem] items-center gap-x-4 gap-y-1">
                                <p class="text-xs text-slate-600">{{ $kelompok[$kunci] ?? $kunci }}</p>
                                <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-indigo-400" style="width: {{ max(0.5, round($isi['byte'] / max(1, $total) * 100, 1)) }}%"></div>
                                </div>
                                <p class="text-xs font-semibold text-slate-700 sm:text-right">{{ $ukuran($isi['byte']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Pertumbuhan harian --}}
                @if ($grafik->count() > 1)
                    <div>
                        <p class="text-xs font-bold text-slate-600 mb-3">Total ukuran {{ $grafik->count() }} pengukuran terakhir</p>
                        <div class="flex items-end gap-1 h-28 px-1 border-b border-slate-200" role="img"
                             aria-label="Grafik ukuran aplikasi dari {{ $grafik->first()->tanggal->translatedFormat('d M') }} sampai {{ $grafik->last()->tanggal->translatedFormat('d M') }}">
                            @foreach ($grafik as $satu)
                                <div class="flex-1 min-w-0 rounded-t bg-teal-400/80 hover:bg-teal-500 transition"
                                     style="height: {{ max(2, round($satu->total_byte / $puncakGrafik * 100)) }}%"
                                     title="{{ $satu->tanggal->translatedFormat('d M Y') }} — {{ $ukuran($satu->total_byte) }}"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                            <span>{{ $grafik->first()->tanggal->translatedFormat('d M') }}</span>
                            <span>{{ $grafik->last()->tanggal->translatedFormat('d M') }}</span>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Folder unggahan --}}
                    <div>
                        <p class="text-xs font-bold text-slate-600 mb-2">Berkas unggahan per jenis</p>
                        <div class="rounded-xl border border-slate-100 overflow-hidden">
                            <table class="w-full text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($rincian['unggahan'] ?? [] as $folder)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-700">
                                                {{ $folder['label'] }}
                                                <span class="block text-[10px] text-slate-400 font-mono">{{ $folder['folder'] }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-right text-slate-500 whitespace-nowrap">{{ number_format($folder['berkas'], 0, ',', '.') }} berkas</td>
                                            <td class="px-3 py-2 text-right font-semibold text-slate-700 whitespace-nowrap">{{ $ukuran($folder['byte']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="px-3 py-6 text-center text-slate-400">Belum ada berkas unggahan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tabel basis data --}}
                    <div>
                        <p class="text-xs font-bold text-slate-600 mb-2">Tabel basis data terbesar</p>
                        <div class="rounded-xl border border-slate-100 overflow-hidden">
                            <table class="w-full text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    @forelse (collect($rincian['tabel'] ?? [])->filter(fn ($tabel) => $tabel['byte'] > 0) as $tabel)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-slate-700">{{ $tabel['nama'] }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-slate-700 whitespace-nowrap">{{ $ukuran($tabel['byte']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="px-3 py-6 text-center text-slate-400">Ukuran per tabel tidak tersedia di peladen ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Berkas terbesar --}}
                @if (($rincian['terbesar'] ?? []) !== [])
                    <div>
                        <p class="text-xs font-bold text-slate-600 mb-2">Berkas terbesar di folder storage</p>
                        <ul class="rounded-xl border border-slate-100 divide-y divide-slate-100">
                            @foreach ($rincian['terbesar'] as $berkas)
                                <li class="px-3 py-2 flex items-center justify-between gap-3 text-xs">
                                    <span class="font-mono text-slate-600 break-all">{{ $berkas['jalur'] }}</span>
                                    <span class="font-semibold text-slate-700 whitespace-nowrap">{{ $ukuran($berkas['byte']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-[11px] text-slate-400 mt-1.5">Log aplikasi (storage/logs) aman dihapus bila membengkak; berkas unggahan jangan dihapus manual.</p>
                    </div>
                @endif

                @if (($rincian['disk']['total'] ?? null) && ($rincian['disk']['bebas'] ?? null) !== null)
                    <p class="text-[11px] text-slate-400">
                        Ruang cakram peladen: bebas {{ $ukuran($rincian['disk']['bebas']) }} dari {{ $ukuran($rincian['disk']['total']) }}.
                        Pada hosting bersama angka ini milik seluruh peladen, bukan kuota akun Anda.
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- ── 3. Peringatan WhatsApp ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-peringatan-whatsapp>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $peringatan['aktif'] ? 'bg-emerald-50' : 'bg-slate-100' }} flex items-center justify-center">
                <svg class="w-4 h-4 {{ $peringatan['aktif'] ? 'text-emerald-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Peringatan WhatsApp</h3>
                <p class="text-xs text-slate-400">Dikirim otomatis bila aplikasi tidak dapat diakses, diulang selama belum pulih, dan dikabarkan saat pulih</p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $peringatan['aktif'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $peringatan['aktif'] ? 'Aktif · '.count($peringatan['nomor']).' nomor' : 'Nonaktif' }}
            </span>
        </div>

        <form method="POST" action="{{ route('administrasi.server.peringatan') }}" class="p-6"
              x-data="{ aktif: {{ old('peringatan_aktif', $peringatan['aktif'] ? '1' : '0') == '1' ? 'true' : 'false' }}, gateway: @js(old('wa_gateway', $peringatan['gateway'])), tampilToken: false }">
            @csrf
            @method('PUT')

            <p class="text-xs text-slate-500 leading-relaxed mb-5">
                Pesan dikirim lewat layanan <em>gateway</em> WhatsApp — PANGI tidak dapat mengirim WhatsApp sendiri.
                Daftarkan nomor pengirim di <strong>Fonnte</strong> (fonnte.com) atau <strong>Wablas</strong> (wablas.com),
                lalu salin tokennya ke sini. Nomor penerima sebaiknya bukan nomor pengirim itu sendiri.
            </p>

            <label class="flex items-start gap-3 mb-5 cursor-pointer">
                <input type="checkbox" name="peringatan_aktif" value="1" x-model="aktif"
                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Aktifkan peringatan WhatsApp</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Butuh cron pemantau pada kartu Status Server agar berjalan.</span>
                </span>
            </label>

            <div class="grid md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="nomor_penerima" class="block text-xs font-semibold text-slate-600 mb-1.5">Nomor WhatsApp penerima <span class="text-red-500" x-show="aktif">*</span></label>
                    <textarea id="nomor_penerima" name="nomor_penerima" rows="2" placeholder="cth. 0812 3456 7890, 0853 1111 2222"
                              class="w-full px-3.5 py-2.5 border {{ $errors->has('nomor_penerima') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('nomor_penerima', implode(', ', $peringatan['nomor'])) }}</textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Pisahkan beberapa nomor dengan koma atau baris baru. Awalan 0 otomatis menjadi 62.</p>
                </div>

                <div>
                    <label for="wa_gateway" class="block text-xs font-semibold text-slate-600 mb-1.5">Gateway</label>
                    <select id="wa_gateway" name="wa_gateway" x-model="gateway"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        @foreach ($pilihanGateway as $kunci => $label)
                            <option value="{{ $kunci }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="peringatan_ulang_menit" class="block text-xs font-semibold text-slate-600 mb-1.5">Ulangi peringatan tiap (menit)</label>
                    <input id="peringatan_ulang_menit" type="number" name="peringatan_ulang_menit" min="15" max="1440"
                           value="{{ old('peringatan_ulang_menit', $peringatan['ulang_menit']) }}"
                           class="w-full px-3.5 py-2.5 border {{ $errors->has('peringatan_ulang_menit') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>

                <div class="md:col-span-2" x-show="gateway === 'wablas'" x-cloak>
                    <label for="wa_url_wablas" class="block text-xs font-semibold text-slate-600 mb-1.5">Alamat peladen Wablas <span class="text-red-500">*</span></label>
                    <input id="wa_url_wablas" type="url" name="wa_url_wablas" value="{{ old('wa_url_wablas', $peringatan['url_wablas']) }}"
                           placeholder="https://tegal.wablas.com"
                           class="w-full px-3.5 py-2.5 border {{ $errors->has('wa_url_wablas') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <p class="text-[11px] text-slate-400 mt-1">Tertera pada dasbor Wablas, berbeda untuk tiap akun.</p>
                </div>

                <div class="md:col-span-2">
                    <label for="wa_token" class="block text-xs font-semibold text-slate-600 mb-1.5">Token gateway</label>
                    <div class="flex gap-2">
                        <input id="wa_token" name="wa_token" :type="tampilToken ? 'text' : 'password'" autocomplete="off" spellcheck="false"
                               placeholder="{{ $peringatan['token_tersamar'] ? 'Tersimpan: '.$peringatan['token_tersamar'].' — isi untuk mengganti' : 'Salin dari dasbor Fonnte atau Wablas' }}"
                               class="flex-1 min-w-0 px-3.5 py-2.5 border {{ $errors->has('wa_token') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <button type="button" @click="tampilToken = ! tampilToken"
                                class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition shrink-0"
                                x-text="tampilToken ? 'Sembunyikan' : 'Lihat'">Lihat</button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        @if ($peringatan['token_tersamar'])
                            Token tersimpan terenkripsi ({{ $peringatan['token_tersamar'] }}); kosongkan kolom ini untuk tetap memakainya.
                            <label class="inline-flex items-center gap-1.5 ml-2 cursor-pointer text-red-600">
                                <input type="checkbox" name="hapus_token" value="1" class="w-3.5 h-3.5 rounded border-slate-300 text-red-600 focus:ring-red-400">
                                Hapus token
                            </label>
                        @else
                            Token disimpan terenkripsi dan tidak ditampilkan kembali.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 mt-6 pt-5 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                    Simpan Pengaturan
                </button>
            </div>
        </form>

        <div class="px-6 pb-6 -mt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500">Uji memakai pengaturan yang sudah disimpan.</p>
            <form method="POST" action="{{ route('administrasi.server.uji') }}">
                @csrf
                <button type="submit" @disabled($peringatan['nomor'] === [] || ! $peringatan['token_tersamar'])
                        class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed text-slate-700 text-xs font-bold rounded-xl transition">
                    Kirim Pesan Uji
                </button>
            </form>
        </div>

        {{-- Riwayat peringatan --}}
        <div class="border-t border-slate-100">
            <p class="px-6 pt-4 pb-2 text-xs font-bold text-slate-600">Riwayat peringatan</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left border-y border-slate-100">
                            <th class="px-6 py-2.5 font-semibold">Waktu</th>
                            <th class="px-4 py-2.5 font-semibold">Jenis</th>
                            <th class="px-4 py-2.5 font-semibold">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($status['riwayat'] as $baris)
                            <tr>
                                <td class="px-6 py-2.5 text-slate-700 whitespace-nowrap text-xs">{{ $waktuLokal($baris['waktu'])?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="px-4 py-2.5 text-xs">
                                    <span class="inline-block font-bold px-2 py-0.5 rounded-full
                                        {{ match ($baris['jenis']) {
                                            PemantauServer::JENIS_GANGGUAN, PemantauServer::JENIS_PENGINGAT => 'bg-red-100 text-red-700',
                                            PemantauServer::JENIS_PULIH => 'bg-emerald-100 text-emerald-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        } }}">
                                        {{ match ($baris['jenis']) {
                                            PemantauServer::JENIS_GANGGUAN => 'Gangguan',
                                            PemantauServer::JENIS_PENGINGAT => 'Pengingat',
                                            PemantauServer::JENIS_PULIH => 'Pulih',
                                            default => 'Uji',
                                        } }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-xs {{ $baris['terkirim'] ? 'text-slate-600' : 'text-red-600' }}">{{ $baris['keterangan'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada peringatan yang dikirim.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
