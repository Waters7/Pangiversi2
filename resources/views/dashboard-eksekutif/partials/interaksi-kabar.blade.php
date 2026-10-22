{{-- Reaksi dan komentar pada satu kartu news feed. --}}
@php
    $komentar = $item['komentar'];
    $jumlahReaksi = $item['reaksi']->sum();
    // Komentar lama dilipat; tiga terakhir selalu tampil.
    $terlipat = max(0, $komentar->count() - 3);
@endphp

<section class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 space-y-3" data-interaksi-kabar
         x-data="{ semua: false }">
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach (\App\Enums\JenisReaksi::cases() as $reaksi)
            @php
                $jumlah = $item['reaksi'][$reaksi->value] ?? 0;
                $pilihanSaya = $item['reaksiSaya'] === $reaksi;
            @endphp
            <form method="POST" action="{{ route('dashboard-eksekutif.news-feed.reaksi', [$item['jenis'], $item['id']]) }}">
                @csrf
                <input type="hidden" name="reaksi" value="{{ $reaksi->value }}">
                <button type="submit" title="{{ $pilihanSaya ? 'Cabut reaksi '.$reaksi->label() : $reaksi->label() }}"
                        aria-pressed="{{ $pilihanSaya ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-semibold transition
                               {{ $pilihanSaya ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-100' }}">
                    <span aria-hidden="true">{{ $reaksi->emoji() }}</span>
                    <span>{{ $reaksi->label() }}</span>
                    @if ($jumlah > 0)
                        <span class="font-bold" data-jumlah-reaksi>{{ $jumlah }}</span>
                    @endif
                </button>
            </form>
        @endforeach

        <span class="ml-auto text-xs text-slate-400">
            {{ $jumlahReaksi }} reaksi · {{ $komentar->count() }} komentar
        </span>
    </div>

    @if ($komentar->isNotEmpty())
        @if ($terlipat > 0)
            <button type="button" x-show="! semua" @click="semua = true"
                    class="text-xs font-semibold text-slate-500 hover:text-teal-600">
                Lihat {{ $terlipat }} komentar sebelumnya
            </button>
        @endif

        <ul class="space-y-2.5">
            @foreach ($komentar as $catatan)
                <li class="flex items-start gap-2.5" @if ($loop->index < $terlipat) x-show="semua" x-cloak @endif>
                    <x-avatar :nama="$catatan->user?->nama" :foto="$catatan->user?->url_foto" ukuran="sm" />
                    <div class="min-w-0 flex-1">
                        <div class="inline-block max-w-full rounded-2xl bg-white border border-slate-100 px-3 py-2">
                            <p class="text-xs font-bold text-slate-700">{{ $catatan->user?->nama ?? 'Pengguna' }}</p>
                            <p class="text-sm text-slate-700 whitespace-pre-line break-words">{{ $catatan->isi }}</p>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5 px-1">
                            <span class="text-[11px] text-slate-400">{{ $catatan->created_at->diffForHumans() }}</span>
                            @if ($catatan->bolehDihapusOleh(auth()->user()))
                                <form method="POST" action="{{ route('dashboard-eksekutif.news-feed.komentar.hapus', $catatan) }}"
                                      onsubmit="return confirm('Hapus komentar ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] font-semibold text-slate-400 hover:text-red-600">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('dashboard-eksekutif.news-feed.komentar', [$item['jenis'], $item['id']]) }}"
          class="flex items-start gap-2.5">
        @csrf
        <x-avatar :nama="auth()->user()->nama" :foto="auth()->user()->url_foto" ukuran="sm" />
        <textarea name="isi" rows="1" maxlength="1000" required
                  placeholder="Tulis komentar…"
                  class="flex-1 min-w-0 px-3 py-2 rounded-2xl border border-slate-200 bg-white text-sm resize-none focus:ring-2 focus:ring-teal-400 focus:border-transparent transition"></textarea>
        <button type="submit"
                class="shrink-0 px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold transition">
            Kirim
        </button>
    </form>
</section>
