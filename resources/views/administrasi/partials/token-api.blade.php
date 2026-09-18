{{-- Kartu Token API dashboard eksekutif beserta kotak konfirmasinya; dipakai
     halaman Integrasi Data. Butuh $tokenApi dari Pengaturan::tokenApi(). --}}
@php
    $adaToken = $tokenApi['token'] !== '';
    $dariEnv = $tokenApi['sumber'] === 'env';
    $tokenTersamar = $adaToken ? str_repeat('•', 24).substr($tokenApi['token'], -4) : '';
    $asalApi = url('/api/v1/dashboard-eksekutif');
@endphp
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5"
     x-data="{ tampil: false, tersalin: false, token: @js($tokenApi['token']), tersamar: @js($tokenTersamar),
               salin() { navigator.clipboard.writeText(this.token).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000) }) } }">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg {{ $adaToken ? 'bg-sky-50' : 'bg-slate-100' }} flex items-center justify-center">
            <svg class="w-4 h-4 {{ $adaToken ? 'text-sky-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="8" cy="15" r="4"/><path d="M10.85 12.15L19 4M18 5l2 2M15 8l2 2"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-bold text-slate-800 text-sm">Token API Dashboard Eksekutif</h3>
            <p class="text-xs text-slate-400">
                Kunci yang dibawa aplikasi dashboard untuk membaca ringkasan, realisasi, dan sebaran pegawai PANGI
            </p>
        </div>
        <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $adaToken ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
            @if (! $adaToken)
                API tertutup — token belum dipasang
            @elseif ($dariEnv)
                Aktif · dari berkas .env
            @else
                Aktif
            @endif
        </span>
    </div>

    <div class="p-6 space-y-5">
        <p class="text-xs text-slate-500 leading-relaxed">
            API ini <strong>baca-saja</strong> dan hanya dapat dipanggil dengan token di bawah. Bagikan tokennya kepada
            pengembang aplikasi dashboard lewat jalur yang aman — jangan lewat grup obrolan bersama. Bila token
            diduga bocor, buat yang baru: token lama seketika tidak berlaku.
        </p>

        @if ($adaToken)
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Token yang berlaku</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <code class="flex-1 min-w-0 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-700 break-all select-all"
                          x-text="tampil ? token : tersamar">{{ $tokenTersamar }}</code>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" @click="tampil = ! tampil"
                                class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition"
                                x-text="tampil ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
                        <button type="button" @click="salin()"
                                class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition"
                                x-text="tersalin ? 'Tersalin ✓' : 'Salin'">Salin</button>
                    </div>
                </div>
                @if ($dariEnv)
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Token ini berasal dari PANGI_API_TOKEN pada berkas .env server. Membuat token baru di sini akan
                        menggantikannya tanpa perlu menyunting .env.
                    </p>
                @endif
            </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Alamat &amp; header</p>
                <p class="text-xs font-mono text-slate-700 break-all">{{ $asalApi }}</p>
                <p class="text-xs font-mono text-slate-700 mt-1">Authorization: Bearer &lt;token&gt;</p>
                <p class="text-[11px] text-slate-400 mt-2">
                    Header <span class="font-mono">X-Api-Token</span> juga diterima. Batas 60 permintaan per menit.
                </p>
            </div>
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Jalur yang tersedia</p>
                <ul class="text-xs font-mono text-slate-700 space-y-0.5">
                    <li>/ <span class="font-sans text-slate-400">— semua bagian sekaligus</span></li>
                    <li>/ringkasan</li>
                    <li>/realisasi</li>
                    <li>/pegawai</li>
                    <li>/tahun-anggaran</li>
                </ul>
                <p class="text-[11px] text-slate-400 mt-2">
                    Asal peramban yang diizinkan (CORS):
                    @if (config('api.origins') === [])
                        <span class="text-slate-500">belum ada — hanya pemanggilan dari sisi server</span>
                    @else
                        <span class="font-mono text-slate-500">{{ implode(', ', config('api.origins')) }}</span>
                    @endif
                    · diatur lewat PANGI_API_ORIGINS di .env.
                </p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-5 border-t border-slate-100">
            @if ($tokenApi['sumber'] === 'pengaturan')
                <button type="button" @click="$dispatch('buka-cabut-token-api')"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-sm font-semibold rounded-xl transition">
                    Cabut Token
                </button>
            @endif
            <button type="button" @click="$dispatch('buka-buat-token-api')"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 {{ $adaToken ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-200' : 'bg-teal-500 hover:bg-teal-600 shadow-teal-200' }} text-white text-sm font-semibold rounded-xl transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h5M20 20v-5h-5"/><path d="M20 9A8 8 0 006.3 6.3L4 9M4 15a8 8 0 0013.7 2.7L20 15"/></svg>
                {{ $adaToken ? 'Ganti Token' : 'Buat Token' }}
            </button>
        </div>
    </div>
</div>

<x-modal-konfirmasi
    nama="buat-token-api"
    :judul="$adaToken ? 'Ganti token API?' : 'Buat token API?'"
    :aksi="route('administrasi.token-api.buat')"
    metode="POST"
    :tombol="$adaToken ? 'Ya, Ganti' : 'Ya, Buat'"
    :warna="$adaToken ? 'amber' : 'teal'"
    ikon="peringatan">
    @if ($adaToken)
        <p>
            Token baru akan dibuat dan <strong class="text-slate-700">token yang sekarang seketika tidak berlaku</strong>.
            Aplikasi dashboard berhenti membaca data sampai tokennya diperbarui.
        </p>
    @else
        <p>Token acak 64 karakter akan dibuat dan API dashboard eksekutif langsung dapat dipanggil dengannya.</p>
    @endif
    <p class="text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
        Kirimkan token yang baru kepada pengembang aplikasi dashboard lewat jalur yang aman. Perubahan ini tercatat
        pada jejak audit.
    </p>
</x-modal-konfirmasi>

@if ($tokenApi['sumber'] === 'pengaturan')
<x-modal-konfirmasi
    nama="cabut-token-api"
    judul="Cabut token API?"
    :aksi="route('administrasi.token-api.cabut')"
    metode="DELETE"
    tombol="Ya, Cabut"
    warna="red"
    ikon="peringatan">
    <p>
        Token yang dibuat di sini dihapus dan aplikasi dashboard tidak dapat lagi membaca data PANGI dengannya.
    </p>
    <p class="text-xs bg-red-50 text-red-700 border border-red-100 rounded-lg px-3 py-2">
        @if ((string) config('api.token') !== '')
            API kembali memakai token dari PANGI_API_TOKEN pada berkas .env server.
        @else
            API tertutup sampai token baru dibuat.
        @endif
    </p>
</x-modal-konfirmasi>
@endif
