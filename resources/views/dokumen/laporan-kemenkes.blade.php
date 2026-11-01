@extends('app')

@section('title', 'Laporan Perjalanan Dinas')

@section('content')

@php
    // Terkunci begitu diselesaikan — dan sejak dikirim ke pimpinan isinya
    // tidak boleh berubah lagi tanpa ditarik lebih dulu.
    $terkunci = $laporan->sudahSelesai();
    $statusLaporan = $laporan->status();

    // Isian bawaan: yang tersimpan, atau isian awal dari usulan bila belum diisi.
    $isi = fn (\App\Enums\BagianLaporan $bagian) => old('bagian.'.$bagian->value, $laporan->isiBagian($bagian) ?? ($awal[$bagian->value] ?? ''));
    $pendahuluan = array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => $bagian->pendahuluan()));
    $bagianUtama = array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => ! $bagian->pendahuluan()));
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dokumen.show', $usulan->no_usulan) }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-800">Laporan Perjalanan Dinas</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $usulan->no_usulan }} · No. <span class="font-mono font-semibold text-teal-700" data-nomor-laporan>{{ $laporan->nomor_laporan }}</span>
                · {{ $usulan->lokasi }} · Format Kemenkes
            </p>
        </div>
        <span class="ml-auto text-xs font-bold px-3 py-1.5 rounded-full shrink-0 {{ $statusLaporan->badge() }}">
            {{ $statusLaporan->label() }}
        </span>
    </div>

    <x-flash />

    @if ($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3">
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('dokumen.partials.laporan-status')

    <form method="POST" action="{{ route('dokumen.laporan.update', $usulan->no_usulan) }}" enctype="multipart/form-data"
          data-format-kemenkes
          x-data="{
            konfirmasiKirim: false,
            tindak: {{ Js::from($laporan->tindakLanjut->map(fn ($t) => [
                'uraian' => $t->uraian,
                'penanggung_jawab' => $t->penanggung_jawab ?? '',
                'target_selesai' => $t->target_selesai?->toDateString() ?? '',
                'status' => $t->status->value,
            ])->all() ?: [['uraian' => '', 'penanggung_jawab' => '', 'target_selesai' => '', 'status' => 'rencana']]) }},
          }">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-5">

                @if ($laporan->acuan)
                    <div class="flex items-start gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl" data-acuan-laporan>
                        <svg class="w-4 h-4 text-teal-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/>
                        </svg>
                        <p class="text-xs text-teal-800 leading-relaxed">
                            Isi laporan ini disalin dari laporan
                            <strong class="font-mono">{{ $laporan->acuan->nomor_laporan }}</strong>
                            milik {{ $laporan->acuan->usulan?->user?->nama ?? '—' }}, rekan dengan surat tugas dan lokasi tugas yang sama.
                            @unless ($terkunci)
                                Anda tetap dapat menyesuaikannya sebelum mengirim.
                            @endunless
                        </p>
                    </div>
                @endif

                {{-- Judul laporan: "tentang" nama kegiatan sesuai surat tugas --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    <p class="text-center text-xs font-bold tracking-widest text-slate-500">LAPORAN PERJALANAN DINAS</p>
                    <label for="tentang" class="block text-center text-sm font-bold text-slate-700 mt-2 mb-1.5">
                        TENTANG <span class="text-red-500">*</span>
                    </label>
                    <textarea name="tentang" id="tentang" rows="2" maxlength="500" @disabled($terkunci)
                              placeholder="Nama kegiatan berdasarkan surat tugas"
                              class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm text-center font-semibold resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('tentang', $laporan->tentang ?? ($awal['tentang'] ?? '')) }}</textarea>
                    <p class="text-xs text-slate-400 mt-1 text-center">
                        Nama kegiatan berdasarkan surat tugas{{ $usulan->no_tugas ? ' '.$usulan->no_tugas : '' }} — terisi dari usulan dan dapat diubah.
                    </p>
                </div>

                {{-- A. Pendahuluan: 1–4 --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-sm">A. Pendahuluan</h3>
                        <p class="text-xs text-slate-400">Umum, maksud dan tujuan, ruang lingkup, serta dasar pelaksanaan</p>
                    </div>
                    <div class="p-6 space-y-6">
                        @foreach ($pendahuluan as $bagian)
                            @include('dokumen.partials.bagian-laporan', ['bagian' => $bagian, 'label' => ($loop->iteration).'. '.$bagian->judul()])
                        @endforeach
                    </div>
                </div>

                {{-- B–E --}}
                @foreach ($bagianUtama as $bagian)
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800 text-sm">
                                {{ $bagian->nomor() }}. {{ $bagian->judul() }}
                                @if ($bagian->wajib()) <span class="text-red-500">*</span> @endif
                            </h3>
                            <p class="text-xs text-slate-400">{{ $bagian->petunjuk() }}</p>
                        </div>
                        <div class="p-6 space-y-5">
                            @include('dokumen.partials.bagian-laporan', ['bagian' => $bagian, 'label' => null])

                            {{-- Status hasil tetap dipilih: dipakai daftar laporan dan dasbor. --}}
                            @if ($bagian === \App\Enums\BagianLaporan::Hasil)
                                <div>
                                    <label for="id_status_hasil" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                        Status hasil yang dicapai <span class="text-red-500">*</span>
                                    </label>
                                    <select name="id_status_hasil" id="id_status_hasil" @disabled($terkunci)
                                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                        <option value="">— Pilih status hasil —</option>
                                        @foreach ($statusHasil as $status)
                                            <option value="{{ $status->id }}"
                                                    @selected(old('id_status_hasil', $laporan->id_status_hasil) == $status->id)>{{ $status->nama }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-slate-400 mt-1">Daftarnya dikelola pada Master Data → Status Hasil.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Rencana tindak lanjut melengkapi Simpulan dan Saran — opsional,
                         tetapi tiap barisnya masuk Daftar Tindak Lanjut dan News Feed. --}}
                    @if ($bagian === \App\Enums\BagianLaporan::Simpulan)
                        @include('dokumen.partials.laporan-tindak-lanjut', [
                            'keterangan' => 'Opsional — melengkapi saran; tiap barisnya masuk Daftar Tindak Lanjut',
                        ])
                    @endif
                @endforeach

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                    @include('dokumen.partials.laporan-berkas')
                </div>
            </div>

            {{-- Tindakan --}}
            <div class="space-y-5">
                @include('dokumen.partials.laporan-tindakan', [
                    'periksa' => 'judul, uraian tiap bagian, status hasil, dan foto dokumentasinya',
                    'syarat' => 'judul "tentang", kegiatan yang dilaksanakan, hasil yang dicapai, simpulan dan saran, serta status hasil',
                ])

                {{-- Rekan dengan surat tugas dan lokasi tugas yang sama cukup memakai
                     laporan yang sudah disusun. Isiannya milik formulir terpisah
                     (atribut form), sebab formulir tidak boleh bersarang. --}}
                @unless ($terkunci)
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5" data-pakai-laporan>
                        <h3 class="font-bold text-slate-800 text-sm">Pakai laporan rekan setugas</h3>
                        <p class="text-xs text-slate-500 leading-relaxed mt-1">
                            Bertugas bersama dengan <strong>surat tugas</strong> dan <strong>lokasi tugas</strong> yang sama?
                            Pakai laporan rekan lewat nomornya: judul, bagian A–E, status hasil, tindak lanjut, dan fotonya
                            disalin ke laporan ini, lalu tetap Anda kirim dan tandatangani sendiri.
                        </p>

                        <label for="nomor_acuan" class="block text-xs font-semibold text-slate-500 mt-3 mb-1">Nomor laporan</label>
                        <div class="flex gap-2">
                            <input type="text" name="nomor_acuan" id="nomor_acuan" form="form-pakai-laporan" maxlength="30"
                                   value="{{ old('nomor_acuan') }}" placeholder="LPD-{{ now()->format('Y') }}-0001"
                                   class="min-w-0 flex-1 px-3 py-2 border border-slate-200 rounded-xl text-sm font-mono uppercase focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <button type="submit" form="form-pakai-laporan"
                                    class="shrink-0 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-xl transition">
                                Pakai
                            </button>
                        </div>
                        @error('nomor_acuan')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror

                        @if ($acuanTersedia->isNotEmpty())
                            <p class="text-xs font-semibold text-slate-500 mt-4 mb-1.5">Laporan rekan setugas</p>
                            <ul class="space-y-2">
                                @foreach ($acuanTersedia as $calon)
                                    <li class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2" data-calon-acuan="{{ $calon->nomor_laporan }}">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-mono text-xs font-bold text-teal-700">{{ $calon->nomor_laporan }}</p>
                                            <p class="text-xs text-slate-500 truncate">{{ $calon->usulan?->user?->nama }} · {{ $calon->status()->label() }}</p>
                                        </div>
                                        <button type="submit" form="form-pakai-laporan" name="pilih_acuan" value="{{ $calon->nomor_laporan }}"
                                                class="shrink-0 px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-bold transition">
                                            Pakai
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <p class="text-[11px] text-slate-400 mt-3">Isian laporan ini saat ini akan diganti isi laporan yang dipakai.</p>
                    </div>
                @endunless

                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <h3 class="font-bold text-slate-800 text-sm mb-2">Tentang format ini</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Dokumen tercetak mengikuti format laporan Kemenkes: kop surat, judul, bagian A–E, tanda tangan
                        Anda dan Direktur, lalu halaman <strong>lampiran</strong> berisi seluruh foto dokumentasi yang diunggah.
                    </p>
                </div>
            </div>
        </div>
    </form>

    @unless ($terkunci)
        <form id="form-pakai-laporan" method="POST" action="{{ route('dokumen.laporan.pakai', $usulan->no_usulan) }}"
              onsubmit="return confirm('Pakai isi laporan rekan? Isian dan foto dokumentasi laporan ini saat ini akan diganti.')">
            @csrf
            @method('PUT')
        </form>
    @endunless

</div>

@endsection
