@extends('app')

@section('title', 'Keuangan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
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

    {{-- Tahap pengerjaan keuangan: masih diproses tim keuangan, sudah
         dikirim ke pelaksana, tinggal dibayarkan, atau selesai. Perjadin yang
         selesai tetap terbuka di sini untuk ditelusuri dan dicetak ulang. --}}
    <x-tab-status
        :aksi="route('keuangan')"
        :terpilih="$status"
        :tab="['' => ['label' => 'Semua', 'jumlah' => array_sum($jumlahStatus)]]
            + collect($tahap)->map(fn ($isi, $kunci) => $isi + ['jumlah' => $jumlahStatus[$kunci]])->all()" />

    {{-- ── List Pejadin ── --}}
    <form method="GET" action="{{ route('keuangan') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="flex flex-col sm:flex-row gap-3">

                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Cari no. usulan, kegiatan, lokasi, instansi..."
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <select name="urut" aria-label="Urutkan" onchange="this.form.submit()"
                        class="px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @foreach ($pilihanUrut as $nilai => $label)
                        <option value="{{ $nilai }}" @selected($urut === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit"
                        class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                    Filter
                </button>

                @if(request('search') || request('status') || request('urut'))
                    <a href="{{ route('keuangan') }}"
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                        Reset
                    </a>
                @endif

            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-slate-700">
                Daftar Perjalanan Dinas
                <span class="ml-2 text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ $usulan->total() }} data
                </span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3.5">Usulan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Pemohon</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Tujuan</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Periode</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Tahap & Pembayaran</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-50">
                    @foreach ($usulan as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800 text-xs">{{ $item->no_usulan }}</p>
                                <p class="text-xs text-slate-400">{{ $item->created_at->format('d M Y') }}</p>
                                {{-- Surat tugas pelaksana — dasar menakar komponen biaya. --}}
                                @php $suratTugas = $item->berkasSuratTugas(); @endphp
                                @if ($suratTugas)
                                    <a href="{{ route('berkas.lihat', $suratTugas) }}" target="_blank" rel="noopener"
                                       title="Surat tugas {{ $item->no_tugas }}"
                                       class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-teal-600 hover:text-teal-700 hover:underline">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        Surat tugas
                                    </a>
                                @endif
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm font-semibold text-slate-700">{{ $item->user?->nama ?? '—' }}</p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm text-slate-700">{{ $item->kategoriPerjadin?->nama ?? $item->kegiatan?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $item->lokasi }}</p>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <p class="text-xs font-semibold text-slate-700">
                                    {{ $item->periode }}
                                </p>
                                <p class="text-xs text-slate-400">{{ $item->durasi }} hari</p>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-col items-center justify-center gap-1.5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $item->tahap_keuangan['badge'] }}">
                                        {{ $item->tahap_keuangan['label'] }}
                                    </span>
                                    @php
                                        $statusConfig = match($item->keuangan?->status) {
                                            'bayar sebagian'  => ['label' => 'Bayar Sebagian',  'class' => 'bg-blue-50 text-blue-600'],
                                            'belum bayar'  => ['label' => 'Belum Bayar',  'class' => 'bg-yellow-50 text-yellow-600'],
                                            'lunas' => ['label' => 'Lunas', 'class' => 'bg-teal-50 text-teal-600'],
                                            default     => ['label' => 'Belum Ada',  'class' => 'bg-slate-100 text-slate-600'],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusConfig['class'] }}">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-col items-center gap-1.5">
                                    <a href="{{ route('keuangan.detail', $item->no_usulan) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold transition border border-slate-200 hover:border-teal-200 whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Detail
                                    </a>

                                    {{-- Menagih berkas yang belum diunggah lewat WhatsApp —
                                         pekerjaan tim keuangan, bukan pemantau. --}}
                                    @if ($item->tautan_wa && auth()->user()->bisaMengelolaBiaya())
                                        <a href="{{ $item->tautan_wa }}" target="_blank" rel="noopener"
                                           title="Kirim pengingat {{ count($item->berkas_kurang) }} berkas kurang lewat WhatsApp"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-white text-xs font-bold transition whitespace-nowrap"
                                           style="background:#25D366"
                                           onmouseover="this.style.background='#1da851'"
                                           onmouseout="this.style.background='#25D366'">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 016.988 2.898 9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                            </svg>
                                            Tagih {{ count($item->berkas_kurang) }} berkas
                                        </a>
                                    @elseif ($item->alasan_tanpa_wa)
                                        <span class="text-[11px] {{ $item->berkas_kurang === [] ? 'text-emerald-600' : 'text-amber-600' }}">
                                            {{ $item->alasan_tanpa_wa }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Daftarnya dibagi per halaman; tanpa navigasi ini baris setelah
             halaman pertama tidak terjangkau sama sekali. --}}
        <x-pagination :paginator="$usulan" />
    </div>

</div>

@endsection
