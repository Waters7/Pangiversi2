<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — PANGI</title>
    @vite('resources/css/app.css')
    <style>[x-cloak] { display: none; }</style>
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
    <div class="flex-1 flex items-center justify-center px-8 py-12 bg-slate-50">

        <div class="w-full max-w-sm">

            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-800">Masuk</h1>
                <p class="text-sm text-slate-500 mt-1">Masukkan akun Anda untuk melanjutkan</p>
            </div>

            {{-- Form --}}
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                @if ($errors->any())
                <div class="p-3 rounded-lg bg-red-50 border border-red-100">
                    <p class="text-sm text-red-700 font-medium">{{ $errors->first() }}</p>
                </div>
                @endif

                {{-- NIP --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        NIP <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nip" value="{{ old('nip') }}" placeholder="Masukkan NIP Anda"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                </div>

                {{-- Kata Sandi --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-slate-700">
                            Kata Sandi <span class="text-red-500">*</span>
                        </label>
                        <a href="#" class="text-xs text-teal-600 hover:underline">Lupa kata sandi?</a>
                    </div>
                    <div class="relative" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="password" placeholder="Masukkan kata sandi"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition pr-10">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                            {{-- Eye open --}}
                            <svg x-show="!show" class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            {{-- Eye closed --}}
                            <svg x-show="show" x-cloak class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember"
                           class="w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <label for="remember" class="text-sm text-slate-600">Ingat saya</label>
                </div>

                {{-- Tombol Masuk --}}
                <button type="submit"
                        class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-lg transition mt-2">
                    Masuk
                </button>

            </form>

            {{-- Link Register --}}
            <p class="text-center text-sm text-slate-500 mt-6">
                Belum punya akun?
                <a href="{{ route('register') }}" class="text-teal-600 font-semibold hover:underline">Daftar</a>
            </p>

        </div>

    </div>

</div>

@vite('resources/js/app.js')

</body>
</html>
