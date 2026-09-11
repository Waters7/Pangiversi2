{{-- Spanduk bagi peran yang hanya memantau modul keuangan (PPK, pimpinan):
     seluruh usulan terbuka untuk dibaca, tanpa satu pun tombol ubah. --}}
@if (auth()->user()->hanyaMelihatKeuangan())
    <div {{ $attributes->merge(['class' => 'mb-5 flex items-start gap-3 px-5 py-3.5 bg-sky-50 border border-sky-200 rounded-xl']) }}>
        <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <div>
            <p class="text-sm font-bold text-sky-800">Mode lihat saja</p>
            <p class="text-xs text-sky-700 mt-0.5 leading-relaxed">
                Sebagai {{ auth()->user()->peran->label() }}, Anda dapat membuka rincian biaya, pembayaran,
                dan bukti setiap usulan tanpa mengubahnya. Penyusunan angka, validasi, dan pencatatan
                pembayaran dikerjakan tim keuangan dan bendahara.
            </p>
        </div>
    </div>
@endif
