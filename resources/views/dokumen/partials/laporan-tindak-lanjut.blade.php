{{-- Rencana tindak lanjut: daftar berulang yang dikelola Alpine lewat
     variabel "tindak" pada formulirnya. Variabel: $terkunci, $statusTindakLanjut,
     dan $keterangan (opsional, teks di bawah judul). --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
        <div>
            <h3 class="font-bold text-slate-800 text-sm">Rencana Tindak Lanjut</h3>
            <p class="text-xs text-slate-400">{{ $keterangan ?? 'Langkah yang dikerjakan setelah kembali bertugas' }}</p>
        </div>
        @unless ($terkunci)
            <button type="button"
                    @click="tindak.push({ uraian: '', penanggung_jawab: '', target_selesai: '', status: 'rencana' })"
                    class="text-xs font-bold text-teal-600 hover:text-teal-700 shrink-0">+ Tambah</button>
        @endunless
    </div>

    <div class="p-6 space-y-4">
        <template x-for="(baris, i) in tindak" :key="'tl-' + i">
            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-500 text-xs font-bold flex items-center justify-center shrink-0 mt-1"
                          x-text="i + 1"></span>
                    <textarea :name="`tindak_lanjut[${i}][uraian]`" x-model="baris.uraian" rows="2" @disabled($terkunci)
                              placeholder="cth: Menyusun draf pedoman internal berdasarkan hasil rapat"
                              class="flex-1 min-w-0 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"></textarea>
                    @unless ($terkunci)
                        <button type="button" x-show="tindak.length > 1" @click="tindak.splice(i, 1)"
                                class="w-7 h-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    @endunless
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pl-9">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Penanggung jawab</label>
                        <input type="text" :name="`tindak_lanjut[${i}][penanggung_jawab]`"
                               x-model="baris.penanggung_jawab" @disabled($terkunci)
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Target selesai</label>
                        <input type="date" :name="`tindak_lanjut[${i}][target_selesai]`"
                               x-model="baris.target_selesai" @disabled($terkunci)
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select :name="`tindak_lanjut[${i}][status]`" x-model="baris.status" @disabled($terkunci)
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            @foreach ($statusTindakLanjut as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
