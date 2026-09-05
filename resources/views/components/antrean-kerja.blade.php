@props(['antrean' => []])

{{-- Apa yang menunggu tindakan orang ini, di paling atas halaman muka.
     Sebelumnya halaman muka hanya memuat perjalanan pribadi, sehingga
     bendahara dan tim keuangan — yang jarang bepergian sendiri — masuk ke
     halaman nyaris kosong lalu harus mencari sendiri menu tempat
     pekerjaannya berada. --}}
@if (! empty($antrean))
    <section class="mb-6">
        <div class="flex items-center gap-2.5 mb-3">
            <h2 class="text-sm font-bold text-slate-700">Menunggu Tindakan Anda</h2>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-teal-100 text-teal-700">
                {{ collect($antrean)->sum('jumlah') }}
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($antrean as $baris)
                <a href="{{ $baris['tautan'] }}"
                   class="group flex items-start gap-3 bg-white rounded-xl border border-slate-200 hover:border-teal-300 hover:shadow-sm px-4 py-3.5 transition">
                    <span class="shrink-0 min-w-9 h-9 px-2 rounded-lg bg-teal-50 text-teal-700 font-black text-base flex items-center justify-center tabular-nums">
                        {{ $baris['jumlah'] }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 leading-snug group-hover:text-teal-700 transition">
                            {{ $baris['label'] }}
                        </span>
                        <span class="block text-xs text-slate-400 mt-0.5 leading-snug">
                            {{ $baris['keterangan'] }}
                        </span>
                    </span>
                    <svg class="w-4 h-4 shrink-0 ml-auto mt-1 text-slate-300 group-hover:text-teal-500 transition"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endforeach
        </div>
    </section>
@endif
