@php
    /** Partial ini dipakai bersama oleh halaman pembuatan dan penyuntingan. */
    $ubah = isset($spd) && $spd->exists;
@endphp
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

    <div class="mb-6">
        @if ($ubah)
            <a href="{{ route('spd.show', $spd) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">← Detail SPD</a>
        @endif
        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 {{ $ubah ? 'mt-1' : '' }}">
            {{ $ubah ? 'Ubah Surat Perjalanan Dinas' : 'Buat Surat Perjalanan Dinas' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">Field bertanda <span class="text-red-500">*</span> wajib diisi.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100">
            <p class="text-sm font-bold text-red-700 mb-1.5">Periksa kembali isian berikut</p>
            <ul class="text-xs text-red-600 space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $ubah ? route('spd.update', $spd) : route('spd.store') }}" id="formSpd"
          x-data="formulirSpd()" class="space-y-5">
        @csrf
        @if ($ubah) @method('PUT') @endif

        {{-- ── Identitas Surat ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Identitas Surat</h3>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="dikeluarkan_di" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Dikeluarkan di <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="dikeluarkan_di" id="dikeluarkan_di" required
                           value="{{ old('dikeluarkan_di', $ubah ? $spd->dikeluarkan_di : 'Manado') }}"
                           class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Dikeluarkan</label>
                    @php $tanggalTerbit = $ubah ? $spd->tanggal_surat : now(); @endphp
                    {{-- Mengikuti tanggal pembuatan SPD, jadi tidak disunting
                         agar tanggal pada dokumen tidak berselisih dengan
                         kapan surat itu benar-benar terbit. --}}
                    <input type="text" readonly
                           value="{{ $tanggalTerbit?->translatedFormat('d F Y') }}"
                           class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-slate-100 text-slate-600">
                    <p class="text-xs text-slate-400 mt-1">Mengikuti tanggal pembuatan SPD.</p>
                </div>
            </div>
        </div>

        {{-- ── Pelaksana ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Pelaksana Perjalanan Dinas</h3>
                <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                    Pelaksana pertama otomatis mengikuti akun yang sedang masuk. Pelaksana tambahan
                    dipilih dari daftar pegawai. Maksimal {{ $maksPelaksana }} pelaksana.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <template x-for="(orang, i) in pelaksana" :key="orang.kunci">
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-bold text-teal-700" x-text="'Pelaksana ' + (i + 1)"></p>
                            <button type="button" x-show="i > 0" @click="hapus(i)"
                                    class="text-xs font-semibold text-red-600 hover:text-red-700">Hapus</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Yang diketik hanya nomor urutnya. Awalan arsip dan
                                 tahun surat ditampilkan sebagai teks tetap supaya
                                 formatnya tidak bisa keliru. --}}
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Nomor Surat <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-stretch rounded-xl border border-slate-200 bg-white overflow-hidden focus-within:ring-2 focus-within:ring-teal-400">
                                    <span class="px-3 py-2.5 text-sm font-mono text-slate-500 bg-slate-50 border-r border-slate-200 whitespace-nowrap">{{ $awalanNomor }}</span>
                                    <input type="text" required x-model="orang.nomor_surat"
                                           :name="`pelaksana[${i}][nomor_surat]`"
                                           inputmode="numeric" maxlength="20" placeholder="1557"
                                           class="flex-1 min-w-0 px-3 py-2.5 text-sm font-mono border-0 focus:ring-0 focus:outline-none">
                                    <span class="px-3 py-2.5 text-sm font-mono text-slate-500 bg-slate-50 border-l border-slate-200 whitespace-nowrap">/{{ $tahunSurat }}</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">
                                    Cukup nomor urut buku agenda. Tahun surat mengikuti tanggal SPD diterbitkan.
                                </p>
                            </div>

                            {{-- Pelaksana pertama terkunci ke akun yang sedang masuk. --}}
                            <div class="sm:col-span-2" x-show="i > 0">
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pilih dari daftar pegawai</label>
                                <select @change="isiDariPegawai(i, $event.target.value)"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    <option value="">— Ketik manual —</option>
                                    @foreach ($calonPelaksana as $p)
                                        <option value="{{ $p->id }}">{{ $p->nama }} — {{ $p->nip }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" :name="`pelaksana[${i}][id_user]`" :value="orang.id_user ?? ''">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Nama Pelaksana <span class="text-red-500">*</span>
                                </label>
                                <input type="text" required x-model="orang.nama" :readonly="i === 0"
                                       :name="`pelaksana[${i}][nama]`"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white read-only:bg-slate-100 read-only:text-slate-500 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                <p class="text-xs text-slate-400 mt-1" x-show="i === 0">Sesuai akun yang sedang masuk.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    NIP Pelaksana <span class="text-red-500">*</span>
                                </label>
                                <input type="text" required x-model="orang.nip" :readonly="i === 0"
                                       :name="`pelaksana[${i}][nip]`"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white read-only:bg-slate-100 read-only:text-slate-500 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pangkat dan Golongan</label>
                                {{-- Dipilih dari daftar baku agar tulisannya seragam pada tiap SPD. --}}
                                <select x-model="orang.pangkat_golongan" :name="`pelaksana[${i}][pangkat_golongan]`"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    <option value="">— Belum ditentukan —</option>
                                    @foreach ($daftarGolongan as $kelompok => $pilihan)
                                        <optgroup label="{{ $kelompok }}">
                                            @foreach ($pilihan as $lengkap)
                                                <option value="{{ $lengkap }}">{{ $lengkap }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Jabatan / Instansi</label>
                                <input type="text" x-model="orang.jabatan_instansi"
                                       :name="`pelaksana[${i}][jabatan_instansi]`"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tingkat Biaya Perjalanan Dinas</label>
                                <input type="text" x-model="orang.tingkat_biaya"
                                       :name="`pelaksana[${i}][tingkat_biaya]`" placeholder="cth: Tingkat C"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="tambah()" x-show="pelaksana.length < {{ $maksPelaksana }}"
                        class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-sm font-semibold text-slate-500 hover:border-teal-400 hover:text-teal-600 transition">
                    + Tambah Pelaksana (maks. {{ $maksPelaksana }})
                </button>
            </div>
        </div>

        {{-- ── Rencana Perjalanan ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Rencana Perjalanan</h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="maksud" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Maksud Perjalanan Dinas <span class="text-red-500">*</span>
                    </label>
                    <textarea name="maksud" id="maksud" rows="3" required
                              class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('maksud', $ubah ? $spd->maksud : '') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="alat_angkut" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Alat Angkut <span class="text-red-500">*</span>
                        </label>
                        <select name="alat_angkut" id="alat_angkut" required
                                class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            @foreach ($alatAngkut as $alat)
                                <option value="{{ $alat }}" @selected(old('alat_angkut', $ubah ? $spd->alat_angkut : 'Angkutan Udara') === $alat)>{{ $alat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tempat_berangkat" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Tempat Berangkat <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="tempat_berangkat" id="tempat_berangkat" required
                               list="daftar-lokasi-spd"
                               value="{{ old('tempat_berangkat', $ubah ? $spd->tempat_berangkat : 'Manado') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label for="tempat_tujuan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Tempat Tujuan <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="tempat_tujuan" id="tempat_tujuan" required
                               list="daftar-lokasi-spd" placeholder="cth: Jakarta, Surabaya, Bandung..."
                               value="{{ old('tempat_tujuan', $ubah ? $spd->tempat_tujuan : '') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <p class="text-xs text-slate-400 mt-1">Pilih dari daftar lokasi terdaftar agar seragam dengan usulan perjadin.</p>
                    </div>

                    {{-- Daftar lokasi yang sama dipakai formulir usulan perjadin. --}}
                    <datalist id="daftar-lokasi-spd">
                        @foreach ($lokasiTujuan as $l)
                            <option value="{{ $l->nama }}">{{ $l->nama_lengkap ?? $l->nama }}</option>
                        @endforeach
                    </datalist>
                    <div>
                        <label for="lama_hari" class="block text-sm font-semibold text-slate-700 mb-1.5">Lamanya Perjalanan (hari)</label>
                        <input type="text" id="lama_hari" readonly x-model="lamaHari"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 bg-slate-100 text-slate-600">
                        <p class="text-xs text-slate-400 mt-1">Terisi otomatis dari tanggal berangkat dan kembali.</p>
                    </div>
                    <div>
                        <label for="tanggal_berangkat" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Tanggal Berangkat <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="tanggal_berangkat" id="tanggal_berangkat" required
                               x-model="berangkat" value="{{ old('tanggal_berangkat') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label for="tanggal_kembali" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Tanggal Harus Kembali <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="tanggal_kembali" id="tanggal_kembali" required
                               x-model="kembali" :min="berangkat" value="{{ old('tanggal_kembali') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Pengikut ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Pengikut</h3>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="adaPengikut"
                           class="w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                    <span class="text-sm text-slate-700">Ada pengikut (maksimal {{ $maksPengikut }} orang)</span>
                </label>

                <div x-show="adaPengikut" x-cloak class="space-y-3">
                    @for ($i = 0; $i < $maksPengikut; $i++)
                        @php $ikutLama = $ubah ? $spd->pengikut->get($i) : null; @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama</label>
                                <input type="text" name="pengikut[{{ $i }}][nama]" value="{{ old("pengikut.$i.nama", $ikutLama?->nama) }}"
                                       class="w-full px-3 py-2 rounded-lg text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Lahir</label>
                                <input type="date" name="pengikut[{{ $i }}][tanggal_lahir]" value="{{ old("pengikut.$i.tanggal_lahir", $ikutLama?->tanggal_lahir?->toDateString()) }}"
                                       class="w-full px-3 py-2 rounded-lg text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Keterangan</label>
                                <input type="text" name="pengikut[{{ $i }}][keterangan]" value="{{ old("pengikut.$i.keterangan", $ikutLama?->keterangan) }}"
                                       class="w-full px-3 py-2 rounded-lg text-sm border border-slate-200 bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- ── Pembebanan ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">Pembebanan Anggaran &amp; Keterangan</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="instansi_pembebanan" class="block text-sm font-semibold text-slate-700 mb-1.5">Instansi</label>
                        <input type="text" name="instansi_pembebanan" id="instansi_pembebanan"
                               value="{{ old('instansi_pembebanan', $ubah ? $spd->instansi_pembebanan : 'Politeknik Kesehatan Kemenkes Manado') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label for="akun_pembebanan" class="block text-sm font-semibold text-slate-700 mb-1.5">Akun</label>
                        <input type="text" name="akun_pembebanan" id="akun_pembebanan" value="{{ old('akun_pembebanan', $ubah ? $spd->akun_pembebanan : '') }}"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                </div>
                <div>
                    <label for="keterangan_lain" class="block text-sm font-semibold text-slate-700 mb-1.5">Keterangan Lain</label>
                    <textarea name="keterangan_lain" id="keterangan_lain" rows="2"
                              class="w-full px-4 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('keterangan_lain', $ubah ? $spd->keterangan_lain : '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ── Tindakan ── --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit" formaction="{{ route('spd.pratinjau') }}" formtarget="_blank"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition">
                Pratinjau
            </button>
            <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                Simpan
            </button>
            <a href="{{ route('spd.index') }}"
               class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 transition self-center">
                Batal
            </a>
        </div>
    </form>
</div>
