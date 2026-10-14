{{-- Tombol Cek dan hasil pemeriksaan berkas bersama pada dokumen
     pertanggungjawaban. Dipakai di dalam komponen Alpine yang memuat objek
     "cekBerkas" — lihat window.cekBerkasBersama di bawah — dan input nomor
     bertanda x-ref sesuai $ref.

     Variabel: $ref (x-ref input nomor), $tombol (teks tombol). --}}
<div class="mt-2 flex flex-wrap items-center gap-2">
    <button type="button" @click="cekBerkas.cek($refs.{{ $ref }}.value, $refs)" :disabled="cekBerkas.status === 'memuat'"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white text-xs font-bold rounded-lg transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <span x-text="cekBerkas.status === 'memuat' ? 'Memeriksa…' : {{ Js::from($tombol) }}">{{ $tombol }}</span>
    </button>
    <span class="text-[11px] text-slate-400">Periksa apakah berkasnya sudah diunggah rekan serombongan.</span>
</div>

<div x-show="cekBerkas.status === 'ada'" x-cloak
     class="mt-2 px-3.5 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 leading-relaxed">
    <p x-text="cekBerkas.hasil && cekBerkas.hasil.pesan"></p>
    <ul class="mt-1 space-y-0.5">
        <template x-for="berkas in (cekBerkas.hasil && cekBerkas.hasil.berkas) || []" :key="berkas.tautan">
            <li>
                <a :href="berkas.tautan" target="_blank" rel="noopener" class="font-semibold underline" x-text="'Lihat ' + berkas.label"></a>
            </li>
        </template>
    </ul>
    <p class="mt-1 text-emerald-700">Unggah berkas lain hanya bila milik Anda memang berbeda.</p>
</div>

<div x-show="cekBerkas.status === 'belum'" x-cloak
     class="mt-2 px-3.5 py-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 leading-relaxed"
     x-text="cekBerkas.hasil && cekBerkas.hasil.pesan"></div>

<div x-show="cekBerkas.status === 'galat'" x-cloak
     class="mt-2 px-3.5 py-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 leading-relaxed"
     x-text="cekBerkas.hasil && cekBerkas.hasil.pesan"></div>

@once
    @push('scripts')
        <script>
            /**
             * Pemeriksaan berkas bersama lewat tombol Cek. Bila ditemukan,
             * kolom isian yang disebut pada "isian" ({ kolom: x-ref }) diisi dari
             * data milik rekan — harga dan nominal tidak pernah ikut.
             */
            window.cekBerkasBersama = (alamat, tetap = {}, isian = {}) => ({
                status: null,
                hasil: null,
                reset() {
                    this.status = null;
                    this.hasil = null;
                },
                async cek(nomor, refs) {
                    nomor = (nomor || '').trim();
                    if (nomor === '') {
                        this.status = 'galat';
                        this.hasil = { pesan: 'Isi nomornya lebih dulu.' };
                        return;
                    }

                    this.status = 'memuat';
                    const parameter = new URLSearchParams(Object.assign({ nomor: nomor }, tetap));

                    try {
                        const jawaban = await fetch(alamat + '?' + parameter.toString(), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (! jawaban.ok) {
                            throw new Error(String(jawaban.status));
                        }
                        this.hasil = await jawaban.json();
                        this.status = this.hasil.ditemukan ? 'ada' : 'belum';

                        if (this.hasil.ditemukan && this.hasil.data) {
                            Object.entries(isian).forEach(([kolom, ref]) => {
                                if (refs[ref] && this.hasil.data[kolom]) {
                                    refs[ref].value = this.hasil.data[kolom];
                                }
                            });
                        }
                    } catch (galat) {
                        this.status = 'galat';
                        this.hasil = { pesan: 'Pemeriksaan gagal. Coba lagi, atau unggah berkasnya langsung.' };
                    }
                },
            });
        </script>
    @endpush
@endonce
