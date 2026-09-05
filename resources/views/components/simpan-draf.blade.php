@props([
    'kunci',
    'bolehPulihkan' => true,
])

{{-- Simpan isian formulir sementara di peramban pengguna.

     Formulir usulan punya belasan isian dan tidak menyimpan apa pun sampai
     tombol ditekan; sesi yang habis, jaringan yang putus, atau tab yang
     tertutup menghapus semuanya. Drafnya hanya ada di peramban orang itu
     sendiri — tidak dikirim ke mana pun — dan terhapus begitu usulannya
     benar-benar terkirim.

     Berkas unggahan tidak ikut tersimpan: peramban tidak mengizinkannya,
     dan menampilkan nama berkas yang sebenarnya sudah hilang justru
     menyesatkan. --}}
<div class="contents"
     x-data="{
        kunci: 'pangi-draf-{{ $kunci }}',
        bolehPulihkan: {{ $bolehPulihkan ? 'true' : 'false' }},
        dipulihkan: false,
        adaBerkas: false,
        jeda: null,

        /** Umur draf yang masih dianggap berguna. */
        get batasUsia() { return 7 * 24 * 60 * 60 * 1000; },

        formulir() { return this.$el.closest('form'); },

        /** Isian yang nilainya masuk akal untuk disimpan dan dipulihkan. */
        kolom() {
            const jenis = ['text', 'search', 'date', 'number', 'email', 'tel', 'url', 'textarea', 'select-one'];

            return Array.from(this.formulir().elements).filter(
                (el) => el.name && el.name !== '_token' && jenis.includes(el.type)
            );
        },

        simpan() {
            const isi = {};
            this.kolom().forEach((el) => { if (el.value) { isi[el.name] = el.value; } });

            try {
                if (Object.keys(isi).length === 0) {
                    localStorage.removeItem(this.kunci);
                    return;
                }
                localStorage.setItem(this.kunci, JSON.stringify({ waktu: Date.now(), isi }));
            } catch (e) { /* penyimpanan diblokir; formulirnya tetap jalan */ }
        },

        pulihkan() {
            let simpanan = null;
            try { simpanan = localStorage.getItem(this.kunci); } catch (e) { return; }
            if (! simpanan) { return; }

            let draf;
            try { draf = JSON.parse(simpanan); } catch (e) { this.hapus(); return; }

            if (! draf?.isi || Date.now() - (draf.waktu ?? 0) > this.batasUsia) { this.hapus(); return; }

            let terisi = 0;
            this.kolom().forEach((el) => {
                if (el.value || draf.isi[el.name] === undefined) { return; }
                el.value = draf.isi[el.name];
                el.dispatchEvent(new Event('change', { bubbles: true }));
                terisi++;
            });

            if (terisi === 0) { return; }

            this.dipulihkan = true;
            this.adaBerkas = Array.from(this.formulir().elements).some((el) => el.type === 'file');
        },

        hapus() {
            try { localStorage.removeItem(this.kunci); } catch (e) { /* diabaikan */ }
        },

        buang() {
            this.hapus();
            this.kolom().forEach((el) => { el.value = ''; });
            this.dipulihkan = false;
        },

        mulai() {
            if (this.bolehPulihkan) { this.pulihkan(); }

            const tunda = () => {
                clearTimeout(this.jeda);
                this.jeda = setTimeout(() => this.simpan(), 800);
            };

            this.formulir().addEventListener('input', tunda);
            this.formulir().addEventListener('change', tunda);
            this.formulir().addEventListener('submit', () => this.hapus());
        },
     }"
     x-init="mulai()">

    <div x-show="dipulihkan" x-cloak
         class="mb-5 flex items-start gap-3 px-4 py-3 rounded-xl bg-amber-50 border border-amber-100">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        </svg>
        <div class="min-w-0 flex-1">
            <p class="text-sm text-amber-800">
                Isian yang belum sempat terkirim sudah dipulihkan dari peramban ini.
                <span x-show="adaBerkas">Berkas unggahan perlu dipilih ulang.</span>
            </p>
            <button type="button" @click="buang()"
                    class="mt-1 text-xs font-semibold text-amber-700 hover:text-amber-900 underline underline-offset-2">
                Kosongkan formulir
            </button>
        </div>
    </div>
</div>
