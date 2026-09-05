@extends('app')

@section('title', 'Daftar Riil Transportasi')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Daftar Riil Transportasi</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Daftar pengeluaran riil transportasi lokal Anda (Lampiran IX PMK 113/PMK.05/2012).
            Rincian biaya perjalanan dinas ada pada submenu tersendiri.
        </p>
    </div>

    <x-berkas-pelaksana
        :jenis="$jenis"
        :aksi="$aksi"
        :daftar="$daftar"
        :kelompok="$kelompok"
        :label-kelompok="$labelKelompok"
        :jumlah="$jumlah"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :cari="$cari"
        kosong="Daftar pengeluaran riil transportasi akan muncul di sini setelah tim keuangan
                memeriksanya dan mengirimkannya kepada Anda." />

</div>

@endsection
