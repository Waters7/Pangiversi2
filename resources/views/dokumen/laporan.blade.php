@extends('app')

@section('title', 'Laporan Perjalanan Dinas')

@section('content')

@php
    // Terkunci begitu diselesaikan — dan sejak dikirim ke pimpinan isinya
    // tidak boleh berubah lagi tanpa ditarik lebih dulu.
    $terkunci = $laporan->sudahSelesai();
    $statusLaporan = $laporan->status();
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
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }}</p>
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

                {{-- Uraian kegiatan --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-sm">Uraian Kegiatan per Hari</h3>
                        <p class="text-xs text-slate-400">
                            Satu baris untuk tiap hari perjalanan dinas: tempat kegiatan dan uraiannya.
                            Hari yang tidak ada kegiatannya boleh dikosongkan.
                        </p>
                    </div>

                    <div class="p-6 space-y-4">
                        @forelse ($hariPerjalanan as $hari)
                            @php $kunci = $hari['tanggal']->toDateString(); @endphp
                            <div>
                                <label class="flex items-center gap-2 text-xs font-bold text-teal-700 mb-1.5">
                                    <span class="w-6 h-6 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center shrink-0">
                                        {{ $loop->iteration }}
                                    </span>
                                    {{ $hari['tanggal']->translatedFormat('l, d F Y') }}
                                </label>
                                <input type="text" name="tempat[{{ $kunci }}]" @disabled($terkunci)
                                       value="{{ old("tempat.{$kunci}", $hari['tempat']) }}"
                                       placeholder="Tempat kegiatan — cth: Dinas Kesehatan Provinsi, Manado"
                                       class="w-full mb-2 px-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                <textarea name="kegiatan[{{ $kunci }}]" rows="2" @disabled($terkunci)
                                          placeholder="cth: Mengikuti rapat koordinasi program di Direktorat…"
                                          class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old("kegiatan.{$kunci}", $hari['uraian']) }}</textarea>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Tanggal perjalanan belum tercatat pada usulannya.</p>
                        @endforelse
                    </div>
                </div>

                @include('dokumen.partials.laporan-tindak-lanjut')

                {{-- Uraian bebas --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-sm">Hasil dan Kesimpulan</h3>
                    </div>
                    <div class="p-6 space-y-4">

                        {{-- Dasar pelaksanaan tidak diketik ulang: nomor surat tugas
                             dan maksud perjalanan sudah tercatat pada usulannya. --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Dasar pelaksanaan</label>
                            <div class="px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-600 leading-relaxed">
                                {{ $laporan->dasarPelaksanaan() }}
                            </div>
                            <p class="text-xs text-slate-400 mt-1">
                                Terisi sendiri dari nomor surat tugas dan maksud perjalanan pada usulan.
                            </p>
                        </div>

                        <div>
                            <label for="id_status_hasil" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                Hasil yang dicapai <span class="text-red-500">*</span>
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

                        <div>
                            <label for="kesimpulan" class="block text-sm font-semibold text-slate-700 mb-1.5">Kesimpulan dan saran</label>
                            <textarea name="kesimpulan" id="kesimpulan" rows="3" @disabled($terkunci)
                                      placeholder="Simpulan serta saran untuk satuan kerja"
                                      class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">{{ old('kesimpulan', $laporan->kesimpulan) }}</textarea>
                        </div>

                        @include('dokumen.partials.laporan-berkas')
                    </div>
                </div>
            </div>

            {{-- Tindakan --}}
            <div class="space-y-5">
                @include('dokumen.partials.laporan-tindakan', [
                    'periksa' => 'uraian kegiatan, rencana tindak lanjut, dan status hasilnya',
                    'syarat' => 'sedikitnya satu uraian kegiatan, satu rencana tindak lanjut, dan status hasil yang dipilih',
                ])
            </div>
        </div>
    </form>

</div>

@endsection
