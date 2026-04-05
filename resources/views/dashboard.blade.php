@extends('app')
@section('content')
  <!-- Body -->
    <main class="flex-1 px-4 md:px-8 py-7 space-y-6 overflow-y-auto">

      <!-- Welcome Banner -->
      <div class="relative overflow-hidden rounded-2xl px-6 md:px-8 py-6 text-white"
         style="background:linear-gradient(120deg,#0d9488,#14b8a6,#06b6d4)">
        <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full" style="background:rgba(255,255,255,.08)"></div>
        <div class="absolute -bottom-14 -left-6 w-40 h-40 rounded-full" style="background:rgba(255,255,255,.06)"></div>
        <div class="relative">
          <p class="text-teal-100 text-sm font-medium mb-0.5">Selamat Datang 👋</p>
          <h2 class="text-xl md:text-2xl font-bold mb-1">Admin PANGI</h2>
          <p class="text-teal-100 text-sm max-w-xl">
            Pantau dan kelola administrasi perjalanan dinas Poltekkes Kemenkes Manado — backlog, status persetujuan, dan LPJ tersedia di sini.
          </p>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            </div>
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">24</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Usulan</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Pending</span>
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">5</p>
          <p class="text-xs text-slate-500 mt-0.5">Menunggu Persetujuan</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">Disetujui</span>
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">17</p>
          <p class="text-xs text-slate-500 mt-0.5">Penugasan Disetujui</p>
        </div>

        <div class="bg-white rounded-xl md:rounded-2xl p-4 md:p-5 border border-slate-100 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
          <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full">Ditolak</span>
          </div>
          <p class="text-2xl md:text-3xl font-bold text-slate-800">3</p>
          <p class="text-xs text-slate-500 mt-0.5">LPJ Belum Diverifikasi</p>
        </div>
      </div>

      <!-- Tabel + Kanan -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Tabel Usulan Terbaru -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm md:text-base">
              <span class="w-2 h-2 rounded-full bg-teal-400 inline-block shrink-0"></span>
              Usulan Terbaru
            </h3>
            <a href="#" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Lihat Semua →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs md:text-sm">
              <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  <th class="px-3 md:px-5 py-3 text-left">Pengusul</th>
                  <th class="px-3 md:px-5 py-3 text-left">Tujuan</th>
                  <th class="px-3 md:px-5 py-3 text-left">Tanggal</th>
                  <th class="px-3 md:px-5 py-3 text-left">Status</th>
                  <th class="px-3 md:px-5 py-3 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">

                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <img src="https://ui-avatars.com/api/?name=Budi+Santoso&background=14b8a6&color=fff&size=64" class="w-7 h-7 rounded-full shrink-0"/>
                      <div class="min-w-0 hidden sm:block">
                        <p class="font-semibold text-slate-800 text-xs truncate">Budi Santoso</p>
                        <p class="text-xs text-slate-400 truncate">Bag. Kepegawaian</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">Jakarta</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">05–07 Apr</td>
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Menunggu</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="#" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>

                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <img src="https://ui-avatars.com/api/?name=Rina+Mokoagow&background=3b82f6&color=fff&size=64" class="w-7 h-7 rounded-full shrink-0"/>
                      <div class="min-w-0 hidden sm:block">
                        <p class="font-semibold text-slate-800 text-xs truncate">Rina Mokoagow</p>
                        <p class="text-xs text-slate-400 truncate">Prodi Keperawatan</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">Surabaya</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">10–12 Apr</td>
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Pemeriksaan</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="#" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>

                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <img src="https://ui-avatars.com/api/?name=Yusuf+Tamboto&background=8b5cf6&color=fff&size=64" class="w-7 h-7 rounded-full shrink-0"/>
                      <div class="min-w-0 hidden sm:block">
                        <p class="font-semibold text-slate-800 text-xs truncate">Yusuf Tamboto</p>
                        <p class="text-xs text-slate-400 truncate">Prodi Gizi</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">Bandung</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">08–09 Apr</td>
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Disetujui</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="#" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>

                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <img src="https://ui-avatars.com/api/?name=Sari+Lumentut&background=f97316&color=fff&size=64" class="w-7 h-7 rounded-full shrink-0"/>
                      <div class="min-w-0 hidden sm:block">
                        <p class="font-semibold text-slate-800 text-xs truncate">Sari Lumentut</p>
                        <p class="text-xs text-slate-400 truncate">Bag. Keuangan</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">Makassar</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">01–03 Apr</td>
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Perlu Revisi</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="#" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>

                <tr class="hover:bg-teal-50/30 transition-colors">
                  <td class="px-3 md:px-5 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <img src="https://ui-avatars.com/api/?name=Anton+Kalesaran&background=0ea5e9&color=fff&size=64" class="w-7 h-7 rounded-full shrink-0"/>
                      <div class="min-w-0 hidden sm:block">
                        <p class="font-semibold text-slate-800 text-xs truncate">Anton Kalesaran</p>
                        <p class="text-xs text-slate-400 truncate">Prodi Kebidanan</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-600 text-xs">Yogyakarta</td>
                  <td class="px-3 md:px-5 py-3.5 text-slate-400 text-xs">15–17 Apr</td>
                  <td class="px-3 md:px-5 py-3.5">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Menunggu</span>
                  </td>
                  <td class="px-3 md:px-5 py-3.5">
                    <a href="#" class="text-xs text-teal-600 font-semibold hover:underline">Detail →</a>
                  </td>
                </tr>

              </tbody>
            </table>
          </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="space-y-5">

          <!-- Antrian Persetujuan -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 class="font-bold text-slate-800 text-sm">Antrian Persetujuan</h3>
              <span class="text-xs font-bold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">5</span>
            </div>
            <div class="p-4 space-y-2">

              <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-amber-50 hover:bg-amber-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-amber-800 truncate">Persetujuan Atasan</p>
                    <p class="text-xs text-amber-600 truncate">Menunggu keputusan</p>
                  </div>
                </div>
                <span class="text-sm font-black text-amber-700 bg-amber-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">2</span>
              </a>

              <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-blue-50 hover:bg-blue-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-blue-800 truncate">Pemeriksaan PPK</p>
                    <p class="text-xs text-blue-600 truncate">Validasi anggaran</p>
                  </div>
                </div>
                <span class="text-sm font-black text-blue-700 bg-blue-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">2</span>
              </a>

              <a href="#" class="flex items-center justify-between p-3 rounded-xl bg-purple-50 hover:bg-purple-100 transition">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-purple-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-purple-800 truncate">Persetujuan Direktur</p>
                    <p class="text-xs text-purple-600 truncate">Pengesahan final</p>
                  </div>
                </div>
                <span class="text-sm font-black text-purple-700 bg-purple-200 w-7 h-7 rounded-full flex items-center justify-center shrink-0 ml-2">1</span>
              </a>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 md:px-5 py-4 border-b border-slate-100">
              <h3 class="font-bold text-slate-800 text-sm">Quick Actions</h3>
            </div>
            <div class="p-4 space-y-2">

              <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-teal-50 hover:bg-teal-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-teal-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-teal-800 truncate">Buat Usulan Baru</p>
                  <p class="text-xs text-teal-600 truncate">Ajukan perjalanan dinas</p>
                </div>
              </a>

              <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-blue-50 hover:bg-blue-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-blue-800 truncate">Unduh Surat Tugas</p>
                  <p class="text-xs text-blue-600 truncate">Cetak atau ekspor dokumen</p>
                </div>
              </a>

              <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-green-50 hover:bg-green-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-green-800 truncate">Verifikasi LPJ</p>
                  <p class="text-xs text-green-600 truncate">Periksa pertanggungjawaban</p>
                </div>
              </a>

              <a href="#" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition group">
                <div class="w-8 h-8 rounded-lg bg-slate-500 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-slate-700 truncate">Laporan & Rekap</p>
                  <p class="text-xs text-slate-500 truncate">Daftar nominatif perdin</p>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Aktivitas Terbaru (Audit Trail) -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-bold text-slate-800 text-sm md:text-base flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-purple-400 inline-block shrink-0"></span>
            Aktivitas Terbaru
          </h3>
          <a href="#" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Lihat Semua →</a>
        </div>
        <div class="divide-y divide-slate-100">

          <div class="flex items-start gap-4 px-4 md:px-6 py-4 hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800">Usulan SPPD disetujui Direktur</p>
              <p class="text-xs text-slate-400 mt-0.5">Pengusul: <span class="font-medium text-slate-600">Yusuf Tamboto</span> · Tujuan: Bandung</p>
            </div>
            <span class="text-xs text-slate-400 shrink-0 ml-2">10 menit lalu</span>
          </div>

          <div class="flex items-start gap-4 px-4 md:px-6 py-4 hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800">PPK meneruskan usulan ke Direktur</p>
              <p class="text-xs text-slate-400 mt-0.5">Pengusul: <span class="font-medium text-slate-600">Anton Kalesaran</span> · Tujuan: Yogyakarta</p>
            </div>
            <span class="text-xs text-slate-400 shrink-0 ml-2">1 jam lalu</span>
          </div>

          <div class="flex items-start gap-4 px-4 md:px-6 py-4 hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800">SDM meminta revisi dokumen administrasi</p>
              <p class="text-xs text-slate-400 mt-0.5">Pengusul: <span class="font-medium text-slate-600">Sari Lumentut</span> · Tujuan: Makassar</p>
            </div>
            <span class="text-xs text-slate-400 shrink-0 ml-2">2 jam lalu</span>
          </div>

          <div class="flex items-start gap-4 px-4 md:px-6 py-4 hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800">Usulan baru dikirim ke atasan</p>
              <p class="text-xs text-slate-400 mt-0.5">Pengusul: <span class="font-medium text-slate-600">Budi Santoso</span> · Tujuan: Jakarta</p>
            </div>
            <span class="text-xs text-slate-400 shrink-0 ml-2">3 jam lalu</span>
          </div>

          <div class="flex items-start gap-4 px-4 md:px-6 py-4 hover:bg-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800">LPJ diverifikasi — pembayaran sisa diproses</p>
              <p class="text-xs text-slate-400 mt-0.5">Oleh: <span class="font-medium text-slate-600">Bendahara</span> · Perdin: Surabaya (Mar 2026)</p>
            </div>
            <span class="text-xs text-slate-400 shrink-0 ml-2">Kemarin</span>
          </div>

        </div>
      </div>

    </main>
@endsection