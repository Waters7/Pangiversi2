@extends('app')

@section('title', 'Daftar Nominatif ' . $nominatif->no_tugas)

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <a href="{{ route('persetujuan.nominatif') }}"
       class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-slate-600 mb-4 transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M15 18l-6-6 6-6"/>
        </svg>
        Kembali ke Daftar Nominatif
    </a>

    <div class="mb-6 flex flex-col lg:flex-row lg:items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Daftar Nominatif</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Surat Tugas {{ $nominatif->no_tugas }}
                @if ($nominatif->tanggal_tugas)
                    · {{ $nominatif->tanggal_tugas->translatedFormat('d F Y') }}
                @endif
            </p>
        </div>

        <span class="self-start inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $nominatif->status_badge }}">
            {{ $nominatif->status_label }}
        </span>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
        <x-tabel-nominatif :baris="$baris" :total="$total" />
        <x-nominatif-menunggu :menunggu="$menunggu" class="mt-3" />
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wide mb-1">Disahkan Oleh</p>
                <p class="text-slate-600">Kepala Seksi Pencairan Dana I KPPN Manado</p>
            </div>
            <div>
                <p class="font-semibold text-slate-400 uppercase tracking-wide mb-1">Pejabat Pembuat Komitmen</p>
                @if ($nominatif->sudahDitandatangani())
                    <p class="font-bold text-slate-700">{{ $nominatif->ppk?->nama }}</p>
                    <p class="text-slate-500">NIP. {{ $nominatif->ppk?->nip }}</p>
                    <p class="text-slate-400 mt-1">
                        Ditandatangani {{ $nominatif->ditandatangani_at->translatedFormat('d F Y, H:i') }}
                    </p>
                @else
                    <p class="text-slate-400">Belum ditandatangani</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mt-6 pt-5 border-t border-slate-100">
            @unless ($nominatif->sudahDitandatangani())
                <form method="POST" action="{{ route('persetujuan.nominatif.tanda-tangan', $nominatif) }}"
                      x-data
                      @submit.prevent="if (confirm('Tandatangani daftar nominatif ini?')) $el.submit()">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                        Tanda Tangani
                    </button>
                </form>
            @endunless

            @if ($nominatif->sudahDitandatangani() && ! $nominatif->sudahDikirim())
                <form method="POST" action="{{ route('persetujuan.nominatif.kirim', $nominatif) }}">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl transition">
                        Kirim ke Tim Keuangan
                    </button>
                </form>
            @endif

            @if ($nominatif->sudahDikirim())
                <p class="text-xs text-slate-400 self-center">
                    Sudah dikirim ke tim keuangan pada
                    {{ $nominatif->dikirim_at->translatedFormat('d F Y, H:i') }}.
                </p>
            @endif
        </div>
    </div>

</div>

@endsection
