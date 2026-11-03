{{-- Pembebanan satu daftar nominatif: sumber dana, akun, dan tim kerja.
     Pilihan tim kerja mengikuti akun — dan sumber dana bila dipilih — menurut
     pagu tim kerja pada tahun anggaran daftarnya. Variabel: $nominatif,
     $aksi, $pilihanKategori, $pilihanAkun, $alokasi (hasil
     PembebananNominatif::untukDaftar). --}}
<form method="POST" action="{{ $aksi }}" class="flex flex-wrap items-end gap-2" data-pembebanan-nominatif
      x-data="pembebananNominatif(@js([
          'sumber' => (string) ($nominatif->id_kategori_pembiayaan ?? ''),
          'akun' => (string) ($nominatif->id_akun_pembiayaan ?? ''),
          'tim' => (string) ($nominatif->id_tim_kerja ?? ''),
          'tahun' => $alokasi['tahun'][$nominatif->id] ?? null,
      ]))"
      x-init="$watch('sumber', () => sesuaikanTim())">
    @csrf @method('PUT')
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori Pembiayaan</label>
        <select name="id_kategori_pembiayaan" x-model="sumber"
                class="px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <option value="">Belum ditetapkan</option>
            @foreach ($pilihanKategori as $kategori)
                <option value="{{ $kategori->id }}" @selected($nominatif->id_kategori_pembiayaan === $kategori->id)>
                    {{ $kategori->label }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Akun Pembiayaan</label>
        <select name="id_akun_pembiayaan" x-model="akun" @change="sesuaikanTim()"
                class="px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <option value="">Belum ditetapkan</option>
            @foreach ($pilihanAkun as $id => $label)
                <option value="{{ $id }}" @selected($nominatif->id_akun_pembiayaan === $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tim Kerja</label>
        <select name="id_tim_kerja" x-model="tim" @change="isiSumber()" data-pilihan-tim
                class="max-w-xs px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            <option value="" x-text="pilihan().length ? 'Belum ditetapkan' : (akun ? 'Akun ini belum berpagu tim kerja' : 'Pilih akun dulu')">Belum ditetapkan</option>
            <template x-for="pilih in pilihan()" :key="pilih.tim">
                <option :value="String(pilih.tim)" x-text="pilih.label" :selected="String(pilih.tim) === tim"></option>
            </template>
        </select>
    </div>
    <button type="submit"
            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
        Simpan
    </button>
</form>

@once
    @push('scripts')
    <script>
    // Pagu tim kerja per tahun anggaran, lalu per akun.
    window.alokasiTimKerja = @json($alokasi['pilihan'] ?? []);

    document.addEventListener('alpine:init', () => {
        const rupiah = new Intl.NumberFormat('id-ID');

        Alpine.data('pembebananNominatif', (awal) => ({
            ...awal,

            // Satu pilihan per tim; sumber dana dan pagunya disebut pada labelnya.
            pilihan() {
                const baris = ((window.alokasiTimKerja[this.tahun] || {})[this.akun] || [])
                    .filter((b) => !this.sumber || String(b.sumber) === this.sumber);
                const perTim = {};
                baris.forEach((b) => { (perTim[b.tim] ||= []).push(b); });

                return Object.values(perTim).map((alokasi) => ({
                    tim: alokasi[0].tim,
                    label: alokasi[0].nama + ' · ' + alokasi.map((b) => (b.sumber_kode || 'Tanpa sumber') + ' Rp ' + rupiah.format(b.pagu)).join(' / '),
                }));
            },

            sesuaikanTim() {
                if (this.tim && !this.pilihan().some((p) => String(p.tim) === this.tim)) {
                    this.tim = '';
                }
            },

            // Sumber dana terisi sendiri bila tim itu hanya berpagu dari satu sumber pada akun ini.
            isiSumber() {
                if (this.sumber || !this.tim) {
                    return;
                }
                const alokasi = ((window.alokasiTimKerja[this.tahun] || {})[this.akun] || []).filter((b) => String(b.tim) === this.tim);
                if (alokasi.length === 1 && alokasi[0].sumber) {
                    this.sumber = String(alokasi[0].sumber);
                }
            },
        }));
    });
    </script>
    @endpush
@endonce
