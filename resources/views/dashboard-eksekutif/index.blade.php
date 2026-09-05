@extends('app')

@section('title', 'Dashboard Eksekutif')

@section('content')

@php
    // Palet kategorikal tetap — urutan slot tidak pernah diputar ulang.
    $palet = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#8a8781'];

    $kategoriTampil = $realisasiPerKategori->take(count($palet));
    $puncakRealisasi = max([...$realisasiPerBulan, 1]);
    $puncakPegawai = max([...$pegawaiPerBulan, 1]);

    $rupiahRingkas = function (float $nilai): string {
        if ($nilai >= 1_000_000_000) {
            return number_format($nilai / 1_000_000_000, 1, ',', '.').' M';
        }
        if ($nilai >= 1_000_000) {
            return number_format($nilai / 1_000_000, 1, ',', '.').' jt';
        }
        return number_format($nilai, 0, ',', '.');
    };
@endphp

<div class="flex-1 px-4 md:px-8 py-7" x-data="{ tabelTerbuka: false }">

    <x-flash />

    {{-- Header + filter tahun --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Dashboard Eksekutif</h1>
            <p class="text-xs text-slate-400 mt-0.5">Realisasi anggaran dan pergerakan pegawai perjalanan dinas tahun {{ $tahun }}</p>
        </div>

        <form method="GET" action="{{ route('dashboard-eksekutif') }}" class="flex gap-2">
            <select name="tahun" onchange="this.form.submit()"
                    class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                @forelse ($tahunTersedia as $pilihan)
                    <option value="{{ $pilihan }}" @selected($pilihan === $tahun)>Tahun {{ $pilihan }}</option>
                @empty
                    <option value="{{ $tahun }}">Tahun {{ $tahun }}</option>
                @endforelse
            </select>
        </form>
    </div>

    {{-- Yang menunggu tindakan lebih dulu daripada angka tahunan: halaman ini
         dibuka justru oleh peran yang mengerjakan berkasnya. --}}
    <x-antrean-kerja :antrean="$antrean" />

    {{-- Angka utama --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Realisasi Anggaran</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</p>
            @if ($tahunAnggaran && $tahunAnggaran->pagu > 0)
                @php $serapan = min(100, round($totalRealisasi / $tahunAnggaran->pagu * 100, 1)); @endphp
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden mt-3">
                    <div class="h-full rounded-full bg-teal-500" style="width: {{ $serapan }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1.5">{{ $serapan }}% dari pagu Rp {{ number_format($tahunAnggaran->pagu, 0, ',', '.') }}</p>
            @else
                <p class="text-xs text-slate-400 mt-1.5">Pagu tahun ini belum ditetapkan</p>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Akan Berangkat</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($akanBerangkat, 0, ',', '.') }}</p>
            <p class="text-xs text-slate-400 mt-1.5">pegawai dengan usulan disetujui, belum berangkat</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Sedang Berjalan</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($sedangBerjalan, 0, ',', '.') }}</p>
            <p class="text-xs text-slate-400 mt-1.5">pegawai sedang dalam perjalanan dinas hari ini</p>
        </div>

        <div class="bg-white rounded-2xl border {{ $belumMelapor > 0 ? 'border-amber-200' : 'border-slate-100' }} shadow-sm p-5">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Belum Melapor</p>
                @if ($belumMelapor > 0)
                    <span class="shrink-0 w-5 h-5 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center" title="Perlu tindak lanjut">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01"/></svg>
                    </span>
                @endif
            </div>
            <p class="text-2xl font-bold {{ $belumMelapor > 0 ? 'text-amber-700' : 'text-slate-800' }} mt-1">{{ number_format($belumMelapor, 0, ',', '.') }}</p>
            <p class="text-xs text-slate-400 mt-1.5">perjalanan selesai, dokumen pertanggungjawaban belum lengkap</p>
        </div>
    </div>

    {{-- Wawasan AI + agen tanya-jawab --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5"
         x-data="asistenAi({{ $tahun }}, {{ $aiAktif ? 'true' : 'false' }})" x-init="muatWawasan()">

        {{-- Wawasan otomatis --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Wawasan AI</h3>
                        <p class="text-xs text-slate-400">Dibaca dari data perjalanan dinas tahun {{ $tahun }}</p>
                    </div>
                </div>

                <button @click="muatWawasan(true)" :disabled="memuatWawasan"
                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-600 text-xs font-semibold rounded-lg transition whitespace-nowrap">
                    <span x-text="memuatWawasan ? 'Memuat…' : 'Segarkan'"></span>
                </button>
            </div>

            <div class="p-6">
                {{-- Rangka saat memuat --}}
                <template x-if="memuatWawasan">
                    <div class="space-y-3">
                        <template x-for="i in 3" :key="i">
                            <div class="animate-pulse space-y-2">
                                <div class="h-3 bg-slate-100 rounded w-1/3"></div>
                                <div class="h-3 bg-slate-100 rounded w-full"></div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="! memuatWawasan && wawasan.length > 0">
                    <ul class="space-y-4">
                        <template x-for="(poin, i) in wawasan" :key="i">
                            <li class="flex gap-3">
                                <span class="w-6 h-6 rounded-lg shrink-0 flex items-center justify-center text-[10px] font-bold"
                                      :class="{
                                        'bg-emerald-100 text-emerald-700': poin.nada === 'positif',
                                        'bg-amber-100 text-amber-700': poin.nada === 'perhatian',
                                        'bg-slate-100 text-slate-600': poin.nada === 'netral'
                                      }"
                                      x-text="i + 1"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800" x-text="poin.judul"></p>
                                    <p class="text-sm text-slate-600 mt-0.5 leading-relaxed" x-text="poin.isi"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </template>

                <template x-if="! memuatWawasan && wawasan.length === 0">
                    <div class="flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                        <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                        </svg>
                        <p class="text-xs text-slate-500" x-text="pesanWawasan"></p>
                    </div>
                </template>

                <p class="text-[11px] text-slate-400 mt-5 pt-4 border-t border-slate-100 leading-relaxed">
                    Wawasan disusun otomatis dari angka pada aplikasi ini. Periksa kembali sebelum
                    dipakai sebagai dasar keputusan.
                </p>
            </div>
        </div>

        {{-- Agen tanya-jawab --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Tanya Data</h3>
                    <p class="text-xs text-slate-400">Agen membaca data aplikasi</p>
                </div>
            </div>

            <div class="flex-1 px-5 py-4 space-y-3 overflow-y-auto max-h-80" x-ref="percakapan">
                <template x-if="percakapan.length === 0">
                    <div class="space-y-2">
                        <p class="text-xs text-slate-400 mb-2">Contoh pertanyaan:</p>
                        <template x-for="contoh in saran" :key="contoh">
                            <button @click="kirim(contoh)" :disabled="! aktif || menjawab"
                                    class="w-full text-left px-3 py-2 bg-slate-50 hover:bg-teal-50 disabled:opacity-50 border border-slate-200 rounded-lg text-xs text-slate-600 transition"
                                    x-text="contoh"></button>
                        </template>
                    </div>
                </template>

                <template x-for="(pesan, i) in percakapan" :key="i">
                    <div :class="pesan.role === 'user' ? 'text-right' : ''">
                        <div class="inline-block max-w-[92%] px-3 py-2 rounded-xl text-xs leading-relaxed text-left whitespace-pre-line"
                             :class="pesan.role === 'user'
                                ? 'bg-teal-500 text-white'
                                : 'bg-slate-50 border border-slate-200 text-slate-700'"
                             x-text="pesan.content"></div>
                    </div>
                </template>

                <template x-if="menjawab">
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-pulse"></span>
                        Menelusuri data…
                    </div>
                </template>
            </div>

            <form @submit.prevent="kirim(draf)" class="px-5 py-4 border-t border-slate-100">
                <div class="flex gap-2">
                    <input type="text" x-model="draf" :disabled="! aktif || menjawab"
                           placeholder="Tulis pertanyaan…"
                           class="flex-1 min-w-0 px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition disabled:bg-slate-50">
                    <button type="submit" :disabled="! aktif || menjawab || ! draf.trim()"
                            class="px-3 py-2 bg-teal-500 hover:bg-teal-600 disabled:opacity-40 text-white text-xs font-bold rounded-xl transition shrink-0">
                        Kirim
                    </button>
                </div>
                <p x-show="! aktif" x-cloak class="text-[11px] text-amber-700 mt-2">
                    Fitur AI belum aktif — isi ANTHROPIC_API_KEY pada berkas .env.
                </p>
            </form>
        </div>
    </div>

    {{-- Baris 1: realisasi per bulan per kategori --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Realisasi Anggaran per Bulan</h3>
                <p class="text-xs text-slate-400">Dikelompokkan menurut kategori kegiatan, berdasarkan bulan keberangkatan</p>
            </div>
            <button @click="tabelTerbuka = !tabelTerbuka"
                    class="self-start px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition whitespace-nowrap">
                <span x-text="tabelTerbuka ? 'Sembunyikan tabel' : 'Lihat sebagai tabel'"></span>
            </button>
        </div>

        @if ($kategoriTampil->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">Belum ada realisasi anggaran pada tahun {{ $tahun }}</p>
        @else
            {{-- Legenda: identitas tidak pernah bergantung pada warna saja --}}
            <div class="px-6 pt-4 flex flex-wrap gap-x-5 gap-y-2">
                @foreach ($kategoriTampil as $nama => $nilaiBulanan)
                    <span class="inline-flex items-center gap-2 text-xs text-slate-600">
                        <span class="w-3 h-3 rounded-sm shrink-0" style="background: {{ $palet[$loop->index] }}"></span>
                        {{ $nama }}
                    </span>
                @endforeach
            </div>

            <div class="px-6 py-5 overflow-x-auto">
                <div class="flex items-end gap-2 min-w-[640px] h-56">
                    @foreach ($bulan as $indeks => $namaBulan)
                        @php $totalBulan = $realisasiPerBulan[$indeks]; @endphp
                        <div class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end group relative">

                            {{-- Label langsung hanya pada bulan yang ada nilainya --}}
                            @if ($totalBulan > 0)
                                <span class="text-[10px] font-bold text-slate-600 tabular-nums">{{ $rupiahRingkas($totalBulan) }}</span>
                            @endif

                            <div class="w-full flex flex-col justify-end rounded-t"
                                 style="height: {{ $totalBulan > 0 ? max(2, round($totalBulan / $puncakRealisasi * 178)) : 2 }}px">
                                @if ($totalBulan > 0)
                                    @foreach ($kategoriTampil as $nama => $nilaiBulanan)
                                        @php $nilai = $nilaiBulanan[$indeks]; @endphp
                                        @if ($nilai > 0)
                                            <div class="w-full {{ $loop->first ? 'rounded-t' : '' }} {{ $loop->last ? 'rounded-b-sm' : '' }}"
                                                 style="flex: {{ $nilai }} 0 0; background: {{ $palet[$loop->index] }}; margin-bottom: 2px"
                                                 title="{{ $namaBulan }} · {{ $nama }}: Rp {{ number_format($nilai, 0, ',', '.') }}"></div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="w-full h-0.5 rounded-sm bg-slate-200"></div>
                                @endif
                            </div>

                            <span class="text-[11px] text-slate-400">{{ $namaBulan }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Tampilan tabel: relief untuk kontras warna sekaligus jalur akses non-visual --}}
            <div x-show="tabelTerbuka" x-transition x-cloak class="border-t border-slate-100 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Kategori</th>
                            @foreach ($bulan as $namaBulan)
                                <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-3 py-3">{{ $namaBulan }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($kategoriTampil as $nama => $nilaiBulanan)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-slate-700 whitespace-nowrap">
                                    <span class="inline-block w-2.5 h-2.5 rounded-sm mr-2" style="background: {{ $palet[$loop->index] }}"></span>
                                    {{ $nama }}
                                </td>
                                @foreach ($nilaiBulanan as $nilai)
                                    <td class="px-3 py-2.5 text-right text-slate-600 tabular-nums whitespace-nowrap">
                                        {{ $nilai > 0 ? $rupiahRingkas($nilai) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="bg-slate-50 font-bold">
                            <td class="px-4 py-2.5 text-slate-700">Total</td>
                            @foreach ($realisasiPerBulan as $nilai)
                                <td class="px-3 py-2.5 text-right text-slate-800 tabular-nums whitespace-nowrap">
                                    {{ $nilai > 0 ? $rupiahRingkas($nilai) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Baris 2: jumlah pegawai berangkat per bulan --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-sm">Pegawai Berangkat per Bulan</h3>
            <p class="text-xs text-slate-400">Jumlah keikutsertaan pegawai pada perjalanan dinas yang disetujui</p>
        </div>

        <div class="px-6 py-5 overflow-x-auto">
            <div class="flex items-end gap-2 min-w-[640px] h-40">
                @foreach ($bulan as $indeks => $namaBulan)
                    @php $jumlah = $pegawaiPerBulan[$indeks]; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
                        @if ($jumlah > 0)
                            <span class="text-[10px] font-bold text-slate-600 tabular-nums">{{ $jumlah }}</span>
                        @endif
                        <div class="w-full rounded-t"
                             style="height: {{ $jumlah > 0 ? max(3, round($jumlah / $puncakPegawai * 110)) : 2 }}px; background: {{ $jumlah > 0 ? '#2a78d6' : '#e2e8f0' }}"
                             title="{{ $namaBulan }}: {{ $jumlah }} pegawai"></div>
                        <span class="text-[11px] text-slate-400">{{ $namaBulan }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Rekap per unit kerja --}}
        <div class="border-t border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100">
                <p class="text-sm font-bold text-slate-700">Rekap per Unit Kerja</p>
                <p class="text-xs text-slate-400">Sebaran keberangkatan tiap bulan, diurutkan dari unit teraktif</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 min-w-[190px]">Unit Kerja</th>
                            @foreach ($bulan as $namaBulan)
                                <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-2 py-3 w-11">{{ $namaBulan }}</th>
                            @endforeach
                            <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-3 py-3 w-20">Orang</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-3 py-3 w-24">Perjalanan</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-28">Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @php $puncakUnit = max([1, ...$pegawaiPerUnit->pluck('perBulan')->flatten()->all()]); @endphp

                        @forelse ($pegawaiPerUnit as $unit)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $unit['unit'] }}</td>

                                @foreach ($unit['perBulan'] as $indeks => $jumlah)
                                    <td class="px-2 py-2.5 text-center">
                                        @if ($jumlah > 0)
                                            {{-- Kepekatan warna mengikuti besar angkanya --}}
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-bold tabular-nums"
                                                  style="background: rgba(42,120,214,{{ number_format(0.12 + 0.68 * ($jumlah / $puncakUnit), 2) }}); color: {{ $jumlah / $puncakUnit > 0.5 ? '#fff' : '#1e3a5f' }}"
                                                  title="{{ $bulan[$indeks] }}: {{ $jumlah }} orang">{{ $jumlah }}</span>
                                        @else
                                            <span class="text-slate-200">·</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-3 py-2.5 text-center font-bold text-slate-800 tabular-nums">{{ $unit['orang'] }}</td>
                                <td class="px-3 py-2.5 text-center text-slate-600 tabular-nums">{{ $unit['perjalanan'] }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-600 tabular-nums whitespace-nowrap">
                                    {{ $unit['biaya'] > 0 ? 'Rp '.$rupiahRingkas($unit['biaya']) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="px-4 py-10 text-center text-sm text-slate-400">
                                    Belum ada keberangkatan pada tahun {{ $tahun }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function asistenAi(tahun, aktif) {
        return {
            tahun, aktif,
            wawasan: [],
            pesanWawasan: 'Wawasan belum tersedia.',
            memuatWawasan: false,
            percakapan: [],
            draf: '',
            menjawab: false,
            saran: [
                'Bulan mana penyerapan anggarannya paling tinggi?',
                'Siapa saja yang belum melengkapi pertanggungjawaban?',
                'Unit kerja mana yang paling sering perjalanan dinas?',
            ],

            async muatWawasan(segarkan = false) {
                if (! this.aktif) {
                    this.pesanWawasan = 'Fitur AI belum aktif — isi ANTHROPIC_API_KEY pada berkas .env.';
                    return;
                }

                this.memuatWawasan = true;

                try {
                    const url = `{{ route('asisten-ai.wawasan') }}?tahun=${this.tahun}${segarkan ? '&segarkan=1' : ''}`;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();

                    this.wawasan = data.status === 'ok' ? (data.poin ?? []) : [];
                    this.pesanWawasan = data.pesan ?? 'Belum ada wawasan yang dapat disimpulkan dari data tahun ini.';
                } catch (e) {
                    this.wawasan = [];
                    this.pesanWawasan = 'Gagal memuat wawasan. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.memuatWawasan = false;
                }
            },

            async kirim(teks) {
                const pertanyaan = (teks ?? '').trim();
                if (! pertanyaan || ! this.aktif || this.menjawab) return;

                this.percakapan.push({ role: 'user', content: pertanyaan });
                this.draf = '';
                this.menjawab = true;
                this.$nextTick(() => this.gulirKeBawah());

                try {
                    const res = await fetch('{{ route('asisten-ai.tanya') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        },
                        body: JSON.stringify({
                            pertanyaan,
                            tahun: this.tahun,
                            // Riwayat dikirim tanpa pesan terakhir agar tidak terduplikasi.
                            riwayat: this.percakapan.slice(0, -1).slice(-10),
                        }),
                    });

                    const data = await res.json();

                    this.percakapan.push({
                        role: 'assistant',
                        content: data.status === 'ok'
                            ? data.jawaban
                            : (data.pesan ?? 'Agen sedang tidak dapat menjawab.'),
                    });
                } catch (e) {
                    this.percakapan.push({ role: 'assistant', content: 'Gagal menghubungi agen. Coba lagi.' });
                } finally {
                    this.menjawab = false;
                    this.$nextTick(() => this.gulirKeBawah());
                }
            },

            gulirKeBawah() {
                const kotak = this.$refs.percakapan;
                if (kotak) kotak.scrollTop = kotak.scrollHeight;
            },
        };
    }
</script>
@endpush

@endsection
