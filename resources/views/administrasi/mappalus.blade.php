@extends('app')

@section('title', 'Integrasi Mappalus — Administrasi Sistem')

@section('content')

@use('App\Services\DataMappalus')

@php
    $badge = [
        DataMappalus::BELUM_DIAMBIL => 'bg-blue-100 text-blue-700',
        DataMappalus::SUDAH_DIAMBIL => 'bg-amber-100 text-amber-700',
        DataMappalus::DIKONFIRMASI => 'bg-emerald-100 text-emerald-700',
        DataMappalus::BERUBAH => 'bg-orange-100 text-orange-700',
        DataMappalus::DITARIK => 'bg-violet-100 text-violet-700',
    ];

    $keterangan = [
        DataMappalus::SUDAH_DIAMBIL => 'Sudah diambil, menunggu konfirmasi Mappalus. Terus disajikan sampai dikonfirmasi.',
        DataMappalus::BERUBAH => 'Isinya berubah sesudah diambil. Disajikan lagi agar Mappalus memperbaruinya.',
        DataMappalus::DITARIK => 'Pernah diambil, tetapi kini tidak tersedia lagi: tanda tangannya dicabut atau tim kerja dan akunnya dikosongkan.',
    ];

    $tab = ['' => ['label' => 'Semua', 'jumlah' => $total]];

    foreach (DataMappalus::LABEL as $nilai => $label) {
        $tab[$nilai] = ['label' => $label, 'jumlah' => $jumlah[$nilai] ?? 0, 'badge' => $badge[$nilai]];
    }

    $judulHalaman = 'Integrasi Mappalus';
    $subjudulHalaman = 'Mappalus mengambil data daftar nominatif lewat API PANGI memakai token dari halaman ini';
    $alamatApi = url('/api/v1/mappalus/nominatif');
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    @include('administrasi.partials.kepala')

    <x-flash />

    @if ($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
            <ul class="text-sm text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Token --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-token-mappalus>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Token untuk Mappalus</h3>
                <p class="text-xs text-slate-400">Hanya pembawa token ini yang dapat mengambil data. Serahkan kepada pengembang Mappalus lewat jalur yang aman.</p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $token['terbuka'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $token['terbuka'] ? 'API terbuka' : 'API tertutup' }}
            </span>
        </div>

        <div class="p-6 space-y-4">
            @if ($tokenBaru)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4" data-token-baru
                     x-data="{ tersalin: false, salin() { navigator.clipboard.writeText($refs.token.textContent.trim()).then(() => { this.tersalin = true; setTimeout(() => this.tersalin = false, 2000) }) } }">
                    <p class="text-xs font-bold text-amber-800 mb-2">Salin sekarang. Token ini tidak akan ditampilkan lagi.</p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <code x-ref="token" class="flex-1 min-w-0 px-3 py-2.5 bg-white border border-amber-200 rounded-xl text-xs font-mono text-slate-700 break-all select-all">{{ $tokenBaru }}</code>
                        <button type="button" @click="salin()" x-text="tersalin ? 'Tersalin ✓' : 'Salin'"
                                class="shrink-0 px-4 py-2.5 bg-white border border-amber-200 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-xl transition">Salin</button>
                    </div>
                </div>
            @elseif ($token['terbuka'])
                <p class="text-xs text-slate-500">
                    Token berakhiran <span class="font-mono font-semibold text-slate-700">…{{ $token['ujung'] }}</span>,
                    dibuat {{ \Illuminate\Support\Carbon::parse($token['dibuat'])->translatedFormat('d F Y, H:i') }}.
                    Yang tersimpan hanya sidiknya; bila hilang, buat token baru.
                </p>
            @else
                <p class="text-xs text-slate-500">Belum ada token, jadi seluruh permintaan ke API ditolak.</p>
            @endif

            <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                @if ($token['terbuka'])
                    <button type="button" @click="$dispatch('buka-cabut-token-mappalus')"
                            class="px-4 py-2.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-sm font-semibold rounded-xl transition">
                        Cabut Token
                    </button>
                @endif
                <button type="button" @click="$dispatch('buka-buat-token-mappalus')"
                        class="px-5 py-2.5 {{ $token['terbuka'] ? 'bg-amber-500 hover:bg-amber-600' : 'bg-teal-500 hover:bg-teal-600' }} text-white text-sm font-semibold rounded-xl transition">
                    {{ $token['terbuka'] ? 'Ganti Token' : 'Buat Token' }}
                </button>
            </div>
        </div>
    </div>

    <x-modal-konfirmasi nama="buat-token-mappalus" :judul="$token['terbuka'] ? 'Ganti token Mappalus?' : 'Buat token Mappalus?'"
                        :aksi="route('administrasi.mappalus.token.buat')" metode="POST" warna="amber"
                        :tombol="$token['terbuka'] ? 'Ya, Ganti' : 'Buat Token'">
        <p>Token ditampilkan sekali. @if ($token['terbuka']) Token lama seketika tidak berlaku, jadi Mappalus harus memakai yang baru. @endif</p>
    </x-modal-konfirmasi>

    @if ($token['terbuka'])
        <x-modal-konfirmasi nama="cabut-token-mappalus" judul="Cabut token Mappalus?"
                            :aksi="route('administrasi.mappalus.token.cabut')" metode="DELETE" warna="red" ikon="peringatan" tombol="Ya, Cabut">
            <p>Mappalus tidak dapat mengambil data sampai token baru dibuat.</p>
        </x-modal-konfirmasi>
    @endif

    <div class="grid gap-5 lg:grid-cols-2 mb-5">
        {{-- Batas alamat --}}
        <form method="POST" action="{{ route('administrasi.mappalus.alamat') }}"
              class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col" data-alamat-mappalus>
            @csrf
            @method('PUT')

            <h3 class="font-bold text-slate-800 text-sm">Batas Alamat IP</h3>
            <p class="text-xs text-slate-400 mb-3">
                Opsional, tetapi dianjurkan: bila diisi, token hanya berlaku dari alamat ini. Satu alamat IP atau CIDR per baris.
                Mintalah alamat keluar server Mappalus kepada pengembangnya.
            </p>

            <label for="alamat_ip" class="sr-only">Alamat IP yang diizinkan</label>
            <textarea name="alamat_ip" id="alamat_ip" rows="3" spellcheck="false" placeholder="Kosong = alamat tidak dibatasi"
                      class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('alamat_ip', implode("\n", $alamat)) }}</textarea>

            <div class="flex justify-end mt-3">
                <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                    Simpan Batas Alamat
                </button>
            </div>
        </form>

        {{-- Cara pakai --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6" data-cara-mappalus>
            <h3 class="font-bold text-slate-800 text-sm mb-3">Cara Mengambil Data</h3>
            <ol class="text-xs text-slate-600 space-y-2 list-decimal list-inside leading-relaxed">
                <li>
                    Ambil data, dengan header <span class="font-mono">Authorization: Bearer &lt;token&gt;</span>:
                    <code class="block mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg font-mono break-all">GET {{ $alamatApi }}</code>
                </li>
                <li>Simpan tiap baris menurut <span class="font-mono">referensi</span>: yang sudah ada diperbarui, bukan ditambah.</li>
                <li>
                    Konfirmasi yang tersimpan, memakai <span class="font-mono">referensi</span> dan <span class="font-mono">versi</span> tiap baris:
                    <code class="block mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg font-mono break-all">POST {{ $alamatApi }}/konfirmasi</code>
                </li>
                <li>Ulangi sampai <span class="font-mono">data</span> kosong. Referensi pada <span class="font-mono">ditarik</span> sudah tidak berlaku.</li>
            </ol>
            <p class="text-[11px] text-slate-400 mt-3">Hanya HTTPS dan token pada header. Batas 30 permintaan per menit. Panggil dari server, bukan dari peramban.</p>
        </div>
    </div>

    {{-- Data --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="min-w-0">
            <h3 class="font-bold text-slate-800 text-sm">Data untuk Mappalus</h3>
            <p class="text-xs text-slate-400">Satu baris per pelaksana pada nominatif bertanda tangan PPK yang tim kerja dan kode akunnya sudah diisi.</p>
        </div>

        <x-tombol-ekspor :tautan="route('administrasi.mappalus.ekspor', request()->query())" />
    </div>

    @if ($belumLengkap > 0)
        <p class="mb-4 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-xl px-4 py-3" data-belum-lengkap>
            {{ $belumLengkap }} pelaksana belum ikut karena tim kerja atau kode akunnya belum diisi.
            <a href="{{ route('pembebanan-anggaran') }}" class="font-semibold underline underline-offset-2">Lengkapi di Pembebanan Anggaran</a>
        </p>
    @endif

    <x-tab-status :aksi="route('administrasi.mappalus')" kunci="status" :terpilih="$status" :tab="$tab" />

    <x-kotak-cari :rute="route('administrasi.mappalus')" :nilai="$cari" petunjuk="Cari nama pelaksana, surat tugas, atau nomor usulan…"
                  :sembunyi="['status' => $status]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-4 py-3 font-semibold">Keterangan</th>
                        <th class="px-4 py-3 font-semibold">Tim Kerja</th>
                        <th class="px-4 py-3 font-semibold">Kode Akun</th>
                        <th class="px-4 py-3 font-semibold text-right">Total</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Pengambilan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($baris as $item)
                        @php $catatan = $item['catatan']; @endphp
                        <tr data-baris-mappalus="{{ $item['status'] }}">
                            <td class="px-4 py-3 align-top">
                                <p class="text-slate-800 font-medium">{{ $item['data']['keterangan'] }}</p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $item['data']['referensi'] }}</p>
                            </td>
                            <td class="px-4 py-3 align-top text-slate-600">{{ $item['data']['tim_kerja'] }}</td>
                            <td class="px-4 py-3 align-top text-slate-600 font-mono text-xs">{{ $item['data']['kode_akun'] }}</td>
                            <td class="px-4 py-3 align-top text-right tabular-nums text-slate-700 whitespace-nowrap">
                                Rp {{ number_format($item['data']['total_nominal'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 align-top min-w-48">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $badge[$item['status']] }}">
                                    {{ DataMappalus::LABEL[$item['status']] }}
                                </span>
                                @isset ($keterangan[$item['status']])
                                    <p class="text-xs text-slate-500 mt-1 leading-snug">{{ $keterangan[$item['status']] }}</p>
                                @endisset
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-slate-500 whitespace-nowrap">
                                @if ($catatan)
                                    <p>{{ $catatan->jumlah_diambil }}× diambil</p>
                                    <p class="text-slate-400">Terakhir {{ $catatan->diambil_at?->translatedFormat('d M Y, H:i') }}</p>
                                    @if ($catatan->dikonfirmasi_at)
                                        <p class="text-slate-400">Dikonfirmasi {{ $catatan->dikonfirmasi_at->translatedFormat('d M Y, H:i') }}</p>
                                    @endif
                                @else
                                    <span class="text-slate-400">Belum pernah</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                Belum ada data. Pelaksana tampil setelah nominatifnya ditandatangani PPK dan tim kerja serta kode akunnya diisi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$baris" />
    </div>

    {{-- Riwayat permintaan --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" data-riwayat-mappalus>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Riwayat Permintaan API</h3>
                <p class="text-xs text-slate-400">Setiap panggilan ke API Mappalus, termasuk yang ditolak</p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $ditolakTujuhHari > 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $ditolakTujuhHari }} ditolak · 7 hari
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-4 py-3 font-semibold">Waktu</th>
                        <th class="px-4 py-3 font-semibold">Alamat IP</th>
                        <th class="px-4 py-3 font-semibold">Permintaan</th>
                        <th class="px-4 py-3 font-semibold">Token</th>
                        <th class="px-4 py-3 font-semibold">Hasil</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($riwayat as $log)
                        <tr>
                            <td class="px-4 py-2.5 text-xs text-slate-600 whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y, H:i:s') }}</td>
                            <td class="px-4 py-2.5 text-xs font-mono text-slate-600">{{ $log->ip }}</td>
                            <td class="px-4 py-2.5 text-xs font-mono text-slate-600">{{ $log->metode }} {{ $log->jalur }}</td>
                            <td class="px-4 py-2.5 text-xs font-mono text-slate-400">{{ $log->token_tersamar ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $log->badgeHasil() }}">{{ $log->labelHasil() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">Belum ada permintaan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
