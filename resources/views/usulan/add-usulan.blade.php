@extends('app')

@section('title', 'Buat Usulan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Buat Usulan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">Lengkapi semua data sebelum usulan diajukan</p>
        </div>
    </div>

    {{-- Main Form --}}
    <form action="{{ route('usulan.store') }}" method="POST" enctype="multipart/form-data" id="formUsulan"
          x-data="{
            konfirmasiAjukan: false,
            daftarSpd: {{ Js::from($spdTerkait) }},
            idSpd: '{{ old('id_spd') }}',
            get spd() { return this.daftarSpd.find(s => String(s.id) === String(this.idSpd)) ?? null; },
            /*
              Salin isi SPD ke formulir. Kolom yang sudah ditulis saat membuat
              SPD tidak diketik ulang di sini — selain merepotkan, dua angka
              tanggal yang berbeda antara SPD dan usulan akan menyulitkan
              bendahara saat mencocokkan berkas.
            */
            salinDariSpd() {
              const s = this.spd;
              if (! s) { return; }

              this.$refs.lokasi.value = s.tempat_tujuan ?? '';
              this.$refs.tanggalMulai.value = s.tanggal_berangkat ?? '';
              this.$refs.tanggalSelesai.value = s.tanggal_kembali ?? '';
              this.$refs.uraian.value = s.maksud ?? '';

              if (s.instansi_pembebanan && ! this.$refs.instansi.value) {
                this.$refs.instansi.value = s.instansi_pembebanan;
              }
            },
          }">
        @csrf

        {{-- Draf hanya dipulihkan pada formulir yang masih kosong: isian yang
             dikembalikan validasi tidak boleh ditimpa isi lama. --}}
        <x-simpan-draf kunci="usulan" :boleh-pulihkan="! $errors->any()" />

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-5">

                {{-- STEP 0: Dasar penugasan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="9" y1="16" x2="15" y2="16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Dasar Penugasan</h3>
                            <p class="text-xs text-slate-400">Bila SPD dibuat lewat aplikasi, pilih untuk mengisi formulir otomatis — tidak wajib</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label for="id_spd" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Perjalanan Dinas dari aplikasi <span class="text-xs font-normal text-slate-400">(opsional)</span>
                            </label>
                            <select name="id_spd" id="id_spd" x-model="idSpd" @change="salinDariSpd()"
                                    class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('id_spd') ? 'border-red-500' : 'border-slate-200' }}">
                                <option value="">— Tanpa SPD dari aplikasi, isi data perjalanan sendiri —</option>
                                @foreach ($spdTerkait as $surat)
                                    <option value="{{ $surat['id'] }}">
                                        {{ $surat['nomor'] }} · {{ $surat['tempat_tujuan'] }}
                                        ({{ \Carbon\Carbon::parse($surat['tanggal_berangkat'])->translatedFormat('d M Y') }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1.5">
                                @if (count($spdTerkait) > 0)
                                    Memilih SPD akan mengisi sendiri tujuan, tanggal, dan uraian di bawah. Usulan tetap
                                    dapat diajukan tanpa memilihnya — yang wajib adalah SPD bertanda tangan pada bagian
                                    Data Dasar Perjalanan.
                                @else
                                    Belum ada SPD yang dibuat lewat aplikasi atas nama Anda. Tidak masalah — isi data
                                    perjalanan di bawah dan unggah SPD bertanda tangan pada bagian Data Dasar Perjalanan.
                                @endif
                            </p>
                            @error('id_spd')
                                <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Ringkasan SPD terpilih: pengusul dapat memastikan
                             surat yang dipilih memang yang dimaksud. --}}
                        <template x-if="spd">
                            <div class="p-4 bg-teal-50/60 border border-teal-100 rounded-xl space-y-2 text-xs">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                                    <p><span class="text-teal-700 font-semibold">Berangkat dari:</span>
                                       <span class="text-slate-700" x-text="spd.tempat_berangkat"></span></p>
                                    <p><span class="text-teal-700 font-semibold">Tujuan:</span>
                                       <span class="text-slate-700" x-text="spd.tempat_tujuan"></span></p>
                                    <p><span class="text-teal-700 font-semibold">Lama:</span>
                                       <span class="text-slate-700" x-text="spd.lama_hari + ' hari'"></span></p>
                                    <p><span class="text-teal-700 font-semibold">Alat angkut:</span>
                                       <span class="text-slate-700" x-text="spd.alat_angkut"></span></p>
                                </div>
                                <p><span class="text-teal-700 font-semibold">Maksud:</span>
                                   <span class="text-slate-700" x-text="spd.maksud"></span></p>
                                <p x-show="spd.rekan.length > 0" class="pt-2 border-t border-teal-100 text-teal-800">
                                    SPD ini memuat
                                    <span class="font-bold" x-text="spd.rekan.length"></span>
                                    rekan pelaksana. Masing-masing mengajukan usulannya sendiri
                                    dengan SPD bertanda tangannya.
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- STEP 1: Data Dasar Perjalanan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Data Dasar Perjalanan</h3>
                            <p class="text-xs text-slate-400">Informasi utama perjalanan dinas</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">

                        {{--
                            Surat tugas ditaruh paling atas karena ia dasar
                            seluruh pengajuan: berkasnya diunggah, lalu nomornya
                            disalin dari dokumen itu.
                        --}}
                        <div class="p-4 bg-teal-50/60 border border-teal-100 rounded-xl">

                            <div class="flex items-start gap-3 mb-4">
                                <span class="w-8 h-8 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-teal-900">Surat Tugas</p>
                                    <p class="text-xs text-teal-700 leading-relaxed mt-0.5">
                                        Unggah berkas surat tugas, lalu salin nomornya
                                        persis seperti tertulis pada dokumen.
                                    </p>
                                </div>
                            </div>

                            {{-- 1. Unggah berkas --}}
                            <label for="surat_tugas" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Berkas Surat Tugas <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="surat_tugas" id="surat_tugas" required
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   data-max-mb="5" data-allowed="pdf,jpg,jpeg,png"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm bg-white transition border
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-100 file:text-teal-700 hover:file:bg-teal-200
                                          {{ $errors->has('surat_tugas') ? 'border-red-500' : 'border-slate-200' }}">
                            <p class="text-xs text-slate-400 mt-1">PDF, JPG, atau PNG — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('surat_tugas')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror

                            {{-- 2. Nomor surat, disalin dari dokumen --}}
                            <div class="mt-4 pt-4 border-t border-teal-100">
                                <label for="no_tugas" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Nomor Surat Tugas <span class="text-red-500">*</span>
                                </label>

                                <input type="text" name="no_tugas" id="no_tugas" required
                                       value="{{ old('no_tugas') }}"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('no_tugas') ? 'border-red-500' : 'border-slate-200' }}"
                                       placeholder="cth: KP.01.02/F.XXX/1557/2026">

                                <p class="text-xs text-slate-400 mt-1.5">
                                    Salin persis seperti tertulis pada surat, termasuk garis miring dan tahunnya.
                                </p>

                                @error('no_tugas')
                                    <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- SPD yang sudah ditandatangani lewat SRIKANDI beserta nomor
                             resminya. Keduanya wajib sebelum pengajuan dikirim: itulah
                             dasar persetujuan PPK yang tercatat pada jejak audit. --}}
                        <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-xl">

                            <div class="flex items-start gap-3 mb-4">
                                <span class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M9 12l2 2 4-4"/>
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-indigo-900">Surat Perjalanan Dinas Bertanda Tangan</p>
                                    <p class="text-xs text-indigo-700 leading-relaxed mt-0.5">
                                        Unggah SPD yang sudah ditandatangani PPK dan Direktur, lalu salin
                                        nomor naskahnya dari SRIKANDI. Pengajuan tidak dapat dikirim tanpa keduanya.
                                    </p>
                                </div>
                            </div>

                            <label for="spd_ditandatangani" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Berkas SPD Bertanda Tangan <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="spd_ditandatangani" id="spd_ditandatangani" required
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   data-max-mb="5" data-allowed="pdf,jpg,jpeg,png"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm bg-white transition border
                                          file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-100 file:text-indigo-700 hover:file:bg-indigo-200
                                          {{ $errors->has('spd_ditandatangani') ? 'border-red-500' : 'border-slate-200' }}">
                            <p class="text-xs text-slate-400 mt-1">PDF, JPG, atau PNG — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('spd_ditandatangani')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror

                            <div class="mt-4 pt-4 border-t border-indigo-100">
                                <label for="no_spd" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Nomor Surat Perjalanan Dinas <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="no_spd" id="no_spd" required
                                       value="{{ old('no_spd') }}"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition border {{ $errors->has('no_spd') ? 'border-red-500' : 'border-slate-200' }}"
                                       placeholder="cth: KU.02.04/F.XXX.8/1234/2026">
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Nomor naskah yang diterbitkan SRIKANDI saat SPD diregistrasi — salin persis seperti pada dokumen.
                                </p>
                                @error('no_spd')
                                    <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Jenis Kegiatan --}}
                        <div>
                            <label for="id_kegiatan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Jenis Kegiatan <span class="text-red-500">*</span>
                            </label>
                            <select name="id_kegiatan" id="id_kegiatan" required
                                    class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('id_kegiatan') ? 'border-red-500' : 'border-slate-200' }}">
                                <option value="">— Pilih jenis kegiatan —</option>
                                @foreach ($jenisKegiatan as $kegiatan)
                                    <option value="{{ $kegiatan->id }}" @selected(old('id_kegiatan') == $kegiatan->id)>{{ $kegiatan->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">
                                Maksud perjalanannya — rapat, pelatihan, monitoring, dan sebagainya.
                                Daftarnya dikelola pada Master Data.
                            </p>
                            @error('id_kegiatan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Kategori Perjalanan Dinas --}}
                        <x-pilih-kategori-perjadin :kategori="$kategoriPerjadin" />

                        {{-- Lokasi Tujuan + Instansi --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="lokasi" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Lokasi / Kota Tujuan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="lokasi" id="lokasi" list="daftar-lokasi" x-ref="lokasi"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('lokasi') ? 'border-red-500' : 'border-slate-200' }}"
                                       placeholder="cth: Jakarta, Surabaya, Bandung..."
                                       value="{{ old('lokasi') }}" required>
                                <datalist id="daftar-lokasi">
                                    @foreach ($lokasiTujuan as $l)
                                        <option value="{{ $l->nama }}">{{ $l->nama_lengkap }}</option>
                                    @endforeach
                                </datalist>
                                <p class="text-xs text-slate-400 mt-1">Pilih dari daftar lokasi terdaftar agar terhubung ke data referensi.</p>
                                @error('lokasi')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="instansi" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Instansi / Tempat Tujuan
                                </label>
                                <input type="text" name="instansi" id="instansi" x-ref="instansi"
                                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"
                                       placeholder="cth: Kemenkes RI, Hotel Grand..."
                                       value="{{ old('instansi') }}">
                            </div>
                        </div>

                        {{-- Tanggal Mulai & Selesai --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tanggal_mulai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Mulai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai" x-ref="tanggalMulai"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_mulai') ? 'border-red-500' : 'border-slate-200' }}"
                                       value="{{ old('tanggal_mulai') }}" required>
                                @error('tanggal_mulai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tanggal_selesai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Selesai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai" x-ref="tanggalSelesai"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_selesai') ? 'border-red-500' : 'border-slate-200' }}"
                                       value="{{ old('tanggal_selesai') }}" required>
                                @error('tanggal_selesai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Keterangan --}}
                        <div>
                            <label for="uraian" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Uraian Tujuan Perjalanan <span class="text-red-500">*</span>
                            </label>
                            <textarea name="uraian" id="uraian" rows="3" x-ref="uraian"
                                      class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition resize-none border {{ $errors->has('uraian') ? 'border-red-500' : 'border-slate-200' }}"
                                      placeholder="Jelaskan secara singkat maksud dan tujuan perjalanan dinas ini..."
                                      >{{ old('uraian') }}</textarea>
                            @error('uraian')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- STEP 2: Lampiran Dokumen --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                            <p class="text-xs text-slate-400">Upload dokumen pendukung perjalanan dinas</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">

                        {{-- Rundown Kegiatan (opsional) --}}
                        <div>
                            <label for="rundown_doc" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Rundown Kegiatan
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional)</span>
                            </label>
                            <input type="file" name="rundown" id="rundown_doc"
                                   accept=".pdf,.doc,.docx"
                                   data-max-mb="5"
                                   data-allowed="pdf,doc,docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, DOCX — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('lampiran_tor')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dokumen lain (opsional) --}}
                        <div>
                            <label for="lampiran_lain" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Dokumen pendukung lainnya
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional)</span>
                            </label>
                            <input type="file" name="dokumen_pendukung" id="dokumen_pendukung"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   data-max-mb="5"
                                   data-allowed="pdf,jpg,jpeg,png,doc,docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, JPG, PNG — maks. 5 MB per file</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('dokumen_pendukung')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Info --}}
                        <div class="flex items-start gap-3 p-3.5 bg-blue-50 rounded-xl border border-blue-100">
                            <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <p class="text-xs text-blue-700">
                                Surat tugas <strong>wajib</strong> dilampirkan sesuai dengan
                                tipe file yang diizinkan: PDF, JPG, PNG, DOC, DOCX. Maksimal ukuran per file: 5 MB.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            {{-- SIDEBAR: Aksi --}}
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-4">

                    {{-- Action Buttons --}}
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                        <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                        {{-- Usulan yang sudah diajukan menggerakkan SPD, biaya, dan
                             pembayaran, jadi pengisiannya ditanya ulang sekali. --}}
                        <button type="button" @click="konfirmasiAjukan = true"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Kirim Pengajuan Perjadin
                        </button>

                        <p class="text-xs text-slate-400 leading-relaxed -mt-1">
                            Pengajuan langsung berlaku — penugasannya sudah disahkan lewat SPD bertanda tangan,
                            dan persetujuan PPK tercatat pada jejak audit.
                        </p>

                        <div x-show="konfirmasiAjukan" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                             @keydown.escape.window="konfirmasiAjukan = false">
                            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left"
                                 @click.outside="konfirmasiAjukan = false">
                                <h3 class="font-bold text-slate-800 text-sm mb-2">Kirim pengajuan perjalanan dinas?</h3>

                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Apakah Anda sudah benar mengisi seluruh datanya? Periksa kembali
                                    tujuan, tanggal, dan uraian kegiatannya sebelum diajukan.
                                </p>

                                <ul class="mt-3 space-y-1.5 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3.5 py-3">
                                    <li>Tujuan: <strong class="text-slate-700" x-text="$refs.lokasi.value || '—'"></strong></li>
                                    <li>Tanggal: <strong class="text-slate-700" x-text="($refs.tanggalMulai.value || '—') + ' s.d. ' + ($refs.tanggalSelesai.value || '—')"></strong></li>
                                    <li>Nomor SPD: <strong class="text-slate-700" x-text="document.getElementById('no_spd').value || '—'"></strong></li>
                                </ul>

                                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                                    Setelah dikirim, pengajuan langsung berlaku dan menjadi dasar penyusunan biaya.
                                </p>

                                <div class="flex justify-end gap-2 mt-5">
                                    <button type="button" @click="konfirmasiAjukan = false"
                                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                                        Periksa Lagi
                                    </button>
                                    <button type="submit" name="action" value="submit"
                                            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                                        Ya, Kirim
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="action" value="draft"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Draft
                        </button>

                        <a href="{{ route('dashboard') }}"
                           class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Batal
                        </a>
                    </div>

                    {{-- Validation Summary --}}
                    @if($errors->any())
                        <div class="bg-red-50 rounded-2xl border border-red-100 p-4">
                            <p class="text-sm font-semibold text-red-700 mb-2">Harap perbaiki kesalahan berikut:</p>
                            <ul class="space-y-1">
                                @foreach($errors->all() as $error)
                                    <li class="text-xs text-red-600 flex items-start gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        {{ $error }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>
            </div>

        </div>

    </form>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const MAX_MB = 5;
    const MAX_BYTES = MAX_MB * 1024 * 1024;

    /**
     * Validate a single file input element.
     * Returns true if valid, false if not.
     */
    function validateInput(input) {
        const errorEl = input.parentElement.querySelector('.file-error');
        const allowed = (input.dataset.allowed || '').split(',').map(e => e.trim().toLowerCase());
        const maxFiles = parseInt(input.dataset.maxFiles || '1', 10);
        const files = Array.from(input.files);

        // Clear previous error
        showError(input, errorEl, null);

        if (files.length === 0) return true;

        // Max file count (multiple)
        if (files.length > maxFiles) {
            showError(input, errorEl, `Maksimal ${maxFiles} file yang dapat dipilih.`);
            input.value = '';
            return false;
        }

        for (const file of files) {
            const ext = file.name.split('.').pop().toLowerCase();

            // Extension check
            if (allowed.length && !allowed.includes(ext)) {
                showError(input, errorEl, `Format file tidak diizinkan: .${ext}. Gunakan: ${allowed.join(', ')}.`);
                input.value = '';
                return false;
            }

            // Size check
            if (file.size > MAX_BYTES) {
                const mb = (file.size / 1024 / 1024).toFixed(1);
                showError(input, errorEl, `File "${file.name}" terlalu besar (${mb} MB). Maksimal ${MAX_MB} MB.`);
                input.value = '';
                return false;
            }
        }

        return true;
    }

    function showError(input, errorEl, message) {
        if (!errorEl) return;
        if (message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
            input.classList.add('border-red-500');
            input.classList.remove('border-slate-200');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
            input.classList.remove('border-red-500');
            input.classList.add('border-slate-200');
        }
    }

    // Attach change listener to all file inputs
    document.querySelectorAll('input[type="file"][data-max-mb]').forEach(input => {
        input.addEventListener('change', () => validateInput(input));
    });

    // Block form submission if any file input has an error
    document.getElementById('formUsulan').addEventListener('submit', function (e) {
        let hasError = false;
        document.querySelectorAll('input[type="file"][data-max-mb]').forEach(input => {
            if (!validateInput(input)) hasError = true;
        });
        if (hasError) e.preventDefault();
    });
}());
</script>
@endpush
