@extends('app')

@section('title', 'Dashboard')
@section('content')
  <main class="flex-1 px-4 md:px-8 py-7 space-y-6 overflow-y-auto">

      <!-- Welcome Banner -->
      <div class="relative overflow-hidden rounded-2xl px-6 md:px-8 py-6 text-white"
         style="background:linear-gradient(120deg,#0d9488,#14b8a6,#06b6d4)">
        <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full" style="background:rgba(255,255,255,.08)"></div>
        <div class="absolute -bottom-14 -left-6 w-40 h-40 rounded-full" style="background:rgba(255,255,255,.06)"></div>
        <div class="relative">
          <p class="text-teal-100 text-sm font-medium mb-0.5">Selamat Datang 👋</p>
          <h2 class="text-xl md:text-2xl font-bold mb-1">{{ $user->nama }}</h2>
          <p class="text-teal-100 text-sm max-w-xl">
            Pantau jadwal perjalanan dinas Anda, jangan lewat batas penyerahan laporan, dan ikuti proses pembayarannya di sini.
          </p>
        </div>
      </div>

      {{-- Antrean kerja lebih dulu daripada perjalanan pribadi: yang menunggu
           tindakan orang lain lebih mendesak daripada catatan sendiri. --}}
      <x-antrean-kerja :antrean="$antrean" />

      {{-- Pengingat batas laporan. Muncul hanya bila memang ada yang
           tertagih, supaya tidak jadi hiasan yang diabaikan. --}}
      @if ($perluLaporan->isNotEmpty())
      <div class="rounded-2xl border {{ $laporanTerlambat->isNotEmpty() ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} px-4 md:px-6 py-5">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl {{ $laporanTerlambat->isNotEmpty() ? 'bg-red-500' : 'bg-amber-400' }} flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="font-bold {{ $laporanTerlambat->isNotEmpty() ? 'text-red-800' : 'text-amber-800' }} text-sm md:text-base">
              @if ($laporanTerlambat->isNotEmpty())
                {{ $laporanTerlambat->count() }} laporan perjalanan dinas melewati batas
              @else
                {{ $perluLaporan->count() }} laporan perjalanan dinas belum lengkap
              @endif
            </h3>
            <p class="text-xs md:text-sm {{ $laporanTerlambat->isNotEmpty() ? 'text-red-700' : 'text-amber-700' }} mt-1 leading-relaxed">
              Berkas pertanggungjawaban wajib lengkap paling lambat
              <strong>H+{{ $tenggangLaporan }}</strong> terhitung sejak perjalanan berakhir.
            </p>

            <div class="mt-3 space-y-2">
              @foreach ($perluLaporan->take(3) as $item)
              <a href="{{ route('dokumen.show', $item->no_usulan) }}"
                 class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-white/70 hover:bg-white px-3 py-2 transition">
                <span class="font-mono text-xs font-bold text-slate-700">{{ $item->no_usulan }}</span>
                <span class="text-xs text-slate-500 truncate">{{ $item->lokasi }}</span>
                @if ($item->sisa_hari_laporan < 0)
                  <span class="text-xs font-bold text-red-600">
                    Terlambat {{ abs($item->sisa_hari_laporan) }} hari
                  </span>
                @elseif ($item->sisa_hari_laporan === 0)
                  <span class="text-xs font-bold text-amber-700">Jatuh tempo hari ini</span>
                @else
                  <span class="text-xs font-bold text-amber-700">
                    Sisa {{ $item->sisa_hari_laporan }} hari
                  </span>
                @endif
                <span class="text-xs text-slate-400">
                  Batas {{ $item->batas_laporan?->translatedFormat('d M Y') }}
                </span>
                <span class="ml-auto text-xs font-semibold text-teal-600">Unggah berkas →</span>
              </a>
              @endforeach

              @if ($perluLaporan->count() > 3)
              <a href="{{ route('dokumen') }}" class="inline-block text-xs font-semibold text-slate-600 hover:text-teal-700">
                dan {{ $perluLaporan->count() - 3 }} perjalanan lainnya →
              </a>
              @endif
            </div>

            {{-- Konsekuensi yang benar-benar berlaku di sistem ini, bukan ancaman umum. --}}
            <div class="mt-3 pt-3 border-t {{ $laporanTerlambat->isNotEmpty() ? 'border-red-200' : 'border-amber-200' }}">
              <p class="text-xs font-semibold {{ $laporanTerlambat->isNotEmpty() ? 'text-red-800' : 'text-amber-800' }} mb-1">
                Bila lewat batas:
              </p>
              <ul class="text-xs {{ $laporanTerlambat->isNotEmpty() ? 'text-red-700' : 'text-amber-700' }} space-y-0.5 list-disc list-inside leading-relaxed">
                <li>Pelunasan sisa 20% tertahan — bendahara hanya memproses berkas yang lengkap.</li>
                <li>Perjalanan tidak dapat ditutup, statusnya tertahan di "Disetujui".</li>
                <li>Pengingat terus dikirim berulang dan tercatat pada jejak audit.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Stat Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            </div>
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $totalUsulan }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Usulan Saya</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            @if ($akanBerangkat->isNotEmpty())
            <span class="text-xs font-bold text-cyan-600 bg-cyan-50 px-2 py-0.5 rounded-full">Terjadwal</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $akanBerangkat->count() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Akan Berangkat</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            @if ($sedangBerjalan->isNotEmpty())
            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Berjalan</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $sedangBerjalan->count() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Sedang Berjalan</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl {{ $laporanTerlambat->isNotEmpty() ? 'bg-red-50' : 'bg-amber-50' }} flex items-center justify-center">
              <svg class="w-5 h-5 {{ $laporanTerlambat->isNotEmpty() ? 'text-red-500' : 'text-amber-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            @if ($laporanTerlambat->isNotEmpty())
            <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">Terlambat</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $perluLaporan->count() }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Perlu Laporan (H+{{ $tenggangLaporan }})</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            @if ($selesai > 0)
            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full">Selesai</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $selesai }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Perdin Selesai</p>
        </div>
      </div>

      <!-- Tabel + Kanan -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri -->
        <div class="lg:col-span-2 space-y-6">

        <!-- Jadwal Perjalanan Saya -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm md:text-base">
              <span class="w-2 h-2 rounded-full bg-cyan-400 inline-block shrink-0"></span>
              Jadwal Perjalanan Saya
            </h3>
            <span class="text-xs font-bold text-cyan-600">{{ $sedangBerjalan->count() + $akanBerangkat->count() }} terjadwal</span>
          </div>

          @if ($sedangBerjalan->isEmpty() && $akanBerangkat->isEmpty())
            <div class="px-4 md:px-6 py-10 text-center">
              <svg class="w-10 h-10 text-slate-200 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <p class="text-sm text-slate-400 mt-2">Tidak ada perjalanan dinas yang dijadwalkan</p>
            </div>
          @else
            <div class="divide-y divide-slate-100">
              {{-- Yang sedang berlangsung didahulukan: itu yang paling perlu diingat. --}}
              @foreach ($sedangBerjalan->concat($akanBerangkat) as $item)
              <div class="px-4 md:px-6 py-3.5 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-bold text-slate-700">{{ $item->no_usulan }}</span>
                    @if ($item->fase === 'berjalan')
                      <span class="text-xs font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">Sedang berjalan</span>
                    @elseif ($item->hari_menuju_berangkat === 1)
                      <span class="text-xs font-bold text-cyan-700 bg-cyan-100 px-2 py-0.5 rounded-full">Berangkat besok</span>
                    @else
                      <span class="text-xs font-bold text-cyan-700 bg-cyan-100 px-2 py-0.5 rounded-full">
                        Berangkat {{ $item->hari_menuju_berangkat }} hari lagi
                      </span>
                    @endif
                  </div>
                  <p class="text-xs text-slate-500 mt-1 truncate">
                    {{ $item->lokasi }}
                    <span class="text-slate-400">
                      · {{ \Carbon\Carbon::parse($item->tanggal_mulai)->translatedFormat('d M') }}–{{ \Carbon\Carbon::parse($item->tanggal_selesai)->translatedFormat('d M Y') }}
                    </span>
                  </p>
                  <p class="text-xs text-slate-400 mt-0.5">
                    Batas laporan H+{{ $tenggangLaporan }}:
                    <span class="font-semibold text-slate-500">{{ $item->batas_laporan?->translatedFormat('d M Y') }}</span>
                  </p>
                </div>
                <a href="{{ route('usulan.show', $item) }}" class="text-xs text-teal-600 font-semibold hover:underline shrink-0">Detail →</a>
              </div>
              @endforeach
            </div>
            <div class="px-4 md:px-6 py-3 border-t border-slate-100">
              <a href="{{ route('dokumen') }}" class="text-xs font-bold text-teal-600 hover:text-teal-700">
                Siapkan berkas pertanggungjawaban →
              </a>
            </div>
          @endif
        </div>

        <!-- Tabel Usulan Terbaru -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm md:text-base">
              <span class="w-2 h-2 rounded-full bg-teal-400 inline-block shrink-0"></span>
              Usulan Terbaru Saya
            </h3>
            <a href="{{ route('usulan.list') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Lihat Semua →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs md:text-sm">
              <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  <th class="px-3 md:px-5 py-3 text-left">No. Usulan</th>
                  <th class="px-3 md:px-5 py-3 text-left">Tujuan</th>
                  <th class="px-3 md:px-5 py-3 text-left">Tanggal</th>
                  <th class="px-3 md:px-5 py-3 text-left">Status</th>
                  <th class="px-3 md:px-5 py-3 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">

                @forelse ($recentUsulan as $item)
                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="font-mono text-xs font-semibold text-slate-700">{{ $item->no_usulan }}</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">{{ $item->lokasi }}</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">
                    {{ \Carbon\Carbon::parse($item->tanggal_mulai)->format('d M') }}–{{ \Carbon\Carbon::parse($item->tanggal_selesai)->format('d M') }}
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <x-status-badge :usulan="$item" />
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="{{ route('usulan.show', $item) }}" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="px-5 py-10 text-center">
                    <div class="flex flex-col items-center gap-2">
                      <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
                      <p class="text-sm text-slate-400">Belum ada usulan perjalanan dinas</p>
                      <a href="{{ route('usulan.create') }}" class="text-xs text-teal-600 font-semibold hover:underline mt-1">Buat Usulan Pertama →</a>
                    </div>
                  </td>
                </tr>
                @endforelse

              </tbody>
            </table>
          </div>
        </div>

        </div>

        <!-- Kolom Kanan -->
        <div class="space-y-5">

          <!-- Surat Perjalanan Dinas -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 class="font-bold text-slate-800 text-sm">Surat Perjalanan Dinas</h3>
              <span class="text-xs font-bold text-teal-600">{{ $totalSpd }}</span>
            </div>

            @if ($spdTerbaru->isEmpty())
              <div class="px-4 md:px-5 py-6 text-center">
                <p class="text-sm text-slate-500 leading-relaxed">
                  Belum ada SPD. Usulan perjalanan dinas diajukan setelah SPD terbit.
                </p>
                <a href="{{ route('spd.create') }}"
                   class="inline-block mt-3 px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold transition">
                  Buat SPD Pertama
                </a>
              </div>
            @else
              <div class="divide-y divide-slate-100">
                @foreach ($spdTerbaru as $surat)
                  <a href="{{ route('spd.show', $surat) }}"
                     class="block px-4 md:px-5 py-3 hover:bg-slate-50/70 transition">
                    <p class="font-mono text-xs text-teal-700 truncate">
                      {{ $surat->nomorUntuk(auth()->user()) ?? 'Tanpa nomor' }}
                    </p>
                    <p class="text-xs text-slate-600 mt-0.5 truncate">
                      {{ $surat->tempat_tujuan }}
                      <span class="text-slate-400">· {{ $surat->tanggal_berangkat?->translatedFormat('d M Y') }}</span>
                    </p>
                  </a>
                @endforeach
              </div>
              <div class="px-4 md:px-5 py-3 border-t border-slate-100">
                <a href="{{ route('spd.index') }}" class="text-xs font-bold text-teal-600 hover:text-teal-700">
                  Lihat Semua →
                </a>
              </div>
            @endif
          </div>

          <!-- Status Pembayaran -->
          {{-- Satu-satunya status yang masih ditunggu pengusul: sampai mana
               bendahara memproses uangnya. Persetujuan sudah tidak ada,
               penugasannya disahkan lewat SPD. --}}
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <div class="min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Status Pembayaran</h3>
                <p class="text-xs text-slate-400 mt-0.5">Diproses oleh Bendahara · Tahun {{ $tahunPantau }}</p>
              </div>
              <span class="text-xs font-bold bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full shrink-0">{{ $pembayaran['total'] }}</span>
            </div>
            <div class="p-4 space-y-2">

              @if ($pembayaran['total'] === 0)
              <div class="flex flex-col items-center py-6 gap-2">
                <svg class="w-8 h-8 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs text-slate-400 text-center px-2">Belum ada perjalanan dinas yang masuk proses pembayaran</p>
              </div>
              @endif

              @if ($pembayaran['belum'] > 0)
              <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-amber-800 truncate">Belum Bayar</p>
                    <p class="text-xs text-amber-600 truncate">Menunggu proses bendahara</p>
                  </div>
                </div>
                <span class="text-sm font-black text-amber-700 bg-amber-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $pembayaran['belum'] }}</span>
              </div>
              @endif

              @if ($pembayaran['sebagian'] > 0)
              <div class="flex items-center justify-between p-3 rounded-xl bg-blue-50">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-blue-800 truncate">Uang Muka</p>
                    <p class="text-xs text-blue-600 truncate">Sisa cair setelah berkas lengkap</p>
                  </div>
                </div>
                <span class="text-sm font-black text-blue-700 bg-blue-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $pembayaran['sebagian'] }}</span>
              </div>
              @endif

              @if ($pembayaran['lunas'] > 0)
              <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-emerald-800 truncate">Lunas 100%</p>
                    <p class="text-xs text-emerald-600 truncate">Pembayaran selesai</p>
                  </div>
                </div>
                <span class="text-sm font-black text-emerald-700 bg-emerald-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $pembayaran['lunas'] }}</span>
              </div>
              @endif

            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100">
              <h3 class="font-bold text-slate-800 text-sm">Aksi Cepat</h3>
            </div>
            <div class="p-4 space-y-2">

              {{-- SPD dari aplikasi membantu mengisi usulan, tetapi tidak
                   lagi menjadi syarat: usulan boleh diajukan langsung. --}}
              <a href="{{ route('spd.create') }}" class="flex items-center gap-3 p-3 rounded-xl bg-teal-50 hover:bg-teal-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-teal-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-teal-800 truncate">Buat SPD</p>
                  <p class="text-xs text-teal-600 truncate">Surat Perjalanan Dinas</p>
                </div>
              </a>

              <a href="{{ route('usulan.create') }}" class="flex items-center gap-3 p-3 rounded-xl bg-cyan-50 hover:bg-cyan-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-cyan-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-cyan-800 truncate">Buat Usulan Perjadin</p>
                  <p class="text-xs text-cyan-600 truncate">Ajukan perjalanan dinas</p>
                </div>
              </a>

              <a href="{{ route('usulan.list') }}" class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 hover:bg-blue-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-blue-800 truncate">Daftar Usulan Saya</p>
                  <p class="text-xs text-blue-600 truncate">Lihat semua usulan</p>
                </div>
              </a>

              <a href="{{ route('dokumen') }}" class="flex items-center gap-3 p-3 rounded-xl bg-green-50 hover:bg-green-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-green-800 truncate">Dokumen Perdin</p>
                  <p class="text-xs text-green-600 truncate">Kelola dokumen perjalanan</p>
                </div>
              </a>

              @if (auth()->user()->role !== 'pegawai')
              <a href="{{ route('laporan') }}" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-slate-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-slate-700 truncate">Laporan & Rekap</p>
                  <p class="text-xs text-slate-500 truncate">Lihat pusat laporan</p>
                </div>
              </a>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- Profil Singkat -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-4 md:px-6 py-4 border-b border-slate-100">
          <h3 class="font-bold text-slate-800 text-sm md:text-base flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-purple-400 inline-block shrink-0"></span>
            Profil Saya
          </h3>
        </div>
        <div class="px-4 md:px-6 py-5">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <x-avatar :nama="$user->nama" :foto="$user->url_foto" ukuran="lg" />
            <div class="flex-1 min-w-0">
              <p class="text-base font-bold text-slate-800">{{ $user->nama }}</p>
              <p class="text-sm text-slate-500">{{ $user->email }}</p>
              <div class="flex flex-wrap items-center gap-3 mt-2">
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                  NIP: {{ $user->nip }}
                </span>
                @php
                    // Label peran diambil dari enum agar kesembilan peran tampil benar.
                    $warnaPeran = match($user->peran) {
                        \App\Enums\PeranPengguna::SuperAdministrator => 'bg-red-50 text-red-700 border-red-100',
                        \App\Enums\PeranPengguna::Pimpinan => 'bg-violet-50 text-violet-700 border-violet-100',
                        \App\Enums\PeranPengguna::Ppk => 'bg-blue-50 text-blue-700 border-blue-100',
                        \App\Enums\PeranPengguna::Bendahara,
                        \App\Enums\PeranPengguna::TimKeuangan => 'bg-amber-50 text-amber-700 border-amber-100',
                        \App\Enums\PeranPengguna::TimSdm => 'bg-sky-50 text-sky-700 border-sky-100',
                        default => 'bg-teal-50 text-teal-700 border-teal-100',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $warnaPeran }}">
                  {{ $user->peran->label() }}
                </span>
                <span class="text-xs text-slate-400">Bergabung {{ $user->created_at?->format('d M Y') ?? '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
@endsection
