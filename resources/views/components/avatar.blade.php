@props([
    'nama' => '',
    'foto' => null,
    'ukuran' => 'md',
    'cincin' => false,
])

@php
    /**
     * Avatar dirender sendiri. Bila pengguna sudah mengunggah foto, fotonya
     * yang dipakai; selain itu inisial namanya. Sengaja tidak memakai layanan
     * gambar dari luar supaya nama pegawai tidak dikirim ke pihak ketiga dan
     * aplikasi tetap tampil utuh saat dijalankan di jaringan lokal.
     */
    $potongan = collect(preg_split('/\s+/', trim((string) $nama)))
        ->filter()
        // Gelar dan sapaan dilewati agar inisialnya tetap mewakili nama orangnya.
        ->reject(fn (string $kata) => (bool) preg_match(
            '/^(dr|drs|dra|ir|h|hj|prof|s\.|m\.|a\.md|se|st|sh|mm|mkes|ns|apt)\.?,?$/i',
            $kata
        ))
        ->values();

    $inisial = $potongan
        ->take(2)
        ->map(fn (string $kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
        ->implode('');

    if ($inisial === '') {
        $inisial = mb_strtoupper(mb_substr(trim((string) $nama) ?: '?', 0, 1));
    }

    // Warna dipilih dari nama, jadi orang yang sama selalu memakai warna sama.
    $palet = [
        ['#00b39b', '#047d78'],
        ['#00b4d8', '#0369a1'],
        ['#2a78d6', '#1e40af'],
        ['#7c8f18', '#4d5a10'],
        ['#eb6834', '#b3401a'],
        ['#5f6663', '#3b4240'],
    ];

    [$muda, $tua] = $palet[crc32(mb_strtolower(trim((string) $nama))) % count($palet)];

    $dimensi = match ($ukuran) {
        'sm' => 'w-8 h-8 text-[11px]',
        'lg' => 'w-16 h-16 text-lg',
        'xl' => 'w-24 h-24 text-2xl',
        default => 'w-11 h-11 text-sm',
    };
@endphp

@if ($foto)
    <img src="{{ $foto }}" alt="{{ $nama }}"
         {{ $attributes->class([
             'rounded-full object-cover bg-slate-200 select-none shrink-0',
             $dimensi,
             'ring-2 ring-white/20' => $cincin,
         ]) }}>
@else
    <span {{ $attributes->class([
            'inline-flex items-center justify-center rounded-full font-bold text-white select-none shrink-0',
            $dimensi,
            'ring-2 ring-white/20' => $cincin,
        ]) }}
          style="background:linear-gradient(135deg,{{ $muda }},{{ $tua }})"
          title="{{ $nama }}"
          aria-hidden="true">{{ $inisial }}</span>
@endif
