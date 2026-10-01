@extends('app')

@section('title', 'Jenis Pengajuan Perjadin')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="max-w-5xl mx-auto">

        <div class="mb-6">
            <a href="{{ route('usulan.list') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">← Daftar Usulan Perjadin</a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">Perjalanan dinas seperti apa yang akan diajukan?</h1>
            <p class="text-sm text-slate-500 mt-1">
                Pilih dahulu jalurnya. Formulir berikutnya hanya menampilkan kategori dan berkas
                yang memang berlaku untuk jalur itu.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($pilihan as $jenis)
                <a href="{{ route('usulan.create', ['jenis' => $jenis->value]) }}"
                   class="group bg-white rounded-2xl border border-slate-100 shadow-sm hover:border-teal-200 hover:shadow-md transition overflow-hidden flex flex-col">

                    <div class="p-6 flex-1">
                        <span class="w-11 h-11 rounded-xl flex items-center justify-center mb-4
                                     {{ $jenis->butuhSpd() ? 'bg-teal-50 text-teal-600' : 'bg-violet-50 text-violet-600' }}">
                            @if ($jenis === \App\Enums\JenisPerjadin::DalamKota)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>
                                </svg>
                            @elseif ($jenis === \App\Enums\JenisPerjadin::LuarKota)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.8 19.2 16 11l3.5-3.5a2.1 2.1 0 0 0-3-3L13 8 4.8 6.2a.8.8 0 0 0-.8 1.3l5 4.5-2.5 2.5-2-.5a.6.6 0 0 0-.6 1l2 2 2 2a.6.6 0 0 0 1-.6l-.5-2 2.5-2.5 4.5 5a.8.8 0 0 0 1.4-.7z"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>
                                </svg>
                            @endif
                        </span>

                        <h2 class="text-base font-bold text-slate-800 leading-snug">{{ $jenis->label() }}</h2>
                        <p class="text-sm text-slate-500 leading-relaxed mt-1.5">{{ $jenis->keterangan() }}</p>

                        <p class="text-xs mt-4 px-3 py-2 rounded-lg leading-relaxed
                                  {{ $jenis->butuhSpd() ? 'bg-slate-50 text-slate-500' : 'bg-violet-50 text-violet-700' }}">
                            {{ $jenis->berkasDasar() }}
                        </p>
                    </div>

                    <div class="px-6 py-3.5 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-teal-600 group-hover:text-teal-700">Pilih jalur ini</span>
                        <svg class="w-4 h-4 text-teal-500 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-5 flex items-start gap-3 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
            <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
            </svg>
            <p class="text-xs text-slate-500 leading-relaxed">
                Kategori perjalanan dinas pada formulir diambil dari Master Data, disaring sesuai jalur
                yang Anda pilih. Salah pilih jalur? Kembali ke halaman ini lewat tombol di formulir.
            </p>
        </div>
    </div>
</div>
@endsection
