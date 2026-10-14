{{-- Tombol Cek dan hasil pemeriksaan nomor surat tugas.
     Dipakai di dalam komponen Alpine yang memuat objek "cekSurat" — lihat
     window.cekSuratTugas di bawah — dan input nomor bertanda x-ref="noTugas". --}}
<div class="mt-2 flex flex-wrap items-center gap-2">
    <button type="button" @click="cekSurat.cek($refs.noTugas.value)" :disabled="cekSurat.status === 'memuat'"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white text-xs font-bold rounded-lg transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <span x-text="cekSurat.status === 'memuat' ? 'Memeriksa…' : 'Cek Nomor Surat Tugas'">Cek Nomor Surat Tugas</span>
    </button>
    <span class="text-[11px] text-slate-400">Periksa apakah surat tugas bernomor ini sudah pernah diunggah.</span>
</div>

<div x-show="cekSurat.status === 'ada'" x-cloak
     class="mt-2 px-3.5 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 leading-relaxed">
    <p x-text="cekSurat.hasil && cekSurat.hasil.pesan"></p>
    <p class="mt-1">
        <a :href="cekSurat.hasil && cekSurat.hasil.tautan" target="_blank" rel="noopener" class="font-semibold underline">Lihat berkasnya</a>
        <span class="text-emerald-700" x-show="cekSurat.hasil && cekSurat.hasil.jumlah > 1"
              x-text="cekSurat.hasil ? '· sudah dipakai ' + cekSurat.hasil.jumlah + ' berkas usulan atau SPD' : ''"></span>
    </p>
    <p class="mt-1 text-emerald-700">Unggah berkas lain hanya bila surat tugas Anda memang berbeda.</p>
</div>

<div x-show="cekSurat.status === 'belum'" x-cloak
     class="mt-2 px-3.5 py-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 leading-relaxed"
     x-text="cekSurat.hasil && cekSurat.hasil.pesan"></div>

<div x-show="cekSurat.status === 'galat'" x-cloak
     class="mt-2 px-3.5 py-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 leading-relaxed"
     x-text="cekSurat.hasil && cekSurat.hasil.pesan"></div>

@once
    @push('scripts')
        <script>
            /**
             * Pemeriksaan nomor surat tugas lewat tombol Cek. Hasilnya menentukan
             * apakah berkas surat tugas masih wajib diunggah: bila nomornya sudah
             * pernah diunggah, berkas itu yang dipakai.
             */
            window.cekSuratTugas = (alamat, kecuali = {}) => ({
                status: null,
                hasil: null,
                get tersedia() {
                    return this.status === 'ada';
                },
                reset() {
                    this.status = null;
                    this.hasil = null;
                },
                async cek(nomor) {
                    nomor = (nomor || '').trim();
                    if (nomor === '') {
                        this.status = 'galat';
                        this.hasil = { pesan: 'Isi nomor surat tugas lebih dulu.' };
                        return;
                    }

                    this.status = 'memuat';
                    const parameter = new URLSearchParams(Object.assign({ no_tugas: nomor }, kecuali));

                    try {
                        const jawaban = await fetch(alamat + '?' + parameter.toString(), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (! jawaban.ok) {
                            throw new Error(String(jawaban.status));
                        }
                        this.hasil = await jawaban.json();
                        this.status = this.hasil.ditemukan ? 'ada' : 'belum';
                    } catch (galat) {
                        this.status = 'galat';
                        this.hasil = { pesan: 'Pemeriksaan gagal. Coba lagi, atau unggah berkasnya langsung.' };
                    }
                },
            });
        </script>
    @endpush
@endonce
