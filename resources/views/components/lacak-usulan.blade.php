@props(['usulan'])

@php
    $pelacak = app(\App\Services\PelacakUsulan::class);
    $tonggak = $pelacak->tonggak($usulan);
    $kemajuan = $pelacak->kemajuan($usulan);
    $berikutnya = $pelacak->langkahBerikutnya($usulan);
    // Lamanya seluruh berkas berjalan, dari dibuat sampai tuntas atau sampai hari ini.
    $lamaBerjalan = $pelacak->lamaBerjalan($usulan);
    $labelLama = $lamaBerjalan === 0 ? 'kurang dari sehari' : $lamaBerjalan.' hari';
@endphp

{{-- Pelacakan berkas: tonggak yang benar-benar menggerakkan perjalanan
     dinas, lengkap dengan waktunya. Yang belum terjadi tetap tampil agar
     jelas berkasnya sedang menunggu apa. --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

    <div class="px-6 py-4 border-b border-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Pelacakan Berkas</h3>
                    <p class="text-xs text-slate-400">
                        {{ $kemajuan['selesai'] }} dari {{ $kemajuan['total'] }} tahap terlewati
                        · berjalan {{ $labelLama }}
                    </p>
                </div>
            </div>

            <span class="text-sm font-bold text-teal-700 tabular-nums">{{ $kemajuan['persen'] }}%</span>
        </div>

        <div class="mt-3 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-teal-500 rounded-full transition-all" style="width: {{ $kemajuan['persen'] }}%"></div>
        </div>

        @if ($berikutnya)
            <p class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                Sedang menunggu: <strong>{{ $berikutnya['judul'] }}</strong>
                @if ($berikutnya['durasi_label'])
                    — {{ mb_strtolower($berikutnya['durasi_label']) }}
                @endif
            </p>
        @else
            <p class="mt-3 text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                Seluruh tahap sudah terlewati — berkas ini tuntas.
            </p>
        @endif
    </div>

    <div class="px-6 py-5">
        @foreach ($tonggak as $langkah)
            <div class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                @unless ($loop->last)
                    <span class="absolute left-[15px] top-8 bottom-0 w-px {{ $langkah['selesai'] ? 'bg-teal-200' : 'bg-slate-200' }}"></span>
                @endunless

                <span class="relative z-10 w-8 h-8 rounded-full shrink-0 flex items-center justify-center
                             {{ $langkah['selesai'] ? 'bg-teal-500 text-white' : 'bg-white border-2 border-slate-200 text-slate-300' }}">
                    @if ($langkah['selesai'])
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    @else
                        <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                    @endif
                </span>

                <div class="min-w-0 flex-1 pt-0.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <p class="text-sm font-semibold {{ $langkah['selesai'] ? 'text-slate-800' : 'text-slate-400' }}">
                            {{ $langkah['judul'] }}
                        </p>

                        @if ($langkah['selesai'])
                            <p class="text-xs font-medium text-slate-500 tabular-nums whitespace-nowrap">
                                {{ $langkah['waktu']->translatedFormat('d M Y, H:i') }}
                            </p>
                        @else
                            <p class="text-[11px] font-bold text-slate-300 uppercase tracking-wide">Belum</p>
                        @endif
                    </div>

                    <p class="text-xs {{ $langkah['selesai'] ? 'text-slate-500' : 'text-slate-400' }} mt-0.5 leading-relaxed">
                        {{ $langkah['keterangan'] }}
                    </p>

                    {{-- Rentang hari dari tahap sebelumnya. Tahap yang sedang
                         menunggu menghitung sampai hari ini, supaya terlihat
                         sudah berapa lama berkas tertahan di situ. --}}
                    @if ($langkah['durasi_label'])
                        <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold tabular-nums
                                     {{ $langkah['selesai'] ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700' }}">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            {{ $langkah['durasi_label'] }}
                        </span>
                    @endif

                    @if ($langkah['selesai'] && $langkah['oleh'])
                        <p class="text-[11px] text-slate-400 mt-1">{{ $langkah['oleh'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
