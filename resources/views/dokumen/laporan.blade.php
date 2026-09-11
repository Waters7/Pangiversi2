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

    @if ($laporan->perluRevisi())
        <div class="mb-5 px-5 py-4 bg-red-50 border border-red-200 rounded-2xl">
            <p class="text-sm font-bold text-red-800">Dikembalikan pimpinan untuk direvisi</p>
            <p class="text-sm text-red-800 mt-1 leading-relaxed">{{ $laporan->catatan_pimpinan }}</p>
            <p class="text-xs text-red-500 mt-2">
                {{ $laporan->pimpinan?->nama }} ·
                {{ $laporan->dikembalikan_at->translatedFormat('d F Y H:i') }} WITA.
                Perbaiki isinya, nyatakan selesai, lalu kirim ulang.
            </p>
        </div>
    @endif

    @if ($laporan->sudahDikonfirmasi())
        <div class="mb-5 flex flex-wrap items-center gap-3 px-5 py-4 bg-emerald-50 border border-emerald-200 rounded-2xl">
            <p class="text-sm text-emerald-800 flex-1 min-w-0">
                Dikonfirmasi dan ditandatangani <strong>{{ $laporan->pimpinan?->nama }}</strong>
                pada {{ $laporan->dikonfirmasi_at->translatedFormat('d F Y H:i') }} WITA.
                Laporan terkunci dan pelunasan pembayaran dapat diproses.
            </p>
            <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shrink-0">
                Unduh Dokumen
            </a>
        </div>
    @elseif ($laporan->sudahDikirim())
        <div class="mb-5 flex flex-wrap items-center gap-3 px-5 py-4 bg-amber-50 border border-amber-200 rounded-2xl">
            <p class="text-sm text-amber-800 flex-1 min-w-0">
                Dikirim ke pimpinan {{ $laporan->dikirim_at->translatedFormat('d F Y H:i') }} WITA
                dan sedang menunggu konfirmasi. Isinya terkunci sampai diputuskan.
            </p>
            <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shrink-0">
                Unduh Dokumen
            </a>
            <form method="POST" action="{{ route('dokumen.laporan.buka', $usulan->no_usulan) }}" class="shrink-0"
                  onsubmit="return confirm('Tarik laporan dari meja pimpinan? QR pelaksana yang sudah tercetak tidak lagi sah.')">
                @csrf @method('PUT')
                <button type="submit" class="px-4 py-2 border border-amber-300 bg-white text-amber-800 text-xs font-bold rounded-xl hover:bg-amber-50 transition">
                    Tarik &amp; Perbaiki
                </button>
            </form>
        </div>
    @elseif ($terkunci)
        <div class="mb-5 flex flex-wrap items-center gap-3 px-5 py-4 bg-blue-50 border border-blue-200 rounded-2xl">
            <p class="text-sm text-blue-800 flex-1 min-w-0">
                Laporan dinyatakan selesai {{ $laporan->diselesaikan_at?->translatedFormat('d F Y H:i') }} WITA
                tetapi belum dikirim. Kirim ke pimpinan untuk dikonfirmasi dan ditandatangani — itu syarat pelunasan pembayaran.
            </p>
            <form method="POST" action="{{ route('dokumen.laporan.kirim', $usulan->no_usulan) }}" class="shrink-0">
                @csrf @method('PUT')
                <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl transition">
                    Kirim ke Pimpinan
                </button>
            </form>
            <a href="{{ route('dokumen.laporan.cetak', $usulan->no_usulan) }}"
               class="px-4 py-2 border border-blue-200 bg-white text-blue-800 text-xs font-bold rounded-xl hover:bg-blue-50 transition shrink-0">
                Unduh Dokumen
            </a>
            <form method="POST" action="{{ route('dokumen.laporan.buka', $usulan->no_usulan) }}" class="shrink-0">
                @csrf @method('PUT')
                <button type="submit" class="px-4 py-2 border border-blue-200 bg-white text-blue-800 text-xs font-bold rounded-xl hover:bg-blue-50 transition">
                    Buka Kembali
                </button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('dokumen.laporan.update', $usulan->no_usulan) }}"
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

                {{-- Rencana tindak lanjut --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Rencana Tindak Lanjut</h3>
                            <p class="text-xs text-slate-400">Langkah yang dikerjakan setelah kembali bertugas</p>
                        </div>
                        @unless ($terkunci)
                            <button type="button"
                                    @click="tindak.push({ uraian: '', penanggung_jawab: '', target_selesai: '', status: 'rencana' })"
                                    class="text-xs font-bold text-teal-600 hover:text-teal-700 shrink-0">+ Tambah</button>
                        @endunless
                    </div>

                    <div class="p-6 space-y-4">
                        <template x-for="(baris, i) in tindak" :key="'tl-' + i">
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                <div class="flex items-start gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-white border border-slate-200 text-slate-500 text-xs font-bold flex items-center justify-center shrink-0 mt-1"
                                          x-text="i + 1"></span>
                                    <textarea :name="`tindak_lanjut[${i}][uraian]`" x-model="baris.uraian" rows="2" @disabled($terkunci)
                                              placeholder="cth: Menyusun draf pedoman internal berdasarkan hasil rapat"
                                              class="flex-1 min-w-0 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"></textarea>
                                    @unless ($terkunci)
                                        <button type="button" x-show="tindak.length > 1" @click="tindak.splice(i, 1)"
                                                class="w-7 h-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center shrink-0 mt-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    @endunless
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pl-9">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Penanggung jawab</label>
                                        <input type="text" :name="`tindak_lanjut[${i}][penanggung_jawab]`"
                                               x-model="baris.penanggung_jawab" @disabled($terkunci)
                                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Target selesai</label>
                                        <input type="date" :name="`tindak_lanjut[${i}][target_selesai]`"
                                               x-model="baris.target_selesai" @disabled($terkunci)
                                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                                        <select :name="`tindak_lanjut[${i}][status]`" x-model="baris.status" @disabled($terkunci)
                                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                            @foreach ($statusTindakLanjut as $nilai => $label)
                                                <option value="{{ $nilai }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

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
                    </div>
                </div>
            </div>

            {{-- Tindakan --}}
            <div class="space-y-5">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                    <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                    @unless ($terkunci)
                        {{-- Menyimpan dan mengirim satu langkah, ditanya ulang sekali:
                             sejak dikirim laporan terkunci dan pimpinan langsung menerimanya. --}}
                        <button type="button" @click="konfirmasiKirim = true"
                                class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Simpan &amp; Kirim ke Pimpinan
                        </button>

                        <button type="submit" name="action" value="draft"
                                class="w-full px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                            Simpan Draf
                        </button>

                        <div x-show="konfirmasiKirim" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                             @keydown.escape.window="konfirmasiKirim = false">
                            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left"
                                 @click.outside="konfirmasiKirim = false">
                                <h3 class="font-bold text-slate-800 text-sm mb-2">Simpan dan kirim laporan ke pimpinan?</h3>

                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Isi laporan disimpan, dinyatakan selesai, lalu langsung dikirim ke pimpinan
                                    untuk dikonfirmasi dan ditandatangani. Periksa kembali uraian kegiatan,
                                    rencana tindak lanjut, dan status hasilnya sebelum melanjutkan.
                                </p>

                                <p class="mt-3 text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                                    Setelah dikirim, laporan terkunci sampai pimpinan memutuskan. Tanda tangan Anda
                                    terbit sebagai QR pada dokumen.
                                </p>

                                <div class="flex justify-end gap-2 mt-5">
                                    <button type="button" @click="konfirmasiKirim = false"
                                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                                        Periksa Lagi
                                    </button>
                                    <button type="submit" name="action" value="kirim"
                                            class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                                        Ya, Kirim ke Pimpinan
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endunless

                    <p class="text-xs text-slate-400 leading-relaxed">
                        <strong>Simpan Draf</strong> menyimpan isinya tanpa mengirim. <strong>Simpan &amp; Kirim</strong>
                        perlu sedikitnya satu uraian kegiatan, satu rencana tindak lanjut, dan status hasil yang dipilih;
                        setelah itu laporan terkunci dan pelunasan menunggu konfirmasi pimpinan.
                    </p>
                </div>
            </div>
        </div>
    </form>

</div>

@endsection
