<!-- ═══════════════════════════════
     SIDEBAR (always fixed)
  ═══════════════════════════════ -->
  @php
    // Angka antrean tiap menu, dihitung sekali untuk seluruh sidebar dan
    // hanya untuk menu yang memang berhak dibuka pengguna ini.
    $antrean = app(\App\Services\AntreanPeran::class)->untuk(auth()->user());
  @endphp

  <aside id="sidebar" :class="sidebarOpen ? 'open' : ''">

    <!-- Logo -->
    <a href="{{ route('dashboard') }}" class="block px-5 py-4 border-b border-white/10 group">
      {{-- Logo sudah transparan, jadi menempel langsung pada latar sidebar. --}}
      <img src="{{ asset('images/pangi-logo.png') }}" alt="PANGI"
           class="mx-auto w-full max-w-[176px] h-auto block transition group-hover:brightness-110"
           width="900" height="405">
      <p class="text-slate-500 text-[10px] text-center mt-1 tracking-wide">Poltekkes Kemenkes Manado</p>
    </a>

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

      @can('melihat-dashboard-eksekutif')
      <a href="{{ route('dashboard-eksekutif') }}"
         class="nav-item {{ request()->routeIs('dashboard-eksekutif') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>
        </svg>
        Dashboard Eksekutif
      </a>
      @endcan

      {{-- Dropdown SPD --}}
      <div x-data="{ spdOpen: {{ request()->routeIs('spd.*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="spdOpen = !spdOpen"
            class="nav-item w-full {{ request()->routeIs('spd.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="13" x2="12" y2="19"/>
              <line x1="9" y1="16" x2="15" y2="16"/>
            </svg>
            Buat SPD
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="spdOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div
            x-show="spdOpen"
            x-transition
            class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('spd.create') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('spd.create') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Pembuatan SPD
            </a>

            <a href="{{ route('spd.index') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('spd.index', 'spd.show', 'spd.edit') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Daftar SPD
            </a>

          </div>
      </div>

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
            Usulan Perjadin
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
              Daftar Usulan Perjadin
            </a>

            <a href="{{ route('usulan.create') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('usulan.create') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Buat Usulan Perjadin
            </a>

          </div>
      </div>

      {{-- Meja pimpinan: laporan perjalanan dinas yang dikirim pelaksana,
           dikonfirmasi dan ditandatangani di sini atau dikembalikan untuk
           direvisi. Konfirmasinya salah satu syarat pelunasan pembayaran. --}}
      @can('mengonfirmasi-laporan-perjadin')
      <div x-data="{ laporanPimpinanOpen: {{ request()->routeIs('laporan-perjadin.*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="laporanPimpinanOpen = !laporanPimpinanOpen"
            class="nav-item w-full {{ request()->routeIs('laporan-perjadin.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 12l2 2 4-4"/>
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            Laporan Perjadin
            <x-lencana-antrean posisi="inline" :jumlah="$antrean['laporan-pimpinan'] ?? 0" />
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="laporanPimpinanOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="laporanPimpinanOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('laporan-perjadin.index') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan-perjadin.index', 'laporan-perjadin.show') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Daftar Laporan Perjadin
              <x-lencana-antrean :jumlah="$antrean['laporan-pimpinan'] ?? 0" />
            </a>

            <a href="{{ route('laporan-perjadin.status') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan-perjadin.status') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>
              </svg>
              Status Konfirmasi Laporan
            </a>

            <a href="{{ route('laporan-perjadin.tindak-lanjut') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan-perjadin.tindak-lanjut') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
              </svg>
              Tindak Lanjut
            </a>

          </div>
      </div>
      @endcan

      {{-- Persetujuan PPK. Bukan lagi validasi usulan — penugasan sudah
           disahkan lewat SPD — melainkan berkas keuangan yang menunggu
           keputusan dan tanda tangan PPK. --}}
      @can('menandatangani-daftar-riil')
      <div x-data="{ ppkOpen: {{ request()->routeIs('persetujuan.*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="ppkOpen = !ppkOpen"
            class="nav-item w-full {{ request()->routeIs('persetujuan.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Persetujuan
            <x-lencana-antrean posisi="inline" :jumlah="($antrean['verifikasi-rincian'] ?? 0) + ($antrean['verifikasi-riil'] ?? 0) + ($antrean['verifikasi-nominatif'] ?? 0)" />
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="ppkOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="ppkOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">
            {{-- Rincian biaya dan daftar riil dipisah karena dokumennya memang
                 berbeda: Lampiran II memuat seluruh komponen kecuali transport
                 lokal, Lampiran IX hanya memuat transport lokal. --}}
            <a href="{{ route('persetujuan.rincian-biaya') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('persetujuan.rincian-biaya') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M12 7v10"/>
              </svg>
              Verifikasi Rincian Biaya
              <x-lencana-antrean :jumlah="$antrean['verifikasi-rincian'] ?? 0" />
            </a>

            <a href="{{ route('persetujuan.daftar-riil') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('persetujuan.daftar-riil') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Verifikasi Daftar Riil
              <x-lencana-antrean :jumlah="$antrean['verifikasi-riil'] ?? 0" />
            </a>

            <a href="{{ route('persetujuan.nominatif') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('persetujuan.nominatif*') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <path d="M3 9h18M9 4v16"/>
              </svg>
              Verifikasi Daftar Nominatif
              <x-lencana-antrean :jumlah="$antrean['verifikasi-nominatif'] ?? 0" />
            </a>

            <a href="{{ route('persetujuan.riwayat') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('persetujuan.riwayat') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
              </svg>
              Riwayat Tanda Tangan
            </a>
          </div>
      </div>
      @endcan

      {{-- Dropdown Dokumen --}}
      <div x-data="{ dokumenOpen: {{ request()->routeIs('dokumen*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="dokumenOpen = !dokumenOpen"
            class="nav-item w-full {{ request()->routeIs('dokumen*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="9" y1="13" x2="15" y2="13"/>
              <line x1="9" y1="17" x2="12" y2="17"/>
            </svg>
            Dokumen
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="dokumenOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div
            x-show="dokumenOpen"
            x-transition
            class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('dokumen') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('dokumen', 'dokumen.show') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Dokumen Perdin
            </a>

            <a href="{{ route('dokumen.laporan.index') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('dokumen.laporan.index') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/>
              </svg>
              List Laporan Perjadin
            </a>

            <a href="{{ route('dokumen.tindak-lanjut') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('dokumen.tindak-lanjut') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
              </svg>
              Daftar Tindak Lanjut
            </a>

          </div>
      </div>

      {{-- Berkas keuangan milik pengguna ini, dipisah menurut jenis dokumennya --}}
      <div x-data="{ rincianOpen: {{ request()->routeIs('rincian-saya.*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="rincianOpen = !rincianOpen"
            class="nav-item w-full {{ request()->routeIs('rincian-saya.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M9 11l3 3L22 4"/>
              <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
            </svg>
            Rincian Saya
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="rincianOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="rincianOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('rincian-saya.daftar-riil') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('rincian-saya.daftar-riil') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Daftar Riil Saya
            </a>

            <a href="{{ route('rincian-saya.rincian-biaya') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('rincian-saya.rincian-biaya') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M12 7v10"/>
              </svg>
              Rincian Biaya Saya
            </a>

          </div>
      </div>

      @can('melihat-jadwal-perjalanan')
      {{-- Jadwal keberangkatan beserta peta kota tujuan: dalam kota & sekitarnya, dan luar kota. --}}
      <div x-data="{ jadwalOpen: {{ request()->routeIs('jadwal-perjalanan', 'jadwal-perjalanan.*') ? 'true' : 'false' }} }">
        <button
          type="button"
          @click.stop="jadwalOpen = !jadwalOpen"
          class="nav-item w-full {{ request()->routeIs('jadwal-perjalanan', 'jadwal-perjalanan.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          Jadwal Perjalanan
          <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
              :class="jadwalOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </button>

        <div x-show="jadwalOpen" x-transition class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">
          @foreach ([
            ['url' => route('jadwal-perjalanan'), 'aktif' => request()->routeIs('jadwal-perjalanan'), 'label' => 'Jadwal Keberangkatan'],
            ['url' => route('jadwal-perjalanan.peta', 'dalam-kota'), 'aktif' => request()->routeIs('jadwal-perjalanan.peta') && request()->route('jenis') === 'dalam-kota', 'label' => 'Peta Dalam Kota & Sekitarnya'],
            ['url' => route('jadwal-perjalanan.peta', 'luar-kota'), 'aktif' => request()->routeIs('jadwal-perjalanan.peta') && request()->route('jenis') === 'luar-kota', 'label' => 'Peta Luar Kota'],
          ] as $menu)
            <a href="{{ $menu['url'] }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ $menu['aktif'] ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
              {{ $menu['label'] }}
            </a>
          @endforeach
        </div>
      </div>
      @endcan

      {{-- Menu kerja bendahara: daftar tahap, lalu jurnal riwayatnya --}}
      @can('melihat-pembayaran')
      <div x-data="{ bayarOpen: {{ request()->routeIs('pembayaran*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="bayarOpen = !bayarOpen"
            class="nav-item w-full {{ request()->routeIs('pembayaran*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Pembayaran
            <x-lencana-antrean posisi="inline" :jumlah="$antrean['pembayaran'] ?? 0" />
            <svg class="w-3.5 h-3.5 ml-auto shrink-0 transition-transform" :class="bayarOpen && 'rotate-180'"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="bayarOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('pembayaran') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('pembayaran') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Daftar Pembayaran
              <x-lencana-antrean :jumlah="$antrean['pembayaran'] ?? 0" />
            </a>

            <a href="{{ route('pembayaran.transport-lokal') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('pembayaran.transport-lokal') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="1" y="6" width="15" height="11" rx="2"/><path d="M16 10h4l3 3v4h-7z"/>
                <circle cx="5.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/>
              </svg>
              Bayar Transport Lokal
            </a>

            <a href="{{ route('pembayaran.riwayat') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('pembayaran.riwayat') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
              </svg>
              Riwayat Pembayaran
            </a>

          </div>
      </div>
      @endcan

      @can('melihat-keuangan')
      {{-- Transport lokal bermenu sendiri karena ia di luar rincian biaya:
           dinyatakan pelaksana pada daftar riil, dibayarkan sebagai
           penggantian saat pelunasan. --}}
      <div x-data="{ keuanganOpen: {{ request()->routeIs('keuangan*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="keuanganOpen = !keuanganOpen"
            class="nav-item w-full {{ request()->routeIs('keuangan*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="1" y="4" width="22" height="16" rx="2"/>
              <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            Keuangan
            <x-lencana-antrean posisi="inline" :jumlah="$antrean['keuangan'] ?? 0" />
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="keuanganOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="keuanganOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            <a href="{{ route('keuangan') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('keuangan', 'keuangan.detail') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              {{ auth()->user()->bisaMengelolaBiaya() ? 'Input Rincian Biaya' : 'Rincian Biaya Usulan' }}
              <x-lencana-antrean :jumlah="$antrean['keuangan'] ?? 0" />
            </a>

            <a href="{{ route('keuangan.transport-lokal') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('keuangan.transport-lokal') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 17h2l1-4h12l1 4h2M5 13l1.5-5h11L19 13M7 17a1.5 1.5 0 103 0 1.5 1.5 0 10-3 0M14 17a1.5 1.5 0 103 0 1.5 1.5 0 10-3 0"/>
              </svg>
              {{ auth()->user()->bisaMengelolaBiaya() ? 'Periksa Transport Lokal' : 'Transport Lokal' }}
            </a>

          </div>
      </div>

      @endcan

      {{-- Laporan. Daftar riil dan daftar nominatif ikut di sini karena Tim SDM
           membacanya sebagai arsip, bukan mengerjakannya. --}}
      @can('melihat-arsip-perjadin')
      <div x-data="{ laporanOpen: {{ request()->routeIs('laporan', 'laporan.*') ? 'true' : 'false' }} }">
          <button
            type="button"
            @click.stop="laporanOpen = !laporanOpen"
            class="nav-item w-full {{ request()->routeIs('laporan', 'laporan.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Laporan
            <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
                :class="laporanOpen ? 'rotate-180' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <div x-show="laporanOpen" x-transition
               class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">

            @can('melihat-laporan')
            <a href="{{ route('laporan') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan', 'laporan.show') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 10h16M4 14h10"/>
              </svg>
              Rekap Perjadin
            </a>
            @endcan

            <a href="{{ route('laporan.daftar-riil') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan.daftar-riil') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
              </svg>
              Arsip Daftar Riil
            </a>

            <a href="{{ route('laporan.rincian-lengkap') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan.rincian-lengkap') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
              </svg>
              Arsip Rincian Lengkap
            </a>

            <a href="{{ route('laporan.nominatif') }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs('laporan.nominatif') ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <path d="M3 9h18M9 4v16"/>
              </svg>
              List Daftar Nominatif
            </a>

          </div>
      </div>
      @endcan

      @can('melihat-laporan')
      <div class="border-t border-white/10 my-3"></div>
      <p class="text-xs font-semibold text-slate-600 uppercase tracking-widest px-3 pb-2">Administrasi</p>

      {{-- Dropdown Master Data --}}
      <div x-data="{ masterOpen: {{ request()->routeIs('master', 'master.*', 'kegiatan.*') ? 'true' : 'false' }} }">
        <button
          type="button"
          @click.stop="masterOpen = !masterOpen"
          class="nav-item w-full {{ request()->routeIs('master', 'master.*', 'kegiatan.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <ellipse cx="12" cy="5" rx="9" ry="3"/>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
          </svg>
          Master Data
          <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
              :class="masterOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </button>

        <div x-show="masterOpen" x-transition class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">
          @php
            $menuMaster = [
              ['route' => 'master', 'aktif' => 'master', 'label' => 'Ringkasan Data'],
            ];

            if (auth()->user()->can('mengelola-master-data')) {
              $menuMaster = array_merge($menuMaster, [
                ['route' => 'master.unit-kerja', 'aktif' => 'master.unit-kerja', 'label' => 'Unit Kerja'],
                ['route' => 'master.lokasi', 'aktif' => 'master.lokasi', 'label' => 'Lokasi Tujuan'],
                ['route' => 'master.kategori-perjadin', 'aktif' => 'master.kategori-perjadin', 'label' => 'Kategori Perjadin'],
                ['route' => 'master.komponen-biaya', 'aktif' => 'master.komponen-biaya', 'label' => 'Komponen Biaya'],
                ['route' => 'master.status-hasil', 'aktif' => 'master.status-hasil', 'label' => 'Status Hasil'],
                ['route' => 'master.kategori-pembiayaan', 'aktif' => 'master.kategori-pembiayaan', 'label' => 'Kategori Pembiayaan'],
                ['route' => 'master.akun-pembiayaan', 'aktif' => 'master.akun-pembiayaan', 'label' => 'Akun Pembiayaan'],
                ['route' => 'master.tahun-anggaran', 'aktif' => 'master.tahun-anggaran', 'label' => 'Tahun Anggaran'],
                ['route' => 'kegiatan.index', 'aktif' => 'kegiatan.*', 'label' => 'Jenis Kegiatan'],
              ]);
            }
          @endphp

          @foreach ($menuMaster as $menu)
            <a href="{{ route($menu['route']) }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs($menu['aktif']) ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
              {{ $menu['label'] }}
            </a>
          @endforeach
        </div>
      </div>
      @endcan

      @can('melihat-jejak-audit')
      <a href="{{ route('audit-log') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('audit-log') ? 'nav-active text-white' : 'text-slate-400' }} transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9"/>
          <path d="M12 7v5l3 2"/>
        </svg>
        Jejak Audit
      </a>
      @endcan

      @can('mengelola-pengguna')
      {{-- Administrasi Sistem dipecah per halaman supaya tiap urusan —
           akun, impor massal, pengaturan — punya pintunya sendiri. --}}
      <div x-data="{ administrasiOpen: {{ request()->routeIs('administrasi', 'administrasi.*') ? 'true' : 'false' }} }">
        <button
          type="button"
          @click.stop="administrasiOpen = !administrasiOpen"
          class="nav-item w-full {{ request()->routeIs('administrasi', 'administrasi.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
          </svg>
          Administrasi Sistem
          <svg class="w-3.5 h-3.5 ml-auto transition-transform duration-200 shrink-0"
              :class="administrasiOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </button>

        <div x-show="administrasiOpen" x-transition class="mt-0.5 ml-3 pl-4 border-l border-white/10 space-y-0.5 overflow-hidden">
          @foreach ([
            ['route' => 'administrasi', 'aktif' => 'administrasi', 'label' => 'Pengguna'],
            ['route' => 'administrasi.massal', 'aktif' => 'administrasi.massal', 'label' => 'Impor & Ekspor'],
            ['route' => 'administrasi.pengaturan', 'aktif' => 'administrasi.pengaturan', 'label' => 'Pengaturan Sistem'],
            ...(auth()->user()->can('mengelola-peran') ? [['route' => 'administrasi.peran', 'aktif' => 'administrasi.peran', 'label' => 'Peran & Hak Akses']] : []),
            ...(auth()->user()->isAdmin() ? [['route' => 'administrasi.integrasi', 'aktif' => 'administrasi.integrasi', 'label' => 'Integrasi Data']] : []),
          ] as $menu)
            <a href="{{ route($menu['route']) }}"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all
                      {{ request()->routeIs($menu['aktif']) ? 'text-teal-400 bg-white/5' : 'text-slate-500 hover:text-slate-300 hover:bg-white/5' }}">
              <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
              {{ $menu['label'] }}
            </a>
          @endforeach
        </div>
      </div>
      @endcan

      <div class="border-t border-white/10 my-3"></div>

      <a href="{{ route('panduan') }}" class="nav-item {{ request()->routeIs('panduan') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        Panduan Penggunaan
      </a>

      {{-- Saluran bantuan: terbuka bagi semua peran. Lencananya menghitung
           obrolan yang menunggu tanggapan orang yang sedang melihat. --}}
      @php
        $bantuanMenunggu = \App\Models\ObrolanBantuan::query()
            ->when(
                auth()->user()->isAdmin(),
                fn ($q) => $q->where('status', \App\Models\ObrolanBantuan::STATUS_TERBUKA),
                fn ($q) => $q->where('id_pelapor', auth()->id())
                    ->where('status', \App\Models\ObrolanBantuan::STATUS_DIJAWAB),
            )
            ->count();
      @endphp

      <a href="{{ route('bantuan.index') }}" class="nav-item {{ request()->routeIs('bantuan.*') ? 'nav-active text-white' : 'text-slate-400' }} flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
        </svg>
        {{ auth()->user()->isAdmin() ? 'Laporan Kendala' : 'Bantuan' }}
        @if ($bantuanMenunggu > 0)
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full bg-teal-500 text-white">{{ $bantuanMenunggu }}</span>
        @endif
      </a>
    </nav>

  </aside>

  <!-- Overlay (mobile only) -->
    <div class="fixed inset-0 bg-black/50 z-40 lg:hidden transition-opacity duration-300"
       :class="sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
       @click="sidebarOpen = false"></div>