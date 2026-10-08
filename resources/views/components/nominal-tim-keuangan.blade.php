@props(['baris'])

{{-- Nominal komponen yang sudah ditetapkan tim keuangan. Menggantikan kolom
     nominal pada berkas pelaksana supaya komponen yang sama tidak diisi dua
     kali dan tercatat ganda pada rincian biaya. --}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 px-4 py-3 rounded-xl bg-slate-50 border border-slate-200']) }} data-nominal-tim-keuangan>
    <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
    </svg>
    <div class="min-w-0">
        <p class="text-sm font-bold text-slate-800">Rp {{ number_format($baris->sum('jumlah'), 0, ',', '.') }}</p>
        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
            Nominal ditetapkan tim keuangan —
            {{ $baris->map(fn ($satu) => $satu->komponen.($satu->isian_pelaksana === \App\Enums\IsianBiaya::TiketPulangPergi ? ' (tiket pergi & pulang)' : ''))->implode(', ') }}.
            Tidak perlu diisi lagi agar tidak tercatat dua kali; bila berbeda dengan bukti Anda, hubungi tim keuangan.
        </p>
    </div>
</div>
