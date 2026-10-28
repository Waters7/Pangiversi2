{{-- Satu bagian laporan format Kemenkes: uraian dan satu foto dokumentasi
     opsional yang tercetak pada halaman lampiran.

     Variabel: $bagian (App\Enums\BagianLaporan), $laporan, $terkunci, $isi
     (closure isian bawaan), dan $label (opsional, judul di atas isian). --}}
@php
    $foto = $laporan->dokumentasiBagian($bagian);
    $idIsian = 'bagian-'.$bagian->value;
@endphp

<div data-bagian-laporan="{{ $bagian->value }}">
    @if ($label ?? null)
        <label for="{{ $idIsian }}" class="block text-sm font-semibold text-slate-700 mb-1.5">
            {{ $label }}
            @if ($bagian->wajib()) <span class="text-red-500">*</span> @endif
        </label>
    @else
        <label for="{{ $idIsian }}" class="sr-only">{{ $bagian->label() }}</label>
    @endif

    <textarea name="bagian[{{ $bagian->value }}]" id="{{ $idIsian }}" rows="5" @disabled($terkunci)
              placeholder="{{ $bagian->petunjuk() }}"
              class="w-full px-4 py-2.5 border {{ $errors->has('bagian.'.$bagian->value) ? 'border-red-500' : 'border-slate-200' }} rounded-xl text-sm leading-relaxed focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ $isi($bagian) }}</textarea>

    {{-- Foto dokumentasi bagian ini — boleh diisi, boleh tidak. --}}
    <div class="mt-2 flex flex-wrap items-center gap-3">
        @if ($foto)
            <a href="{{ route('berkas.lihat', $foto->berkas) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-2 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition"
               data-dokumentasi-tersimpan>
                <img src="{{ route('berkas.lihat', $foto->berkas) }}" alt="Dokumentasi {{ $bagian->judul() }}"
                     class="w-12 h-12 rounded-lg object-cover">
                <span class="text-xs font-semibold text-emerald-800 pr-1">Dokumentasi tersimpan</span>
            </a>
            @unless ($terkunci)
                <label class="inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                    <input type="checkbox" name="hapus_dokumentasi[{{ $bagian->value }}]" value="1"
                           class="rounded border-slate-300 text-red-500 focus:ring-red-400">
                    Hapus foto
                </label>
            @endunless
        @endif

        @unless ($terkunci)
            <label class="flex-1 min-w-[14rem]">
                <span class="sr-only">Foto dokumentasi {{ $bagian->judul() }}</span>
                <input type="file" name="dokumentasi[{{ $bagian->value }}]" accept=".jpg,.jpeg,.png"
                       class="w-full px-3 py-2 rounded-xl text-xs bg-white border {{ $errors->has('dokumentasi.'.$bagian->value) ? 'border-red-500' : 'border-slate-200' }}
                              file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-100 file:text-teal-700 hover:file:bg-teal-200">
            </label>
        @endunless
    </div>
    @unless ($terkunci)
        <p class="text-[11px] text-slate-400 mt-1">
            Dokumentasi opsional — foto JPG atau PNG, maks. 2 MB{{ $foto ? '; unggah lagi untuk mengganti' : '' }}. Tercetak pada halaman lampiran.
        </p>
    @endunless
    @error('dokumentasi.'.$bagian->value)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
