@props(['menunggu'])

{{-- Pelaksana satu surat tugas yang belum tercantum: daftarnya terbit begitu
     satu pelaksana tuntas, yang lain bertambah sendiri setelah berkasnya
     ditandatangani PPK. --}}
@if ($menunggu->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800']) }}>
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>
            <span class="font-semibold">{{ $menunggu->count() }} pelaksana lain pada surat tugas ini belum tercantum:</span>
            {{ $menunggu->join(', ') }}.
            Mereka bertambah otomatis ke daftar ini begitu rincian biaya dan daftar riilnya ditandatangani PPK.
        </p>
    </div>
@endif
