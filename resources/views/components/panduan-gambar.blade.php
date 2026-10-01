@props([
    'berkas',
    'judul',
    'ukuran' => 'penuh',
])

@php
    $lebar = [
        'penuh' => 'max-w-3xl',
        'sedang' => 'max-w-xl',
        'kecil' => 'max-w-xs',
    ][$ukuran] ?? 'max-w-3xl';
    $sumber = asset('images/panduan/'.$berkas.'.webp');
@endphp

{{-- Tangkapan layar pada panduan, memakai data contoh. Angka merah pada
     gambar merujuk ke langkah yang sedang dibaca; ketuk untuk memperbesar. --}}
<figure x-data="{ besar: false }" class="mt-3 mb-1 {{ $lebar }}">
    <button type="button" @click="besar = true"
            class="group relative block w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 hover:border-teal-300 transition cursor-zoom-in"
            aria-label="Perbesar gambar: {{ $judul }}">
        <img src="{{ $sumber }}" alt="{{ $judul }}" loading="lazy" decoding="async" class="block w-full h-auto">
        <span class="absolute right-2 bottom-2 inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-900/70 text-white text-[10px] font-semibold opacity-0 group-hover:opacity-100 transition">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3M11 8v6M8 11h6"/>
            </svg>
            Perbesar
        </span>
    </button>
    <figcaption class="mt-1.5 text-[11px] text-slate-500 leading-relaxed">{{ $judul }}</figcaption>

    <template x-teleport="body">
        <div x-show="besar" x-cloak x-transition.opacity
             @keydown.escape.window="besar = false" @click="besar = false"
             class="fixed inset-0 z-[60] bg-slate-900/80 flex flex-col items-center justify-center gap-3 p-4 cursor-zoom-out"
             role="dialog" aria-label="{{ $judul }}">
            <img src="{{ $sumber }}" alt="{{ $judul }}" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl bg-white">
            <p class="text-xs text-white/80 text-center max-w-2xl">{{ $judul }}</p>
        </div>
    </template>
</figure>
