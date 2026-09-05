@extends('app')

@section('title', 'Edit Usulan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('usulan.show', $usulan) }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Usulan</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }}</p>
        </div>
        <div class="ml-auto">
            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $usulan->status_badge }}">
                {{ $usulan->status_text }}
            </span>
        </div>
    </div>

    {{-- Main Form --}}
    <form action="{{ route('usulan.update', $usulan) }}" method="POST" enctype="multipart/form-data" id="formUsulan">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-5">

                {{-- Catatan Penolakan PPK --}}
                @if($usulan->status === 'ditolak' && $usulan->catatan)
                <div class="bg-red-50 rounded-2xl border border-red-100 p-5 flex gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-red-700 mb-1">Catatan Penolakan dari PPK</p>
                        <p class="text-sm text-red-600 leading-relaxed">{{ $usulan->catatan }}</p>
                    </div>
                </div>
                @endif

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

                        {{-- Jenis Kegiatan --}}
                        <div>
                            <label for="id_kegiatan" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Jenis Kegiatan <span class="text-red-500">*</span>
                            </label>
                            <select name="id_kegiatan" id="id_kegiatan" required
                                    class="w-full px-4 py-2.5 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('id_kegiatan') ? 'border-red-500' : 'border-slate-200' }}">
                                <option value="">— Pilih jenis kegiatan —</option>
                                @foreach ($jenisKegiatan as $kegiatan)
                                    <option value="{{ $kegiatan->id }}"
                                            @selected(old('id_kegiatan', $usulan->id_kegiatan) == $kegiatan->id)>{{ $kegiatan->nama }}</option>
                                @endforeach
                            </select>
                            @error('id_kegiatan')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Kategori Perjalanan Dinas --}}
                        <x-pilih-kategori-perjadin :kategori="$kategoriPerjadin"
                                                   :terpilih="$usulan->id_kategori_perjadin" />

                        {{-- Nomor Surat Tugas --}}
                        <div>
                            <label for="no_tugas" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Nomor Surat Tugas <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="no_tugas" id="no_tugas"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('no_tugas') ? 'border-red-500' : 'border-slate-200' }}"
                                   placeholder="cth: KP.01.02/F.XXX/123/2026"
                                   value="{{ old('no_tugas', $usulan->no_tugas) }}" required>
                            <p class="text-xs text-slate-400 mt-1">Nomor pada surat tugas yang diunggah</p>
                            @error('no_tugas')
                                <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Lokasi Tujuan + Instansi --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="lokasi" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Lokasi / Kota Tujuan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="lokasi" id="lokasi" list="daftar-lokasi"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('lokasi') ? 'border-red-500' : 'border-slate-200' }}"
                                       placeholder="cth: Jakarta, Surabaya, Bandung..."
                                       value="{{ old('lokasi', $usulan->lokasi) }}" required>
                                <datalist id="daftar-lokasi">
                                    @foreach ($lokasiTujuan as $l)
                                        <option value="{{ $l->nama }}">{{ $l->nama_lengkap }}</option>
                                    @endforeach
                                </datalist>
                                @error('lokasi')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="instansi" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Instansi / Tempat Tujuan
                                </label>
                                <input type="text" name="instansi" id="instansi"
                                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"
                                       placeholder="cth: Kemenkes RI, Hotel Grand..."
                                       value="{{ old('instansi', $usulan->instansi) }}">
                            </div>
                        </div>

                        {{-- Tanggal Mulai & Selesai --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tanggal_mulai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Mulai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_mulai') ? 'border-red-500' : 'border-slate-200' }}"
                                       value="{{ old('tanggal_mulai', $usulan->tanggal_mulai) }}" required>
                                @error('tanggal_mulai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tanggal_selesai" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                    Tanggal Selesai <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('tanggal_selesai') ? 'border-red-500' : 'border-slate-200' }}"
                                       value="{{ old('tanggal_selesai', $usulan->tanggal_selesai) }}" required>
                                @error('tanggal_selesai')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Uraian --}}
                        <div>
                            <label for="uraian" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Uraian Tujuan Perjalanan
                            </label>
                            <textarea name="uraian" id="uraian" rows="3"
                                      class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition resize-none border {{ $errors->has('uraian') ? 'border-red-500' : 'border-slate-200' }}"
                                      placeholder="Jelaskan secara singkat maksud dan tujuan perjalanan dinas ini...">{{ old('uraian', $usulan->uraian) }}</textarea>
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
                            <p class="text-xs text-slate-400">Kosongkan jika tidak ingin mengganti file</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">

                        @php $dokumen = $usulan->dokumen->first(); @endphp

                        {{-- Surat Tugas --}}
                        <div>
                            <label for="surat_tugas" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Surat Tugas
                                <span class="text-xs font-normal text-slate-400 ml-1">(kosongkan jika tidak diganti)</span>
                            </label>
                            @if($dokumen?->surat_tugas)
                                <div class="flex items-center justify-between px-3 py-2 mb-2 bg-slate-50 border border-slate-200 rounded-xl">
                                    <div class="flex items-center gap-2 text-xs text-slate-600">
                                        <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span class="font-semibold text-slate-700">File saat ini:</span>
                                        <span class="text-slate-500 truncate max-w-40">{{ basename($dokumen->surat_tugas) }}</span>
                                    </div>
                                    <a href="{{ asset('storage/' . $dokumen->surat_tugas) }}" target="_blank"
                                       class="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline shrink-0">Lihat</a>
                                </div>
                            @endif
                            <input type="file" name="surat_tugas" id="surat_tugas"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   data-max-mb="5"
                                   data-allowed="pdf,jpg,jpeg,png"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition border {{ $errors->has('surat_tugas') ? 'border-red-500' : 'border-slate-200' }}">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, JPG, PNG — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('surat_tugas')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Rundown Kegiatan --}}
                        <div>
                            <label for="rundown" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Rundown Kegiatan
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional)</span>
                            </label>
                            @if($dokumen?->rundown)
                                <div class="flex items-center justify-between px-3 py-2 mb-2 bg-slate-50 border border-slate-200 rounded-xl">
                                    <div class="flex items-center gap-2 text-xs text-slate-600">
                                        <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span class="font-semibold text-slate-700">File saat ini:</span>
                                        <span class="text-slate-500 truncate max-w-40">{{ basename($dokumen->rundown) }}</span>
                                    </div>
                                    <a href="{{ asset('storage/' . $dokumen->rundown) }}" target="_blank"
                                       class="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline shrink-0">Lihat</a>
                                </div>
                            @endif
                            <input type="file" name="rundown" id="rundown"
                                   accept=".pdf,.doc,.docx"
                                   data-max-mb="5"
                                   data-allowed="pdf,doc,docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, DOCX — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('rundown')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dokumen lain --}}
                        <div>
                            <label for="lampiran_lain" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Dokumen Lainnya
                                <span class="text-xs font-normal text-slate-400 ml-1">(opsional)</span>
                            </label>
                            @if($dokumen?->dokumen_pendukung)
                                <div class="flex items-center justify-between px-3 py-2 mb-2 bg-slate-50 border border-slate-200 rounded-xl">
                                    <div class="flex items-center gap-2 text-xs text-slate-600">
                                        <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        <span class="font-semibold text-slate-700">File saat ini:</span>
                                        <span class="text-slate-500 truncate max-w-40">{{ basename($dokumen->dokumen_pendukung) }}</span>
                                    </div>
                                    <a href="{{ asset('storage/' . $dokumen->dokumen_pendukung) }}" target="_blank"
                                       class="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline shrink-0">Lihat</a>
                                </div>
                            @endif
                            <input type="file" name="dokumen_pendukung" id="lampiran_lain"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   data-max-mb="5"
                                   data-allowed="pdf,jpg,jpeg,png,doc,docx"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <p class="text-xs text-slate-400 mt-1">Format: PDF, DOC, JPG, PNG — maks. 5 MB</p>
                            <p class="file-error hidden text-red-500 text-xs mt-1"></p>
                            @error('lampiran_lain')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-start gap-3 p-3.5 bg-blue-50 rounded-xl border border-blue-100">
                            <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <p class="text-xs text-blue-700">
                                Kosongkan kolom file jika tidak ingin mengganti lampiran yang sudah ada sebelumnya.
                                Maksimal ukuran per file: <strong>5 MB</strong>.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

            {{-- SIDEBAR: Aksi --}}
            <div class="xl:col-span-1">
                <div class="sticky top-20 space-y-4">

                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                        <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                        <button type="submit" name="action" value="submit"
                                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Simpan & Kirim Verifikasi PPK
                        </button>

                        <button type="submit" name="action" value="draft"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Draft
                        </button>

                        <a href="{{ route('usulan.show', $usulan) }}"
                           class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Batal
                        </a>
                    </div>

                    {{-- Info No. Usulan --}}
                    <div class="bg-slate-50 rounded-2xl border border-slate-100 p-4">
                        <p class="text-xs text-slate-400 mb-1">No. Usulan</p>
                        <p class="text-sm font-bold text-slate-700">{{ $usulan->no_usulan }}</p>
                        <p class="text-xs text-slate-400 mt-2 mb-1">Dibuat pada</p>
                        <p class="text-xs font-semibold text-slate-600">{{ $usulan->created_at->format('d M Y, H:i') }}</p>
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
    const MAX_BYTES = 5 * 1024 * 1024;

    function validateInput(input) {
        const errorEl = input.parentElement.querySelector('.file-error');
        const allowed = (input.dataset.allowed || '').split(',').map(e => e.trim().toLowerCase());
        const maxFiles = parseInt(input.dataset.maxFiles || '1', 10);
        const files = Array.from(input.files);

        showError(input, errorEl, null);
        if (files.length === 0) return true;

        if (files.length > maxFiles) {
            showError(input, errorEl, `Maksimal ${maxFiles} file yang dapat dipilih.`);
            input.value = '';
            return false;
        }

        for (const file of files) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (allowed.length && !allowed.includes(ext)) {
                showError(input, errorEl, `Format file tidak diizinkan: .${ext}. Gunakan: ${allowed.join(', ')}.`);
                input.value = '';
                return false;
            }
            if (file.size > MAX_BYTES) {
                const mb = (file.size / 1024 / 1024).toFixed(1);
                showError(input, errorEl, `File "${file.name}" terlalu besar (${mb} MB). Maksimal 5 MB.`);
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

    document.querySelectorAll('input[type="file"][data-max-mb]').forEach(input => {
        input.addEventListener('change', () => validateInput(input));
    });

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
