@props(['jumlah' => 0, 'posisi' => 'kanan'])

{{-- Lencana angka pada menu sidebar: berapa berkas menunggu tindakan di
     balik menu itu. Menu tanpa antrean tidak menampilkan apa pun, supaya
     angka yang muncul benar-benar berarti ada yang perlu dikerjakan. --}}
@if ($jumlah > 0)
    <span class="{{ $posisi === 'kanan' ? 'ml-auto' : '' }} shrink-0 text-[10px] font-bold leading-none px-1.5 py-1 rounded-full bg-teal-500 text-white tabular-nums"
          title="{{ $jumlah }} berkas menunggu tindakan Anda">
        {{ $jumlah > 99 ? '99+' : $jumlah }}
    </span>
@endif
