@props([
    'judul' => '',
    'terpenuhi' => false,
    'isi' => null,
    'kosong' => 'Belum diisi',
])

{{-- Satu baris kelengkapan profil: hijau bila sudah diisi, kuning bila belum. --}}
<div {{ $attributes->class([
        'flex items-start gap-3 px-4 py-3 rounded-xl border',
        'bg-emerald-50/60 border-emerald-100' => $terpenuhi,
        'bg-amber-50/60 border-amber-100' => ! $terpenuhi,
    ]) }}>

    <span @class([
        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
        'bg-emerald-100 text-emerald-700' => $terpenuhi,
        'bg-amber-100 text-amber-700' => ! $terpenuhi,
    ])>
        {{ $slot }}
    </span>

    <div class="min-w-0 flex-1">
        <p class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
            {{ $judul }}
            @if ($terpenuhi)
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7"/>
                </svg>
            @endif
        </p>
        <p @class([
            'text-[11px] mt-0.5 leading-relaxed break-words',
            'text-slate-600' => $terpenuhi,
            'text-amber-700' => ! $terpenuhi,
        ])>
            {{ $terpenuhi ? $isi : $kosong }}
        </p>
    </div>
</div>
