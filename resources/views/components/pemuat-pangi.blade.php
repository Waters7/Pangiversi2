@props([
    'id' => 'pemuatPangi',
    'pesan' => 'Menyiapkan sistem',
    'tampilAwal' => false,
])

{{--
    Layar pemuat PANGI.

    Dipakai dua kali pada alur masuk: sekali sebagai sambutan saat halaman
    login pertama dibuka, dan sekali lagi saat kredensial dikirim. Latarnya putih,
    senada dengan halaman masuk, dan lambangnya berlatar tembus pandang
    sehingga menyatu tanpa tepi.
--}}
<div id="{{ $id }}"
     class="pangi-pemuat {{ $tampilAwal ? '' : 'pangi-pemuat--sembunyi' }}"
     role="status"
     aria-live="polite"
     aria-label="{{ $pesan }}">

    <div class="pangi-pemuat__isi">
        {{-- Cahaya lembut di belakang lambang --}}
        <span class="pangi-pemuat__aura" aria-hidden="true"></span>

        <img src="{{ asset('images/pangi-logo.png') }}"
             alt="PANGI — Perjadin Aman, Nggak drama, Inovatif"
             class="pangi-pemuat__logo">

        <p class="pangi-pemuat__pesan">{{ $pesan }}</p>

        {{-- Bilah tak-tentu: bergerak terus selama proses berjalan --}}
        <div class="pangi-pemuat__bilah" aria-hidden="true">
            <span class="pangi-pemuat__bilah-isi"></span>
        </div>
    </div>
</div>

@once
<style>
    .pangi-pemuat {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        opacity: 1;
        transition: opacity .55s ease, visibility .55s ease;
    }

    .pangi-pemuat--sembunyi {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .pangi-pemuat__isi {
        position: relative;
        width: min(88vw, 460px);
        text-align: center;
    }

    .pangi-pemuat__aura {
        position: absolute;
        left: 50%;
        top: 38%;
        width: 420px;
        height: 420px;
        max-width: 90vw;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 179, 155, .22) 0%, rgba(0, 180, 216, .10) 42%, transparent 70%);
        animation: pangi-nafas 3.6s ease-in-out infinite;
    }

    .pangi-pemuat__logo {
        position: relative;
        width: 100%;
        height: auto;
        display: block;
        /* Lambang berlatar tembus pandang, jadi tepinya tidak terlihat. */
        animation: pangi-muncul 1.1s cubic-bezier(.22, .61, .36, 1) both;
    }

    .pangi-pemuat__pesan {
        position: relative;
        margin: 4px 0 18px;
        font-size: 12px;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: rgba(31, 51, 48, .55);
        animation: pangi-muncul 1.1s cubic-bezier(.22, .61, .36, 1) .25s both;
    }

    .pangi-pemuat__bilah {
        position: relative;
        width: 180px;
        height: 3px;
        margin: 0 auto;
        border-radius: 999px;
        overflow: hidden;
        background: rgba(0, 179, 155, .14);
        animation: pangi-muncul 1.1s cubic-bezier(.22, .61, .36, 1) .35s both;
    }

    .pangi-pemuat__bilah-isi {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        /* Tiga warna merek mengalir dari kiri ke kanan. */
        background: linear-gradient(90deg,
            transparent 0%,
            var(--pangi-turquoise, #00b39b) 30%,
            var(--pangi-cyan, #00b4d8) 55%,
            var(--pangi-lime, #d2df23) 80%,
            transparent 100%);
        transform: translateX(-100%);
        animation: pangi-alir 1.5s ease-in-out infinite;
    }

    @keyframes pangi-muncul {
        from { opacity: 0; transform: translateY(14px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @keyframes pangi-alir {
        0%   { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    @keyframes pangi-nafas {
        0%, 100% { opacity: .55; transform: translate(-50%, -50%) scale(1); }
        50%      { opacity: 1;   transform: translate(-50%, -50%) scale(1.08); }
    }

    /* Hormati pengguna yang meminta gerak seminimal mungkin. */
    @media (prefers-reduced-motion: reduce) {
        .pangi-pemuat__logo,
        .pangi-pemuat__pesan,
        .pangi-pemuat__bilah,
        .pangi-pemuat__aura {
            animation: none;
        }

        .pangi-pemuat__bilah-isi {
            animation-duration: 3s;
        }
    }
</style>
@endonce
