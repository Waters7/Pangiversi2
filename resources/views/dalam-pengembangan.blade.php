<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-ikon-tab />
    <title>Fitur Masih Dikembangkan — PANGI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Senada dengan halaman masuk: latar putih, warna lambang Kemenkes. */
        :root {
            --tinta: #17302c;
            --tinta-lembut: #55706b;
            --tinta-samar: #8aa39e;
            --garis: #e2ebe8;
            --kertas: #f6faf9;
        }

        body { background: #ffffff; color: var(--tinta); }

        .pangi-pita {
            height: 4px;
            background: linear-gradient(90deg,
                var(--pangi-turquoise) 0% 25%,
                var(--pangi-lime) 25% 50%,
                var(--pangi-cyan) 50% 75%,
                var(--pangi-abu) 75% 100%);
        }

        .cahaya {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }

        .kartu {
            background: linear-gradient(155deg, #00655a 0%, #007d6c 45%, #00708c 100%);
            box-shadow: 0 22px 50px -22px rgba(0, 70, 62, .55);
        }

        .penanda {
            width: 34px;
            height: 3px;
            border-radius: 999px;
            background: var(--pangi-lime);
        }

        .tombol {
            background: var(--pangi-lime);
            color: #17302c;
            box-shadow: 0 12px 26px -12px rgba(210, 223, 35, .6);
            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
        }
        .tombol:hover { filter: brightness(1.06); box-shadow: 0 16px 32px -12px rgba(210, 223, 35, .8); }
        .tombol:active { transform: translateY(1px); }
    </style>
</head>
<body class="font-sans antialiased">

    <div class="pangi-pita"></div>

    <main class="relative min-h-[calc(100vh-4px)] flex items-center justify-center px-5 py-12 overflow-hidden">

        <span class="cahaya" style="width:460px;height:460px;background:rgba(0,179,155,.14);top:-160px;left:-120px"></span>
        <span class="cahaya" style="width:360px;height:360px;background:rgba(0,180,216,.12);bottom:-130px;right:10%"></span>

        <div class="relative w-full max-w-md">

            <img src="{{ asset('images/pangi-logo.png') }}"
                 alt="PANGI — Perjadin Aman, Nggak drama, Inovatif"
                 class="w-full max-w-[240px] mx-auto mb-8 select-none"
                 draggable="false">

            <div class="kartu rounded-2xl overflow-hidden">
                <div class="pangi-pita"></div>

                <div class="p-7 sm:p-8 text-white">
                    <span class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider px-3 py-1 rounded-full"
                          style="background: rgba(255,255,255,.14)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.1 5.1a2.1 2.1 0 01-3-3l5.1-5.1m3-3l2.5-2.5a4 4 0 015.66 5.66l-2.5 2.5m-5.66-5.66l5.66 5.66"/></svg>
                        Masih dikembangkan
                    </span>

                    <h1 class="text-xl font-bold mt-4">Fitur untuk peran Anda belum tersedia</h1>
                    <div class="penanda mt-2.5 mb-4"></div>

                    <p class="text-sm leading-relaxed" style="color: rgba(255,255,255,.82)">
                        Halo, <strong class="text-white">{{ $pengguna->nama }}</strong>.
                        Akun Anda terdaftar sebagai <strong class="text-white">{{ $pengguna->peran->label() }}</strong>.
                        Modul perjalanan dinas untuk pegawai eksternal dan mahasiswa
                        <strong class="text-white">masih dikembangkan</strong> dan belum dapat dipakai untuk saat ini.
                    </p>
                    <p class="text-sm leading-relaxed mt-3" style="color: rgba(255,255,255,.82)">
                        Kami akan memberi tahu begitu modulnya siap. Untuk keperluan mendesak,
                        silakan hubungi Tim SDM Politeknik Kesehatan Kemenkes Manado.
                    </p>

                    <form action="{{ route('logout') }}" method="POST" class="mt-7">
                        @csrf
                        <button type="submit"
                                class="tombol w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-xs text-center mt-6" style="color: var(--tinta-samar)">
                Sistem Administrasi Perjalanan Dinas · Politeknik Kesehatan Kemenkes Manado
            </p>
        </div>
    </main>
</body>
</html>
