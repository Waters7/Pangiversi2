{{-- Popup sebelum SPD diunduh: cara memasukkannya ke SRIKANDI, dan pengingat
     mengajukan usulan perjadin begitu SPD-nya ditandatangani. Dipasang sekali
     per halaman; tautan unduh memanggilnya lewat event "unduh-spd" berisi
     alamat PDF-nya. Tanpa JavaScript tautan itu tetap mengunduh langsung. --}}
<div x-data="{ buka: false, alamat: null, paham: false }"
     @unduh-spd.window="alamat = $event.detail.alamat; paham = false; buka = true"
     @keydown.escape.window="buka = false">
    <div x-show="buka" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6"
         role="dialog" aria-modal="true" aria-labelledby="judul-unduh-spd">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-full overflow-y-auto p-6 text-left"
             @click.outside="buka = false">
            <h3 id="judul-unduh-spd" class="font-bold text-slate-800 text-base">Sebelum mengunduh: memasukkan SPD ke SRIKANDI</h3>
            <p class="text-xs text-slate-500 mt-1">
                PDF dari PANGI masih rancangan. Nomor resmi dan tanda tangannya diterbitkan lewat SRIKANDI.
            </p>

            <ol class="mt-4 ml-4 list-decimal space-y-2.5 text-xs text-slate-700 leading-relaxed">
                <li>
                    Unggah PDF ini ke SRIKANDI apa adanya. Penanda <code>${nomor_naskah}</code>,
                    <code>${ttd_pengirim1}</code>, dan <code>${ttd_pengirim2}</code> jangan diubah atau dihapus.
                </li>
                <li>
                    Buka <strong>Naskah Keluar → Registrasi Naskah Keluar</strong>, lalu isi formulirnya seperti berikut:
                    <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                        @include('spd.partials.contoh-srikandi')
                    </div>
                </li>
                <li>Tunggu tanda tangan PPK (lembar pertama) dan Direktur (lembar kedua).</li>
                <li>Setelah selesai, unduh SPD bertanda tangan dari SRIKANDI dan catat <strong>nomor naskahnya</strong>.</li>
            </ol>

            <div class="mt-4 flex items-start gap-2.5 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-3">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <p class="text-xs text-amber-900 leading-relaxed">
                    <strong>Catatan:</strong> jika SPD sudah ditandatangani, jangan lupa mengajukan usulan perjalanan
                    dinas di PANGI lewat <strong>Usulan Perjadin → Buat Usulan Perjadin</strong> — pilih SPD ini,
                    unggah SPD bertanda tangan, lalu isi nomor SPD dengan nomor naskah dari SRIKANDI.
                </p>
            </div>

            <label class="mt-4 flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                <input type="checkbox" x-model="paham"
                       class="w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                Saya sudah mengerti cara memasukkan SPD ke SRIKANDI
            </label>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="buka = false"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                    Batal
                </button>
                <button type="button" :disabled="! paham" @click="window.location.href = alamat; buka = false"
                        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition">
                    Mengerti, Unduh PDF
                </button>
            </div>
        </div>
    </div>
</div>
