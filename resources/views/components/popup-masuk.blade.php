@props(['pengumuman', 'dokumen'])

{{-- Popup sekali setiap masuk: pengumuman yang sedang tayang dan dokumen pada
     Rincian Saya yang menunggu tanda tangan. Tampil hanya bila ada isinya. --}}
<div x-data="{ terbuka: true }" x-show="terbuka" x-cloak @keydown.escape.window="terbuka = false"
     class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4" data-popup-masuk>
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-full flex flex-col overflow-hidden"
         role="dialog" aria-modal="true" aria-labelledby="judul-popup-masuk" @click.outside="terbuka = false">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 id="judul-popup-masuk" class="font-bold text-slate-800 text-base">Pemberitahuan untuk Anda</h2>
        </div>

        <div class="px-6 py-4 space-y-5 overflow-y-auto">
            @if ($dokumen->isNotEmpty())
                <section data-popup-dokumen>
                    <h3 class="text-xs font-bold text-amber-700 mb-2">
                        {{ $dokumen->count() }} dokumen menunggu tanda tangan Anda
                    </h3>
                    <ul class="space-y-1.5">
                        @foreach ($dokumen->take(4) as $baris)
                            <li>
                                <a href="{{ $baris['tautan'] }}"
                                   class="block rounded-xl border border-amber-200 bg-amber-50 hover:border-amber-400 px-3 py-2 transition">
                                    <span class="block text-sm font-semibold text-slate-800">{{ $baris['jalur']->nama() }}</span>
                                    <span class="block text-xs text-slate-500">{{ $baris['usulan']->no_usulan }} · {{ $baris['usulan']->lokasi }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($dokumen->count() > 4)
                        <p class="text-xs text-slate-500 mt-1.5">Dan {{ $dokumen->count() - 4 }} dokumen lainnya pada menu Rincian Saya.</p>
                    @endif
                </section>
            @endif

            @if ($pengumuman->isNotEmpty())
                <section data-popup-pengumuman>
                    <h3 class="text-xs font-bold text-teal-700 mb-2">Pengumuman</h3>
                    <div class="space-y-3">
                        @foreach ($pengumuman as $item)
                            <article>
                                <p class="text-sm font-semibold text-slate-800">{{ $item->judul }}</p>
                                <p class="text-sm text-slate-600 leading-relaxed mt-0.5 whitespace-pre-line break-words">{{ $item->isi }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3">
            <a href="{{ route('pengumuman.index') }}" class="text-xs font-semibold text-slate-500 hover:text-teal-600">Semua pengumuman</a>
            <button type="button" @click="terbuka = false"
                    class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                Mengerti
            </button>
        </div>
    </div>
</div>
