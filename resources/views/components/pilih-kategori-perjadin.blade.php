@props([
    'kategori' => collect(),
    'terpilih' => null,
])

{{-- Kategori perjalanan dinas, dikelompokkan sesuai daftar resmi. --}}
<div>
    <label for="id_kategori_perjadin" class="block text-sm font-semibold text-slate-700 mb-1.5">
        Kategori Perjalanan Dinas <span class="text-red-500">*</span>
    </label>

    <select name="id_kategori_perjadin" id="id_kategori_perjadin" required
            {{ $attributes->class([
                'w-full px-4 py-2.5 rounded-xl text-sm bg-white border transition',
                'focus:ring-2 focus:ring-teal-400 focus:border-transparent',
                'border-red-500' => $errors->has('id_kategori_perjadin'),
                'border-slate-200' => ! $errors->has('id_kategori_perjadin'),
            ]) }}>
        <option value="">— Pilih Jenis —</option>

        @foreach ($kategori as $grup => $daftar)
            <optgroup label="{{ $grup }}">
                @foreach ($daftar as $item)
                    <option value="{{ $item->id }}"
                        @selected((string) old('id_kategori_perjadin', $terpilih) === (string) $item->id)>
                        {{ $item->nama }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>

    <p class="text-xs text-slate-400 mt-1">
        Menentukan kelas biaya perjalanan — fullboard, fullday, halfday, atau transport lokal.
    </p>

    @error('id_kategori_perjadin')
        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
