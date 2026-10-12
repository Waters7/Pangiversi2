@props([
    'aksi',
    'pilihan',
    'terpilih' => null,
])

{{-- Judul kolom yang mengurutkan daftar saat diklik.
     $pilihan memuat nilai urutan kolom ini; bila dua, klik berikutnya pada
     kolom yang sedang aktif membalik arahnya. Saringan lain tetap terbawa. --}}
@php
    $pilihan = array_values((array) $pilihan);
    $posisi = array_search($terpilih, $pilihan, true);
    $aktif = $posisi !== false;
    $berikut = $aktif ? $pilihan[($posisi + 1) % count($pilihan)] : $pilihan[0];

    $isi = array_filter(
        array_merge(collect(request()->query())->except(['urut', 'page'])->all(), ['urut' => $berikut]),
        fn ($nilai) => filled($nilai),
    );
@endphp

<a href="{{ $aksi.'?'.http_build_query($isi) }}"
   {{ $attributes->class([
       'inline-flex items-center gap-1 uppercase tracking-wide transition hover:text-teal-600',
       'text-teal-600' => $aktif,
   ]) }}>
    {{ $slot }}
    @if ($aktif)
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
            @if ($posisi === 0)
                <path d="M6 9l6 6 6-6"/>
            @else
                <path d="M18 15l-6-6-6 6"/>
            @endif
        </svg>
    @else
        <svg class="w-3 h-3 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M8 9l4-4 4 4M16 15l-4 4-4-4"/>
        </svg>
    @endif
</a>
