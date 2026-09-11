@extends('app')

@section('title', 'Laporan — List Daftar Nominatif')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">List Daftar Nominatif</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Daftar nominatif per surat tugas yang sudah ditandatangani dan dikirim PPK ke tim keuangan.
        </p>
    </div>

    {{-- Penyaring akun: memperlihatkan berapa yang keluar dari tiap mata anggaran. --}}
    <form method="GET" action="{{ route('laporan.nominatif') }}"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="cari" value="{{ $cari }}">
        <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Akun Pembiayaan</label>
            <select name="akun" onchange="this.form.submit()"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <option value="">Semua akun</option>
                <option value="belum" @selected($akun === 'belum')>Belum ditetapkan ({{ $jumlahBelumBerakun }})</option>
                @foreach ($pilihanAkun as $id => $label)
                    <option value="{{ $id }}" @selected((string) $akun === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($akun !== null && $akun !== '')
            <div class="flex items-end">
                <a href="{{ route('laporan.nominatif', ['tahun' => $tahun, 'bulan' => $bulan, 'cari' => $cari]) }}"
                   class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                    Tampilkan Semua
                </a>
            </div>
        @endif
    </form>

    <x-kotak-cari :rute="route('laporan.nominatif')" :nilai="$cari"
                  petunjuk="Cari nomor surat tugas"
                  :sembunyi="['akun' => $akun, 'tahun' => $tahun, 'bulan' => $bulan]" />

    <x-saring-periode
        :aksi="route('laporan.nominatif')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['akun' => $akun, 'cari' => $cari]" />

    {{-- Dikelompokkan menurut bulan daftar diterima tim keuangan. --}}
    @forelse ($daftar as $periode => $kelompok)
        <div class="flex items-center gap-3 mt-6 mb-3 first:mt-0">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">{{ $periode }}</p>
            <span class="text-[11px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                {{ $kelompok->count() }} daftar ·
                Rp {{ number_format($kelompok->sum(fn ($e) => $e['baris']->sum('jumlah')), 0, ',', '.') }}
            </span>
            <div class="flex-1 h-px bg-slate-200"></div>
        </div>

        @foreach ($kelompok as $entri)
        @php
            $nominatif = $entri['nominatif'];
            $baris = $entri['baris'];
            $menunggu = $entri['menunggu'];
        @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">

            <div class="px-6 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-slate-800">{{ $nominatif->no_tugas }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $baris->count() }} pelaksana ·
                        Rp {{ number_format($baris->sum('jumlah'), 0, ',', '.') }} ·
                        diterima {{ $nominatif->dikirim_at->translatedFormat('d F Y') }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1">
                        Ditandatangani {{ $nominatif->ppk?->nama ?? '—' }}
                        @if ($nominatif->ppk?->nip)
                            · NIP. {{ $nominatif->ppk->nip }}
                        @endif
                    </p>

                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @if ($nominatif->kategoriPembiayaan)
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700">
                                {{ $nominatif->kategoriPembiayaan->kode }}
                            </span>
                        @endif
                        @if ($nominatif->akunPembiayaan)
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                                {{ $nominatif->akunPembiayaan->kode }}
                            </span>
                        @endif
                        @if (! $nominatif->kategoriPembiayaan && ! $nominatif->akunPembiayaan)
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                Pembebanan belum ditetapkan
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row lg:items-end gap-3">
                    {{-- Pembebanan ditetapkan di sini, bukan saat daftar terbit:
                         sumber dana dan mata anggarannya baru pasti setelah tim
                         keuangan memeriksa. --}}
                    <form method="POST" action="{{ route('laporan.nominatif.akun', $nominatif) }}"
                          class="flex flex-wrap items-end gap-2">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori Pembiayaan</label>
                            <select name="id_kategori_pembiayaan"
                                    class="px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                <option value="">Belum ditetapkan</option>
                                @foreach ($pilihanKategori as $kategori)
                                    <option value="{{ $kategori->id }}" @selected($nominatif->id_kategori_pembiayaan === $kategori->id)>
                                        {{ $kategori->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Akun Pembiayaan</label>
                            <select name="id_akun_pembiayaan"
                                    class="px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                <option value="">Belum ditetapkan</option>
                                @foreach ($pilihanAkun as $id => $label)
                                    <option value="{{ $id }}" @selected($nominatif->id_akun_pembiayaan === $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
                            Simpan
                        </button>
                    </form>

                    <a href="{{ route('laporan.nominatif.cetak', $nominatif) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition self-end">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Cetak
                    </a>
                </div>
            </div>

            <div class="p-4">
                <x-tabel-nominatif :baris="$baris" />
                <x-nominatif-menunggu :menunggu="$menunggu" class="mt-3" />
            </div>
        </div>
        @endforeach
    @empty
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-14 text-center">
            <p class="text-sm font-semibold text-slate-500">
                {{ ($akun !== null && $akun !== '') || $tahun || $bulan
                    ? 'Tidak ada daftar pada saringan ini'
                    : 'Belum ada daftar nominatif yang diterima' }}
            </p>
            <p class="text-xs text-slate-400 mt-1.5 max-w-md mx-auto">
                Daftar muncul di sini setelah PPK menandatangani dan mengirimkannya.
            </p>
        </div>
    @endforelse

</div>

@endsection
