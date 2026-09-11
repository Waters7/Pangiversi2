{{-- Riwayat tahap laporan: kapan diselesaikan, dikirim, dikembalikan, dan
     dikonfirmasi — beserta kode QR yang terbit pada tiap tahap. --}}
@php $statusLaporan = $laporan->status(); @endphp

<dl class="space-y-3 text-xs">
    <div>
        <dt class="text-slate-400">Status Laporan</dt>
        <dd class="mt-1">
            <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $statusLaporan->badge() }}">
                {{ $statusLaporan->label() }}
            </span>
        </dd>
    </div>

    <div>
        <dt class="text-slate-400">Pelaksana</dt>
        <dd class="font-semibold text-slate-700">{{ $usulan->user?->nama }}</dd>
    </div>

    @if ($laporan->sudahSelesai())
        <div>
            <dt class="text-slate-400">Diselesaikan</dt>
            <dd class="font-semibold text-slate-700">
                {{ $laporan->diselesaikan_at->translatedFormat('d F Y H:i') }} WITA
            </dd>
        </div>
    @endif

    @if ($laporan->dikirim_at)
        <div>
            <dt class="text-slate-400">Dikirim ke Pimpinan</dt>
            <dd class="font-semibold text-slate-700">
                {{ $laporan->dikirim_at->translatedFormat('d F Y H:i') }} WITA
            </dd>
            @if ($laporan->kode_pelaksana)
                <dd class="font-mono text-[11px] text-slate-500 mt-0.5">Kode QR pelaksana: {{ $laporan->kode_pelaksana }}</dd>
            @endif
        </div>
    @endif

    @if ($laporan->sudahDikonfirmasi())
        <div>
            <dt class="text-slate-400">Dikonfirmasi Pimpinan</dt>
            <dd class="font-semibold text-slate-700">
                {{ $laporan->pimpinan?->nama }}<br>
                {{ $laporan->dikonfirmasi_at->translatedFormat('d F Y H:i') }} WITA
            </dd>
            <dd class="font-mono text-[11px] text-slate-500 mt-0.5">Kode QR pimpinan: {{ $laporan->kode_pimpinan }}</dd>
        </div>
    @endif

    @if ($laporan->perluRevisi())
        <div class="p-3 bg-red-50 border border-red-100 rounded-lg">
            <dt class="text-red-700 font-bold">Arahan revisi pimpinan</dt>
            <dd class="text-red-800 mt-1 leading-relaxed">{{ $laporan->catatan_pimpinan }}</dd>
            <dd class="text-red-500 mt-1">
                Dikembalikan {{ $laporan->dikembalikan_at->translatedFormat('d F Y H:i') }} WITA
                oleh {{ $laporan->pimpinan?->nama }}
            </dd>
        </div>
    @elseif ($laporan->sudahDikonfirmasi() && filled($laporan->catatan_pimpinan))
        <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-lg">
            <dt class="text-emerald-700 font-bold">Catatan pimpinan</dt>
            <dd class="text-emerald-800 mt-1 leading-relaxed">{{ $laporan->catatan_pimpinan }}</dd>
        </div>
    @endif

    @unless ($laporan->bolehDisunting())
        <div>
            <dt class="text-slate-400">Penyuntingan</dt>
            <dd class="font-semibold text-slate-700">
                @if ($laporan->sudahDikonfirmasi())
                    Terkunci — sudah ditandatangani pimpinan.
                @elseif ($laporan->sudahDikirim())
                    Terkunci — sedang menunggu keputusan pimpinan.
                @else
                    Terkunci — perjalanan sudah ditutup.
                @endif
            </dd>
        </div>
    @endunless
</dl>
