@props(['baris', 'total' => null])

@php
    $total ??= [
        'tiket' => $baris->sum('tiket'),
        'transport' => $baris->sum('transport'),
        'harian_jumlah' => $baris->sum('harian_jumlah'),
        'inap_jumlah' => $baris->sum('inap_jumlah'),
        'jumlah' => $baris->sum('jumlah'),
    ];
    $rupiah = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

{{-- Tabelnya lebar; ia menggulung di dalam kotaknya sendiri agar halaman
     tidak ikut bergeser ke samping. --}}
<div class="overflow-x-auto">
    <table class="w-full text-xs border-collapse min-w-[1100px]">
        <thead>
            <tr class="bg-slate-50 text-slate-600">
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold w-10">No.</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold text-left">Nama / Gol</th>
                <th colspan="2" class="border border-slate-200 px-2 py-2 font-semibold">Tempat</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold">Lamanya<br>(hari)</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold text-left">Maksud Perjalanan,<br>No. &amp; Tgl. SPPD / Surat Tugas</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold text-right">Tiket<br>(PP)</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold text-right">Transport</th>
                <th colspan="3" class="border border-slate-200 px-2 py-2 font-semibold">Uang Harian / Saku</th>
                <th colspan="3" class="border border-slate-200 px-2 py-2 font-semibold">Uang Penginapan</th>
                <th rowspan="2" class="border border-slate-200 px-2 py-2 font-semibold text-right">Jumlah<br>Pembayaran</th>
            </tr>
            <tr class="bg-slate-50 text-slate-600">
                <th class="border border-slate-200 px-2 py-1.5 font-semibold">Asal</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold">Tujuan</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold">Hari</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold text-right">Biaya</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold text-right">Jumlah</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold">Hari</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold text-right">Biaya</th>
                <th class="border border-slate-200 px-2 py-1.5 font-semibold text-right">Jumlah</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($baris as $item)
                <tr class="text-slate-700">
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['nomor'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 font-semibold">{{ $item['nama'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['asal'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['tujuan'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['lamanya'] }}</td>
                    <td class="border border-slate-200 px-2 py-2">
                        <p>{{ $item['maksud'] }}</p>
                        <p class="text-[11px] text-slate-400 mt-1">
                            SPPD {{ $item['no_sppd'] ?? '—' }}
                            @if ($item['tanggal_sppd'])
                                · {{ $item['tanggal_sppd']->translatedFormat('d M Y') }}
                            @endif
                        </p>
                        <p class="text-[11px] text-slate-400">Surat Tugas {{ $item['no_tugas'] ?? '—' }}</p>
                    </td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['tiket']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['transport']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['harian_hari'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['harian_biaya']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['harian_jumlah']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-center">{{ $item['inap_hari'] }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['inap_biaya']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($item['inap_jumlah']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right font-bold">{{ $rupiah($item['jumlah']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="border border-slate-200 px-3 py-6 text-center text-slate-400">
                        Belum ada pelaksana pada surat tugas ini.
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if ($baris->isNotEmpty())
            <tfoot>
                <tr class="bg-slate-50 font-bold text-slate-800">
                    <td colspan="6" class="border border-slate-200 px-2 py-2 text-center">T O T A L</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($total['tiket']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($total['transport']) }}</td>
                    <td colspan="2" class="border border-slate-200"></td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($total['harian_jumlah']) }}</td>
                    <td colspan="2" class="border border-slate-200"></td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($total['inap_jumlah']) }}</td>
                    <td class="border border-slate-200 px-2 py-2 text-right">{{ $rupiah($total['jumlah']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
