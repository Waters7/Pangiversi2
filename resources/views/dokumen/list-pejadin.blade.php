{{-- Komponen Pilih Perjalanan Dinas --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
        </div>
        <div>
            <h3 class="font-bold text-slate-800 text-sm">Pilih Perjalanan Dinas</h3>
            <p class="text-xs text-slate-400">Pilih perjalanan dinas yang akan dilengkapi dokumennya</p>
        </div>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">

            {{-- Select Pejadin --}}
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Daftar Perjalanan Dinas <span class="text-red-500">*</span>
                </label>
                <select name="usulan_id" id="pejadin_select"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">-- Pilih Perjalanan Dinas --</option>

                    {{-- Contoh data statis, nantinya dari $daftarPejadin --}}
                    <optgroup label="Sedang Berjalan">
                        <option value="1">USL-2025-001 · Rapat Koordinasi Nasional — Jakarta (15–17 Jan 2025)</option>
                        <option value="2">USL-2025-004 · Bimtek Pengelolaan Anggaran — Surabaya (20–22 Jan 2025)</option>
                    </optgroup>

                    <optgroup label="Selesai / Perlu LPJ">
                        <option value="3">USL-2024-089 · Workshop SDM — Makassar (5–7 Des 2024)</option>
                        <option value="4">USL-2024-075 · Studi Banding — Yogyakarta (1–3 Nov 2024)</option>
                        <option value="5">USL-2024-060 · Seminar Nasional Kesehatan — Bandung (15–16 Okt 2024)</option>
                    </optgroup>
                </select>
            </div>

            {{-- Tombol Pilih --}}
            <div>
                <button type="button" id="pilihPejadinBtn"
                        class="w-full py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    Pilih
                </button>
            </div>

        </div>

        {{-- Preview info pejadin yang dipilih --}}
        <div id="pejadin_info" class="hidden mt-4 p-4 bg-teal-50 border border-teal-100 rounded-xl">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-teal-600 font-medium">Nomor Usulan</p>
                    <p class="font-bold text-slate-800 mt-0.5" id="info_nomor">—</p>
                </div>
                <div>
                    <p class="text-xs text-teal-600 font-medium">Kegiatan</p>
                    <p class="font-bold text-slate-800 mt-0.5" id="info_kegiatan">—</p>
                </div>
                <div>
                    <p class="text-xs text-teal-600 font-medium">Tujuan</p>
                    <p class="font-bold text-slate-800 mt-0.5" id="info_tujuan">—</p>
                </div>
                <div>
                    <p class="text-xs text-teal-600 font-medium">Periode</p>
                    <p class="font-bold text-slate-800 mt-0.5" id="info_periode">—</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const pejadinData = {
        '1': { nomor: 'USL-2025-001', kegiatan: 'Rapat Koordinasi Nasional', tujuan: 'Jakarta', periode: '15–17 Jan 2025' },
        '2': { nomor: 'USL-2025-004', kegiatan: 'Bimtek Pengelolaan Anggaran', tujuan: 'Surabaya', periode: '20–22 Jan 2025' },
        '3': { nomor: 'USL-2024-089', kegiatan: 'Workshop SDM', tujuan: 'Makassar', periode: '5–7 Des 2024' },
        '4': { nomor: 'USL-2024-075', kegiatan: 'Studi Banding', tujuan: 'Yogyakarta', periode: '1–3 Nov 2024' },
        '5': { nomor: 'USL-2024-060', kegiatan: 'Seminar Nasional Kesehatan', tujuan: 'Bandung', periode: '15–16 Okt 2024' },
    };

    document.getElementById('pilihPejadinBtn').addEventListener('click', function () {
        const val = document.getElementById('pejadin_select').value;
        const info = document.getElementById('pejadin_info');
        if (val && pejadinData[val]) {
            const d = pejadinData[val];
            document.getElementById('info_nomor').textContent    = d.nomor;
            document.getElementById('info_kegiatan').textContent = d.kegiatan;
            document.getElementById('info_tujuan').textContent   = d.tujuan;
            document.getElementById('info_periode').textContent  = d.periode;
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
        }
    });
</script>
