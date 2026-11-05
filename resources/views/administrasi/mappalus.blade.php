@extends('app')

@section('title', 'Integrasi Mappalus — Administrasi Sistem')

@section('content')

@use('App\Services\SinkronMappalus')

@php
    $badge = [
        SinkronMappalus::SIAP => 'bg-blue-100 text-blue-700',
        SinkronMappalus::BERHASIL => 'bg-emerald-100 text-emerald-700',
        SinkronMappalus::GAGAL => 'bg-red-100 text-red-700',
        SinkronMappalus::BERUBAH => 'bg-amber-100 text-amber-700',
        SinkronMappalus::BELUM_LENGKAP => 'bg-slate-100 text-slate-600',
        SinkronMappalus::DITARIK => 'bg-violet-100 text-violet-700',
    ];

    $keterangan = [
        SinkronMappalus::BELUM_LENGKAP => 'Tetapkan akun dan tim kerjanya pada menu Pembebanan Anggaran.',
        SinkronMappalus::BERUBAH => 'Isinya berubah sesudah diterima Mappalus. Kirim ulang bila memang perlu diperbarui.',
        SinkronMappalus::DITARIK => 'Sudah diterima Mappalus, tetapi tanda tangan nominatifnya dicabut. Sampaikan kepada pengelola Mappalus.',
    ];

    $tab = ['' => ['label' => 'Semua', 'jumlah' => $total]];

    foreach (SinkronMappalus::LABEL as $nilai => $label) {
        $tab[$nilai] = ['label' => $label, 'jumlah' => $jumlah[$nilai] ?? 0, 'badge' => $badge[$nilai]];
    }

    $judulHalaman = 'Integrasi Mappalus';
    $subjudulHalaman = 'Daftar nominatif bertanda tangan PPK dikirim ke Mappalus, satu kiriman per pelaksana';
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

    {{-- Pengaturan --}}
    <form method="POST" action="{{ route('administrasi.mappalus.pengaturan') }}" x-data="{ tampilToken: false }"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-pengaturan-mappalus>
        @csrf
        @method('PUT')

        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-sm">Sambungan ke Mappalus</h3>
            <p class="text-xs text-slate-400">Alamat API penerima dan token diberikan pengembang Mappalus</p>
        </div>

        <div class="p-6 grid gap-4 md:grid-cols-2">
            <div>
                <label for="mappalus_url" class="block text-xs font-semibold text-slate-600 mb-1.5">Alamat API penerima</label>
                <input id="mappalus_url" name="mappalus_url" type="url" value="{{ old('mappalus_url', $pengaturan['url']) }}"
                       class="w-full px-3.5 py-2.5 border {{ $errors->has('mappalus_url') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <p class="text-[11px] text-slate-400 mt-1.5">Data dikirim dengan POST berformat JSON ke alamat ini.</p>
            </div>

            <div>
                <label for="mappalus_token" class="block text-xs font-semibold text-slate-600 mb-1.5">Token</label>
                <div class="flex gap-2">
                    <input id="mappalus_token" name="mappalus_token" :type="tampilToken ? 'text' : 'password'"
                           autocomplete="off" spellcheck="false"
                           placeholder="{{ $pengaturan['token_tersamar'] ? 'Tersimpan: '.$pengaturan['token_tersamar'].' — isi untuk mengganti' : 'Token dari pengembang Mappalus' }}"
                           class="flex-1 min-w-0 px-3.5 py-2.5 border {{ $errors->has('mappalus_token') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <button type="button" @click="tampilToken = ! tampilToken"
                            class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition shrink-0"
                            x-text="tampilToken ? 'Sembunyikan' : 'Lihat'">Lihat</button>
                </div>
                @if ($pengaturan['token_tersamar'])
                    <label class="inline-flex items-center gap-1.5 mt-1.5 text-[11px] text-red-600 cursor-pointer">
                        <input type="checkbox" name="hapus_mappalus_token" value="1" class="w-3.5 h-3.5 rounded border-slate-300 text-red-600 focus:ring-red-400">
                        Hapus token
                    </label>
                @else
                    <p class="text-[11px] text-slate-400 mt-1.5">Disimpan terenkripsi.</p>
                @endif
            </div>

            <label class="md:col-span-2 flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="mappalus_aktif" value="1" @checked(old('mappalus_aktif', $pengaturan['aktif']))
                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                <span class="text-xs text-slate-600 leading-relaxed">
                    <strong>Kirim otomatis tiap 15 menit.</strong> Data yang gagal dicoba ulang sampai
                    {{ SinkronMappalus::MAKS_PERCOBAAN_OTOMATIS }} kali, sesudah itu menunggu Sinkron Sekarang.
                </span>
            </label>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                Simpan Pengaturan
            </button>
        </div>
    </form>

    {{-- Status kiriman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <h3 class="font-bold text-slate-800 text-sm">Status Kiriman per Pelaksana</h3>
            <p class="text-xs text-slate-400">Data yang sudah berhasil tidak dikirim lagi, sehingga tidak tercatat ganda.</p>
        </div>

        <form method="POST" action="{{ route('administrasi.mappalus.sinkron') }}"
              x-data="{ mengirim: false, kosong: {{ Js::from(! $siap || $antrean === 0) }} }" @submit="mengirim = true" class="shrink-0">
            @csrf
            <button type="submit" x-bind:disabled="mengirim || kosong" @disabled(! $siap || $antrean === 0) data-tombol-sinkron
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h5M20 20v-5h-5"/><path d="M20 9A8 8 0 006.3 6.3L4 9M4 15a8 8 0 0013.7 2.7L20 15"/></svg>
                <span x-show="! mengirim">Sinkron Sekarang ({{ $antrean }})</span>
                <span x-show="mengirim" x-cloak>Mengirim…</span>
            </button>
        </form>
    </div>

    @unless ($siap)
        <p class="mb-4 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-xl px-4 py-3">
            Sinkron belum dapat dijalankan: isi dulu alamat API dan token dari pengembang Mappalus.
        </p>
    @endunless

    <x-tab-status :aksi="route('administrasi.mappalus')" kunci="status" :terpilih="$status" :tab="$tab" />

    <x-kotak-cari :rute="route('administrasi.mappalus')" :nilai="$cari" petunjuk="Cari nama pelaksana, surat tugas, atau nomor usulan…"
                  :sembunyi="['status' => $status]" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-4 py-3 font-semibold">Keterangan</th>
                        <th class="px-4 py-3 font-semibold">Tim Kerja</th>
                        <th class="px-4 py-3 font-semibold">Kode Akun</th>
                        <th class="px-4 py-3 font-semibold text-right">Total</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($baris as $item)
                        @php
                            $catatan = $item['catatan'];
                            $pesan = $item['status'] === SinkronMappalus::GAGAL ? $catatan?->pesan : ($keterangan[$item['status']] ?? null);
                        @endphp
                        <tr data-baris-mappalus="{{ $item['status'] }}">
                            <td class="px-4 py-3 align-top">
                                <p class="text-slate-800 font-medium">{{ $item['data']['keterangan'] }}</p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $item['data']['referensi'] }}</p>
                            </td>
                            <td class="px-4 py-3 align-top text-slate-600">{{ $item['data']['tim_kerja'] ?? '—' }}</td>
                            <td class="px-4 py-3 align-top text-slate-600 font-mono text-xs">{{ $item['data']['kode_akun'] ?? '—' }}</td>
                            <td class="px-4 py-3 align-top text-right tabular-nums text-slate-700 whitespace-nowrap">
                                Rp {{ number_format($item['data']['total_nominal'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 align-top min-w-52">
                                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $badge[$item['status']] }}">
                                    {{ SinkronMappalus::LABEL[$item['status']] }}
                                </span>
                                @if ($pesan)
                                    <p class="text-xs text-slate-500 mt-1 leading-snug break-words">{{ $pesan }}</p>
                                @endif
                                @if ($catatan?->diterima_at)
                                    <p class="text-[11px] text-slate-400 mt-1">Diterima {{ $catatan->diterima_at->translatedFormat('d M Y, H:i') }}</p>
                                @elseif ($catatan?->dikirim_at)
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        Percobaan {{ $catatan->percobaan }} · {{ $catatan->dikirim_at->translatedFormat('d M Y, H:i') }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-right">
                                @if (in_array($item['status'], [SinkronMappalus::GAGAL, SinkronMappalus::BERUBAH], true) && $siap)
                                    <form method="POST" action="{{ route('administrasi.mappalus.kirim-ulang', $item['data']['referensi']) }}"
                                          x-data
                                          @submit.prevent="if (confirm('Kirim ulang data ini ke Mappalus?')) $el.submit()">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition whitespace-nowrap">
                                            Kirim Ulang
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                Belum ada data. Pelaksana tampil di sini setelah daftar nominatifnya ditandatangani PPK.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$baris" />
    </div>

</div>

@endsection
