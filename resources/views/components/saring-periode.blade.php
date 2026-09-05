@props([
    'aksi',
    'tahun' => null,
    'bulan' => null,
    'tahunTersedia' => collect(),
    'jumlahBulan' => collect(),
    'ekstra' => [],
])

@php
    $namaBulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    $adaSaringan = filled($tahun) || filled($bulan);

    // Tautan mempertahankan saringan lain yang sedang berjalan — termasuk
    // saringan di luar periode (tab status, tahap, pencarian) yang dititipkan
    // lewat $ekstra, supaya memilih bulan tidak melempar pengguna ke tab lain.
    $bawaan = array_filter($ekstra, fn ($nilai) => filled($nilai));

    $tautan = fn (array $ubah) => $aksi.'?'.http_build_query(array_filter(
        array_merge($bawaan, ['tahun' => $tahun, 'bulan' => $bulan], $ubah),
        fn ($nilai) => filled($nilai),
    ));
@endphp

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 space-y-3">

    {{-- Tahun --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wide w-14 shrink-0">Tahun</span>

        <a href="{{ $tautan(['tahun' => null, 'bulan' => null]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-bold transition
                  {{ blank($tahun) ? 'bg-slate-800 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
            Semua
        </a>

        @foreach ($tahunTersedia as $pilihan)
            <a href="{{ $tautan(['tahun' => $pilihan]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition
                      {{ (int) $tahun === (int) $pilihan ? 'bg-teal-500 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
                {{ $pilihan }}
            </a>
        @endforeach

        @if ($tahunTersedia->isEmpty())
            <span class="text-xs text-slate-400">Belum ada data bertanggal</span>
        @endif
    </div>

    {{-- Bulan. Yang kosong tetap tampil tapi diredupkan, supaya letak tiap
         bulan tidak berpindah-pindah saat tahunnya diganti. --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wide w-14 shrink-0">Bulan</span>

        <a href="{{ $tautan(['bulan' => null]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-bold transition
                  {{ blank($bulan) ? 'bg-slate-800 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' }}">
            Semua
        </a>

        @foreach ($namaBulan as $nomor => $label)
            @php $jumlah = (int) ($jumlahBulan[$nomor] ?? 0); @endphp

            <a href="{{ $tautan(['bulan' => $nomor]) }}"
               class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition
                      {{ (int) $bulan === $nomor
                            ? 'bg-teal-500 text-white'
                            : ($jumlah > 0 ? 'bg-slate-100 hover:bg-slate-200 text-slate-600' : 'bg-slate-50 text-slate-300') }}">
                {{ $label }}
                @if ($jumlah > 0)
                    <span class="ml-0.5 {{ (int) $bulan === $nomor ? 'text-teal-100' : 'text-slate-400' }}">{{ $jumlah }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($adaSaringan)
        <div class="pt-1">
            <a href="{{ $aksi.($bawaan ? '?'.http_build_query($bawaan) : '') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 transition">
                Tampilkan semua
            </a>
        </div>
    @endif
</div>
