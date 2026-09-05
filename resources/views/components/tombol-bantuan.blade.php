@php
    $pengguna = auth()->user();
    $admin = (bool) $pengguna?->isAdmin();

    // Lencana menghitung yang menunggu tanggapan orang yang sedang melihat:
    // bagi administrator laporan yang belum dijawab, bagi pengguna jawaban
    // yang belum dibacanya.
    $menunggu = \App\Models\ObrolanBantuan::query()
        ->when(
            $admin,
            fn ($q) => $q->where('status', \App\Models\ObrolanBantuan::STATUS_TERBUKA),
            fn ($q) => $q->where('id_pelapor', $pengguna?->id)
                ->where('status', \App\Models\ObrolanBantuan::STATUS_DIJAWAB),
        )
        ->count();

    $diBantuan = request()->routeIs('bantuan.*');
@endphp

{{-- Tombol bantuan mengambang: jalan pintas melaporkan kendala dari halaman
     mana pun, tanpa harus mencari menunya lebih dulu. Disembunyikan saat
     halaman bantuan itu sendiri sedang dibuka. --}}
@unless ($diBantuan)
    <div x-data="{ tip: false }"
         class="fixed bottom-5 right-5 z-40 flex items-center gap-3 print:hidden">

        <span x-show="tip" x-cloak x-transition.opacity
              class="hidden sm:block px-3 py-1.5 bg-slate-800 text-white text-xs font-semibold rounded-lg shadow-lg whitespace-nowrap">
            {{ $admin ? 'Laporan kendala pengguna' : 'Ada kendala? Laporkan ke admin' }}
        </span>

        <a href="{{ route('bantuan.index') }}"
           @mouseenter="tip = true" @mouseleave="tip = false"
           @focus="tip = true" @blur="tip = false"
           aria-label="{{ $admin ? 'Laporan kendala pengguna' : 'Laporkan kendala ke administrator' }}"
           class="relative w-14 h-14 rounded-full bg-teal-500 hover:bg-teal-600 text-white
                  flex items-center justify-center shadow-lg shadow-teal-500/30
                  transition hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300">

            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
            </svg>

            @if ($menunggu > 0)
                <span class="absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1.5 rounded-full
                             bg-red-500 text-white text-[11px] font-bold
                             flex items-center justify-center ring-2 ring-slate-100">
                    {{ $menunggu > 9 ? '9+' : $menunggu }}
                </span>
            @endif
        </a>
    </div>
@endunless
