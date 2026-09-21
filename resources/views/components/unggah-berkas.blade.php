@use('Illuminate\Support\Facades\Storage')

@props([
    'nama',
    'label',
    'berkas' => null,
    'terima' => '.pdf',
    'keterangan' => null,
    'terkunci' => false,
])

{{-- Satu baris unggahan berkas pertanggungjawaban.

     Berkas yang sudah tersimpan ditampilkan sebagai tautan, bukan sekadar
     tanda centang: pelaksana perlu bisa memastikan yang terunggah memang
     berkas yang benar sebelum tim keuangan memeriksanya. --}}

<div>
    <label for="{{ $nama }}" class="block text-sm font-semibold text-slate-700 mb-1.5">
        {{ $label }}
    </label>

    @if ($berkas)
        <div class="mb-2 flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
            </svg>
            <a href="{{ route('berkas.lihat', $berkas) }}" target="_blank"
               class="text-xs font-semibold text-emerald-800 hover:underline truncate">
                Berkas tersimpan — lihat
            </a>
            @unless ($terkunci)
                <span class="ml-auto text-xs text-emerald-700 shrink-0">Unggah lagi untuk mengganti</span>
            @endunless
        </div>
    @endif

    @unless ($terkunci)
        <input type="file" name="{{ $nama }}" id="{{ $nama }}" accept="{{ $terima }}"
               class="w-full px-4 py-2.5 rounded-xl text-sm bg-white transition border
                      file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-100 file:text-teal-700 hover:file:bg-teal-200
                      {{ $errors->has($nama) ? 'border-red-500' : 'border-slate-200' }}">

        @if ($keterangan)
            <p class="text-xs text-slate-400 mt-1">{{ $keterangan }}</p>
        @endif
    @endunless

    @error($nama)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
