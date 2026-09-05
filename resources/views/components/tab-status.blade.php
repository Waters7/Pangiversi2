@props([
    'aksi',
    'kunci' => 'status',
    'terpilih' => null,
    'tab' => [],
    'bawaan' => null,
])

{{-- Tab pengelompokan berdasarkan status.
     Tiap tab mempertahankan parameter lain yang sedang berjalan (pencarian,
     periode), supaya berpindah kelompok tidak menghapus saringan lain. --}}
@php
    $lain = collect(request()->query())->except([$kunci, 'page'])->all();

    $tautan = function (?string $nilai) use ($aksi, $kunci, $lain) {
        $isi = array_filter(
            array_merge($lain, [$kunci => $nilai]),
            fn ($v) => filled($v),
        );

        return $isi === [] ? $aksi : $aksi.'?'.http_build_query($isi);
    };
@endphp

<div class="flex flex-wrap gap-2 mb-5">
    @foreach ($tab as $nilai => $isi)
        @php
            $nilai = $nilai === '' ? null : (string) $nilai;
            $aktif = (string) ($terpilih ?? $bawaan ?? '') === (string) ($nilai ?? '');
            $jumlah = $isi['jumlah'] ?? null;
        @endphp

        <a href="{{ $tautan($nilai) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition
                  {{ $aktif ? 'bg-teal-500 text-white shadow-sm shadow-teal-200' : 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-600' }}">
            {{ $isi['label'] }}

            @if ($jumlah !== null)
                <span class="text-xs font-bold px-2 py-0.5 rounded-full
                             {{ $aktif ? 'bg-white/20 text-white' : ($isi['badge'] ?? 'bg-slate-100 text-slate-500') }}">
                    {{ $jumlah }}
                </span>
            @endif
        </a>
    @endforeach
</div>
