@extends('app')

@section('title', 'Pengumuman')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Pengumuman</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Pemberitahuan dari tim keuangan dan administrator. Yang sedang tayang juga muncul saat masuk ke aplikasi.
            </p>
        </div>

        @if ($kelola)
            <a href="{{ route('pengumuman.create') }}"
               class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Buat Pengumuman
            </a>
        @endif
    </div>

    <x-flash />

    <div class="space-y-4">
        @forelse ($pengumuman as $item)
            <article class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5" data-pengumuman>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-800 text-sm">{{ $item->judul }}</h2>
                            @if ($kelola)
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->badgeStatus() }}">{{ $item->status() }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $item->mulai_tayang->translatedFormat('d F Y') }}
                            @if ($item->selesai_tayang)
                                — {{ $item->selesai_tayang->translatedFormat('d F Y') }}
                            @endif
                            @if ($item->pembuat)
                                · {{ $item->pembuat->nama }}
                            @endif
                        </p>
                    </div>

                    @if ($kelola)
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('pengumuman.edit', $item) }}"
                               class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">Ubah</a>
                            <form method="POST" action="{{ route('pengumuman.destroy', $item) }}"
                                  x-data @submit.prevent="if (confirm('Hapus pengumuman ini?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-xs font-semibold rounded-lg transition">Hapus</button>
                            </form>
                        </div>
                    @endif
                </div>

                <p class="text-sm text-slate-600 leading-relaxed mt-3 whitespace-pre-line break-words">{{ $item->isi }}</p>
            </article>
        @empty
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-10 text-center">
                <p class="text-sm text-slate-500">Belum ada pengumuman.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $pengumuman->links() }}
    </div>

</div>

@endsection
