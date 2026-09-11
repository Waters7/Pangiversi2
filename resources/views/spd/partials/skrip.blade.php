@php
    $ubah = isset($spd) && $spd->exists;

    // Baris pelaksana pada formulir: dari data saat menyunting, dari akun
    // yang sedang masuk saat membuat baru.
    $barisAwal = $ubah
        ? $spd->pelaksana->values()->map(fn ($orang, $i) => [
            'kunci' => $i,
            // Formulir hanya mengurus nomor urutnya; awalan dan tahun
            // disusun ulang oleh sistem saat disimpan.
            'id_user' => $orang->id_user,
            'nama' => $orang->nama,
            'nip' => $orang->nip ?? '',
            'pangkat_golongan' => $orang->pangkat_golongan ?? '',
            'jabatan_instansi' => $orang->jabatan_instansi ?? '',
            'tingkat_biaya' => $orang->tingkat_biaya ?? '',
        ])->all()
        : [[
            'kunci' => 0,
            'id_user' => $pengguna->id,
            'nama' => $pengguna->nama,
            'nip' => $pengguna->nip,
            // Golongan pegawai tercatat sejak berkas DUK dimuat, jadi
            // pangkatnya terisi sendiri.
            'pangkat_golongan' => \App\Enums\Golongan::dari($pengguna->golongan)?->lengkap() ?? '',
            'jabatan_instansi' => $pengguna->jabatan ?? '',
            'tingkat_biaya' => '',
        ]];
@endphp

@push('scripts')
<script>
    function formulirSpd() {
        return {
            berangkat: @js(old('tanggal_berangkat', $ubah ? $spd->tanggal_berangkat?->toDateString() : '')),
            kembali: @js(old('tanggal_kembali', $ubah ? $spd->tanggal_kembali?->toDateString() : '')),
            adaPengikut: @js(filled(old('pengikut.0.nama')) || ($ubah && $spd->pengikut->isNotEmpty())),
            pegawai: @js($calonPelaksana->keyBy('id')),
            urut: {{ count($barisAwal) }},

            pelaksana: @js(old('pelaksana')) ?? @js($barisAwal),

            /** Berangkat dan kembali pada hari yang sama terhitung satu hari. */
            get lamaHari() {
                if (! this.berangkat || ! this.kembali) return '';
                const a = new Date(this.berangkat), b = new Date(this.kembali);
                if (b < a) return '';
                return Math.round((b - a) / 86400000) + 1;
            },

            tambah() {
                if (this.pelaksana.length >= {{ $maksPelaksana }}) return;
                this.pelaksana.push({
                    kunci: ++this.urut,
                    id_user: null,
                    nama: '', nip: '', pangkat_golongan: '', jabatan_instansi: '', tingkat_biaya: '',
                });
            },

            hapus(i) {
                if (i > 0) this.pelaksana.splice(i, 1);
            },

            // Nama pangkat per golongan, sumbernya enum Golongan.
            pangkat: @js(collect(\App\Enums\Golongan::cases())->mapWithKeys(fn ($g) => [$g->value => $g->lengkap()])),

            isiDariPegawai(i, id) {
                const p = this.pegawai[id];
                if (! p) { this.pelaksana[i].id_user = null; return; }
                this.pelaksana[i].id_user = p.id;
                this.pelaksana[i].nama = p.nama;
                this.pelaksana[i].nip = p.nip ?? '';
                this.pelaksana[i].jabatan_instansi = p.jabatan ?? '';
                this.pelaksana[i].pangkat_golongan = this.pangkat[p.golongan] ?? '';
            },
        };
    }
</script>
@endpush
