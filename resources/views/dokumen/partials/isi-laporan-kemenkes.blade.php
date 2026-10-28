{{-- Isi laporan format Kemenkes untuk halaman lihat pelaksana dan meja
     pimpinan: judul, bagian A–E beserta foto dokumentasinya, dan rencana
     tindak lanjut bila ada. --}}
@php $laporan->loadMissing('dokumentasi', 'tindakLanjut'); @endphp

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 text-center" data-format-kemenkes>
    <p class="text-xs font-bold tracking-widest text-slate-500">LAPORAN PERJALANAN DINAS</p>
    <p class="text-xs font-bold text-slate-500 mt-2">TENTANG</p>
    <p class="text-base font-bold text-slate-800 mt-1">{{ $laporan->tentang ?: '—' }}</p>
</div>

@foreach ([
    'A. Pendahuluan' => array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => $bagian->pendahuluan())),
    null => array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => ! $bagian->pendahuluan())),
] as $judulKelompok => $kelompok)
    @if ($judulKelompok)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">{{ $judulKelompok }}</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($kelompok as $bagian)
                    @include('dokumen.partials.isi-bagian-laporan', ['bagian' => $bagian, 'judul' => $loop->iteration.'. '.$bagian->judul()])
                @endforeach
            </div>
        </div>
    @else
        @foreach ($kelompok as $bagian)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">{{ $bagian->nomor() }}. {{ $bagian->judul() }}</h3>
                </div>
                @include('dokumen.partials.isi-bagian-laporan', ['bagian' => $bagian, 'judul' => null])

                @if ($bagian === \App\Enums\BagianLaporan::Simpulan && $laporan->tindakLanjut->isNotEmpty())
                    <div class="px-6 pb-5">
                        <p class="text-xs font-bold text-slate-500 mb-2">Rencana tindak lanjut</p>
                        <ul class="space-y-2">
                            @foreach ($laporan->tindakLanjut as $tindak)
                                <li class="flex flex-wrap items-start gap-x-3 gap-y-1 text-sm text-slate-700">
                                    <span class="flex-1 min-w-0">{{ $tindak->uraian }}</span>
                                    <span class="text-xs text-slate-400">
                                        {{ $tindak->penanggung_jawab ? 'PJ: '.$tindak->penanggung_jawab : '' }}
                                        {{ $tindak->target_selesai ? '· Target '.$tindak->target_selesai->translatedFormat('d M Y') : '' }}
                                    </span>
                                    <span class="inline-block text-xs font-bold px-2.5 py-0.5 rounded-full {{ $tindak->status->badge() }}">{{ $tindak->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
@endforeach
