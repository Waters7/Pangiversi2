@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('usulan.list') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Usulan</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }}</p>
        </div>
        <div class="ml-auto">
            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $usulan->status_badge }}">
                {{ $usulan->status_text }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Kiri: Informasi Utama --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Data Perjalanan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Data Perjalanan Dinas</h3>
                        <p class="text-xs text-slate-400">Informasi utama usulan</p>
                    </div>
                </div>

                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">No. Usulan</dt>
                            <dd class="text-sm font-bold text-slate-800">{{ $usulan->no_usulan }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Jenis Kegiatan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->kegiatan?->nama ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Dasar Penugasan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->no_tugas }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Lokasi / Kota Tujuan</dt>
                            <dd class="text-sm text-slate-800 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                {{ $usulan->lokasi }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Instansi Tujuan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->instansi }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Mulai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_mulai)) }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Selesai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_selesai)) }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Durasi</dt>
                            <dd class="text-sm text-slate-800">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-700 rounded-lg text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    {{ $usulan->durasi }} hari — {{ $usulan->periode }}
                                </span>
                            </dd>
                        </div>

                        @if($usulan->uraian)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Uraian Tujuan</dt>
                            <dd class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $usulan->uraian }}</dd>
                        </div>
                        @endif

                    </dl>
                </div>
            </div>

            {{-- Penolakan (jika ditolak) --}}
            @if($usulan->status === 'ditolak' && $usulan->alasan_ditolak)
            <div class="bg-red-50 rounded-2xl border border-red-100 p-5 flex gap-3">
                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <p class="text-sm font-bold text-red-700 mb-1">Alasan Penolakan</p>
                    <p class="text-sm text-red-600 leading-relaxed">{{ $usulan->alasan_ditolak }}</p>
                </div>
            </div>
            @endif

            {{-- Timeline Status --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm">Riwayat Status</h3>
                </div>
                <div class="p-6">
                    @php
                        $allStatuses = ['draft', 'diajukan', 'menunggu', 'disetujui', 'selesai'];
                        $currentIndex = array_search($usulan->status, $allStatuses);
                        $isTolak = $usulan->status === 'ditolak';
                    @endphp
                    <ol class="relative border-l border-slate-200 ml-3 space-y-6">
                        @foreach($allStatuses as $i => $s)
                            @php
                                $isDone = !$isTolak && $currentIndex !== false && $i <= $currentIndex;
                                $isCurrent = !$isTolak && $i === $currentIndex;
                            @endphp
                            <li class="ml-6">
                                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white
                                    {{ $isDone ? 'bg-teal-500' : 'bg-slate-200' }}">
                                    @if($isDone)
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    @endif
                                </span>
                                <p class="text-xs font-semibold {{ $isCurrent ? 'text-teal-600' : ($isDone ? 'text-slate-700' : 'text-slate-400') }}">
                                    {{ ucfirst($s) }}
                                    @if($isCurrent) <span class="ml-1 text-[10px] font-bold bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded-full">Saat ini</span> @endif
                                </p>
                            </li>
                        @endforeach
                        @if($isTolak)
                            <li class="ml-6">
                                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white bg-red-500">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                </span>
                                <p class="text-xs font-semibold text-red-600">
                                    Ditolak <span class="ml-1 text-[10px] font-bold bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">Saat ini</span>
                                </p>
                            </li>
                        @endif
                    </ol>
                </div>
            </div>

        </div>

        {{-- Kanan: Sidebar Info --}}
        <div class="xl:col-span-1 space-y-4">

            {{-- Pengusul --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Informasi Pengusul</h3>
                <div class="flex items-center gap-3 mb-4">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($usulan->user?->name ?? 'U') }}&background=14b8a6&color=fff"
                         class="w-10 h-10 rounded-full shrink-0" alt="avatar">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $usulan->user?->name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $usulan->user?->email ?? '—' }}</p>
                    </div>
                </div>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Dibuat</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Diperbarui</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->updated_at->format('d M Y, H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Aksi --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                @if(in_array($usulan->status, ['draft']))
                    <a href="#"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Ajukan Sekarang
                    </a>
                    <a href="{{ route('usulan.edit', $usulan) }}"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Usulan
                    </a>
                @endif

                <a href="{{ route('usulan.list') }}"
                   class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                    Kembali ke List
                </a>

                @if(in_array($usulan->status, ['draft', 'diajukan']))
                    <form method="POST" action="{{ route('usulan.destroy', $usulan) }}"
                          x-data
                          @submit.prevent="if(confirm('Hapus usulan {{ $usulan->no_usulan }}?\nTindakan ini tidak dapat dibatalkan.')) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-red-200 bg-white text-red-600 text-sm font-semibold rounded-xl hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                            </svg>
                            Hapus Usulan
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

</div>

@endsection
