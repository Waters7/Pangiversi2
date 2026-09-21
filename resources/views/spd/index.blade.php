@extends('app')

@section('title', 'Surat Perjalanan Dinas')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800">Surat Perjalanan Dinas</h1>
            {{-- Keterangan cakupan ditulis apa adanya supaya tidak ada yang
                 mengira daftarnya memuat SPD seluruh pegawai. --}}
            @if ($bolehLihatSemua)
                <p class="text-sm text-slate-500 mt-1">Seluruh SPD yang pernah dibuat di lingkungan Poltekkes Kemenkes Manado.</p>
            @else
                <p class="text-sm text-slate-500 mt-1">Daftar SPD yang Anda buat atau yang mencantumkan nama Anda.</p>
            @endif
        </div>
        <a href="{{ route('spd.create') }}"
           class="px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition whitespace-nowrap self-start">
            + Buat SPD
        </a>
    </div>

    @if (session('success'))
        <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <x-kotak-cari :rute="route('spd.index')" :nilai="$cari"
                  petunjuk="Cari nomor surat, nama pelaksana, tujuan, atau maksud"
                  :sembunyi="['tahun' => $tahun, 'bulan' => $bulan]" />

    <x-saring-periode
        :aksi="route('spd.index')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['cari' => $cari]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <th class="px-4 py-3 font-semibold">Nomor Surat</th>
                        <th class="px-4 py-3 font-semibold">Pelaksana</th>
                        @if ($bolehLihatSemua)
                            <th class="px-4 py-3 font-semibold">Dibuat Oleh</th>
                        @endif
                        <th class="px-4 py-3 font-semibold">Tujuan</th>
                        <th class="px-4 py-3 font-semibold">Berangkat</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($spd as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                {{ $item->nomorUntuk(auth()->user()) ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800 text-xs sm:text-sm">{{ $item->ringkasan }}</p>
                            </td>
                            @if ($bolehLihatSemua)
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $item->pembuat?->nama ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3 text-slate-600">{{ $item->tempat_tujuan }}</td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                {{ $item->tanggal_berangkat?->translatedFormat('d M Y') }}
                                <span class="text-slate-400">· {{ $item->lama_hari }} hari</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    {{-- Disembunyikan bila tak berwenang, supaya tautannya tidak
                                         menjanjikan sesuatu yang berujung halaman 403. --}}
                                    @if ($item->bolehDiubahOleh(auth()->user()))
                                    <a href="{{ route('spd.edit', $item) }}"
                                       class="text-slate-500 hover:text-teal-600 text-xs font-bold">Ubah</a>
                                    @endif
                                    <a href="{{ route('spd.cetak', $item) }}"
                                       class="text-slate-500 hover:text-teal-600 text-xs font-bold">Unduh</a>
                                    <a href="{{ route('spd.show', $item) }}"
                                       class="text-teal-600 hover:text-teal-700 text-xs font-bold">Detail →</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $bolehLihatSemua ? 6 : 5 }}" class="px-4 py-12 text-center">
                                <p class="text-slate-500 text-sm mb-3">Belum ada Surat Perjalanan Dinas.</p>
                                <a href="{{ route('spd.create') }}" class="text-teal-600 hover:text-teal-700 text-sm font-bold">
                                    Buat SPD Pertama →
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($spd->hasPages())
        <div class="mt-5">{{ $spd->links() }}</div>
    @endif
</div>
@endsection
