<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — PANGI</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-white font-sans antialiased">

<div class="min-h-screen flex">

    {{-- LEFT PANEL --}}
    <div class="hidden lg:flex w-1/2 bg-teal-600 flex-col items-center justify-center p-12 relative overflow-hidden">

        {{-- Dekorasi lingkaran --}}
        <div class="absolute w-80 h-80 bg-teal-500 rounded-full -top-20 -left-20 opacity-40"></div>
        <div class="absolute w-64 h-64 bg-teal-700 rounded-full -bottom-16 -right-16 opacity-40"></div>

        <div class="relative z-10 text-center">
            <img src="{{ asset('images/pangi.png') }}" alt="PANGI" class="w-64 mx-auto mb-8 drop-shadow-lg">
            <h2 class="text-3xl font-bold text-white mb-3">Selamat Datang</h2>
            <p class="text-teal-100 text-sm leading-relaxed">
                Sistem Administrasi Perjalanan Dinas<br>
                Poltekkes Kemenkes Manado
            </p>
        </div>
    </div>

    {{-- RIGHT PANEL --}}
    <div class="flex-1 flex items-center justify-center px-8 py-12 overflow-y-auto bg-slate-50">

        <div class="w-full max-w-md">

            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-800">Buat Akun</h1>
                <p class="text-sm text-slate-500 mt-1">Lengkapi data diri Anda untuk mendaftar</p>
            </div>

            {{-- Form --}}
            <form action="#" method="POST" class="space-y-4">
                @csrf

                {{-- Nama Lengkap --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" placeholder="Masukkan nama lengkap"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                </div>

                {{-- NIP + Email --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            NIP <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nip" placeholder="Nomor Induk Pegawai"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" placeholder="nama@mail.com"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                </div>

                {{-- Jabatan + Unit Kerja --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Jabatan <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="jabatan" placeholder="cth: Staff Program"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Unit Kerja <span class="text-red-500">*</span>
                        </label>
                        <select name="unit_kerja"
                                class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                            <option value="">-- Pilih --</option>
                            <option value="akademik">Bag. Akademik</option>
                            <option value="keuangan">Bag. Keuangan</option>
                            <option value="kepegawaian">Bag. Kepegawaian</option>
                            <option value="umum">Bag. Umum</option>
                            <option value="kemahasiswaan">Kemahasiswaan</option>
                            <option value="penelitian">Pusat Penelitian</option>
                        </select>
                    </div>
                </div>

                {{-- Kata Sandi --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Kata Sandi <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" placeholder="Minimal 8 karakter"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition pr-10">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Konfirmasi Kata Sandi --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Konfirmasi Kata Sandi <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition pr-10">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Tombol Daftar --}}
                <button type="submit"
                        class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-lg transition mt-2">
                    Daftar Akun
                </button>

            </form>

            {{-- Link Login --}}
            <p class="text-center text-sm text-slate-500 mt-6">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="text-teal-600 font-semibold hover:underline">Masuk</a>
            </p>

        </div>

    </div>

</div>

@vite('resources/js/app.js')

</body>
</html>
