{{-- Kepala bersama halaman Administrasi Sistem: judul, subjudul, dan
     navigasi antarbagian. Tiap bagian halaman sendiri supaya yang mencari
     satu hal tidak menggulung kartu-kartu yang tidak ia butuhkan. --}}
@php
    $bagianAdministrasi = [
        ['rute' => 'administrasi', 'aktif' => 'administrasi', 'label' => 'Pengguna'],
        ['rute' => 'administrasi.massal', 'aktif' => 'administrasi.massal', 'label' => 'Impor & Ekspor'],
        ['rute' => 'administrasi.pengaturan', 'aktif' => 'administrasi.pengaturan', 'label' => 'Pengaturan Sistem'],
    ];

    if (auth()->user()->can('mengelola-peran')) {
        $bagianAdministrasi[] = ['rute' => 'administrasi.peran', 'aktif' => 'administrasi.peran', 'label' => 'Peran & Hak Akses'];
    }

    // Integrasi Data memuat token dan alamat aplikasi lain — hanya super administrator.
    if (auth()->user()->isAdmin()) {
        $bagianAdministrasi[] = ['rute' => 'administrasi.integrasi', 'aktif' => 'administrasi.integrasi', 'label' => 'Integrasi Data'];
    }
@endphp

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">{{ $judulHalaman }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $subjudulHalaman }}</p>
        </div>
    </div>

    {{ $aksiKepala ?? '' }}
</div>

<div class="flex gap-2 mb-5 overflow-x-auto pb-1">
    @foreach ($bagianAdministrasi as $bagian)
        <a href="{{ route($bagian['rute']) }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                  {{ request()->routeIs($bagian['aktif']) ? 'bg-teal-500 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ $bagian['label'] }}
        </a>
    @endforeach
</div>
