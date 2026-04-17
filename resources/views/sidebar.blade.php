<!-- ═══════════════════════════════
     SIDEBAR (always fixed)
  ═══════════════════════════════ -->
  <aside id="sidebar" :class="sidebarOpen ? 'open' : ''">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
         style="background:linear-gradient(135deg,#2dd4bf,#06b6d4);box-shadow:0 4px 14px rgba(13,148,136,.4)">
        <span class="text-white font-black text-sm">PG</span>
      </div>
      <div class="leading-tight">
        <p class="text-white font-bold text-sm">PANGI</p>
        <p class="text-slate-500 text-xs">Poltekkes Kemenkes Manado</p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

      <p class="text-xs font-semibold text-slate-600 uppercase tracking-widest px-3 pt-1 pb-2">Menu Utama</p>

      <a href="{{ route('dashboard') }}"
         class="nav-item {{ request()->routeIs('dashboard') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="7" rx="1.5"/>
          <rect x="14" y="3" width="7" height="7" rx="1.5"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5"/>
          <rect x="14" y="14" width="7" height="7" rx="1.5"/>
        </svg>
        Dashboard
      </a>

      {{-- Dropdown Usulan --}}
      <div x-data="{ usulanOpen: {{request()->routeIs('usulan*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="usulanOpen = !usulanOpen"
            class="nav-item w-full {{ request()->routeIs('usulan*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
              <rect x="9" y="3" width="6" height="4" rx="1"/>
              <path d="M9 12h6M9 16h4"/>
            </svg>
            Usulan
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="usulanOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div
            x-show="usulanOpen"
            x-transition
            class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('usulan.list') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('usulan.list') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Daftar Usulan
            </a>

            <a href="{{ route('usulan.create') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('usulan.create') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Buat Usulan
            </a>

          </div>
      </div>

      <a href="https://docs.google.com/forms/d/e/1FAIpQLSftgSW_UI-x2QaRFR3HzojEWkpK1bwWnU3Z3JR3_ELiyla0aQ/viewform"
        target="_blank" class="nav-item {{ request()->routeIs('spd.create') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
         <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
         <polyline points="14 2 14 8 20 8"/>
         <line x1="12" y1="13" x2="12" y2="19"/>
         <line x1="9" y1="16" x2="15" y2="16"/>
        </svg>
        Buat SPD
      </a>

      @if (! auth()->user()->isPegawai())
      <a href="{{ route('persetujuan') }}" class="nav-item {{ request()->routeIs('persetujuan*') ? 'nav-active text-white' : 'text-slate-400' }}  flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Persetujuan
        
      </a>
      @endif

      <a href="{{ route('dokumen') }}" class="nav-item {{ request()->routeIs('dokumen*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="9" y1="13" x2="15" y2="13"/>
          <line x1="9" y1="17" x2="12" y2="17"/>
        </svg>
        Dokumen
      </a>

      @if (! auth()->user()->isPegawai())
      <a href="{{ route('keuangan') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('keuangan*') ? 'nav-active text-white' : 'text-slate-400' }} transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="1" y="4" width="22" height="16" rx="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
        Keuangan
      </a>

      <a href="{{ route('laporan') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('laporan') ? 'nav-active text-white' : 'text-slate-400' }} transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        Laporan
      </a>
      @endif

      @if (! auth()->user()->isPegawai())
      <div class="border-t border-white/10 my-3"></div>
      <p class="text-xs font-semibold text-slate-600 uppercase tracking-widest px-3 pb-2">Administrasi</p>

      <a href="{{ route('master') }}" class="{{ request()->routeIs('master') ? 'nav-active text-white' : 'text-slate-400' }} nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <ellipse cx="12" cy="5" rx="9" ry="3"/>
          <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
          <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
        </svg>
        Master Data
      </a>
      @endif

      @if (auth()->user()->isAdmin())
      <a href="{{ route('administrasi') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('administrasi', 'kegiatan.*') ? 'nav-active text-white' : 'text-slate-400' }} transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
        </svg>
        Administrasi Sistem
      </a>
      @endif
    </nav>

    <!-- User -->
    <div class="px-3 py-4 border-t border-white/10">
      <div class="flex items-center gap-3 rounded-xl px-3 py-2.5" style="background:rgba(255,255,255,.05)">
        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->nama) }}&background=14b8a6&color=fff&size=64"
           class="w-8 h-8 rounded-full shrink-0" alt="avatar"/>
        <div class="flex-1 min-w-0">
          <p class="text-white text-xs font-semibold truncate">{{ auth()->user()->nama }}</p>
          <p class="text-slate-500 text-xs truncate">{{ auth()->user()->role_label }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="text-slate-500 hover:text-red-400 transition" title="Logout">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <!-- Overlay (mobile only) -->
    <div class="fixed inset-0 bg-black/50 z-40 lg:hidden transition-opacity duration-300"
       :class="sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
       @click="sidebarOpen = false"></div>