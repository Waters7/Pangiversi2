@extends('app')

@section('title', 'Profil')

@section('content')

@php
    $punyaRekening = $pengguna->punyaRekening();
    $punyaWhatsapp = $pengguna->punyaWhatsapp();
    $lengkap = $punyaRekening && $punyaWhatsapp;
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    {{-- ── Kartu identitas ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">

        {{-- Banner merek: gradien gelap PANGI dengan logo sebagai cap air --}}
        <div class="relative h-32 sm:h-28"
             style="background:linear-gradient(110deg,#0b1620 0%,#123c40 42%,#00806f 78%,#00b39b 100%)">

            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute inset-0"
                     style="background:radial-gradient(ellipse at 88% 130%, rgba(210,223,35,.28) 0%, transparent 52%),
                            radial-gradient(ellipse at 8% -30%, rgba(0,180,216,.32) 0%, transparent 55%)"></div>

                {{-- Garis halus supaya bidangnya tidak terasa datar --}}
                <div class="absolute inset-0 opacity-[0.07]"
                     style="background:repeating-linear-gradient(115deg, #fff 0 1px, transparent 1px 14px)"></div>

                <img src="{{ asset('images/pangi-logo.png') }}" alt=""
                     class="absolute right-5 top-1/2 -translate-y-1/2 w-40 sm:w-48 opacity-20 pointer-events-none select-none"
                     aria-hidden="true">
            </div>

            <div class="absolute left-6 top-5">
                <p class="text-white/95 text-sm font-bold tracking-wide">Profil Saya</p>
                <p class="text-white/55 text-[11px] mt-0.5">Poltekkes Kemenkes Manado</p>
            </div>

            {{-- Pita empat warna merek menutup bagian bawah banner --}}
            <div class="absolute bottom-0 inset-x-0 h-1"
                 style="background:linear-gradient(90deg,#00b39b 0 25%,#d2df23 25% 50%,#00b4d8 50% 75%,#5f6663 75% 100%)"></div>
        </div>

        {{--
            Foto dinaikkan setengah tingginya ke atas pita, dengan cincin putih
            tebal agar terbaca sebagai lapisan tersendiri, bukan terpotong pita.
        --}}
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-start gap-5">

                <div class="-mt-14 shrink-0" x-data="{ ganti: false }">
                    <div class="relative w-fit">
                        <x-avatar :nama="$pengguna->nama" :foto="$pengguna->url_foto" ukuran="xl"
                                  class="ring-4 ring-white shadow-lg" />

                        <button type="button" @click="ganti = ! ganti"
                                class="absolute -bottom-0.5 -right-0.5 w-8 h-8 rounded-full bg-teal-500 hover:bg-teal-600 text-white ring-4 ring-white flex items-center justify-center transition shadow-sm"
                                title="Ganti foto profil">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <circle cx="12" cy="13" r="3"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Panel unggah muncul hanya saat dibutuhkan --}}
                    <div x-show="ganti" x-transition x-cloak
                         class="mt-3 w-64 p-3 bg-white rounded-xl border border-slate-200 shadow-lg">
                        <form method="POST" action="{{ route('profil.foto') }}" enctype="multipart/form-data">
                            @csrf
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Pilih foto</label>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                                   class="w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 {{ $errors->has('foto') ? 'text-red-600' : 'text-slate-500' }}">
                            <p class="text-[11px] text-slate-400 mt-1.5 leading-relaxed">
                                JPG, PNG, atau WebP maks. 5 MB. Gambar dipotong otomatis menjadi bujur sangkar.
                            </p>
                            @error('foto')
                                <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>
                            @enderror

                            <button type="submit"
                                    class="w-full mt-2.5 px-3 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                                Unggah Foto
                            </button>
                        </form>

                        @if ($pengguna->punyaFoto())
                            <form method="POST" action="{{ route('profil.foto.hapus') }}" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-full px-3 py-2 border border-slate-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                                    Hapus Foto
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="flex-1 min-w-0 sm:pt-4">
                    <h1 class="text-lg font-bold text-slate-800">{{ $pengguna->nama }}</h1>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-teal-50 text-teal-700 text-xs font-bold">
                            {{ $pengguna->role_label }}
                        </span>
                        @if ($pengguna->jabatan)
                            <span class="text-slate-300">·</span>
                            <span class="text-xs text-slate-500">{{ $pengguna->jabatan }}</span>
                        @endif
                        @if ($pengguna->unit)
                            <span class="text-slate-300">·</span>
                            <span class="text-xs text-slate-500">{{ $pengguna->unit->nama }}</span>
                        @endif
                    </div>
                </div>

                <div class="sm:pt-4 shrink-0">
                    @if ($lengkap)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold ring-1 ring-emerald-100 whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Profil lengkap
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-xs font-bold ring-1 ring-amber-100 whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                            </svg>
                            Perlu dilengkapi
                        </span>
                    @endif
                </div>
            </div>

            {{-- Kelengkapan yang benar-benar memengaruhi pembayaran --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                <x-profil-kelengkapan
                    judul="Rekening bank"
                    :terpenuhi="$punyaRekening"
                    :isi="$pengguna->rekening_ringkas"
                    kosong="Belum diisi — bendahara tidak dapat mentransfer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </x-profil-kelengkapan>

                <x-profil-kelengkapan
                    judul="Nomor WhatsApp"
                    :terpenuhi="$punyaWhatsapp"
                    :isi="$pengguna->no_hp"
                    kosong="Belum diisi — pengingat berkas tidak dapat dikirim">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                </x-profil-kelengkapan>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ── Identitas kepegawaian ── --}}
        <div class="xl:col-span-1">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0115 0"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Data Kepegawaian</h3>
                        <p class="text-xs text-slate-400">Dikelola Tim SDM</p>
                    </div>
                </div>

                <dl class="px-6 py-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">NIP</dt>
                        <dd class="text-slate-700 mt-0.5 font-mono">{{ $pengguna->nip }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Email</dt>
                        <dd class="text-slate-700 mt-0.5 break-all">{{ $pengguna->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Jabatan</dt>
                        <dd class="text-slate-700 mt-0.5">{{ $pengguna->jabatan ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Unit Kerja</dt>
                        <dd class="text-slate-700 mt-0.5">{{ $pengguna->unit?->nama ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Atasan Langsung</dt>
                        <dd class="text-slate-700 mt-0.5 flex items-center gap-2">
                            @if ($pengguna->atasan)
                                <x-avatar :nama="$pengguna->atasan->nama" :foto="$pengguna->atasan->url_foto" ukuran="sm" />
                                <span class="min-w-0 truncate">{{ $pengguna->atasan->nama }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>

                <p class="px-6 pb-5 text-xs text-slate-400 leading-relaxed">
                    Perubahan identitas, peran, dan penempatan dilakukan oleh Tim SDM atau
                    Super Administrator.
                </p>
            </div>
        </div>

        {{-- ── Yang dapat Anda ubah sendiri ── --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Rekening bank --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Rekening & Kontak</h3>
                        <p class="text-xs text-slate-400">Dipakai bendahara untuk mentransfer uang perjalanan dinas</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profil.rekening') }}" class="p-6">
                    @csrf
                    @method('PUT')

                    @unless ($punyaRekening)
                        <div class="mb-5 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                            </svg>
                            <p>Rekening belum lengkap. Lengkapi agar pembayaran perjalanan dinas Anda dapat diproses.</p>
                        </div>
                    @endunless

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Bank <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_bank" required list="daftar-bank"
                                   value="{{ old('nama_bank', $pengguna->nama_bank) }}" placeholder="cth. Bank Mandiri"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('nama_bank') ? 'border-red-500' : 'border-slate-200' }}">
                            <datalist id="daftar-bank">
                                @foreach (['Bank Mandiri', 'Bank BRI', 'Bank BNI', 'Bank BTN', 'Bank Syariah Indonesia', 'Bank SulutGo'] as $bank)
                                    <option value="{{ $bank }}"></option>
                                @endforeach
                            </datalist>
                            @error('nama_bank') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Rekening <span class="text-red-500">*</span></label>
                            <input type="text" name="nomor_rekening" required inputmode="numeric"
                                   value="{{ old('nomor_rekening', $pengguna->nomor_rekening) }}" placeholder="cth. 1234567890"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('nomor_rekening') ? 'border-red-500' : 'border-slate-200' }}">
                            @error('nomor_rekening') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Pemilik Rekening <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_rekening" required
                                   value="{{ old('nama_rekening', $pengguna->nama_rekening ?? $pengguna->nama) }}"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('nama_rekening') ? 'border-red-500' : 'border-slate-200' }}">
                            <p class="text-xs text-slate-400 mt-1">Tulis persis seperti yang tertera pada buku tabungan.</p>
                            @error('nama_rekening') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2 pt-4 border-t border-slate-100">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor WhatsApp</label>
                            <input type="text" name="no_hp" inputmode="tel"
                                   value="{{ old('no_hp', $pengguna->no_hp) }}" placeholder="cth. 081234567890"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('no_hp') ? 'border-red-500' : 'border-slate-200' }}">
                            <p class="text-xs text-slate-400 mt-1">
                                Dipakai tim keuangan untuk mengingatkan berkas pertanggungjawaban yang belum lengkap.
                            </p>
                            @error('no_hp') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </div>

            {{-- Kata sandi --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Ganti Kata Sandi</h3>
                        <p class="text-xs text-slate-400">Minimal 8 karakter</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profil.password') }}" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kata Sandi Saat Ini <span class="text-red-500">*</span></label>
                            <input type="password" name="password_lama" required autocomplete="current-password"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('password_lama') ? 'border-red-500' : 'border-slate-200' }}">
                            @error('password_lama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kata Sandi Baru <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('password') ? 'border-red-500' : 'border-slate-200' }}">
                            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ulangi Kata Sandi Baru <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit"
                                class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                            Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>

@endsection
