{{-- Berkas laporan opsional: melengkapi isian laporan, misalnya laporan
     lengkap berfoto yang sudah disusun pelaksana. --}}
<div>
    <label for="berkas_laporan" class="block text-sm font-semibold text-slate-700 mb-1.5">
        Berkas laporan <span class="font-normal text-slate-400">— opsional</span>
    </label>
    @if ($laporan->berkas_laporan)
        <div class="mb-2 flex flex-wrap items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200">
            <a href="{{ route('berkas.lihat', $laporan->berkas_laporan) }}" target="_blank" rel="noopener"
               class="text-xs font-semibold text-emerald-800 hover:underline">Berkas laporan tersimpan — lihat</a>
            @unless ($terkunci)
                <label class="ml-auto inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                    <input type="checkbox" name="hapus_berkas_laporan" value="1" class="rounded border-slate-300 text-red-500 focus:ring-red-400">
                    Hapus berkas ini
                </label>
            @endunless
        </div>
    @endif
    @unless ($terkunci)
        <input type="file" name="berkas_laporan" id="berkas_laporan" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
               class="w-full px-4 py-2.5 rounded-xl text-sm bg-white transition border {{ $errors->has('berkas_laporan') ? 'border-red-500' : 'border-slate-200' }}
                      file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-100 file:text-teal-700 hover:file:bg-teal-200">
        <p class="text-xs text-slate-400 mt-1">
            Bila Anda sudah menyusun laporan lengkap — misalnya berfoto — unggah di sini. PDF, Word, JPG,
            atau PNG, maks. 10 MB. Isian di atas tetap diisi karena dikonfirmasi pimpinan.
        </p>
    @endunless
    @error('berkas_laporan')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
