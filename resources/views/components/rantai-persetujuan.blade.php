@props(['usulan'])

@php
    use App\Enums\LevelPersetujuan;
    use App\Models\Persetujuan;

    $status = $usulan->status_enum;
    $levelBerjalan = $status->level();

    // Keputusan terakhir per tahap — sebuah usulan bisa melewati tahap yang
    // sama lebih dari sekali bila sempat dikembalikan untuk revisi.
    $keputusan = $usulan->persetujuan->keyBy(fn ($p) => $p->level->value);
@endphp

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h3 class="font-bold text-slate-800 text-sm">Rantai Persetujuan</h3>
            <p class="text-xs text-slate-400">Status saat ini: {{ $status->label() }}</p>
        </div>
    </div>

    <div class="px-6 py-5">
        <ol class="relative border-l border-slate-200 ml-3 space-y-6">
            @foreach (LevelPersetujuan::urutan() as $level)
                @php
                    $putusan = $keputusan->get($level->value);
                    $sedangBerjalan = $levelBerjalan === $level;
                    $sudahSetuju = $putusan?->keputusan === Persetujuan::KEPUTUSAN_SETUJU;
                    $ditolakDisini = $putusan?->keputusan === Persetujuan::KEPUTUSAN_TOLAK;
                    $revisiDisini = $putusan?->keputusan === Persetujuan::KEPUTUSAN_REVISI;
                @endphp

                <li class="ml-6">
                    <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white
                        {{ $sudahSetuju ? 'bg-teal-500' : ($ditolakDisini ? 'bg-red-500' : ($revisiDisini ? 'bg-amber-500' : ($sedangBerjalan ? 'bg-blue-500' : 'bg-slate-200'))) }}">
                        @if ($sudahSetuju)
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        @elseif ($ditolakDisini)
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        @elseif ($revisiDisini)
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M3 10h10M3 6h14M3 14h6m6 6l4-4-4-4"/></svg>
                        @else
                            <span class="w-2 h-2 rounded-full {{ $sedangBerjalan ? 'bg-white' : 'bg-slate-400' }}"></span>
                        @endif
                    </span>

                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-bold {{ $sedangBerjalan ? 'text-blue-600' : ($putusan ? 'text-slate-700' : 'text-slate-400') }}">
                            {{ $level->value }}. {{ $level->label() }}
                        </p>
                        @if ($sedangBerjalan)
                            <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Menunggu keputusan</span>
                        @elseif ($putusan)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $putusan->keputusan_badge }}">{{ $putusan->keputusan_label }}</span>
                        @endif
                    </div>

                    <p class="text-[11px] text-slate-400 mt-0.5">{{ $level->keterangan() }}</p>

                    @if ($putusan)
                        <p class="text-[11px] text-slate-500 mt-1">
                            {{ $putusan->approver?->nama ?? 'Sistem' }}
                            · {{ $putusan->waktu_keputusan->translatedFormat('d M Y, H:i') }}
                        </p>
                        @if ($putusan->catatan)
                            <p class="mt-1.5 px-3 py-2 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-800">
                                <span class="font-semibold">Catatan:</span> {{ $putusan->catatan }}
                            </p>
                        @endif
                    @endif
                </li>
            @endforeach

            {{-- Tahap akhir --}}
            <li class="ml-6">
                @php
                    $sudahFinal = in_array($status, [\App\Enums\StatusUsulan::Disetujui, \App\Enums\StatusUsulan::Selesai], true);
                @endphp
                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white {{ $sudahFinal ? 'bg-purple-500' : 'bg-slate-200' }}">
                    @if ($sudahFinal)
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    @else
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    @endif
                </span>
                <p class="text-xs font-bold {{ $sudahFinal ? 'text-purple-600' : 'text-slate-400' }}">
                    Dokumen Terbit & Proses Keuangan
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">Surat Tugas dan SPPD dapat diterbitkan setelah seluruh tahap disetujui</p>
            </li>
        </ol>
    </div>
</div>
