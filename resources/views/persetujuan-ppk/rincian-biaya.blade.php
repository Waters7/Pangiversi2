@extends('app')

@section('title', 'Verifikasi Rincian Biaya Perjadin')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Verifikasi Rincian Biaya Perjadin</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Lampiran II PMK 113/PMK.05/2012. Rincian komponennya diperiksa tim keuangan
            pada menu Keuangan; di sini PPK memutuskan atas dokumennya.
        </p>
    </div>

    <form method="GET" action="{{ route('persetujuan.rincian-biaya') }}" class="mb-5">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="cari" value="{{ $cari }}"
                   placeholder="Cari nama pelaksana, no. usulan, atau no. surat tugas"
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <button type="submit"
                    class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                Cari
            </button>
            @if ($cari)
                <a href="{{ route('persetujuan.rincian-biaya') }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">
                    Reset
                </a>
            @endif
        </div>
    </form>

    <x-tab-status
        :aksi="route('persetujuan.rincian-biaya')"
        :terpilih="$status"
        :tab="[
            '' => ['label' => 'Semua', 'jumlah' => $jumlah['semua']],
            'menunggu' => ['label' => 'Menunggu Validasi', 'jumlah' => $jumlah['menunggu'], 'badge' => 'bg-amber-100 text-amber-700'],
            'tervalidasi' => ['label' => 'Tervalidasi', 'jumlah' => $jumlah['tervalidasi'], 'badge' => 'bg-blue-100 text-blue-700'],
            'ditandatangani' => ['label' => 'Ditandatangani', 'jumlah' => $jumlah['ditandatangani'], 'badge' => 'bg-emerald-100 text-emerald-700'],
        ]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">No. Perjadin</th>
                        <th class="px-5 py-3 font-semibold">No. Surat Tugas</th>
                        <th class="px-5 py-3 font-semibold text-right">Total Biaya</th>
                        <th class="px-5 py-3 font-semibold text-center w-44">Validasi Tim Keuangan</th>
                        <th class="px-5 py-3 font-semibold text-right w-56">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftar as $baris)
                        @php
                            $usulan = $baris['usulan'];
                            $berkas = $baris['berkas'];
                            $jalur = $baris['jalur'];
                        @endphp

                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $usulan->no_usulan }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->user?->nama }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $usulan->no_tugas ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-800">
                                Rp {{ number_format($baris['total'], 0, ',', '.') }}
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                @if ($baris['belum_divalidasi'] > 0)
                                    <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                                        {{ $baris['belum_divalidasi'] }} belum diperiksa
                                    </span>
                                @else
                                    <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        Sudah divalidasi
                                    </span>
                                @endif

                                @if ($jalur?->sudahDitandatangani())
                                    <span class="block text-[11px] text-slate-400 mt-1">
                                        Ditandatangani {{ $jalur->ppk()?->nama }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('keuangan.cetak-rincian', $usulan->no_usulan) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                                            <rect x="6" y="14" width="12" height="8"/>
                                        </svg>
                                        Cetak
                                    </a>

                                    {{-- Rincian biaya ditandatangani sendiri: daftar riil
                                         transportasinya punya submenu dan tanda tangannya
                                         sendiri karena dokumennya memang berbeda. --}}
                                    @if ($jalur?->sudahDitandatangani())
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                            Ditandatangani
                                        </span>
                                    @elseif ($jalur?->siapDitandatanganiPpk() && $baris['peserta'])
                                        <x-aksi-tanda-tangan
                                            :nama="'rincian-'.$berkas->id"
                                            judul="Tandatangani rincian biaya ini?"
                                            :ringkas="$usulan->no_usulan.' · Rp '.number_format($baris['total'], 0, ',', '.')"
                                            :aksi-tanda-tangan="route('daftar-riil.tanda-tangan', [$usulan, $baris['peserta'], 'rincian'])"
                                            :aksi-kembalikan="route('daftar-riil.kembalikan', [$usulan, $baris['peserta'], 'rincian'])" />
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $jalur?->statusLabel() ?? 'Belum berjalan' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Tidak ada berkas pada kelompok ini</p>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Rincian biaya muncul setelah pelaksana mengisi nominal pada menu Dokumen.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
