@props(['logs', 'judul' => 'Jejak Audit'])

{{-- Timeline jejak audit sebuah usulan: siapa, kapan, aksi apa, dan catatannya (FR-14) --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
        </div>
        <div>
            <h3 class="font-bold text-slate-800 text-sm">{{ $judul }}</h3>
            <p class="text-xs text-slate-400">{{ $logs->count() }} tindakan tercatat</p>
        </div>
    </div>

    <div class="px-6 py-5">
        @forelse ($logs as $log)
            <div class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                {{-- Garis penghubung antar titik --}}
                @unless ($loop->last)
                    <span class="absolute left-[15px] top-8 bottom-0 w-px bg-slate-200"></span>
                @endunless

                <span class="relative z-10 w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-[10px] font-bold {{ $log->aksi_badge }}">
                    {{ mb_strtoupper(mb_substr($log->aksi, 0, 2)) }}
                </span>

                <div class="min-w-0 flex-1 pt-0.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $log->aksi_badge }}">{{ $log->aksi_label }}</span>
                        @if ($log->adaPerubahanStatus())
                            <span class="text-[11px] text-slate-400">{{ $log->status_lama_label }} → <span class="font-semibold text-slate-600">{{ $log->status_baru_label }}</span></span>
                        @endif
                    </div>

                    <p class="text-sm text-slate-700 mt-1.5">{{ $log->deskripsi }}</p>

                    @if ($log->catatan)
                        <p class="mt-1.5 px-3 py-2 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-800">
                            <span class="font-semibold">Catatan:</span> {{ $log->catatan }}
                        </p>
                    @endif

                    <p class="text-[11px] text-slate-400 mt-1.5">
                        {{ $log->pelaku?->nama ?? 'Sistem' }}
                        · {{ $log->created_at->translatedFormat('d M Y, H:i') }}
                        <span class="text-slate-300">({{ $log->created_at->diffForHumans() }})</span>
                    </p>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-400">Belum ada tindakan tercatat</p>
        @endforelse
    </div>
</div>
