@extends('app')

@section('title', 'Notifikasi')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Notifikasi</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $totalBelumDibaca > 0 ? "{$totalBelumDibaca} notifikasi belum dibaca" : 'Semua notifikasi sudah dibaca' }}
            </p>
        </div>
        @if ($totalBelumDibaca > 0)
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                @method('PUT')
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Tandai Semua Dibaca
                </button>
            </form>
        @endif
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 mb-5">
        <a href="{{ route('notifikasi.index') }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $filter !== 'belum' ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            Semua
        </a>
        <a href="{{ route('notifikasi.index', ['filter' => 'belum']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $filter === 'belum' ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            Belum Dibaca
            @if ($totalBelumDibaca > 0)
                <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] {{ $filter === 'belum' ? 'bg-white/25' : 'bg-red-100 text-red-600' }}">{{ $totalBelumDibaca }}</span>
            @endif
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="divide-y divide-slate-50">
            @forelse ($notifikasi as $notif)
                <div class="flex gap-4 px-6 py-4 hover:bg-slate-50/60 transition {{ $notif->sudah_dibaca ? '' : 'bg-teal-50/30' }}">
                    <span class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center {{ $notif->tipe_badge }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-bold text-slate-800">{{ $notif->judul }}</p>
                            @unless ($notif->sudah_dibaca)
                                <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700">Baru</span>
                            @endunless
                        </div>
                        <p class="text-sm text-slate-600 mt-0.5">{{ $notif->pesan }}</p>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ $notif->created_at->translatedFormat('d M Y, H:i') }}
                            <span class="text-slate-300">· {{ $notif->created_at->diffForHumans() }}</span>
                        </p>

                        <div class="flex items-center gap-3 mt-2">
                            @if ($notif->url)
                                <form method="POST" action="{{ route('notifikasi.baca', $notif) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="text-xs font-semibold text-teal-600 hover:underline">Buka detail →</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('notifikasi.destroy', $notif) }}"
                                  x-data
                                  @submit.prevent="if (confirm('Hapus notifikasi ini?')) $el.submit()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-slate-400 hover:text-red-600 transition">Hapus</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                        </svg>
                        <p class="text-sm text-slate-400">
                            {{ $filter === 'belum' ? 'Tidak ada notifikasi yang belum dibaca' : 'Belum ada notifikasi' }}
                        </p>
                    </div>
                </div>
            @endforelse
        </div>

        <x-pagination :paginator="$notifikasi" />
    </div>

</div>

@endsection
