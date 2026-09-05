@extends('app')

@section('title', 'Riwayat Tanda Tangan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Riwayat Tanda Tangan</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Seluruh dokumen yang pernah ditandatangani PPK, terbaru lebih dulu.
        </p>
    </div>

    <x-tab-status
        :aksi="route('persetujuan.riwayat')"
        kunci="jenis"
        :terpilih="$jenis"
        :tab="[
            '' => ['label' => 'Semua Dokumen', 'jumlah' => $jumlah['semua']],
            'berkas' => ['label' => 'Rincian Biaya & Daftar Riil', 'jumlah' => $jumlah['berkas'], 'badge' => 'bg-blue-100 text-blue-700'],
            'nominatif' => ['label' => 'Daftar Nominatif', 'jumlah' => $jumlah['nominatif'], 'badge' => 'bg-violet-100 text-violet-700'],
        ]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-semibold">Ditandatangani</th>
                        <th class="px-5 py-3 font-semibold">Dokumen</th>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-5 py-3 font-semibold text-right">Nilai</th>
                        <th class="px-5 py-3 font-semibold">Penandatangan</th>
                        <th class="px-5 py-3 font-semibold w-24"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($riwayat as $baris)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <p class="font-semibold text-slate-800">{{ $baris['waktu']->translatedFormat('d F Y') }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $baris['waktu']->format('H:i') }} WITA</p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full
                                             {{ $baris['jenis'] === 'nominatif' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $baris['label'] }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-700">{{ $baris['nomor'] }}</p>
                                @if ($baris['keterangan'])
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $baris['keterangan'] }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-semibold text-slate-800">
                                {{ $baris['nilai'] !== null ? 'Rp '.number_format($baris['nilai'], 0, ',', '.') : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $baris['ppk']?->nama ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($baris['tautan'])
                                    <a href="{{ $baris['tautan'] }}"
                                       class="text-xs font-semibold text-teal-600 hover:underline">Cetak</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-500">Belum ada dokumen yang ditandatangani</p>
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Riwayat terisi sendiri setiap kali Anda menandatangani berkas.
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
