<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <x-ikon-tab />
    <title>Masuk — PANGI</title>
    {{-- Alpine ikut terbundel di app.js, jadi halaman masuk tidak bergantung CDN luar. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none; }

        /*
         * Halaman masuk berlatar putih, mengikuti warna lambang Kemenkes:
         * toska #00b39b, hijau #d2df23, dan biru #00b4d8. Lambang PANGI
         * berlatar tembus pandang sehingga menyatu di atas putih.
         */
        :root {
            --tinta: #17302c;        /* hampir hitam, condong ke toska */
            --tinta-lembut: #55706b;
            --tinta-samar: #8aa39e;
            --garis: #e2ebe8;
            --kertas: #f6faf9;       /* putih dengan sentuhan toska tipis */
        }

        body { background: #ffffff; color: var(--tinta); }

        /* Pita empat warna palet — penanda merek yang wajib tampil. */
        .pangi-pita {
            height: 4px;
            background: linear-gradient(90deg,
                var(--pangi-turquoise) 0% 25%,
                var(--pangi-lime) 25% 50%,
                var(--pangi-cyan) 50% 75%,
                var(--pangi-abu) 75% 100%);
        }

        /* Panel lambang berlatar putih; lambangnya memang dirancang terbaca
           di atas terang. */
        .panel-merek { background: #ffffff; }

        /* Bulatan cahaya berwarna merek, sangat samar di atas putih. */
        .cahaya {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }

        /* Anyaman garis halus, meminjam motif sirkuit pada lambang. */
        .anyaman::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0, 179, 155, .08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 179, 155, .08) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse at 32% 42%, #000 14%, transparent 70%);
        }

        /*
         * Kartu masuk memakai warna Kemenkes. Gradasinya sengaja dipilih
         * dari sisi tua palet: tulisan putih di atas toska terang hanya
         * mencapai rasio 2,6:1 dan sulit dibaca, sedangkan di atas nada tua
         * ini mencapai sekitar 6:1.
         */
        .kartu {
            background: linear-gradient(155deg,
                #00655a 0%,
                #007d6c 45%,
                #00708c 100%);
            box-shadow: 0 22px 50px -22px rgba(0, 70, 62, .55);
        }

        /* Kolom isian tetap putih pekat supaya yang diketik paling terbaca. */
        .isian {
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, .35);
            color: var(--tinta);
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .isian::placeholder { color: var(--tinta-samar); }
        .isian:focus {
            outline: none;
            border-color: var(--pangi-lime);
            box-shadow: 0 0 0 3px rgba(210, 223, 35, .35);
        }

        /* Tombol hijau limau: warna paling menyala di atas toska tua,
           dengan tulisan gelap agar kontrasnya tinggi. */
        .tombol {
            background: var(--pangi-lime);
            color: #17302c;
            box-shadow: 0 12px 26px -12px rgba(210, 223, 35, .6);
            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
        }
        .tombol:hover:not(:disabled) {
            filter: brightness(1.06);
            box-shadow: 0 16px 32px -12px rgba(210, 223, 35, .8);
        }
        .tombol:active:not(:disabled) { transform: translateY(1px); }
        .tombol:disabled { opacity: .6; cursor: not-allowed; }

        .centang {
            border: 1.5px solid rgba(255, 255, 255, .5);
            background-color: rgba(255, 255, 255, .12);
        }
        .centang:checked {
            background-color: var(--pangi-lime);
            border-color: var(--pangi-lime);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%2317302c' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 8.5l3.2 3.2L13 5'/%3E%3C/svg%3E");
            background-size: 100%;
        }

        .mata { color: var(--tinta-samar); transition: color .16s ease; }
        .mata:hover { color: var(--pangi-turquoise); }

        /* Garis pemisah pada judul kartu, memakai hijau limau. */
        .penanda {
            width: 34px;
            height: 3px;
            border-radius: 999px;
            background: var(--pangi-lime);
        }
    </style>
</head>
<body class="font-sans antialiased"
      x-data="halamanMasuk()"
      x-init="mulai()">

    {{-- Sambutan saat halaman pertama dibuka --}}
    <x-pemuat-pangi id="pemuatSambutan" pesan="Menyiapkan sistem" :tampil-awal="true" />

    {{-- Pemuat kedua, tampil saat kredensial dikirim --}}
    <x-pemuat-pangi id="pemuatMasuk" pesan="Memverifikasi akun Anda" />

    <div class="pangi-pita"></div>

    <div class="min-h-[calc(100vh-4px)] flex flex-col lg:flex-row">

        {{-- ── Panel merek ── --}}
        <div class="panel-merek anyaman relative lg:w-[52%] flex items-center justify-center px-6 pt-12 pb-10 lg:py-12 overflow-hidden">

            <span class="cahaya" style="width:460px;height:460px;background:rgba(0,179,155,.14);top:-160px;left:-120px"></span>
            <span class="cahaya" style="width:360px;height:360px;background:rgba(0,180,216,.12);bottom:-130px;left:20%"></span>
            <span class="cahaya" style="width:280px;height:280px;background:rgba(210,223,35,.16);top:12%;right:-80px"></span>

            <div class="relative w-full max-w-lg text-center lg:text-left">

                <img src="{{ asset('images/pangi-logo.png') }}"
                     alt="PANGI — Perjadin Aman, Nggak drama, Inovatif"
                     class="w-full max-w-sm mx-auto lg:mx-0 mb-8 select-none"
                     draggable="false">

                <h2 class="text-2xl sm:text-3xl font-bold leading-snug" style="color: var(--tinta)">
                    Administrasi perjalanan dinas,<br class="hidden sm:block">
                    <span style="color: var(--pangi-turquoise)">tanpa drama.</span>
                </h2>

                <p class="text-sm mt-3 leading-relaxed" style="color: var(--tinta-lembut)">
                    Sistem Administrasi Perjalanan Dinas<br>
                    Politeknik Kesehatan Kemenkes Manado
                </p>

                {{-- Tiga janji singkat, memakai tiga warna palet --}}
                <div class="flex flex-wrap justify-center lg:justify-start gap-2.5 mt-8">
                    @php
                        $janji = [
                            ['Pengajuan sekali jalan', 'var(--pangi-turquoise)'],
                            ['Validasi terlacak', 'var(--pangi-cyan)'],
                            ['Arsip rapi', 'var(--pangi-lime)'],
                        ];
                    @endphp
                    @foreach ($janji as [$teks, $warna])
                        <span class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-full"
                              style="color: var(--tinta-lembut); background: var(--kertas); border: 1px solid var(--garis)">
                            <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $warna }}"></span>
                            {{ $teks }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Panel formulir ── --}}
        <div class="relative flex-1 flex items-center justify-center px-5 pb-12 pt-2 lg:py-12">
            <div class="w-full max-w-sm">

                <div class="kartu rounded-2xl overflow-hidden">
                    <div class="pangi-pita"></div>

                    <div class="p-7 sm:p-8">
                        <h1 class="text-xl font-bold text-white">Masuk</h1>
                        <div class="penanda mt-2.5 mb-3"></div>
                        <p class="text-sm" style="color: rgba(255,255,255,.78)">
                            Gunakan NIP dan kata sandi Anda
                        </p>

                        <form action="{{ route('login') }}" method="POST" class="space-y-4 mt-6"
                              @submit="tampilkanPemuat()">
                            @csrf

                            @if ($errors->any())
                                <div class="flex items-start gap-2.5 px-3.5 py-3 rounded-xl"
                                     style="background: #fef2f2; border: 1px solid #fecaca">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                                    </svg>
                                    <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                                </div>
                            @endif

                            {{-- NIP --}}
                            <div>
                                <label for="nip" class="block text-xs font-semibold uppercase tracking-wider mb-1.5"
                                       style="color: rgba(255,255,255,.80)">
                                    NIP <span style="color: var(--pangi-lime)">*</span>
                                </label>
                                <input type="text" id="nip" name="nip" value="{{ old('nip') }}" required autofocus
                                       autocomplete="username" placeholder="Masukkan NIP Anda"
                                       class="isian w-full px-3.5 py-2.5 rounded-xl text-sm">
                            </div>

                            {{-- Kata sandi --}}
                            <div x-data="{ lihat: false }">
                                <label for="password" class="block text-xs font-semibold uppercase tracking-wider mb-1.5"
                                       style="color: rgba(255,255,255,.80)">
                                    Kata Sandi <span style="color: var(--pangi-lime)">*</span>
                                </label>
                                <div class="relative">
                                    <input :type="lihat ? 'text' : 'password'" id="password" name="password" required
                                           autocomplete="current-password" placeholder="Masukkan kata sandi"
                                           class="isian w-full px-3.5 py-2.5 pr-11 rounded-xl text-sm">
                                    <button type="button" @click="lihat = !lihat"
                                            :aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                            class="mata absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg x-show="!lihat" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg x-show="lihat" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Ingat saya --}}
                            <label for="remember" class="flex items-center gap-2.5 cursor-pointer select-none w-fit">
                                <input type="checkbox" id="remember" name="remember"
                                       class="centang w-4 h-4 rounded appearance-none cursor-pointer bg-no-repeat bg-center">
                                <span class="text-sm" style="color: rgba(255,255,255,.85)">Ingat saya</span>
                            </label>

                            <button type="submit" :disabled="mengirim"
                                    class="tombol w-full py-3 text-sm font-bold rounded-xl mt-2">
                                <span x-show="!mengirim">Masuk</span>
                                <span x-show="mengirim" x-cloak>Memverifikasi…</span>
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-center text-xs mt-6" style="color: var(--tinta-samar)">
                    Butuh akun? Hubungi Tim SDM atau administrator sistem.
                </p>

                {{-- Versi tayang disebut di sini supaya laporan masalah dapat
                     menyebut versi yang dipakai tanpa perlu masuk dulu. --}}
                <p class="text-center text-[11px] mt-3 tracking-wide" style="color: var(--tinta-samar)">
                    PANGI {{ app(\App\Services\VersiAplikasi::class)->label() }}
                </p>
            </div>
        </div>
    </div>

    <script>
        function halamanMasuk() {
            return {
                mengirim: false,

                mulai() {
                    // Sambutan menghilang setelah halaman siap, dengan jeda
                    // sependek mungkin supaya tidak terasa menghambat.
                    const sembunyikan = () => {
                        setTimeout(() => {
                            document.getElementById('pemuatSambutan')
                                ?.classList.add('pangi-pemuat--sembunyi');
                        }, 650);
                    };

                    document.readyState === 'complete'
                        ? sembunyikan()
                        : window.addEventListener('load', sembunyikan, { once: true });
                },

                tampilkanPemuat() {
                    this.mengirim = true;
                    document.getElementById('pemuatMasuk')
                        ?.classList.remove('pangi-pemuat--sembunyi');
                },
            };
        }
    </script>

</body>
</html>
