@props(['pantau'])

@php
    use App\Services\PemantauBerkas;

    /**
     * Panel pemantauan satu berkas: sampai di mana tanda tangannya dan
     * sudah dibayar apa saja. Keadaan tiap butir dibedakan dengan ikon dan
     * warna supaya terbaca sekilas dari kartu, tanpa membuka halaman lain.
     */
    $gaya = fn (string $keadaan): array => match ($keadaan) {
        PemantauBerkas::SELESAI => ['bg-emerald-100 text-emerald-700', 'text-slate-700'],
        PemantauBerkas::PERHATIAN => ['bg-red-100 text-red-700', 'text-red-700'],
        PemantauBerkas::TIDAK_PERLU => ['bg-slate-100 text-slate-400', 'text-slate-400'],
        default => ['bg-slate-100 text-slate-400', 'text-slate-500'],
    };

    $kelompok = [
        ['judul' => 'Status Tanda Tangan', 'butir' => $pantau['tanda_tangan']],
        ['judul' => 'Status Pembayaran', 'butir' => $pantau['pembayaran']],
    ];

    $selesai = collect($pantau['tanda_tangan'])->merge($pantau['pembayaran'])
        ->reject(fn (array $b) => $b['keadaan'] === PemantauBerkas::TIDAK_PERLU);
    $jumlahSelesai = $selesai->where('keadaan', PemantauBerkas::SELESAI)->count();
@endphp

<div class="mb-5 rounded-xl border border-slate-200 overflow-hidden" data-pantau-berkas>
    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-3">
        <p class="text-xs font-bold text-slate-600">Pemantauan Berkas</p>
        <p class="text-[11px] font-semibold text-slate-500">
            {{ $jumlahSelesai }} dari {{ $selesai->count() }} tahap selesai
        </p>
    </div>

    <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200">
        @foreach ($kelompok as $bagian)
            <div class="px-4 py-3">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">{{ $bagian['judul'] }}</p>
                <ul class="space-y-2">
                    @foreach ($bagian['butir'] as $butir)
                        @php [$gayaIkon, $gayaLabel] = $gaya($butir['keadaan']); @endphp
                        <li class="flex items-start gap-2.5">
                            <span class="mt-0.5 w-5 h-5 rounded-full shrink-0 flex items-center justify-center {{ $gayaIkon }}">
                                @if ($butir['keadaan'] === PemantauBerkas::SELESAI)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                @elseif ($butir['keadaan'] === PemantauBerkas::PERHATIAN)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01"/></svg>
                                @elseif ($butir['keadaan'] === PemantauBerkas::TIDAK_PERLU)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 12h12"/></svg>
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold {{ $gayaLabel }} leading-snug">
                                    {{ $butir['label'] }}
                                    @if ($butir['nominal'] !== null)
                                        <span class="font-bold text-slate-800">· Rp {{ number_format($butir['nominal'], 0, ',', '.') }}</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-slate-400 leading-snug">
                                    @if ($butir['waktu'])
                                        {{ $butir['waktu']->translatedFormat('d M Y') }}{{ $butir['waktu']->format('H:i') !== '00:00' ? ', '.$butir['waktu']->format('H:i') : '' }}
                                        @if ($butir['oleh']) · {{ $butir['oleh'] }} @endif
                                        @if ($butir['keterangan']) · {{ $butir['keterangan'] }} @endif
                                    @elseif ($butir['keterangan'])
                                        {{ $butir['keterangan'] }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
