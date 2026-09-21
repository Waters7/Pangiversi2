<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
  <x-ikon-tab />
  <title>@yield('title', 'PANGI') — Poltekkes Kemenkes Manado</title>

  {{--
    Tailwind dan Alpine dimuat dari bundel Vite. Jangan menambahkan lagi
    versi CDN-nya: dua instans Alpine akan sama-sama memproses x-show dan
    membuat panel bertumpuk tidak mau bertukar.
  --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    [x-cloak] { display: none; }
    .nav-active {
      background: rgba(20,184,166,.12);
      color: #2dd4bf;
      border-left-color: #14b8a6;
    }
    .nav-item { border-left: 2px solid transparent; }
    .nav-item:not(.nav-active):hover {
      background: rgba(255,255,255,.05);
      color: #fff;
    }

    /* Sidebar always fixed, main content has left padding on lg+ */
    #sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 256px; /* w-64 */
      z-index: 50;
      background: #0f172a;
      display: flex;
      flex-direction: column;
      transform: translateX(-100%);
      transition: transform 300ms ease;
    }
    #sidebar.open {
      transform: translateX(0);
    }
    @media (min-width: 1024px) {
      #sidebar {
        transform: translateX(0) !important;
      }
      #main-content {
        padding-left: 256px;
      }
    }
    #main-content {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      width: 100%;
    }
  </style>
  @stack('head')
</head>
<body class="bg-slate-100 font-sans" x-data="{ sidebarOpen: false }" @keydown.escape="sidebarOpen = false">

@include('sidebar')

<div id="main-content">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200 px-4 md:px-8 py-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 hover:bg-slate-100 rounded-lg transition">
          <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
        <div>
          <h1 class="text-base font-bold text-slate-800">@yield('title', 'Dashboard')</h1>
          <p class="text-xs text-slate-400">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">

        {{-- Lonceng notifikasi --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
          <button @click="open = !open"
                  class="relative p-2 hover:bg-slate-100 rounded-lg transition"
                  aria-label="Notifikasi">
            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            @if (($notifikasiBelumDibaca ?? 0) > 0)
              <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ $notifikasiBelumDibaca > 9 ? '9+' : $notifikasiBelumDibaca }}
              </span>
            @endif
          </button>

          <div x-show="open" x-transition x-cloak
               class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden z-50">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
              <p class="text-sm font-bold text-slate-700">Notifikasi</p>
              @if (($notifikasiBelumDibaca ?? 0) > 0)
                <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                  @csrf
                  @method('PUT')
                  <button type="submit" class="text-xs font-semibold text-teal-600 hover:underline">Tandai semua dibaca</button>
                </form>
              @endif
            </div>

            <div class="max-h-96 overflow-y-auto divide-y divide-slate-50">
              @forelse (($notifikasiTerbaru ?? collect()) as $notif)
                <form method="POST" action="{{ route('notifikasi.baca', $notif) }}">
                  @csrf
                  @method('PUT')
                  <button type="submit"
                          class="w-full text-left px-4 py-3 hover:bg-slate-50 transition flex gap-3 {{ $notif->sudah_dibaca ? '' : 'bg-teal-50/40' }}">
                    <span class="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center {{ $notif->tipe_badge }}">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="block text-xs font-bold text-slate-800 truncate">{{ $notif->judul }}</span>
                      <span class="block text-xs text-slate-500 line-clamp-2">{{ $notif->pesan }}</span>
                      <span class="block text-[11px] text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</span>
                    </span>
                    @unless ($notif->sudah_dibaca)
                      <span class="w-2 h-2 rounded-full bg-teal-500 shrink-0 mt-1.5"></span>
                    @endunless
                  </button>
                </form>
              @empty
                <p class="px-4 py-8 text-center text-sm text-slate-400">Belum ada notifikasi</p>
              @endforelse
            </div>

            <a href="{{ route('notifikasi.index') }}"
               class="block px-4 py-3 text-center text-xs font-semibold text-teal-600 hover:bg-slate-50 border-t border-slate-100 transition">
              Lihat semua notifikasi
            </a>
          </div>
        </div>

        {{-- Panel pengguna: identitas, profil, dan keluar --}}
        @php
          $pengguna = auth()->user();
          $profilKurang = ! $pengguna->punyaRekening() || ! $pengguna->punyaWhatsapp();
        @endphp

        <div class="relative" x-data="{ menuAkun: false }" @click.outside="menuAkun = false">
          <button @click="menuAkun = !menuAkun"
                  class="flex items-center gap-2.5 pl-1.5 pr-2 py-1.5 rounded-xl hover:bg-slate-100 transition"
                  aria-label="Menu akun">
            <span class="relative shrink-0">
              <x-avatar :nama="$pengguna->nama" :foto="$pengguna->url_foto" ukuran="sm" />
              @if ($profilKurang)
                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-amber-400 ring-2 ring-white"
                      title="Profil belum lengkap"></span>
              @endif
            </span>
            <span class="hidden sm:block leading-tight text-left max-w-[11rem]">
              <span class="block text-sm font-semibold text-slate-700 truncate">{{ $pengguna->nama }}</span>
              <span class="block text-xs text-slate-400 truncate">{{ $pengguna->role_label }}</span>
            </span>
            <svg class="hidden sm:block w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform"
                 :class="menuAkun ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="menuAkun" x-transition x-cloak
               class="absolute right-0 mt-2 w-64 bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden z-50">

            <div class="px-4 py-3.5 border-b border-slate-100 flex items-center gap-3">
              <x-avatar :nama="$pengguna->nama" :foto="$pengguna->url_foto" />
              <div class="min-w-0">
                <p class="text-sm font-bold text-slate-800 truncate">{{ $pengguna->nama }}</p>
                <p class="text-xs text-slate-400 truncate">{{ $pengguna->role_label }}</p>
                <p class="text-[11px] text-slate-400 font-mono truncate">{{ $pengguna->nip }}</p>
              </div>
            </div>

            @if ($profilKurang)
              <p class="px-4 py-2.5 bg-amber-50 text-[11px] text-amber-800 leading-relaxed border-b border-amber-100">
                Profil belum lengkap — rekening atau nomor WhatsApp masih kosong.
              </p>
            @endif

            <a href="{{ route('profil.index') }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0115 0"/>
              </svg>
              Profil Saya
            </a>

            <a href="{{ route('panduan') }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition border-t border-slate-50">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
              </svg>
              Panduan Penggunaan
            </a>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
              @csrf
              <button type="submit"
                      class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
              </button>
            </form>
          </div>
        </div>
      </div>
    </header>

    @yield('content')
  </div>

  {{-- Jalan pintas melaporkan kendala, tersedia dari halaman mana pun --}}
  <x-tombol-bantuan />

@stack('scripts')
</body>
</html>