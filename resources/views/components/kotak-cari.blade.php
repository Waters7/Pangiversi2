@props([
    'rute',
    'nilai' => null,
    'nama' => 'cari',
    'petunjuk' => 'Cari…',
    'sembunyi' => [],
])

{{-- Kotak cari halaman daftar. Orang biasanya datang membawa satu nomor di
     tangan — nomor surat tugas dari berkas kertas — dan hanya ingin menemukan
     baris itu. Saringan lain yang sedang aktif dibawa serta lewat $sembunyi
     supaya mencari tidak menghapus pilihan tab atau periodenya. --}}
<form method="GET" action="{{ $rute }}" {{ $attributes->merge(['class' => 'mb-5']) }}>
    @foreach ($sembunyi as $kunci => $isi)
        @if (filled($isi))
            <input type="hidden" name="{{ $kunci }}" value="{{ $isi }}">
        @endif
    @endforeach

    <div class="flex flex-col sm:flex-row gap-3">
        <input type="search" name="{{ $nama }}" value="{{ $nilai }}"
               placeholder="{{ $petunjuk }}"
               class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
        <button type="submit"
                class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
            Search
        </button>
        @if (filled($nilai))
            <a href="{{ $rute }}"
               class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">
                Reset
            </a>
        @endif
    </div>
</form>
