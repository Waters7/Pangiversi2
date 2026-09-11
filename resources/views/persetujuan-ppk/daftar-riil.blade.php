@extends('app')

@section('title', 'Persetujuan — Daftar Riil')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Verifikasi Daftar Riil Transportasi Pelaksana</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Biaya transport yang dinyatakan pelaksana, menunggu verifikasi dan tanda tangan Anda
        </p>
    </div>

    <x-flash />

    <x-kotak-cari :rute="route('persetujuan.daftar-riil')" :nilai="$cari"
                  petunjuk="Cari nama pelaksana, no. usulan, atau no. surat tugas"
                  :sembunyi="['status' => $status]" />

    {{-- Tab disusun menurut apa yang perlu dikerjakan, bukan menurut status
         mentahnya — supaya PPK langsung melihat antriannya sendiri. --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($label as $kunci => $teks)
            <a href="{{ route('persetujuan.daftar-riil', ['status' => $kunci, 'cari' => $cari]) }}"
               class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                      {{ $status === $kunci
                         ? 'bg-teal-500 text-white shadow-sm'
                         : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $teks }}
                <span class="ml-1.5 text-xs font-bold px-2 py-0.5 rounded-full
                             {{ $status === $kunci ? 'bg-white/25' : 'bg-slate-100 text-slate-500' }}">
                    {{ $jumlah[$kunci] }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Saringan periode: berkas dikelompokkan per bulan keberangkatan. --}}
    <x-saring-periode
        :aksi="route('persetujuan.daftar-riil')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['status' => $status, 'cari' => $cari]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <th class="px-4 py-3 font-semibold">Pelaksana</th>
                        <th class="px-4 py-3 font-semibold">No. Usulan</th>
                        <th class="px-4 py-3 font-semibold">Surat Tugas</th>
                        <th class="px-4 py-3 font-semibold text-right">Total Riil</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($daftar as $periode => $kelompokPeriode)
                        <tr class="bg-slate-100/70">
                            <td colspan="6" class="px-4 py-2 text-xs font-bold text-slate-600 uppercase tracking-wide">
                                {{ $periode }}
                                <span class="ml-1.5 font-semibold text-slate-400 normal-case tracking-normal">{{ $kelompokPeriode->count() }} berkas</span>
                            </td>
                        </tr>
                    @foreach ($kelompokPeriode as $item)
                        @php $usulan = $item->usulan; @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-slate-800">{{ $item->peserta?->nama ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $usulan?->lokasi }}</p>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-xs text-slate-600">{{ $usulan?->no_usulan }}</td>
                            <td class="px-4 py-3.5 font-mono text-xs text-slate-500">{{ $usulan?->no_tugas ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-800">
                                Rp {{ number_format($item->total_riil, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->status_badge }}">
                                    {{ $item->status_label }}
                                </span>
                                @if ($item->masaSanggahBerjalan())
                                    <p class="text-[11px] text-slate-400 mt-1">Sisa {{ $item->sisaHariSanggah() }} hari</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    @if ($usulan)
                                        {{-- Berkas sampai ke sini sudah divalidasi tim keuangan
                                             dan disetujui pelaksana. Yang tersisa: mengesahkannya,
                                             atau mengembalikannya untuk diperbaiki. --}}
                                        @if ($item->siapDitandatanganiPpk() && ! $item->sudah_ditandatangani)
                                            <x-aksi-tanda-tangan
                                                :nama="'riil-'.$item->id"
                                                judul="Tandatangani daftar pengeluaran riil ini?"
                                                :ringkas="$usulan->no_usulan.' · Rp '.number_format($item->total_riil, 0, ',', '.')"
                                                :aksi-tanda-tangan="route('daftar-riil.tanda-tangan', [$usulan->no_usulan, $item->id_peserta])"
                                                :aksi-kembalikan="route('daftar-riil.kembalikan', [$usulan->no_usulan, $item->id_peserta])" />
                                        @endif

                                        @if ($item->sudah_ditandatangani)
                                            <a href="{{ route('daftar-riil.cetak', [$usulan->no_usulan, $item->id_peserta]) }}"
                                               class="text-xs font-bold text-slate-500 hover:text-teal-600">Unduh</a>
                                        @endif

                                        <a href="{{ route('daftar-riil.show', $usulan->no_usulan) }}"
                                           class="text-xs font-bold text-slate-500 hover:text-teal-600">Detail →</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <svg class="w-10 h-10 text-slate-200 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-slate-400 mt-2">Tidak ada berkas pada kelompok ini</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
