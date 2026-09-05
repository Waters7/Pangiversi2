@props([
    'aksiTandaTangan',
    'aksiKembalikan' => null,
    'nama',
    'judul' => 'Tandatangani dokumen ini?',
    'label' => 'Tanda Tangani',
    'ringkas' => null,
])

{{-- Sepasang aksi PPK: menandatangani, atau mengembalikan ke tim keuangan
     dengan alasan. Keduanya meminta konfirmasi lebih dulu — tanda tangan
     tidak boleh terbubuh karena salah klik, dan pengembalian tanpa alasan
     hanya membuat tim keuangan menebak-nebak. --}}
<div x-data="{ ttd: false, kembali: false }" class="inline-flex items-center gap-2">

    <button type="button" @click="ttd = true"
            class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
        {{ $label }}
    </button>

    @if ($aksiKembalikan)
        <button type="button" @click="kembali = true"
                class="px-3 py-1.5 bg-white border border-amber-200 hover:bg-amber-50 text-amber-700 text-xs font-bold rounded-lg transition">
            Kembalikan
        </button>
    @endif

    {{-- Konfirmasi tanda tangan --}}
    <div x-show="ttd" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
         @keydown.escape.window="ttd = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" @click.outside="ttd = false">
            <h3 class="font-bold text-slate-800 text-sm mb-2">{{ $judul }}</h3>

            <p class="text-xs text-slate-600 leading-relaxed">
                Apakah Anda yakin dokumen ini sudah benar dikerjakan?
            </p>

            @if ($ringkas)
                <p class="mt-2 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                    {{ $ringkas }}
                </p>
            @endif

            <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                Setelah ditandatangani, dokumen ini tidak dapat diubah tanpa mencabut tanda tangannya.
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="ttd = false"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                    Tidak
                </button>

                <form method="POST" action="{{ $aksiTandaTangan }}">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                        Ya, Tandatangani
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Alasan pengembalian --}}
    @if ($aksiKembalikan)
        <div x-show="kembali" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
             @keydown.escape.window="kembali = false">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" @click.outside="kembali = false">
                <h3 class="font-bold text-slate-800 text-sm mb-2">Kembalikan ke tim keuangan</h3>

                <form method="POST" action="{{ $aksiKembalikan }}">
                    @csrf @method('PUT')

                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Alasan pengembalian <span class="text-red-500">*</span>
                    </label>

                    <textarea name="alasan_kembali" rows="3" required minlength="10"
                              placeholder="Contoh: nominal uang harian belum sesuai lama perjalanan pada SPD."
                              class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent transition {{ $errors->has('alasan_kembali') ? 'border-red-500' : 'border-slate-200' }}">{{ old('alasan_kembali') }}</textarea>

                    @error('alasan_kembali')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror

                    <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                        Validasinya dicabut dan berkas kembali ke tim keuangan untuk diperiksa ulang,
                        lalu dikirimkan lagi kepada pelaksana bila sudah benar.
                    </p>

                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" @click="kembali = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition">
                            Kembalikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
