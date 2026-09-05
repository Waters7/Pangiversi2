@extends('app')

@section('title', 'Rincian Biaya Perjadin')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Rincian Biaya Perjadin</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Seluruh komponen biaya perjalanan dinas Anda kecuali transport lokal, yang
            sudah pindah ke daftar pengeluaran riil (Lampiran II PMK 113/PMK.05/2012).
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
        kosong="Rincian biaya akan muncul di sini setelah tim keuangan memvalidasi
                nominalnya dan mengirimkannya kepada Anda." />

</div>

@endsection
