@props(['daftar'])

{{-- Siapa saja pelaksana di bawah surat tugas ini dan sejauh mana tanda
     tangannya. Yang sudah disahkan PPK tercantum pada daftar; yang lain
     menyusul begitu berkasnya disahkan. --}}
@php
    $gaya = [
        'ppk' => 'bg-emerald-100 text-emerald-700',
        'pelaksana' => 'bg-teal-100 text-teal-700',
        'menunggu-pelaksana' => 'bg-amber-100 text-amber-700',
        'disanggah' => 'bg-red-100 text-red-700',
        'keuangan' => 'bg-slate-100 text-slate-600',
        'draf' => 'bg-slate-100 text-slate-500',
    ];
    $sudah = $daftar->where('tahap', 'ppk')->count();
@endphp

@if ($daftar->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3']) }}>
        <p class="text-xs font-bold text-slate-600 mb-2">
            Tanda tangan pelaksana
            <span class="ml-1 font-semibold text-slate-400">{{ $sudah }} dari {{ $daftar->count() }} sudah disahkan PPK</span>
        </p>
        <ul class="space-y-1.5">
            @foreach ($daftar as $orang)
                <li class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <span class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full {{ $gaya[$orang['tahap']] ?? 'bg-slate-100 text-slate-600' }}">
                        @if ($orang['tahap'] === 'ppk')
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        @endif
                        {{ $orang['nama'] }}
                    </span>
                    <span class="text-slate-500">
                        {{ $orang['keterangan'] }}
                        @if ($orang['waktu'])
                            · {{ $orang['waktu']->translatedFormat('d M Y H:i') }}
                        @endif
                        @unless ($orang['tercantum'])
                            <span class="text-slate-400">— belum tercantum pada daftar</span>
                        @endunless
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
