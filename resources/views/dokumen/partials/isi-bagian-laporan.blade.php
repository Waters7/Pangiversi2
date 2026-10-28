{{-- Satu bagian laporan format Kemenkes untuk dibaca: uraian dan foto
     dokumentasinya. Variabel: $bagian, $laporan, $judul (opsional). --}}
@php
    $teks = $laporan->isiBagian($bagian);
    $foto = $laporan->dokumentasiBagian($bagian);
@endphp

<div class="px-6 py-4" data-isi-bagian="{{ $bagian->value }}">
    @if ($judul)
        <p class="text-xs font-bold text-teal-700 mb-1">{{ $judul }}</p>
    @endif
    <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $teks ?? '—' }}</p>

    @if ($foto)
        <a href="{{ route('berkas.lihat', $foto->berkas) }}" target="_blank" rel="noopener"
           class="mt-3 inline-block rounded-xl overflow-hidden border border-slate-200 hover:border-teal-300 transition">
            <img src="{{ route('berkas.lihat', $foto->berkas) }}" alt="Dokumentasi {{ $bagian->judul() }}"
                 class="max-h-48 w-auto object-cover">
        </a>
    @endif
</div>
