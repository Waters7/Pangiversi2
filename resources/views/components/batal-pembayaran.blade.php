@props([
    'aksi',
    'nama',
    'judul' => 'Batalkan pembayaran ini?',
    'ringkas' => null,
])

{{-- Pembatalan pembayaran wajib beralasan: yang dicabut adalah angka uang,
     dan jurnalnya harus dapat menerangkan sendiri kenapa berubah. Bukti
     transfer yang lama tidak ikut terhapus. --}}
<div x-data="{ batal: false }" class="inline-block">

    <button type="button" @click="batal = true"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-xs font-bold rounded-lg transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>
        </svg>
        Batalkan Pembayaran
    </button>

    <div x-show="batal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
         @keydown.escape.window="batal = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left" @click.outside="batal = false">
            <h3 class="font-bold text-slate-800 text-sm mb-2">{{ $judul }}</h3>

            @if ($ringkas)
                <p class="text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 mb-3">
                    {{ $ringkas }}
                </p>
            @endif

            <form method="POST" action="{{ $aksi }}">
                @csrf @method('PUT')

                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    Alasan pembatalan <span class="text-red-500">*</span>
                </label>

                <textarea name="alasan" rows="3" required minlength="10"
                          placeholder="Contoh: tanggal transfer salah ketik, seharusnya 4 April 2026."
                          class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent transition {{ $errors->has('alasan') ? 'border-red-500' : 'border-slate-200' }}">{{ old('alasan') }}</textarea>

                @error('alasan')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror

                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                    Pembatalan dicatat sebagai baris tersendiri pada Riwayat Pembayaran.
                    Bukti transfer yang lama tetap tersimpan dan dapat ditelusuri.
                </p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="batal = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                        Tidak Jadi
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition">
                        Ya, Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
