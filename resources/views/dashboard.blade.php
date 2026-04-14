@extends('app')
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
            Pantau status usulan perjalanan dinas Anda — lihat ringkasan, buat usulan baru, dan kelola dokumen di sini.
          </p>
        </div>
      </div>

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
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            @if ($menunggu > 0)
            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Pending</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $menunggu }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Menunggu Persetujuan</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            @if ($disetujui > 0)
            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">Disetujui</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $disetujui }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Penugasan Disetujui</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
            </div>
            @if ($ditolak > 0)
            <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full">Ditolak</span>
            @endif
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">{{ $ditolak }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Usulan Ditolak</p>
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

        <!-- Tabel Usulan Terbaru -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
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
                    @if ($item->status === 'draft')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Draft</span>
                    @elseif ($item->status === 'diajukan')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Diajukan</span>
                    @elseif ($item->status === 'menunggu')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Menunggu</span>
                    @elseif ($item->status === 'disetujui')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Disetujui</span>
                    @elseif ($item->status === 'ditolak')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Ditolak</span>
                    @elseif ($item->status === 'selesai')
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Selesai</span>
                    @endif
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

        <!-- Kolom Kanan -->
        <div class="space-y-5">

          <!-- Ringkasan Status -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 class="font-bold text-slate-800 text-sm">Ringkasan Status</h3>
              <span class="text-xs font-bold bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full">{{ $totalUsulan }}</span>
            </div>
            <div class="p-4 space-y-2">

              @if ($draftCount > 0)
              <a href="{{ route('usulan.list', ['status' => 'draft']) }}" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-slate-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-700 truncate">Draft</p>
                    <p class="text-xs text-slate-400 truncate">Belum diajukan</p>
                  </div>
                </div>
                <span class="text-sm font-black text-slate-600 bg-slate-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $draftCount }}</span>
              </a>
              @endif

              @if ($menunggu > 0)
              <a href="{{ route('usulan.list', ['status' => 'diajukan']) }}" class="flex items-center justify-between p-3 rounded-xl bg-amber-50 hover:bg-amber-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-amber-800 truncate">Menunggu Persetujuan</p>
                    <p class="text-xs text-amber-600 truncate">Sedang diproses</p>
                  </div>
                </div>
                <span class="text-sm font-black text-amber-700 bg-amber-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $menunggu }}</span>
              </a>
              @endif

              @if ($disetujui > 0)
              <a href="{{ route('usulan.list', ['status' => 'disetujui']) }}" class="flex items-center justify-between p-3 rounded-xl bg-green-50 hover:bg-green-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-green-800 truncate">Disetujui</p>
                    <p class="text-xs text-green-600 truncate">Siap berangkat</p>
                  </div>
                </div>
                <span class="text-sm font-black text-green-700 bg-green-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">{{ $disetujui }}</span>
              </a>
              @endif

              @if ($totalUsulan === 0)
              <div class="flex flex-col items-center py-6 gap-2">
                <svg class="w-8 h-8 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
                <p class="text-xs text-slate-400">Belum ada usulan</p>
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

              <a href="{{ route('usulan.create') }}" class="flex items-center gap-3 p-3 rounded-xl bg-teal-50 hover:bg-teal-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-teal-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-teal-800 truncate">Buat Usulan Baru</p>
                  <p class="text-xs text-teal-600 truncate">Ajukan perjalanan dinas</p>
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
            <img src="https://ui-avatars.com/api/?name={{ urlencode($user->nama) }}&background=14b8a6&color=fff&size=80"
                 class="w-14 h-14 rounded-full shrink-0" alt="avatar">
            <div class="flex-1 min-w-0">
              <p class="text-base font-bold text-slate-800">{{ $user->nama }}</p>
              <p class="text-sm text-slate-500">{{ $user->email }}</p>
              <div class="flex flex-wrap items-center gap-3 mt-2">
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                  NIP: {{ $user->nip }}
                </span>
                @php
                    $roleBadge = match($user->role) {
                        'administrator' => ['bg' => 'bg-red-50 text-red-700 border-red-100', 'label' => 'Administrator'],
                        'ppk' => ['bg' => 'bg-blue-50 text-blue-700 border-blue-100', 'label' => 'PPK'],
                        default => ['bg' => 'bg-teal-50 text-teal-700 border-teal-100', 'label' => 'Pegawai'],
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $roleBadge['bg'] }}">
                  {{ $roleBadge['label'] }}
                </span>
                <span class="text-xs text-slate-400">Bergabung {{ $user->created_at?->format('d M Y') ?? '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
@endsection
