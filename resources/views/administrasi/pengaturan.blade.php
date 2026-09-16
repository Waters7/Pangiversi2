@extends('app')

@section('title', 'Pengaturan Sistem')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Pengaturan Sistem';
        $subjudulHalaman = 'Pengingat kelengkapan berkas, kunci tanggal SPD, dan token API';
    @endphp
    @include('administrasi.partials.kepala')

    {{-- Flash Message --}}
    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Pengingat kelengkapan berkas ── --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Pengingat Kelengkapan Berkas</h3>
                <p class="text-xs text-slate-400">
                    Notifikasi otomatis untuk pegawai yang belum mengunggah berkas pertanggungjawaban
                </p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $kandidatPengingat > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $kandidatPengingat }} perjadin belum lengkap
            </span>
        </div>

        <form method="POST" action="{{ route('administrasi.pengaturan.simpan') }}" class="p-6">
            @csrf
            @method('PUT')

            <label class="flex items-start gap-3 mb-5 cursor-pointer">
                <input type="checkbox" name="pengingat_aktif" value="1"
                       @checked(old('pengingat_aktif', $pengaturan['pengingat_dokumen_aktif']) == '1')
                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Aktifkan pengingat otomatis</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Bila dimatikan, penjadwal harian tetap berjalan namun tidak mengirim notifikasi apa pun.
                    </span>
                </span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Kirim setelah <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_hari" min="0" max="90" required
                               value="{{ old('pengingat_hari', $pengaturan['pengingat_dokumen_hari']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_hari') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">hari</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Dihitung sejak tanggal perjalanan berakhir.</p>
                    @error('pengingat_hari') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Diulang tiap <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_ulang" min="1" max="60" required
                               value="{{ old('pengingat_ulang', $pengaturan['pengingat_dokumen_ulang']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_ulang') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">hari</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Selama berkasnya masih belum lengkap.</p>
                    @error('pengingat_ulang') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Paling banyak <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="pengingat_maksimal" min="0" max="20" required
                               value="{{ old('pengingat_maksimal', $pengaturan['pengingat_dokumen_maksimal']) }}"
                               class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('pengingat_maksimal') ? 'border-red-500' : 'border-slate-200' }}">
                        <span class="text-sm text-slate-500 shrink-0">kali</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Isi 0 bila ingin diingatkan terus-menerus.</p>
                    @error('pengingat_maksimal') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 mt-5 pt-5 border-t border-slate-100">
                <button type="submit" form="jalankan-pengingat"
                        class="px-5 py-2.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition">
                    Jalankan Sekarang
                </button>
                <button type="submit"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200">
                    Simpan Pengaturan
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('administrasi.pengingat') }}" id="jalankan-pengingat" class="hidden">
            @csrf
        </form>
    </div>

    {{-- Kunci tanggal dikeluarkan SPD — hanya super administrator yang melihat
         dan mengubahnya; Tim SDM yang berbagi halaman ini tidak. --}}
    @if (auth()->user()->isAdmin())
        @php $tanggalSpdTerbuka = ($pengaturan['tanggal_spd_terbuka'] ?? '0') === '1'; @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg {{ $tanggalSpdTerbuka ? 'bg-amber-50' : 'bg-slate-100' }} flex items-center justify-center">
                    <svg class="w-4 h-4 {{ $tanggalSpdTerbuka ? 'text-amber-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                        @if ($tanggalSpdTerbuka)
                            <path d="M8 11V7a4 4 0 017.5-2"/>
                        @else
                            <path d="M8 11V7a4 4 0 018 0v4"/>
                        @endif
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-slate-800 text-sm">Tanggal Dikeluarkan SPD</h3>
                    <p class="text-xs text-slate-400">
                        Siapa yang boleh menetapkan tanggal terbit SPD sendiri — untuk kasus tanggal mundur (backdate)
                    </p>
                </div>
                <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $tanggalSpdTerbuka ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $tanggalSpdTerbuka ? 'Terbuka untuk semua peran' : 'Terkunci' }}
                </span>
            </div>

            <div class="p-6">
                <p class="text-xs text-slate-500 leading-relaxed">
                    Bawaannya <strong>terkunci</strong>: pimpinan dan administrator dapat menyesuaikan tanggal terbit dengan
                    buku agenda, sedangkan peran lain otomatis mendapat tanggal pembuatan. Buka kuncinya hanya bila ada SPD yang
                    harus diterbitkan dengan tanggal lebih awal oleh pengguna biasa, lalu kunci kembali — setiap perubahan tercatat
                    pada jejak audit.
                </p>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-5 pt-5 border-t border-slate-100">
                    <p class="text-xs text-slate-500">
                        @if ($tanggalSpdTerbuka)
                            Saat ini formulir SPD <strong class="text-amber-700">setiap pengguna</strong> menampilkan kolom tanggal yang dapat diisi sendiri.
                        @else
                            Saat ini hanya <strong class="text-slate-700">pimpinan dan administrator</strong> yang dapat mengisi tanggal dikeluarkan.
                        @endif
                    </p>

                    {{-- Tombolnya membuka kotak konfirmasi; perubahan baru dikirim
                         setelah dikonfirmasi di sana. --}}
                    @if ($tanggalSpdTerbuka)
                        <button type="button" @click="$dispatch('buka-kunci-tanggal-spd')"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                            Kunci Tanggal SPD
                        </button>
                    @else
                        <button type="button" @click="$dispatch('buka-buka-tanggal-spd')"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-amber-200 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 017.5-2"/></svg>
                            Buka Tanggal SPD
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <x-modal-konfirmasi
            nama="buka-tanggal-spd"
            judul="Buka tanggal dikeluarkan SPD untuk seluruh peran?"
            :aksi="route('administrasi.tanggal-spd')"
            tombol="Ya, Buka"
            warna="amber"
            ikon="peringatan">
            <p>
                Selama terbuka, <strong class="text-slate-700">semua pengguna</strong> dapat menerbitkan SPD dengan
                tanggal mundur sampai Anda menguncinya kembali.
            </p>
            <p class="text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                Perubahan ini tercatat pada jejak audit. Jangan lupa mengunci kembali setelah SPD yang dimaksud terbit.
            </p>
            <x-slot:tambahan>
                <input type="hidden" name="tanggal_spd_terbuka" value="1">
            </x-slot:tambahan>
        </x-modal-konfirmasi>

        <x-modal-konfirmasi
            nama="kunci-tanggal-spd"
            judul="Kunci kembali tanggal dikeluarkan SPD?"
            :aksi="route('administrasi.tanggal-spd')"
            tombol="Ya, Kunci"
            warna="teal"
            ikon="peringatan">
            <p>
                Peran selain pimpinan dan administrator akan kembali mengikuti
                <strong class="text-slate-700">tanggal pembuatan</strong> sebagai tanggal dikeluarkan SPD.
            </p>
            <p class="text-xs bg-slate-50 text-slate-600 border border-slate-100 rounded-lg px-3 py-2">
                SPD yang sudah terlanjur terbit dengan tanggal mundur tidak berubah.
            </p>
        </x-modal-konfirmasi>

        {{-- Token API dashboard eksekutif: dibuat di sini supaya administrator
             tidak perlu menyunting .env di server; hanya super administrator. --}}
        @php
            $adaToken = $tokenApi['token'] !== '';
            $dariEnv = $tokenApi['sumber'] === 'env';
            $tokenTersamar = $adaToken ? str_repeat('•', 24).substr($tokenApi['token'], -4) : '';
            $asalApi = url('/api/v1/dashboard-eksekutif');
        @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5"
             x-data="{ tampil: false, tersalin: false, token: @js($tokenApi['token']), tersamar: @js($tokenTersamar),
                       salin() { navigator.clipboard.writeText(this.token).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000) }) } }">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg {{ $adaToken ? 'bg-sky-50' : 'bg-slate-100' }} flex items-center justify-center">
                    <svg class="w-4 h-4 {{ $adaToken ? 'text-sky-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="8" cy="15" r="4"/><path d="M10.85 12.15L19 4M18 5l2 2M15 8l2 2"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-slate-800 text-sm">Token API Dashboard Eksekutif</h3>
                    <p class="text-xs text-slate-400">
                        Kunci yang dibawa aplikasi dashboard untuk membaca ringkasan, realisasi, dan sebaran pegawai PANGI
                    </p>
                </div>
                <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $adaToken ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                    @if (! $adaToken)
                        API tertutup — token belum dipasang
                    @elseif ($dariEnv)
                        Aktif · dari berkas .env
                    @else
                        Aktif
                    @endif
                </span>
            </div>

            <div class="p-6 space-y-5">
                <p class="text-xs text-slate-500 leading-relaxed">
                    API ini <strong>baca-saja</strong> dan hanya dapat dipanggil dengan token di bawah. Bagikan tokennya kepada
                    pengembang aplikasi dashboard lewat jalur yang aman — jangan lewat grup obrolan bersama. Bila token
                    diduga bocor, buat yang baru: token lama seketika tidak berlaku.
                </p>

                @if ($adaToken)
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Token yang berlaku</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <code class="flex-1 min-w-0 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-700 break-all select-all"
                                  x-text="tampil ? token : tersamar">{{ $tokenTersamar }}</code>
                            <div class="flex gap-2 shrink-0">
                                <button type="button" @click="tampil = ! tampil"
                                        class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition"
                                        x-text="tampil ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
                                <button type="button" @click="salin()"
                                        class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition"
                                        x-text="tersalin ? 'Tersalin ✓' : 'Salin'">Salin</button>
                            </div>
                        </div>
                        @if ($dariEnv)
                            <p class="text-[11px] text-slate-400 mt-1.5">
                                Token ini berasal dari PANGI_API_TOKEN pada berkas .env server. Membuat token baru di sini akan
                                menggantikannya tanpa perlu menyunting .env.
                            </p>
                        @endif
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Alamat &amp; header</p>
                        <p class="text-xs font-mono text-slate-700 break-all">{{ $asalApi }}</p>
                        <p class="text-xs font-mono text-slate-700 mt-1">Authorization: Bearer &lt;token&gt;</p>
                        <p class="text-[11px] text-slate-400 mt-2">
                            Header <span class="font-mono">X-Api-Token</span> juga diterima. Batas 60 permintaan per menit.
                        </p>
                    </div>
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Jalur yang tersedia</p>
                        <ul class="text-xs font-mono text-slate-700 space-y-0.5">
                            <li>/ <span class="font-sans text-slate-400">— semua bagian sekaligus</span></li>
                            <li>/ringkasan</li>
                            <li>/realisasi</li>
                            <li>/pegawai</li>
                            <li>/tahun-anggaran</li>
                        </ul>
                        <p class="text-[11px] text-slate-400 mt-2">
                            Asal peramban yang diizinkan (CORS):
                            @if (config('api.origins') === [])
                                <span class="text-slate-500">belum ada — hanya pemanggilan dari sisi server</span>
                            @else
                                <span class="font-mono text-slate-500">{{ implode(', ', config('api.origins')) }}</span>
                            @endif
                            · diatur lewat PANGI_API_ORIGINS di .env.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-5 border-t border-slate-100">
                    @if ($tokenApi['sumber'] === 'pengaturan')
                        <button type="button" @click="$dispatch('buka-cabut-token-api')"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-sm font-semibold rounded-xl transition">
                            Cabut Token
                        </button>
                    @endif
                    <button type="button" @click="$dispatch('buka-buat-token-api')"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 {{ $adaToken ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-200' : 'bg-teal-500 hover:bg-teal-600 shadow-teal-200' }} text-white text-sm font-semibold rounded-xl transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h5M20 20v-5h-5"/><path d="M20 9A8 8 0 006.3 6.3L4 9M4 15a8 8 0 0013.7 2.7L20 15"/></svg>
                        {{ $adaToken ? 'Ganti Token' : 'Buat Token' }}
                    </button>
                </div>
            </div>
        </div>

        <x-modal-konfirmasi
            nama="buat-token-api"
            :judul="$adaToken ? 'Ganti token API?' : 'Buat token API?'"
            :aksi="route('administrasi.token-api.buat')"
            metode="POST"
            :tombol="$adaToken ? 'Ya, Ganti' : 'Ya, Buat'"
            :warna="$adaToken ? 'amber' : 'teal'"
            ikon="peringatan">
            @if ($adaToken)
                <p>
                    Token baru akan dibuat dan <strong class="text-slate-700">token yang sekarang seketika tidak berlaku</strong>.
                    Aplikasi dashboard berhenti membaca data sampai tokennya diperbarui.
                </p>
            @else
                <p>Token acak 64 karakter akan dibuat dan API dashboard eksekutif langsung dapat dipanggil dengannya.</p>
            @endif
            <p class="text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                Kirimkan token yang baru kepada pengembang aplikasi dashboard lewat jalur yang aman. Perubahan ini tercatat
                pada jejak audit.
            </p>
        </x-modal-konfirmasi>

        @if ($tokenApi['sumber'] === 'pengaturan')
        <x-modal-konfirmasi
            nama="cabut-token-api"
            judul="Cabut token API?"
            :aksi="route('administrasi.token-api.cabut')"
            metode="DELETE"
            tombol="Ya, Cabut"
            warna="red"
            ikon="peringatan">
            <p>
                Token yang dibuat di sini dihapus dan aplikasi dashboard tidak dapat lagi membaca data PANGI dengannya.
            </p>
            <p class="text-xs bg-red-50 text-red-700 border border-red-100 rounded-lg px-3 py-2">
                @if ((string) config('api.token') !== '')
                    API kembali memakai token dari PANGI_API_TOKEN pada berkas .env server.
                @else
                    API tertutup sampai token baru dibuat.
                @endif
            </p>
        </x-modal-konfirmasi>
        @endif
    @endif

</div>

@endsection
