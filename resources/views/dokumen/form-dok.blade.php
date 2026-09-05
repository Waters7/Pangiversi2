@use('Illuminate\Support\Facades\Storage')
@use('App\Enums\ArahTiket')
@use('App\Enums\RuasTransport')
@extends('app')

@section('title', 'Dokumen Pertanggungjawaban')

@section('content')

@php
    $dokumen = $usulan->dokumen->last();

    // Perjalanan dalam kota tidak melibatkan tiket, penginapan, maupun
    // kuitansi: yang dipertanggungjawabkan hanya SPD dan transport lokalnya.
    $dalamKota = $usulan->dalamKota();

    // Tiket, nota, dan bill hotel adalah sumber angka pada rincian biaya
    // dan daftar riil. Begitu salah satunya ditandatangani, sumbernya
    // ikut terkunci supaya angka yang sudah disetujui tidak bergeser.
    $alasanKunci = app(\App\Services\PenguncianBerkas::class)->unggahanPelaksana($usulan);
    $terkunci = $alasanKunci !== null
        || ($usulan->status === 'selesai' && ! auth()->user()->isAdmin());
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    @if ($alasanKunci)
        <div class="mb-5 flex items-start gap-3 px-5 py-3.5 bg-amber-50 border border-amber-200 rounded-xl">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            <div>
                <p class="text-sm font-bold text-amber-800">Berkas terkunci</p>
                <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">{{ $alasanKunci }}</p>
            </div>
        </div>
    @endif

    @if ($dalamKota)
        <div class="mb-5 flex items-start gap-3 px-5 py-3.5 bg-teal-50 border border-teal-200 rounded-xl">
            <svg class="w-5 h-5 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            <div>
                <p class="text-sm font-bold text-teal-800">Perjalanan dinas dalam kota</p>
                <p class="text-xs text-teal-700 mt-0.5 leading-relaxed">
                    Kategori <strong>{{ $usulan->kategoriPerjadin?->nama }}</strong> tidak memakai tiket,
                    penginapan, maupun kuitansi. Yang perlu Anda lengkapi hanya SPPD dan nota
                    transportasi lokal — surat tugasnya sudah terkunci dari SPD.
                </p>
            </div>
        </div>
    @endif

    {{-- Banner Selesai --}}
    @if($usulan->status === 'selesai')
        <div class="mb-5 flex items-center gap-3 px-5 py-3 bg-purple-50 border border-purple-200 rounded-xl">
            <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-purple-700 font-semibold">
                Usulan ini telah <strong>Selesai</strong>.
                @if(auth()->user()->isAdmin())
                    Sebagai administrator, Anda tetap dapat mengunggah ulang dokumen.
                @else
                    Dokumen tidak dapat diubah lagi.
                @endif
            </p>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dokumen') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Dokumen Pertanggungjawaban</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }}</p>
        </div>
    </div>

    {{-- Batas waktu penyelesaian berkas. Ditaruh paling atas karena inilah
         yang paling mudah terlewat setelah pegawai kembali bertugas. --}}
    <div class="mb-5 rounded-2xl border {{ $sisaHariLaporan !== null && $sisaHariLaporan < 0 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} px-5 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $sisaHariLaporan !== null && $sisaHariLaporan < 0 ? 'text-red-500' : 'text-amber-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div class="min-w-0">
                <p class="text-sm font-bold {{ $sisaHariLaporan !== null && $sisaHariLaporan < 0 ? 'text-red-800' : 'text-amber-800' }}">
                    Wajib diselesaikan paling lambat H+{{ $tenggangLaporan }} setelah perjalanan berakhir
                </p>
                <p class="text-xs {{ $sisaHariLaporan !== null && $sisaHariLaporan < 0 ? 'text-red-700' : 'text-amber-700' }} mt-1 leading-relaxed">
                    Batasnya <strong>{{ $batasLaporan?->translatedFormat('d F Y') ?? '—' }}</strong>.
                    @if ($sisaHariLaporan !== null && $sisaHariLaporan < 0)
                        Sudah lewat {{ abs($sisaHariLaporan) }} hari.
                    @elseif ($sisaHariLaporan !== null && $sisaHariLaporan === 0)
                        Jatuh tempo hari ini.
                    @elseif ($sisaHariLaporan !== null)
                        Sisa {{ $sisaHariLaporan }} hari.
                    @endif
                    Lewat batas itu, pelunasan sisa 20% tertahan dan perjalanan tidak dapat ditutup.
                </p>
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-5 bg-teal-50 border border-teal-200 rounded-xl px-5 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm text-teal-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3 flex items-center gap-3">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3">
            <p class="text-sm font-bold text-red-700 mb-1">Periksa kembali isian berikut:</p>
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2 space-y-5">

            {{-- ══════════ 1. PENUGASAN ══════════ --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="penugasan">

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">1. Dokumen Penugasan</h3>
                            <p class="text-xs text-slate-400">SPPD bertanda tangan</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">
                        {{-- Surat tugas tidak diunggah: nomornya sudah terkunci
                             dari SPD yang menerbitkan perjalanan ini. Ditampilkan
                             agar pelaksana tahu berkas mana yang sedang berjalan. --}}
                        <div class="flex items-start gap-2.5 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                            <div>
                                <p class="text-xs font-bold text-slate-600">Surat Tugas</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $usulan->no_tugas ?? 'Belum diterbitkan' }}
                                </p>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    Terkunci dari SPD, tidak perlu diunggah lagi.
                                </p>
                            </div>
                        </div>


                        <div>
                            <x-unggah-berkas
                                nama="sppd"
                                label="SPPD yang sudah ditandatangani lengkap"
                                :berkas="$dokumen?->sppd"
                                terima=".pdf"
                                keterangan="Pindaian SPPD dengan seluruh tanda tangan dan cap sudah terisi. PDF, maks. 2 MB."
                                :terkunci="$terkunci" />

                            {{-- Catatan wajib: berkas pindaian tidak menggantikan
                                 dokumen aslinya bagi keperluan arsip keuangan. --}}
                            <div class="mt-3 flex items-start gap-2.5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl">
                                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                <p class="text-xs text-amber-800 leading-relaxed">
                                    <strong>Hardcopy SPPD tetap dikumpulkan ke Tim Keuangan.</strong>
                                    Unggahan di sini hanya salinan untuk pemeriksaan berkas; dokumen aslinya
                                    yang bertanda tangan basah tetap harus diserahkan.
                                </p>
                            </div>
                        </div>

                        @unless ($terkunci)
                            <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                                Simpan Dokumen Penugasan
                            </button>
                        @endunless
                    </div>
                </div>
            </form>

            {{-- ══════════ 2. TIKET PERGI & PULANG ══════════ --}}
            @unless ($dalamKota)
            @foreach (ArahTiket::urutan() as $arah)
                @php $data = $tiket->get($arah->value); @endphp

                <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="section" value="tiket">
                    <input type="hidden" name="arah" value="{{ $arah->value }}">

                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.8 19.2L16 11l3.5-3.5a2.12 2.12 0 00-3-3L13 8 4.8 6.2a1 1 0 00-1 1.6l4.2 3.2-2 2-2-.5a1 1 0 00-1 1.6l2.5 2.5 2.5 2.5a1 1 0 001.6-1l-.5-2 2-2 3.2 4.2a1 1 0 001.6-1z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-800 text-sm">
                                    2{{ $arah === ArahTiket::Pergi ? 'a' : 'b' }}. {{ $arah->label() }}
                                </h3>
                                <p class="text-xs text-slate-400">{{ $arah->keteranganRute() }}</p>
                            </div>
                            @if ($data?->lengkap())
                                <span class="ml-auto text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-1 rounded-full shrink-0">Lengkap</span>
                            @endif
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                        {{ $arah === ArahTiket::Pergi ? 'Kota asal' : 'Kota perjadin' }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="kota_asal" @disabled($terkunci)
                                           value="{{ old('kota_asal', $data?->kota_asal) }}"
                                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                        {{ $arah === ArahTiket::Pergi ? 'Kota tujuan' : 'Kota pulang' }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="kota_tujuan" @disabled($terkunci)
                                           value="{{ old('kota_tujuan', $data?->kota_tujuan) }}"
                                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor tiket <span class="text-red-500">*</span></label>
                                    <input type="text" name="nomor_tiket" @disabled($terkunci)
                                           value="{{ old('nomor_tiket', $data?->nomor_tiket) }}"
                                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kode booking <span class="text-red-500">*</span></label>
                                    <input type="text" name="kode_booking" @disabled($terkunci)
                                           value="{{ old('kode_booking', $data?->kode_booking) }}"
                                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono uppercase focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Harga tiket <span class="text-red-500">*</span></label>
                                <div class="flex items-stretch rounded-xl border border-slate-200 overflow-hidden focus-within:ring-2 focus-within:ring-teal-400">
                                    <span class="px-3 py-2.5 text-sm text-slate-500 bg-slate-50 border-r border-slate-200">Rp</span>
                                    <input type="number" name="harga" min="0" step="1" @disabled($terkunci)
                                           value="{{ old('harga', $data?->harga ? (int) $data->harga : '') }}"
                                           class="flex-1 min-w-0 px-3 py-2.5 text-sm border-0 focus:ring-0 focus:outline-none">
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Isi harga yang tertera pada tiket — sudah termasuk pajak.</p>
                            </div>

                            <x-unggah-berkas
                                nama="boarding_pass"
                                label="Boarding pass {{ $arah === ArahTiket::Pergi ? 'pergi' : 'pulang' }}"
                                :berkas="$data?->boarding_pass"
                                terima=".pdf,.jpg,.jpeg,.png"
                                keterangan="PDF, JPG, atau PNG — maks. 2 MB."
                                :terkunci="$terkunci" />

                            @unless ($terkunci)
                                <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                                    Simpan {{ $arah->label() }}
                                </button>
                            @endunless
                        </div>
                    </div>
                </form>
            @endforeach
            @endunless

            {{-- ══════════ 3. NOTA TRANSPORTASI LOKAL ══════════ --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="nota">

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">3. Nota / Bukti Biaya Transportasi</h3>
                            <p class="text-xs text-slate-400">Empat ruas, dari rumah sampai kembali ke rumah</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5"
                         x-data="{
                            nominal: {{ Js::from($nota->mapWithKeys(fn ($n) => [$n->urutan => (float) $n->nominal])->all() ?: (object) []) }},
                            get total() { return Object.values(this.nominal).reduce((a, b) => a + (Number(b) || 0), 0); },
                            rupiah(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0); }
                         }">

                        @foreach (RuasTransport::urutan() as $ruas)
                            @php $baris = $nota->get($ruas->value); @endphp

                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-700">{{ $ruas->value }}. {{ $ruas->label() }}</p>
                                        <p class="text-xs text-slate-400">{{ $ruas->keterangan() }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nominal</label>
                                        <div class="flex items-stretch rounded-xl border border-slate-200 bg-white overflow-hidden focus-within:ring-2 focus-within:ring-teal-400">
                                            <span class="px-3 py-2 text-xs text-slate-500 bg-slate-50 border-r border-slate-200">Rp</span>
                                            <input type="number" name="ruas[{{ $ruas->value }}][nominal]" min="0" step="1" @disabled($terkunci)
                                                   x-model.number="nominal[{{ $ruas->value }}]"
                                                   value="{{ old("ruas.{$ruas->value}.nominal", $baris?->nominal ? (int) $baris->nominal : '') }}"
                                                   class="flex-1 min-w-0 px-3 py-2 text-sm border-0 focus:ring-0 focus:outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Keterangan</label>
                                        <input type="text" name="ruas[{{ $ruas->value }}][keterangan]" @disabled($terkunci)
                                               value="{{ old("ruas.{$ruas->value}.keterangan", $baris?->keterangan) }}"
                                               placeholder="cth: taksi bandara"
                                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Bukti / nota</label>
                                    @if ($baris?->bukti)
                                        <a href="{{ Storage::url($baris->bukti) }}" target="_blank"
                                           class="inline-block mb-2 text-xs font-semibold text-teal-600 hover:underline">Lihat berkas tersimpan →</a>
                                    @endif
                                    <input type="file" name="ruas[{{ $ruas->value }}][bukti]" accept=".pdf,.jpg,.jpeg,.png" @disabled($terkunci)
                                           class="w-full px-3 py-2 rounded-xl text-sm bg-white border border-slate-200
                                                  file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-100 file:text-teal-700">
                                </div>
                            </div>
                        @endforeach

                        {{-- Total dihitung di layar agar pelaksana langsung melihat
                             angka yang akan masuk ke rincian biaya. --}}
                        <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-indigo-50 border border-indigo-200">
                            <span class="text-sm font-bold text-indigo-900">Total biaya transportasi</span>
                            <span class="text-lg font-black text-indigo-900" x-text="rupiah(total)">Rp {{ number_format($totalNota, 0, ',', '.') }}</span>
                        </div>

                        @unless ($terkunci)
                            <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                                Simpan Nota Transportasi
                            </button>
                        @endunless
                    </div>
                </div>
            </form>

            @unless ($dalamKota)
            {{-- ══════════ 4. AKOMODASI & BUKTI BIAYA ══════════ --}}
            <form action="{{ route('dokumen.store', $usulan->no_usulan) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="section" value="akomodasi">

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">4. Akomodasi & Bukti Biaya</h3>
                            <p class="text-xs text-slate-400">Bill hotel dan kuitansi</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-5">
                        <x-unggah-berkas
                            nama="bill_hotel"
                            label="Bill hotel"
                            :berkas="$dokumen?->bill_hotel"
                            terima=".pdf,.jpg,.jpeg,.png"
                            keterangan="PDF, JPG, atau PNG — maks. 2 MB."
                            :terkunci="$terkunci" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor transaksi</label>
                                <input type="text" name="bill_hotel_no_transaksi" @disabled($terkunci)
                                       value="{{ old('bill_hotel_no_transaksi', $dokumen?->bill_hotel_no_transaksi) }}"
                                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nominal</label>
                                <div class="flex items-stretch rounded-xl border border-slate-200 overflow-hidden focus-within:ring-2 focus-within:ring-teal-400">
                                    <span class="px-3 py-2.5 text-sm text-slate-500 bg-slate-50 border-r border-slate-200">Rp</span>
                                    <input type="number" name="bill_hotel_nominal" min="0" step="1" @disabled($terkunci)
                                           value="{{ old('bill_hotel_nominal', $dokumen?->bill_hotel_nominal ? (int) $dokumen->bill_hotel_nominal : '') }}"
                                           class="flex-1 min-w-0 px-3 py-2.5 text-sm border-0 focus:ring-0 focus:outline-none">
                                </div>
                            </div>
                        </div>

                        <x-unggah-berkas
                            nama="kwintasi"
                            label="Kuitansi"
                            :berkas="$dokumen?->kwintasi"
                            terima=".pdf,.jpg,.jpeg,.png"
                            keterangan="PDF, JPG, atau PNG — maks. 2 MB."
                            :terkunci="$terkunci" />

                        @unless ($terkunci)
                            <button type="submit" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                                Simpan Akomodasi & Bukti Biaya
                            </button>
                        @endunless
                    </div>
                </div>
            </form>
            @endunless

            {{-- ══════════ 5. LAPORAN PERJALANAN DINAS ══════════ --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-800 text-sm">5. Laporan Perjalanan Dinas</h3>
                        <p class="text-xs text-slate-400">Diisi langsung di aplikasi, dokumennya terbit sendiri</p>
                    </div>
                    @if ($usulan->laporan?->sudahSelesai())
                        <span class="ml-auto text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-1 rounded-full shrink-0">Selesai</span>
                    @endif
                </div>

                <div class="p-6">
                    <p class="text-sm text-slate-600 leading-relaxed mb-4">
                        Isi uraian kegiatan dan rencana tindak lanjut pada menu tersendiri.
                        Begitu dinyatakan selesai, dokumen laporannya langsung dapat dicetak —
                        tidak perlu lagi diketik di luar lalu diunggah kembali.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('dokumen.laporan.edit', $usulan->no_usulan) }}"
                           class="px-5 py-2.5 bg-violet-500 hover:bg-violet-600 text-white text-sm font-bold rounded-xl transition">
                            {{ $usulan->laporan?->sudahSelesai() ? 'Lihat / Ubah Laporan' : 'Isi Laporan' }}
                        </a>

                        @if ($usulan->laporan?->sudahSelesai())
                            <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
                               class="px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                                Unduh Dokumen Laporan
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: ringkasan --}}
        <div class="space-y-5">

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Info Perjalanan</h3>
                <dl class="space-y-3 text-xs">
                    <div><dt class="text-slate-400">No. Usulan</dt><dd class="font-mono font-semibold text-slate-700">{{ $usulan->no_usulan }}</dd></div>
                    <div><dt class="text-slate-400">Pelaksana</dt><dd class="font-semibold text-slate-700">{{ $usulan->user?->nama }}</dd></div>
                    <div><dt class="text-slate-400">Tujuan</dt><dd class="font-semibold text-slate-700">{{ $usulan->lokasi }}</dd></div>
                    <div>
                        <dt class="text-slate-400">Kategori Perjadin</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->kategoriPerjadin?->nama ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Tanggal</dt>
                        <dd class="font-semibold text-slate-700">
                            {{ \Carbon\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d M') }}–{{ \Carbon\Carbon::parse($usulan->tanggal_selesai)->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Kelengkapan Berkas</h3>

                @if ($berkasKurang === [])
                    <div class="flex items-start gap-2.5 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                        <p class="text-xs text-emerald-800 leading-relaxed">
                            Seluruh berkas sudah lengkap. Tim keuangan akan memvalidasi nominalnya.
                        </p>
                    </div>
                @else
                    <p class="text-xs text-slate-500 mb-3">Masih menunggu {{ count($berkasKurang) }} hal berikut:</p>
                    <ul class="space-y-1.5">
                        @foreach ($berkasKurang as $item)
                            <li class="flex items-start gap-2 text-xs text-slate-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0 mt-1.5"></span>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-2">Setelah berkas lengkap</h3>
                <ol class="text-xs text-slate-600 space-y-1.5 list-decimal list-inside leading-relaxed">
                    <li>Nominal yang Anda isi masuk ke rincian biaya sebagai usulan angka.</li>
                    <li>Tim keuangan memvalidasi tiap angkanya.</li>
                    <li>Rincian dikirim ke <strong>Rincian Saya</strong> untuk masa sanggah.</li>
                    <li>Setelah Anda setujui, rincian biaya ditandatangani.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@endsection
