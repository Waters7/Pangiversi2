@props([
    'nama',
    'judul',
    'aksi',
    'metode' => 'PUT',
    'tombol' => 'Lanjutkan',
    'warna' => 'teal',
    'ikon' => 'tanda-tangan',
])

@php
    /**
     * Kotak konfirmasi untuk tindakan yang tidak dapat ditarik kembali, seperti
     * menandatangani daftar pengeluaran riil. Dibuka lewat Alpine dengan
     * $dispatch('buka-{nama}'), sehingga satu halaman dapat memuat beberapa
     * kotak tanpa saling bertabrakan.
     */
    $gaya = match ($warna) {
        'emerald' => ['bg-emerald-500 hover:bg-emerald-600', 'bg-emerald-50 text-emerald-600'],
        'amber' => ['bg-amber-500 hover:bg-amber-600', 'bg-amber-50 text-amber-600'],
        'red' => ['bg-red-500 hover:bg-red-600', 'bg-red-50 text-red-600'],
        default => ['bg-teal-500 hover:bg-teal-600', 'bg-teal-50 text-teal-600'],
    };

    [$gayaTombol, $gayaIkon] = $gaya;
@endphp

<div x-data="{ terbuka: false, mengirim: false }"
     x-on:buka-{{ $nama }}.window="terbuka = true"
     x-show="terbuka"
     x-cloak
     @keydown.escape.window="terbuka = false"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4">

    <div x-show="terbuka" x-transition.opacity
         class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]"
         @click="terbuka = false"></div>

    <div x-show="terbuka" x-transition
         class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden"
         role="dialog" aria-modal="true">

        <div class="p-6">
            <div class="flex gap-4">
                <span class="w-11 h-11 rounded-xl shrink-0 flex items-center justify-center {{ $gayaIkon }}">
                    @if ($ikon === 'tanda-tangan')
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 19c3-1 5-4 5-7a2 2 0 10-4 0c0 4 4 6 8 6 3 0 5-1 7-3"/>
                            <path d="M14 8l3-3 3 3-3 3z"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                        </svg>
                    @endif
                </span>

                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-slate-800">{{ $judul }}</h3>
                    <div class="text-sm text-slate-500 leading-relaxed mt-1.5 space-y-2">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="terbuka = false" x-bind:disabled="mengirim"
                    class="px-4 py-2.5 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-sm font-semibold rounded-xl transition disabled:opacity-50">
                Batal
            </button>

            <form method="POST" action="{{ $aksi }}" @submit="mengirim = true">
                @csrf
                @method($metode)
                {{ $tambahan ?? '' }}

                <button type="submit" x-bind:disabled="mengirim"
                        class="px-5 py-2.5 {{ $gayaTombol }} text-white text-sm font-bold rounded-xl transition shadow-sm disabled:opacity-60 disabled:cursor-wait">
                    <span x-show="! mengirim">{{ $tombol }}</span>
                    <span x-show="mengirim" x-cloak>Memproses…</span>
                </button>
            </form>
        </div>
    </div>
</div>
