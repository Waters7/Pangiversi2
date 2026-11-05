{{-- Menahan unggahan yang pasti ditolak, sebelum formulirnya terkirim.

     Berkas yang melebihi batas ditolak saat dipilih, lengkap dengan alasannya,
     supaya tidak ikut terkirim. Kiriman yang melampaui batas server lebih
     parah: server membuang seluruh isinya, termasuk seluruh isian teks, jadi
     pengirimannya ditahan di peramban.

     Isian berkas memakai data-maks-mb, di dalam pembungkus data-unggahan yang
     memuat tempat pesan data-galat-unggahan. Letakkan di dalam formulirnya. --}}
@php
    $batasBerkas = \Illuminate\Http\UploadedFile::getMaxFilesize();
    $batasKirim = ini_parse_quantity((string) ini_get('post_max_size'));
@endphp

<div class="contents"
     x-data="{
        batasBerkas: {{ Js::from($batasBerkas) }},
        batasKirim: {{ Js::from($batasKirim) }},
        pesanKirim: '',

        /** Cadangan untuk isian teks yang ikut terkirim bersama berkasnya. */
        get cadangan() { return 1024 * 1024; },

        formulir() { return this.$el.closest('form'); },

        mb(ukuran) { return (ukuran / (1024 * 1024)).toLocaleString('id-ID', { maximumFractionDigits: 1 }); },

        periksaBerkas(el) {
            const batas = Math.min(Number(el.dataset.maksMb) * 1024 * 1024, this.batasBerkas);
            const pesan = el.closest('[data-unggahan]')?.querySelector('[data-galat-unggahan]');
            const berkas = el.files[0];
            const terlaluBesar = Boolean(berkas) && berkas.size > batas;

            if (pesan) {
                pesan.hidden = ! terlaluBesar;
                pesan.textContent = terlaluBesar
                    ? `Berkas ${berkas.name} berukuran ${this.mb(berkas.size)} MB, melebihi batas ${this.mb(batas)} MB, jadi tidak dipakai. Perkecil dulu lalu pilih lagi.`
                    : '';
            }

            if (terlaluBesar) { el.value = ''; }
        },

        periksaKiriman(kejadian) {
            const total = Array.from(this.formulir().elements)
                .filter((el) => el.type === 'file')
                .reduce((jumlah, el) => jumlah + (el.files[0]?.size ?? 0), 0);

            this.pesanKirim = '';

            if (this.batasKirim === 0 || total + this.cadangan <= this.batasKirim) { return; }

            kejadian.preventDefault();
            this.konfirmasiKirim = false;
            this.pesanKirim = `Total unggahan ${this.mb(total)} MB melebihi batas kirim server ${this.mb(this.batasKirim - this.cadangan)} MB. Kosongkan sebagian berkas, simpan, lalu unggah sisanya.`;
            this.$nextTick(() => this.$refs.pesanKirim.scrollIntoView({ block: 'center' }));
        },

        mulai() {
            this.formulir().addEventListener('change', (kejadian) => {
                if (kejadian.target.type === 'file' && kejadian.target.dataset.maksMb) { this.periksaBerkas(kejadian.target); }
            });

            this.formulir().addEventListener('submit', (kejadian) => this.periksaKiriman(kejadian));
        },
     }"
     x-init="mulai()">
    <p x-ref="pesanKirim" x-show="pesanKirim" x-cloak x-text="pesanKirim" data-pesan-kirim
       class="mb-5 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700"></p>
</div>
