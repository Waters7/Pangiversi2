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
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }} · Format Kemenkes</p>
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

</div>

@endsection
