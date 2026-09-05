@props([
    'nomor',
    'judul',
    'terakhir' => false,
])

{{-- Satu langkah pada panduan, dirangkai sebagai garis waktu vertikal. --}}
<div class="relative flex gap-4 {{ $terakhir ? '' : 'pb-6' }}">
    @unless ($terakhir)
        <span class="absolute left-[15px] top-9 bottom-0 w-px bg-slate-200"></span>
    @endunless

    <span class="relative z-10 w-8 h-8 rounded-full bg-teal-500 text-white shrink-0 flex items-center justify-center text-xs font-bold">
        {{ $nomor }}
    </span>

    <div class="min-w-0 flex-1 pt-1">
        <p class="text-sm font-bold text-slate-800">{{ $judul }}</p>
        <div class="text-xs text-slate-600 mt-1.5 leading-relaxed">
            {{ $slot }}
        </div>
    </div>
</div>
