@extends('app')

@section('title', 'Daftar Tindak Lanjut')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Daftar Tindak Lanjut</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Rencana tindak lanjut dari seluruh laporan perjalanan dinas Anda
            </p>
        </div>
        <a href="{{ route('dokumen') }}"
           class="text-xs font-bold text-teal-600 hover:text-teal-700">← Kembali ke Dokumen</a>
    </div>

    <x-flash />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $tindakLanjut->total() }}</p>
                <p class="text-xs text-slate-400">Total Tindak Lanjut</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">{{ $jumlahBelumSelesai }}</p>
                <p class="text-xs text-slate-400">Belum Tuntas</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('dokumen.tindak-lanjut') }}" class="mb-5">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col sm:flex-row gap-3">
            <select name="status"
                    class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <option value="">Semua status</option>
                @foreach ($statusOptions as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Filter</button>
            @if ($status)
                <a href="{{ route('dokumen.tindak-lanjut') }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition text-center">Reset</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse ($tindakLanjut as $item)
                @php $usulan = $item->laporan?->usulan; @endphp

                <div class="px-4 md:px-6 py-4">
                    <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-800 leading-relaxed">{{ $item->uraian }}</p>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs text-slate-400">
                                @if ($usulan)
                                    <a href="{{ route('dokumen.laporan.edit', $usulan->no_usulan) }}"
                                       class="font-mono font-semibold text-teal-600 hover:underline">{{ $usulan->no_usulan }}</a>
                                    <span>{{ $usulan->lokasi }}</span>
                                @endif

                                @if ($item->penanggung_jawab)
                                    <span>PJ: {{ $item->penanggung_jawab }}</span>
                                @endif

                                @if ($item->target_selesai)
                                    <span class="{{ $item->terlambat() ? 'font-bold text-red-600' : '' }}">
                                        Target {{ $item->target_selesai->translatedFormat('d M Y') }}
                                        @if ($item->terlambat())
                                            · terlambat
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Status dapat diperbarui di tempat, tanpa membuka
                             laporannya — itu yang membuat daftar ini terpakai. --}}
                        <form method="POST" action="{{ route('dokumen.tindak-lanjut.status', $item) }}"
                              class="shrink-0 flex items-center gap-2">
                            @csrf @method('PUT')
                            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $item->status->badge() }}">
                                {{ $item->status->label() }}
                            </span>
                            <select name="status" onchange="this.form.submit()"
                                    class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                @foreach ($statusOptions as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected($item->status->value === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <svg class="w-10 h-10 text-slate-200 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    <p class="text-sm text-slate-400 mt-2">Belum ada rencana tindak lanjut</p>
                    <p class="text-xs text-slate-400 mt-1">
                        Tindak lanjut muncul di sini setelah Anda mengisinya pada laporan perjalanan dinas.
                    </p>
                </div>
            @endforelse
        </div>

        <x-pagination :paginator="$tindakLanjut" />
    </div>
</div>

@endsection
