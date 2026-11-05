@props(['nominatif'])

{{-- Tanda tangan daftar nominatif yang ternyata keliru dicabut supaya dapat
     dikoreksi. Wajib beralasan: dokumennya sudah terkirim ke tim keuangan,
     dan alasannya menjadi satu-satunya penjelasan mengapa ia ditarik. --}}
<div x-data="{ cabut: false }" class="inline-block" data-cabut-nominatif>
    <button type="button" @click="cabut = true"
            class="px-4 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-xs font-semibold rounded-xl transition">
        Cabut Tanda Tangan
    </button>

    <div x-show="cabut" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
         @keydown.escape.window="cabut = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left" @click.outside="cabut = false">
            <h3 class="font-bold text-slate-800 text-sm mb-2">Cabut tanda tangan daftar nominatif ini?</h3>

            <p class="text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2 mb-3">
                Surat Tugas {{ $nominatif->no_tugas }}
            </p>

            <form method="POST" action="{{ route('persetujuan.nominatif.batal-tanda-tangan', $nominatif) }}">
                @csrf @method('DELETE')

                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    Kesalahan yang perlu dikoreksi <span class="text-red-500">*</span>
                </label>

                <textarea name="alasan" rows="3" required minlength="10" maxlength="500"
                          placeholder="Contoh: akun pembebanan keliru, seharusnya 524111."
                          class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent transition"></textarea>

                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                    Daftar kembali menunggu tanda tangan, ditarik dari tim keuangan, dan QR pada cetakan
                    lamanya tidak berlaku lagi. Setelah dikoreksi, tanda tangani ulang.
                </p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="cabut = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                        Tidak Jadi
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition">
                        Ya, Cabut
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
