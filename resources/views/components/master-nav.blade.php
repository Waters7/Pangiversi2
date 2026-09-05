{{-- Sub navigasi antar halaman master data referensi --}}
@php
    $tautan = [
        ['route' => 'master', 'label' => 'Ringkasan', 'aktif' => 'master'],
        ['route' => 'master.unit-kerja', 'label' => 'Unit Kerja', 'aktif' => 'master.unit-kerja'],
        ['route' => 'master.lokasi', 'label' => 'Lokasi Tujuan', 'aktif' => 'master.lokasi'],
        ['route' => 'master.kategori-perjadin', 'label' => 'Kategori Perjadin', 'aktif' => 'master.kategori-perjadin'],
        ['route' => 'master.komponen-biaya', 'label' => 'Komponen Biaya', 'aktif' => 'master.komponen-biaya'],
        ['route' => 'master.status-hasil', 'label' => 'Status Hasil', 'aktif' => 'master.status-hasil'],
        ['route' => 'master.kategori-pembiayaan', 'label' => 'Kategori Pembiayaan', 'aktif' => 'master.kategori-pembiayaan'],
        ['route' => 'master.akun-pembiayaan', 'label' => 'Akun Pembiayaan', 'aktif' => 'master.akun-pembiayaan'],
        ['route' => 'master.tahun-anggaran', 'label' => 'Tahun Anggaran', 'aktif' => 'master.tahun-anggaran'],
        ['route' => 'kegiatan.index', 'label' => 'Jenis Kegiatan', 'aktif' => 'kegiatan.*'],
    ];
@endphp

<div class="flex gap-2 mb-5 overflow-x-auto pb-1">
    @foreach ($tautan as $item)
        <a href="{{ route($item['route']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                  {{ request()->routeIs($item['aktif'])
                     ? 'bg-teal-500 text-white shadow-sm'
                     : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
