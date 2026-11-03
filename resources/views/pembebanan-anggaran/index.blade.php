@extends('app')

@section('title', 'Pembebanan Anggaran')

@section('content')

@php
    $tautan = fn (array $ubah) => route('pembebanan-anggaran', array_filter(array_merge([
        'tahap' => $tahap,
        'pembebanan' => $saringPembebanan,
        'cari' => $cari,
        'ta' => request('ta'),
    ], $ubah), fn ($nilai) => $nilai !== null && $nilai !== ''));
    $rupiah = fn ($nilai) => 'Rp '.number_format((float) $nilai, 0, ',', '.');
@endphp

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    @if ($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-3">
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Pembebanan Anggaran</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Tetapkan sumber dana, akun pembiayaan, dan tim kerja tiap daftar nominatif — yang masih menunggu
            tanda tangan PPK, yang sudah ditandatangani, maupun yang sudah diterima tim keuangan.
            {{ $jumlahBelumLengkap }} daftar belum lengkap pembebanannya.
        </p>
    </div>

    {{-- Pagu dan realisasi tiap tim kerja per akun --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" data-anggaran-tim>
        <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-800 text-sm">Pagu dan Realisasi per Tim Kerja{{ $tahunAnggaran ? ' — TA '.$tahunAnggaran->tahun : '' }}</h3>
                <p class="text-xs text-slate-400">Realisasi dihitung dari daftar nominatif yang sudah diberi akun dan tim kerja.</p>
            </div>
            @if ($pilihanTahun->count() > 1)
                <form method="GET" action="{{ route('pembebanan-anggaran') }}">
                    @foreach (['tahap' => $tahap, 'pembebanan' => $saringPembebanan, 'cari' => $cari] as $nama => $nilai)
                        @if ($nilai)
                            <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                        @endif
                    @endforeach
                    <select name="ta" onchange="this.form.submit()" aria-label="Tahun anggaran"
                            class="px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        @foreach ($pilihanTahun as $pilihan)
                            <option value="{{ $pilihan->id }}" @selected($tahunAnggaran?->id === $pilihan->id)>TA {{ $pilihan->tahun }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @forelse ($anggaranTim as $akun)
            <div class="px-6 py-4 border-b border-slate-100 last:border-b-0">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                    <p class="text-sm font-bold text-slate-700">
                        <span class="font-mono text-blue-700">{{ $akun['kode'] }}</span> {{ $akun['nama'] }}
                    </p>
                    <p class="text-xs text-slate-500">
                        Pagu akun {{ $rupiah($akun['pagu']) }} · realisasi {{ $rupiah($akun['realisasi']) }}
                        @if ($akun['belum_bertim'] > 0)
                            · <span class="text-amber-600">{{ $rupiah($akun['belum_bertim']) }} belum bertim kerja</span>
                        @endif
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 text-left">
                                <th class="py-1.5 pr-3 font-semibold">Tim Kerja</th>
                                <th class="py-1.5 pr-3 font-semibold">Sumber</th>
                                <th class="py-1.5 pr-3 font-semibold text-right">Pagu</th>
                                <th class="py-1.5 pr-3 font-semibold text-right">Realisasi</th>
                                <th class="py-1.5 font-semibold text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($akun['tim'] as $tim)
                                <tr>
                                    <td class="py-1.5 pr-3 text-slate-700">
                                        {{ $tim['nama'] }}
                                        @if ($tim['kewadiran'])
                                            <span class="text-slate-400">· {{ $tim['kewadiran'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 pr-3 text-slate-500">{{ $tim['sumber'] ?? '—' }}</td>
                                    <td class="py-1.5 pr-3 text-right text-slate-700 whitespace-nowrap">{{ $rupiah($tim['pagu']) }}</td>
                                    <td class="py-1.5 pr-3 text-right text-slate-700 whitespace-nowrap">{{ $rupiah($tim['realisasi']) }}</td>
                                    <td class="py-1.5 text-right whitespace-nowrap {{ $tim['melebihi'] ? 'text-red-600 font-bold' : 'text-slate-700' }}">{{ $rupiah($tim['sisa']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="px-6 py-5 text-xs text-slate-400">Belum ada pagu tim kerja pada tahun anggaran ini.</p>
        @endforelse
    </div>

    {{-- Saringan tahap dan kelengkapan pembebanan --}}
    <div class="flex flex-wrap gap-2 mb-3">
        <a href="{{ $tautan(['tahap' => null]) }}"
           class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $tahap === null ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            Semua <span class="ml-1 opacity-70">{{ $jumlahTahap->sum() }}</span>
        </a>
        @foreach ($pilihanTahap as $kunci => $label)
            <a href="{{ $tautan(['tahap' => $kunci]) }}"
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $tahap === $kunci ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $label }} <span class="ml-1 opacity-70">{{ $jumlahTahap[$kunci] }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('pembebanan-anggaran') }}"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="tahap" value="{{ $tahap }}">
        <input type="hidden" name="ta" value="{{ request('ta') }}">
        <div class="flex-1">
            <label for="cari" class="block text-xs font-semibold text-slate-600 mb-1.5">Cari</label>
            <input type="text" name="cari" id="cari" value="{{ $cari }}" placeholder="Nomor surat tugas, nama pelaksana, atau kegiatan"
                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
        </div>
        <div class="sm:w-56">
            <label for="pembebanan" class="block text-xs font-semibold text-slate-600 mb-1.5">Pembebanan</label>
            <select name="pembebanan" id="pembebanan"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <option value="">Semua</option>
                @foreach ($pilihanPembebanan as $kunci => $label)
                    <option value="{{ $kunci }}" @selected($saringPembebanan === $kunci)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Filter</button>
            @if ($cari !== '' || $saringPembebanan || $tahap)
                <a href="{{ route('pembebanan-anggaran') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
            @endif
        </div>
    </form>

    {{-- Daftar nominatif --}}
    @forelse ($daftar as $nominatif)
        @php $barisDaftar = $baris->get($nominatif->no_tugas) ?? collect(); @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-4" data-baris-pembebanan="{{ $nominatif->id }}">
            <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-bold text-slate-800">{{ $nominatif->no_tugas }}</p>
                        <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full {{ $nominatif->status_badge }}">
                            {{ $nominatif->status_label }}
                        </span>
                        @if ($nominatif->akunPembiayaan && $nominatif->timKerja)
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Pembebanan lengkap</span>
                        @else
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pembebanan belum lengkap</span>
                        @endif
                    </div>
                    @php
                        $usulanTugas = $usulan->get($nominatif->no_tugas) ?? collect();
                        $kegiatan = $usulanTugas->map(fn ($u) => $u->spd?->maksud ?: $u->uraian)->filter()->unique()->values();
                        $pelaksana = $usulanTugas->map(fn ($u) => $u->user?->nama)->filter()->unique()->values();
                    @endphp
                    @if ($kegiatan->isNotEmpty())
                        <p class="text-sm text-slate-700 mt-1" data-kegiatan-nominatif>
                            <span class="text-xs font-semibold text-slate-500">Kegiatan:</span> {{ $kegiatan->implode(' · ') }}
                        </p>
                    @endif
                    @if ($pelaksana->isNotEmpty())
                        <p class="text-xs text-slate-600 mt-0.5" data-pelaksana-nominatif>
                            <span class="font-semibold text-slate-500">Pelaksana:</span>
                            {{ $pelaksana->take(6)->implode(', ') }}{{ $pelaksana->count() > 6 ? ', dan '.($pelaksana->count() - 6).' lainnya' : '' }}
                        </p>
                    @endif
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $barisDaftar->count() }} pelaksana ·
                        {{ $rupiah($barisDaftar->sum('jumlah')) }}
                        @if ($nominatif->tanggal_tugas)
                            · surat tugas {{ $nominatif->tanggal_tugas->translatedFormat('d F Y') }}
                        @endif
                    </p>
                    @if ($nominatif->timKerja)
                        <p class="text-xs text-slate-500 mt-1">
                            Dibebankan: {{ collect([$nominatif->kategoriPembiayaan?->kode, $nominatif->akunPembiayaan?->kode, $nominatif->timKerja->nama])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>

                @include('laporan.partials.form-pembebanan', ['aksi' => route('pembebanan-anggaran.update', $nominatif)])
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-10 text-center">
            <p class="text-sm text-slate-500">Tidak ada daftar nominatif yang cocok.</p>
        </div>
    @endforelse

    <div class="mt-4">
        {{ $daftar->links() }}
    </div>

</div>

@endsection
