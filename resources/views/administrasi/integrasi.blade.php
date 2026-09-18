@extends('app')

@section('title', 'Integrasi Data — Administrasi Sistem')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Integrasi Data';
        $subjudulHalaman = 'Token API, pemantauan permintaan masuk, dan pengiriman data terjadwal ke aplikasi lain';
    @endphp
    @include('administrasi.partials.kepala')

    <x-flash />

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── 1. Token API (ditarik aplikasi lain) ── --}}
    @include('administrasi.partials.token-api')

    {{-- ── 2. Pemantauan permintaan API ── --}}
    @php $terakhir = $ringkasanLog['terakhir']; @endphp
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-pemantauan-api>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 12h4l3-8 4 16 3-8h4"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Pemantauan Permintaan API</h3>
                <p class="text-xs text-slate-400">
                    Setiap panggilan yang masuk beserta token yang dibawanya — diterima, ditolak, atau saat API tertutup
                </p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $ringkasanLog['ditolak_tujuh_hari'] > 0 ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $ringkasanLog['ditolak_tujuh_hari'] }} ditolak · 7 hari
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 border-b border-slate-100">
            <div class="px-6 py-4">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Hari ini</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($ringkasanLog['hari_ini'], 0, ',', '.') }}</p>
                <p class="text-[11px] text-slate-400">permintaan</p>
            </div>
            <div class="px-6 py-4">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">7 hari</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($ringkasanLog['tujuh_hari'], 0, ',', '.') }}</p>
                <p class="text-[11px] text-slate-400">dari {{ $ringkasanLog['ip_unik'] }} alamat IP</p>
            </div>
            <div class="px-6 py-4">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Ditolak · 7 hari</p>
                <p class="text-2xl font-bold {{ $ringkasanLog['ditolak_tujuh_hari'] > 0 ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ number_format($ringkasanLog['ditolak_tujuh_hari'], 0, ',', '.') }}</p>
                <p class="text-[11px] text-slate-400">token keliru atau API tertutup</p>
            </div>
            <div class="px-6 py-4">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Permintaan terakhir</p>
                @if ($terakhir)
                    <p class="text-sm font-bold text-slate-800 mt-1">{{ $terakhir->created_at->translatedFormat('d M Y, H:i') }}</p>
                    <p class="text-[11px] text-slate-400 font-mono">{{ $terakhir->ip }} · {{ $terakhir->labelHasil() }}</p>
                @else
                    <p class="text-sm font-bold text-slate-400 mt-1">Belum ada</p>
                @endif
            </div>
        </div>

        <div class="px-6 py-3 border-b border-slate-100 flex flex-wrap items-center gap-2">
            @foreach ([null => 'Semua', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'tertutup' => 'API tertutup'] as $kunci => $label)
                <a href="{{ route('administrasi.integrasi', array_filter(['hasil' => $kunci])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $saringHasil === $kunci ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="ml-auto text-[11px] text-slate-400">{{ $log->count() }} catatan terbaru · disimpan {{ \App\Models\LogApi::SIMPAN_HARI }} hari</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left border-b border-slate-100">
                        <th class="px-6 py-2.5 font-semibold">Waktu</th>
                        <th class="px-4 py-2.5 font-semibold">Alamat IP</th>
                        <th class="px-4 py-2.5 font-semibold">Jalur</th>
                        <th class="px-4 py-2.5 font-semibold">Token dibawa</th>
                        <th class="px-4 py-2.5 font-semibold">Hasil</th>
                        <th class="px-4 py-2.5 font-semibold text-right">Lama</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($log as $baris)
                        <tr>
                            <td class="px-6 py-2.5 text-slate-700 whitespace-nowrap">{{ $baris->created_at->translatedFormat('d M Y, H:i:s') }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-700">{{ $baris->ip }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-600">
                                <span class="text-slate-400">{{ $baris->metode }}</span> {{ $baris->jalur }}
                                @if ($baris->user_agent)
                                    <span class="block text-[10px] text-slate-400 font-sans truncate max-w-[22rem]" title="{{ $baris->user_agent }}">{{ $baris->user_agent }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-700">{{ $baris->token_tersamar ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full {{ $baris->badgeHasil() }}">{{ $baris->labelHasil() }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-500">{{ $baris->durasi_ms !== null ? $baris->durasi_ms.' ms' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-400">
                                Belum ada permintaan API yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 3. Pengiriman data terjadwal ── --}}
    @php
        $kirim = $pengaturanKirim;
        $terakhirKirim = $pengiriman->first();
    @endphp
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5" data-pengiriman-terjadwal>
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $kirim['aktif'] ? 'bg-teal-50' : 'bg-slate-100' }} flex items-center justify-center">
                <svg class="w-4 h-4 {{ $kirim['aktif'] ? 'text-teal-600' : 'text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-slate-800 text-sm">Pengiriman Data Terjadwal</h3>
                <p class="text-xs text-slate-400">
                    PANGI mengirim paket dashboard eksekutif ke aplikasi tujuan memakai token, sesuai jadwal
                </p>
            </div>
            <span class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-full {{ $kirim['aktif'] ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-500' }}">
                @if ($kirim['aktif'] && $jadwalBerikutnya)
                    Aktif · berikutnya {{ $jadwalBerikutnya->translatedFormat('d M, H:i') }}
                @elseif ($kirim['aktif'])
                    Aktif · alamat belum sah
                @else
                    Nonaktif
                @endif
            </span>
        </div>

        <form method="POST" action="{{ route('administrasi.integrasi.jadwal') }}" class="p-6"
              x-data="{ aktif: {{ old('integrasi_aktif', $kirim['aktif'] ? '1' : '0') == '1' ? 'true' : 'false' }}, jadwal: @js(old('integrasi_jadwal', $kirim['jadwal'])), tampilToken: false }">
            @csrf
            @method('PUT')

            <p class="text-xs text-slate-500 leading-relaxed mb-5">
                Paket yang dikirim sama persis dengan balasan jalur <span class="font-mono">/api/v1/dashboard-eksekutif</span>
                untuk tahun anggaran berjalan, dikirim dengan <span class="font-mono">POST</span> dan header
                <span class="font-mono">Authorization: Bearer &lt;token&gt;</span> (juga <span class="font-mono">X-Api-Token</span>).
                Bila token tujuan dikosongkan, token API PANGI di atas yang dibawa — satu token bersama untuk dua arah.
            </p>

            <label class="flex items-start gap-3 mb-5 cursor-pointer">
                <input type="checkbox" name="integrasi_aktif" value="1" x-model="aktif"
                       class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                <span>
                    <span class="block text-sm font-semibold text-slate-700">Aktifkan pengiriman terjadwal</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Penjadwal memeriksa tiap menit dan mengirim saat jadwal jatuh tempo; kegagalan diulang pada jadwal berikutnya.</span>
                </span>
            </label>

            <div class="grid md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="integrasi_url" class="block text-xs font-semibold text-slate-600 mb-1.5">Alamat tujuan (URL) <span class="text-red-500" x-show="aktif">*</span></label>
                    <input id="integrasi_url" type="url" name="integrasi_url" value="{{ old('integrasi_url', $kirim['url']) }}"
                           placeholder="https://dashboard.poltekkes-manado.ac.id/api/pangi/terima"
                           class="w-full px-3.5 py-2.5 border {{ $errors->has('integrasi_url') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    @error('integrasi_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="integrasi_token_tujuan" class="block text-xs font-semibold text-slate-600 mb-1.5">Token tujuan</label>
                    <div class="flex gap-2">
                        <input id="integrasi_token_tujuan" name="integrasi_token_tujuan" :type="tampilToken ? 'text' : 'password'"
                               autocomplete="off" spellcheck="false"
                               placeholder="{{ $kirim['token_dari'] === 'sendiri' ? 'Tersimpan: '.$kirim['token_tersamar'].' — isi untuk mengganti' : 'Kosong = memakai token API PANGI' }}"
                               class="flex-1 min-w-0 px-3.5 py-2.5 border {{ $errors->has('integrasi_token_tujuan') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <button type="button" @click="tampilToken = ! tampilToken"
                                class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold rounded-xl transition shrink-0"
                                x-text="tampilToken ? 'Sembunyikan' : 'Lihat'">Lihat</button>
                    </div>
                    @error('integrasi_token_tujuan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        @if ($kirim['token_dari'] === 'sendiri')
                            Token tujuan tersimpan terenkripsi ({{ $kirim['token_tersamar'] }}).
                            <label class="inline-flex items-center gap-1.5 ml-2 cursor-pointer text-red-600">
                                <input type="checkbox" name="hapus_token_tujuan" value="1" class="w-3.5 h-3.5 rounded border-slate-300 text-red-600 focus:ring-red-400">
                                Hapus token tujuan, kembali memakai token API PANGI
                            </label>
                        @elseif ($kirim['token_dari'] === 'api')
                            Saat ini memakai token API PANGI ({{ $kirim['token_tersamar'] }}).
                        @else
                            Belum ada token yang dapat dibawa — buat token API di kartu paling atas atau isi token tujuan di sini.
                        @endif
                    </p>
                </div>

                <div>
                    <label for="integrasi_jadwal" class="block text-xs font-semibold text-slate-600 mb-1.5">Jadwal</label>
                    <select id="integrasi_jadwal" name="integrasi_jadwal" x-model="jadwal"
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        @foreach ($pilihanJadwal as $kunci => $label)
                            <option value="{{ $kunci }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div x-show="jadwal !== 'tiap_jam'">
                        <label for="integrasi_jam" class="block text-xs font-semibold text-slate-600 mb-1.5">Jam kirim (WITA)</label>
                        <input id="integrasi_jam" type="time" name="integrasi_jam" value="{{ old('integrasi_jam', $kirim['jam']) }}"
                               class="w-full px-3.5 py-2.5 border {{ $errors->has('integrasi_jam') ? 'border-red-400' : 'border-slate-200' }} rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        @error('integrasi_jam') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div x-show="jadwal === 'mingguan'">
                        <label for="integrasi_hari" class="block text-xs font-semibold text-slate-600 mb-1.5">Hari</label>
                        <select id="integrasi_hari" name="integrasi_hari"
                                class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            @foreach ($pilihanHari as $kunci => $label)
                                <option value="{{ $kunci }}" @selected(old('integrasi_hari', $kirim['hari']) == $kunci)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-6 pt-5 border-t border-slate-100">
                <p class="text-xs text-slate-500">
                    @if ($terakhirKirim)
                        Pengiriman terakhir {{ $terakhirKirim->created_at->translatedFormat('d M Y, H:i') }} —
                        <span class="{{ $terakhirKirim->berhasil() ? 'text-emerald-700' : 'text-red-700' }} font-semibold">{{ $terakhirKirim->berhasil() ? 'berhasil' : 'gagal' }}</span>
                        ({{ $terakhirKirim->labelPemicu() }}{{ $terakhirKirim->kode_http ? ', HTTP '.$terakhirKirim->kode_http : '' }}).
                    @else
                        Belum pernah ada pengiriman.
                    @endif
                </p>
                <div class="flex gap-2">
                    <button type="button" @click="$dispatch('buka-kirim-integrasi')"
                            class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition">
                        Kirim Sekarang
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200">
                        Simpan Pengaturan
                    </button>
                </div>
            </div>
        </form>

        <div class="border-t border-slate-100">
            <p class="px-6 py-3 text-xs font-bold text-slate-600 bg-slate-50 border-b border-slate-100">Riwayat Pengiriman</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-500 text-xs uppercase tracking-wide text-left border-b border-slate-100">
                            <th class="px-6 py-2.5 font-semibold">Waktu</th>
                            <th class="px-4 py-2.5 font-semibold">Pemicu</th>
                            <th class="px-4 py-2.5 font-semibold">Tujuan</th>
                            <th class="px-4 py-2.5 font-semibold">Status</th>
                            <th class="px-4 py-2.5 font-semibold">Keterangan</th>
                            <th class="px-4 py-2.5 font-semibold text-right">Ukuran · Lama</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pengiriman as $baris)
                            <tr>
                                <td class="px-6 py-2.5 text-slate-700 whitespace-nowrap">{{ $baris->created_at->translatedFormat('d M Y, H:i') }}</td>
                                <td class="px-4 py-2.5 text-xs text-slate-600">
                                    {{ $baris->labelPemicu() }}
                                    @if ($baris->pengguna) <span class="block text-[10px] text-slate-400">{{ $baris->pengguna->nama }}</span> @endif
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600 break-all max-w-[18rem]">{{ $baris->tujuan }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full {{ $baris->berhasil() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $baris->berhasil() ? 'Berhasil' : 'Gagal' }}{{ $baris->kode_http ? ' · '.$baris->kode_http : '' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-500 max-w-[20rem]">{{ $baris->pesan ?? 'Tahun '.$baris->tahun }}</td>
                                <td class="px-4 py-2.5 text-right text-xs text-slate-500 whitespace-nowrap">
                                    {{ $baris->ukuran_byte !== null ? number_format($baris->ukuran_byte, 0, ',', '.').' B' : '—' }}
                                    · {{ $baris->durasi_ms !== null ? $baris->durasi_ms.' ms' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada pengiriman.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal-konfirmasi
        nama="kirim-integrasi"
        judul="Kirim data ke aplikasi tujuan sekarang?"
        :aksi="route('administrasi.integrasi.kirim')"
        metode="POST"
        tombol="Ya, Kirim"
        warna="teal"
        ikon="peringatan">
        <p>
            Paket dashboard eksekutif tahun berjalan dikirim seketika ke
            <strong class="text-slate-700 font-mono break-all">{{ $kirim['url'] ?: '— alamat belum diisi —' }}</strong>
            memakai token {{ $kirim['token_tersamar'] ?? '(belum ada)' }}. Pengaturan yang belum disimpan tidak ikut.
        </p>
        <p class="text-xs bg-slate-50 text-slate-600 border border-slate-100 rounded-lg px-3 py-2">
            Hasilnya tercatat pada Riwayat Pengiriman dan jejak audit; jadwal rutin tidak bergeser.
        </p>
    </x-modal-konfirmasi>

</div>

@endsection
