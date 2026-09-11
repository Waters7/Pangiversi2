@extends('app')

@section('title', 'Verifikasi Daftar Nominatif')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Verifikasi Daftar Nominatif</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Terbit per surat tugas, setelah seluruh pelaksana di bawahnya menyelesaikan dokumen.
            Tandatangani, lalu kirim ke tim keuangan.
        </p>
    </div>

    <x-kotak-cari :rute="route('persetujuan.nominatif')" :nilai="$cari"
                  petunjuk="Cari nomor surat tugas"
                  :sembunyi="['tanda-tangan' => $tandaTangan]" />

    {{-- Lompat ke satu surat tugas. Berguna saat daftarnya sudah panjang dan
         PPK hanya ingin memeriksa satu berkas. --}}
    @if ($suratTugas->isNotEmpty())
        <form method="GET" action="{{ route('persetujuan.nominatif') }}"
              class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Lompat ke Surat Tugas</label>
                <select name="surat-tugas" onchange="this.form.submit()"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    <option value="">Semua surat tugas ({{ $suratTugas->count() }})</option>
                    @foreach ($suratTugas as $opsi)
                        <option value="{{ $opsi['no'] }}" @selected($fokus === $opsi['no'])>{{ $opsi['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @if ($fokus)
                <div class="flex items-end">
                    <a href="{{ route('persetujuan.nominatif') }}"
                       class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                        Tampilkan Semua
                    </a>
                </div>
            @endif
        </form>
    @endif

    {{-- Dikelompokkan menurut status tanda tangannya, sehingga PPK
         langsung melihat mana yang masih menuntut keputusannya. --}}
    <x-tab-status
        :aksi="route('persetujuan.nominatif')"
        kunci="tanda-tangan"
        :terpilih="$tandaTangan"
        :tab="[
            '' => ['label' => 'Semua', 'jumlah' => $jumlahBelum + $jumlahSudah],
            'belum' => ['label' => 'Belum Ditandatangani', 'jumlah' => $jumlahBelum, 'badge' => 'bg-amber-100 text-amber-700'],
            'sudah' => ['label' => 'Sudah Ditandatangani', 'jumlah' => $jumlahSudah, 'badge' => 'bg-emerald-100 text-emerald-700'],
        ]" />

    {{-- Saringan periode: surat tugas dikelompokkan per bulan tanggal tugasnya. --}}
    <x-saring-periode
        :aksi="route('persetujuan.nominatif')"
        :tahun="$tahun"
        :bulan="$bulan"
        :tahun-tersedia="$tahunTersedia"
        :jumlah-bulan="$jumlahBulan"
        :ekstra="['tanda-tangan' => $tandaTangan, 'cari' => $cari]" />

    @php
        $kelompok = collect([
            [
                'judul' => 'Belum Ditandatangani',
                'kunci' => 'belum',
                'isi' => $belum,
                'jumlah' => $jumlahBelum,
                'warna' => 'bg-amber-100 text-amber-700',
                'kosong' => 'Tidak ada daftar nominatif yang menunggu tanda tangan.',
            ],
            [
                'judul' => 'Sudah Ditandatangani',
                'kunci' => 'sudah',
                'isi' => $sudah,
                'jumlah' => $jumlahSudah,
                'warna' => 'bg-emerald-100 text-emerald-700',
                'kosong' => 'Belum ada daftar nominatif yang ditandatangani.',
            ],
        ])->when($tandaTangan, fn ($k) => $k->where('kunci', $tandaTangan))->values();
    @endphp

    @foreach ($kelompok as $grup)
        <div class="flex items-center gap-2.5 mb-3 {{ $loop->first ? '' : 'mt-8' }}">
            <h2 class="text-sm font-bold text-slate-700">{{ $grup['judul'] }}</h2>
            <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $grup['warna'] }}">{{ $grup['jumlah'] }}</span>
        </div>

        @forelse ($grup['isi'] as $periode => $entriPeriode)
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 {{ $loop->first ? '' : 'mt-5' }}">
                {{ $periode }}
                <span class="ml-1.5 font-semibold text-slate-400 normal-case tracking-normal">{{ $entriPeriode->count() }} surat tugas</span>
            </p>
        @foreach ($entriPeriode as $entri)
            @php
                $nominatif = $entri['daftar'];
                $baris = $entri['baris'];
                $menunggu = $entri['menunggu'];
            @endphp

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4">

                <div class="px-6 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-800">{{ $nominatif->no_tugas }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $baris->count() }} pelaksana ·
                            Rp {{ number_format($baris->sum('jumlah'), 0, ',', '.') }}
                            @if ($nominatif->tanggal_tugas)
                                · {{ $nominatif->tanggal_tugas->translatedFormat('d F Y') }}
                            @endif
                            @if ($nominatif->sudahDitandatangani())
                                · ditandatangani {{ $nominatif->ppk?->nama }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $nominatif->status_badge }}">
                            {{ $nominatif->status_label }}
                        </span>

                        @unless ($nominatif->sudahDitandatangani())
                            <form method="POST" action="{{ route('persetujuan.nominatif.tanda-tangan', $nominatif) }}"
                                  x-data
                                  @submit.prevent="if (confirm('Tandatangani daftar nominatif {{ $nominatif->no_tugas }}?')) $el.submit()">
                                @csrf @method('PUT')
                                <button type="submit"
                                        class="px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
                                    Tanda Tangani
                                </button>
                            </form>
                        @endunless

                        @if ($nominatif->sudahDitandatangani() && ! $nominatif->sudahDikirim())
                            <form method="POST" action="{{ route('persetujuan.nominatif.kirim', $nominatif) }}">
                                @csrf @method('PUT')
                                <button type="submit"
                                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition">
                                    Kirim ke Tim Keuangan
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('laporan.nominatif.cetak', $nominatif) }}"
                           class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                            Cetak
                        </a>

                        <a href="{{ route('persetujuan.nominatif.detail', $nominatif) }}"
                           class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition">
                            Detail →
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
            {{-- Pesan kosongnya diambil dari $grup, bukan dari $loop induk:
                 di dalam @empty, Blade tidak menyediakan loop bersarang. --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-10 text-center mb-4">
                <p class="text-sm text-slate-400">{{ $grup['kosong'] }}</p>
            </div>
        @endforelse
    @endforeach

    {{-- Tiap daftar nominatif yang tampil menyusun barisnya dari usulan,
         jadi halaman ini dibatasi agar bebannya tidak ikut tumbuh bersama
         arsip. Angka pada lencana kelompok tetap total keseluruhan. --}}
    @if ($halaman->hasPages())
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mt-6">
            <x-pagination :paginator="$halaman" />
        </div>
    @endif

</div>

@endsection
