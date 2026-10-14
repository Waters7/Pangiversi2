@props([
    'anggaran',
    'judul' => 'Pagu dan Realisasi per Kode Akun',
    'tautan' => null,
])

{{-- Pagu maksimal tiap kode akun berdampingan dengan realisasinya, lalu
     rekapnya per sumber dana. Dipakai dashboard eksekutif dan master data
     tahun anggaran supaya keduanya menampilkan angka yang sama. --}}
@php
    $rupiah = fn (float $nilai): string => 'Rp '.number_format($nilai, 0, ',', '.');
    $totalPagu = (float) $anggaran['pagu'];
    $totalRealisasi = (float) $anggaran['akun']->sum('realisasi') + (float) $anggaran['belum_berakun'];
    $serapanTotal = $totalPagu > 0 ? round($totalRealisasi / $totalPagu * 100, 2) : null;

    $warnaBatang = fn (?float $persen, bool $melebihi): string => match (true) {
        $melebihi => 'bg-red-500',
        $persen !== null && $persen >= 90 => 'bg-amber-500',
        default => 'bg-teal-500',
    };
    $persenTeks = fn (?float $persen): string => $persen === null ? '—' : number_format($persen, 2, ',', '.').'%';
@endphp

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h3 class="font-bold text-slate-800 text-sm">{{ $judul }}</h3>
            <p class="text-xs text-slate-400 mt-0.5">Realisasi dibebankan menurut akun yang ditetapkan pada daftar nominatif surat tugasnya</p>
        </div>
        @if ($tautan)
            <a href="{{ $tautan }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700 whitespace-nowrap">Atur pagu per akun →</a>
        @endif
    </div>

    @if ($anggaran['akun']->isEmpty() && $anggaran['belum_berakun'] <= 0)
        <p class="px-6 py-10 text-center text-sm text-slate-400">Pagu per kode akun tahun ini belum diatur.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Kode Akun</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Uraian Akun</th>
                        <th class="text-center text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Sumber Dana</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Pagu</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Realisasi</th>
                        <th class="text-right text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Sisa Pagu</th>
                        <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3 w-44">Serapan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($anggaran['akun'] as $baris)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700 whitespace-nowrap">{{ $baris['kode'] }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $baris['nama'] }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($baris['sumber'])
                                    <span class="inline-block text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600" title="{{ $baris['sumber_nama'] }}">{{ $baris['sumber'] }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700 whitespace-nowrap">
                                {{ $baris['pagu'] > 0 ? $rupiah($baris['pagu']) : 'Belum berpagu' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-600 whitespace-nowrap">{{ $rupiah($baris['realisasi']) }}</td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $baris['sisa'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ $rupiah($baris['sisa']) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $warnaBatang($baris['persen'], $baris['melebihi']) }}"
                                         style="width: {{ $baris['pagu'] > 0 ? min(100, $baris['persen']) : ($baris['realisasi'] > 0 ? 100 : 0) }}%"></div>
                                </div>
                                <p class="text-[11px] mt-1 {{ $baris['melebihi'] ? 'text-red-600 font-semibold' : 'text-slate-400' }}">
                                    {{ $baris['melebihi'] ? ($baris['pagu'] > 0 ? 'Melebihi pagu · ' : 'Belum ada pagu') : '' }}{{ $baris['pagu'] > 0 ? $persenTeks($baris['persen']) : '' }}
                                </p>
                            </td>
                        </tr>
                    @endforeach

                    @if ($anggaran['belum_berakun'] > 0)
                        <tr class="bg-amber-50/40">
                            <td class="px-4 py-3 text-xs text-slate-400">—</td>
                            <td class="px-4 py-3 text-slate-600" colspan="3">
                                Belum ditetapkan akunnya
                                <span class="block text-[11px] text-slate-400">Tetapkan akun pada Laporan → Daftar Nominatif agar realisasinya masuk ke akun yang benar.</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-600 whitespace-nowrap">{{ $rupiah($anggaran['belum_berakun']) }}</td>
                            <td class="px-4 py-3" colspan="2"></td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 border-t border-slate-100 font-bold">
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-slate-800" colspan="2">TOTAL</td>
                        <td class="px-4 py-3 text-right text-slate-800 whitespace-nowrap">{{ $rupiah($totalPagu) }}</td>
                        <td class="px-4 py-3 text-right text-amber-600 whitespace-nowrap">{{ $rupiah($totalRealisasi) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap {{ $totalPagu - $totalRealisasi < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $rupiah($totalPagu - $totalRealisasi) }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $persenTeks($serapanTotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($anggaran['sumber']->isNotEmpty())
            <div class="px-6 py-5 border-t border-slate-100">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Rekap per Sumber Dana</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @foreach ($anggaran['sumber'] as $sumber)
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-700">
                                    {{ $sumber['nama'] }}@if ($sumber['kode']) ({{ $sumber['kode'] }})@endif
                                </p>
                                @if ($sumber['porsi'] !== null)
                                    <p class="text-xs text-slate-400 whitespace-nowrap">{{ $persenTeks($sumber['porsi']) }} dari pagu</p>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pagu <span class="font-semibold text-slate-700">{{ $rupiah($sumber['pagu']) }}</span>
                                · Realisasi <span class="font-semibold text-amber-600">{{ $rupiah($sumber['realisasi']) }}</span>
                            </p>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden mt-2">
                                <div class="h-full rounded-full {{ $warnaBatang($sumber['persen'], $sumber['realisasi'] > $sumber['pagu']) }}"
                                     style="width: {{ $sumber['persen'] === null ? ($sumber['realisasi'] > 0 ? 100 : 0) : min(100, $sumber['persen']) }}%"></div>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Serapan {{ $persenTeks($sumber['persen']) }} · sisa
                                <span class="{{ $sumber['sisa'] < 0 ? 'text-red-600 font-semibold' : '' }}">{{ $rupiah($sumber['sisa']) }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
