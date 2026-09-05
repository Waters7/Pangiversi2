@props([
    'jenis',
    'aksi',
    'daftar',
    'kelompok' => null,
    'labelKelompok' => [],
    'jumlah' => [],
    'tahun' => null,
    'bulan' => null,
    'tahunTersedia' => null,
    'jumlahBulan' => null,
    'cari' => null,
    'kosong' => 'Belum ada berkas yang menunggu tanggapan Anda.',
])

{{-- Kerangka bersama kedua submenu Rincian Saya: tab status, saringan
     periode, lalu kartu berkas yang dikelompokkan per bulan keberangkatan. --}}

@php
    // Kelompok yang kosong tetap tampil agar letak tabnya tidak berpindah,
    // kecuali "Lainnya" yang hanya berguna saat memang ada isinya.
    $tab = [];

    foreach ($labelKelompok as $kunci => $teks) {
        if ($kunci === 'lainnya' && (int) ($jumlah[$kunci] ?? 0) === 0) {
            continue;
        }

        $tab[$kunci] = [
            'label' => $teks,
            'jumlah' => $jumlah[$kunci] ?? 0,
            'badge' => match ($kunci) {
                'perlu-tanggapan' => 'bg-amber-100 text-amber-700',
                'menunggu-ppk' => 'bg-teal-100 text-teal-700',
                'disanggah' => 'bg-red-100 text-red-700',
                'selesai' => 'bg-emerald-100 text-emerald-700',
                default => 'bg-slate-100 text-slate-500',
            },
        ];
    }

    $tab = ['' => ['label' => 'Semua', 'jumlah' => array_sum($jumlah)]] + $tab;
@endphp

<x-kotak-cari :rute="$aksi" :nilai="$cari"
              petunjuk="Cari no. usulan, no. surat tugas, atau tujuan"
              :sembunyi="['kelompok' => $kelompok, 'tahun' => $tahun, 'bulan' => $bulan]" />

<x-tab-status :aksi="$aksi" kunci="kelompok" :terpilih="$kelompok" :tab="$tab" />

<x-saring-periode
    :aksi="$aksi"
    :tahun="$tahun"
    :bulan="$bulan"
    :tahun-tersedia="$tahunTersedia ?? collect()"
    :jumlah-bulan="$jumlahBulan ?? collect()"
    :ekstra="['kelompok' => $kelompok, 'cari' => $cari]" />

@forelse ($daftar as $periode => $baris)
    <div class="flex items-center gap-3 mt-6 mb-3 first:mt-0">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">{{ $periode }}</p>
        <span class="text-[11px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
            {{ $baris->count() }} berkas
        </span>
        <div class="flex-1 h-px bg-slate-200"></div>
    </div>

    @foreach ($baris as $entri)
        <x-kartu-dokumen-pelaksana :entri="$entri" :jenis="$jenis" />
    @endforeach
@empty
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-16 text-center">
        <div class="flex flex-col items-center gap-2">
            <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13h6M9 17h4"/>
            </svg>
            <p class="text-sm text-slate-400">Tidak ada berkas pada kelompok ini</p>
            <p class="text-xs text-slate-400 max-w-sm leading-relaxed mt-1">{{ $kosong }}</p>
        </div>
    </div>
@endforelse
