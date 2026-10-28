{{-- Tombol Simpan & Kirim beserta konfirmasinya, dan Simpan Draf.
     Variabel: $periksa (yang perlu diperiksa sebelum mengirim) dan
     $syarat (syarat isi agar laporan dapat dikirim). --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
    <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

    @unless ($terkunci)
        {{-- Menyimpan dan mengirim satu langkah, ditanya ulang sekali:
             sejak dikirim laporan terkunci dan pimpinan langsung menerimanya. --}}
        <button type="button" @click="konfirmasiKirim = true"
                class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Simpan &amp; Kirim ke Pimpinan
        </button>

        <button type="submit" name="action" value="draft"
                class="w-full px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
            Simpan Draf
        </button>

        <div x-show="konfirmasiKirim" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
             @keydown.escape.window="konfirmasiKirim = false">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left"
                 @click.outside="konfirmasiKirim = false">
                <h3 class="font-bold text-slate-800 text-sm mb-2">Simpan dan kirim laporan ke pimpinan?</h3>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Isi laporan disimpan, dinyatakan selesai, lalu langsung dikirim ke pimpinan
                    untuk dikonfirmasi dan ditandatangani. Periksa kembali {{ $periksa }}
                    sebelum melanjutkan.
                </p>

                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                    Setelah dikirim, laporan terkunci sampai pimpinan memutuskan. Tanda tangan Anda
                    terbit sebagai QR pada dokumen.
                </p>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="konfirmasiKirim = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                        Periksa Lagi
                    </button>
                    <button type="submit" name="action" value="kirim"
                            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                        Ya, Kirim ke Pimpinan
                    </button>
                </div>
            </div>
        </div>
    @endunless

    <p class="text-xs text-slate-400 leading-relaxed">
        <strong>Simpan Draf</strong> menyimpan isinya tanpa mengirim. <strong>Simpan &amp; Kirim</strong>
        perlu {{ $syarat }};
        setelah itu laporan terkunci dan pelunasan menunggu konfirmasi pimpinan.
    </p>
</div>
