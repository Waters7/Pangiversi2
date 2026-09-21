@extends('app')

@section('title', 'Bantuan — '.$obrolan->judul)

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-5 flex items-start gap-3">
        <a href="{{ route('bantuan.index') }}"
           class="w-9 h-9 shrink-0 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-slate-800">{{ $obrolan->judul }}</h1>
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $obrolan->status_badge }}">
                    {{ $obrolan->status_label }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Dilaporkan {{ $obrolan->pelapor?->nama ?? '—' }} ·
                {{ $obrolan->created_at?->translatedFormat('d F Y, H:i') }}
                @if ($obrolan->usulan)
                    ·
                    <a href="{{ route('usulan.show', $obrolan->usulan->no_usulan) }}"
                       class="font-mono font-semibold text-teal-600 hover:text-teal-700">
                        {{ $obrolan->usulan->no_usulan }}
                    </a>
                @endif
            </p>
        </div>

        @unless ($obrolan->sudahSelesai())
            <form method="POST" action="{{ route('bantuan.selesai', $obrolan) }}" class="shrink-0">
                @csrf @method('PUT')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-emerald-200 hover:bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    Tandai Selesai
                </button>
            </form>
        @endunless
    </div>

    @if ($obrolan->sudahSelesai())
        <div class="mb-5 flex items-start gap-3 px-5 py-3.5 bg-emerald-50 border border-emerald-200 rounded-xl">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-bold text-emerald-800">Kendala dinyatakan selesai</p>
                <p class="text-xs text-emerald-700 mt-0.5">
                    Ditandai {{ $obrolan->penyelesai?->nama ?? '—' }} pada
                    {{ $obrolan->diselesaikan_at?->translatedFormat('d F Y, H:i') }}.
                    Kirim pesan lagi bila kendalanya ternyata belum beres.
                </p>
            </div>
        </div>
    @endif

    {{-- Percakapan: pesan sendiri di kanan, lawan bicara di kiri. --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-5 space-y-4">
        @foreach ($obrolan->pesan as $pesan)
            @php $sendiri = $pesan->id_pengirim === auth()->id(); @endphp

            <div class="flex gap-3 {{ $sendiri ? 'flex-row-reverse' : '' }}">
                <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-xs font-bold
                            {{ $pesan->dariAdmin() ? 'bg-red-100 text-red-700' : 'bg-teal-100 text-teal-700' }}">
                    {{ strtoupper(substr($pesan->pengirim?->nama ?? '?', 0, 1)) }}
                </div>

                <div class="max-w-[85%] sm:max-w-[70%] {{ $sendiri ? 'text-right' : '' }}">
                    <p class="text-[11px] text-slate-400 mb-1">
                        {{ $pesan->pengirim?->nama ?? '—' }}
                        @if ($pesan->dariAdmin())
                            <span class="font-bold text-red-500">· Administrator</span>
                        @endif
                        · {{ $pesan->created_at?->translatedFormat('d M Y, H:i') }}
                    </p>

                    <div class="inline-block text-left px-4 py-2.5 rounded-2xl text-sm leading-relaxed whitespace-pre-line
                                {{ $sendiri ? 'bg-teal-500 text-white' : 'bg-slate-100 text-slate-700' }}">
                        {{ $pesan->isi }}
                    </div>

                    @if ($pesan->lampiran)
                        <div class="mt-1.5">
                            <a href="{{ route('berkas.lihat', $pesan->lampiran) }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-600 hover:text-teal-700">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                                </svg>
                                Lihat lampiran
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('bantuan.balas', $obrolan) }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        @csrf

        <label class="block text-xs font-semibold text-slate-600 mb-1.5">
            {{ $admin ? 'Jawab pelapor' : 'Tulis pesan' }}
        </label>

        <textarea name="isi" rows="3" required
                  placeholder="{{ $admin ? 'Sampaikan jawaban atau langkah yang perlu ditempuh pelapor.' : 'Tambahkan keterangan atau tanggapi jawaban administrator.' }}"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('isi') ? 'border-red-500' : 'border-slate-200' }}">{{ old('isi') }}</textarea>
        @error('isi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
            <input type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png"
                   class="text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-600 file:text-xs file:font-semibold">

            <button type="submit"
                    class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                </svg>
                Kirim
            </button>
        </div>
    </form>

</div>

@endsection
