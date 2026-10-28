{{-- Status laporan di atas formulir: perlu revisi, sudah dikonfirmasi,
     menunggu konfirmasi, atau selesai tetapi belum dikirim. Dipakai formulir
     format lama maupun format Kemenkes. --}}
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
