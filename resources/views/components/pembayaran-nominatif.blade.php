@props(['baris'])

{{-- Sampai mana bendahara membayar pelaksana pada daftar nominatif ini, supaya
     yang memantau dari menu Persetujuan tidak perlu membuka menu Pembayaran. --}}
@if ($baris->isNotEmpty())
    @php
        $keuangan = $baris->map(fn (array $isi) => $isi['usulan']->keuangan ?? new \App\Models\Keuangan);
        $lunas = $keuangan->filter(fn (\App\Models\Keuangan $item) => $item->sudahLunas())->count();
    @endphp

    <div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 px-4 py-3']) }} data-pembayaran-nominatif>
        <p class="text-xs font-bold text-slate-700">
            Pembayaran Bendahara
            <span class="font-semibold text-slate-400">· {{ $lunas }} dari {{ $baris->count() }} pelaksana lunas</span>
        </p>

        <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5">
            @foreach ($baris as $i => $isi)
                <li class="inline-flex items-center gap-2 text-xs text-slate-600">
                    {{ $isi['nama'] }}
                    <span class="font-bold px-2 py-0.5 rounded-full {{ $keuangan[$i]->status_badge }}">{{ $keuangan[$i]->status_label }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
