@props(['dokumen'])

{{-- Dokumen pada Rincian Saya yang menunggu tanda tangan pengguna ini.
     Tim keuangan mengirimnya lewat notifikasi yang mudah terlewat, padahal
     PPK baru dapat mengesahkan setelah pelaksana menyikapinya. --}}
@if ($dokumen->isNotEmpty())
    <section class="mb-6" data-tanda-tangan-saya>
        <div class="flex items-center gap-2.5 mb-3">
            <h2 class="text-sm font-bold text-slate-700">Dokumen untuk Anda Tandatangani</h2>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">{{ $dokumen->count() }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($dokumen as $baris)
                <a href="{{ $baris['tautan'] }}"
                   class="group flex items-start gap-3 bg-white rounded-xl border border-amber-200 hover:border-amber-400 hover:shadow-sm px-4 py-3.5 transition">
                    <span class="w-9 h-9 shrink-0 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 leading-snug group-hover:text-amber-700 transition">
                            {{ $baris['jalur']->nama() }}
                        </span>
                        <span class="block text-xs text-slate-500 mt-0.5 leading-snug">
                            {{ $baris['usulan']->no_usulan }} · {{ $baris['usulan']->lokasi }}
                        </span>
                        <span class="block text-xs text-slate-400 mt-0.5 leading-snug">
                            Rp {{ number_format($baris['jalur']->total(), 0, ',', '.') }}
                            @if ($baris['jalur']->masaSanggahBerjalan())
                                · sanggah sampai {{ $baris['jalur']->berkas()->batas_sanggah->translatedFormat('d M Y') }}
                            @endif
                        </span>
                    </span>
                    <svg class="w-4 h-4 shrink-0 ml-auto mt-1 text-slate-300 group-hover:text-amber-500 transition"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endforeach
        </div>
    </section>
@endif
