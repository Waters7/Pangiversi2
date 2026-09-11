{{-- Tabel laporan perjalanan dinas di meja pimpinan: satu baris per
     perjalanan, dengan tahap laporannya dan tombol menuju halaman keputusan. --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                    <th class="px-4 py-3 font-semibold">Pelaksana</th>
                    <th class="px-4 py-3 font-semibold">No. Usulan</th>
                    <th class="px-4 py-3 font-semibold">Tujuan</th>
                    <th class="px-4 py-3 font-semibold">Tanggal</th>
                    <th class="px-4 py-3 font-semibold">Status Laporan</th>
                    <th class="px-4 py-3 font-semibold">Dikirim</th>
                    <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($usulan as $item)
                    @php
                        $laporan = $item->laporan;
                        $statusLaporan = $laporan?->status() ?? \App\Enums\StatusLaporanPerjadin::Draf;
                    @endphp

                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-4 py-3.5">
                            <p class="font-semibold text-slate-700">{{ $item->user?->nama }}</p>
                            <p class="text-xs text-slate-400">{{ $item->user?->unit?->nama }}</p>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-slate-700">{{ $item->no_usulan }}</td>
                        <td class="px-4 py-3.5 text-slate-600">{{ $item->lokasi }}</td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($item->tanggal_mulai)->translatedFormat('d M') }}–{{ \Carbon\Carbon::parse($item->tanggal_selesai)->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $statusLaporan->badge() }}">
                                {{ $statusLaporan->label() }}
                            </span>
                            @if ($laporan?->sudahDikonfirmasi())
                                <p class="text-[11px] text-slate-400 mt-1">oleh {{ $laporan->pimpinan?->nama }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs whitespace-nowrap">
                            {{ $laporan?->dikirim_at?->translatedFormat('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                @if ($laporan?->sudahDikirim())
                                    <a href="{{ route('laporan-perjadin.show', $item->no_usulan) }}"
                                       class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition">
                                        Periksa
                                    </a>
                                @else
                                    <a href="{{ route('laporan-perjadin.show', $item->no_usulan) }}"
                                       class="text-xs font-bold text-slate-500 hover:text-teal-600">Lihat</a>
                                @endif
                                @if ($laporan?->sudahSelesai())
                                    <a href="{{ route('dokumen.laporan.cetak', $item->no_usulan) }}"
                                       class="text-xs font-bold text-slate-500 hover:text-teal-600">Unduh</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center">
                            <svg class="w-10 h-10 text-slate-200 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <p class="text-sm text-slate-400 mt-2">{{ $kosong ?? 'Tidak ada laporan pada tahap ini' }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$usulan" />
</div>
