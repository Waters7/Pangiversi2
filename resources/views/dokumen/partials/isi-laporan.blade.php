{{-- Isi laporan perjalanan dinas: dipakai halaman lihat pelaksana dan meja
     pimpinan, supaya keduanya membaca dokumen yang sama persis. --}}
{{-- Dasar pelaksanaan diturunkan dari surat tugas dan maksud
     perjalanan, jadi tidak pernah berbeda dari yang tercatat. --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
    <h3 class="font-bold text-slate-800 text-sm mb-2">Dasar Pelaksanaan</h3>
    <p class="text-sm text-slate-600 leading-relaxed">{{ $laporan->dasarPelaksanaan() }}</p>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-800 text-sm">Uraian Kegiatan per Hari</h3>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($laporan->kegiatan as $kegiatan)
            <div class="px-6 py-4">
                <p class="text-xs font-bold text-teal-600 mb-1">
                    {{ $kegiatan->tanggal?->translatedFormat('l, d F Y') ?? 'Hari ke-'.$kegiatan->urutan }}
                </p>
                @if ($kegiatan->tempat)
                    <p class="text-xs text-slate-500 mb-1">{{ $kegiatan->tempat }}</p>
                @endif
                <p class="text-sm text-slate-700 leading-relaxed">{{ $kegiatan->uraian }}</p>
            </div>
        @empty
            <p class="px-6 py-8 text-sm text-slate-400 text-center">Belum ada uraian kegiatan.</p>
        @endforelse
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-800 text-sm">Rencana Tindak Lanjut</h3>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($laporan->tindakLanjut as $tindak)
            <div class="px-6 py-4 flex flex-wrap items-start gap-x-4 gap-y-2">
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700 leading-relaxed">{{ $tindak->uraian }}</p>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-400">
                        @if ($tindak->penanggung_jawab)
                            <span>PJ: {{ $tindak->penanggung_jawab }}</span>
                        @endif
                        @if ($tindak->target_selesai)
                            <span class="{{ $tindak->terlambat() ? 'font-bold text-red-600' : '' }}">
                                Target {{ $tindak->target_selesai->translatedFormat('d M Y') }}
                            </span>
                        @endif
                    </div>
                </div>
                <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full shrink-0 {{ $tindak->status->badge() }}">
                    {{ $tindak->status->label() }}
                </span>
            </div>
        @empty
            <p class="px-6 py-8 text-sm text-slate-400 text-center">Belum ada rencana tindak lanjut.</p>
        @endforelse
    </div>
</div>

@if (filled($laporan->kesimpulan))
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <h3 class="font-bold text-slate-800 text-sm mb-2">Kesimpulan dan Saran</h3>
        <p class="text-sm text-slate-600 leading-relaxed">{{ $laporan->kesimpulan }}</p>
    </div>
@endif
