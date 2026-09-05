@extends('app')

@section('title', 'Laporan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('laporan') }}"
               class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Detail Laporan Pejadin</h1>
                <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }}</p>
            </div>
        </div>
        @php
            $riilTerkonfirmasi = $riil->filter(fn (array $baris) => $baris['daftar']?->sudah_ditandatangani)->count();
        @endphp

        @if ($riil->isNotEmpty())
            <span @class([
                'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold',
                'bg-emerald-50 text-emerald-700' => $riilTerkonfirmasi === $riil->count(),
                'bg-amber-50 text-amber-700' => $riilTerkonfirmasi !== $riil->count(),
            ])>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    @if ($riilTerkonfirmasi === $riil->count())
                        <path d="M5 13l4 4L19 7"/>
                    @else
                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                    @endif
                </svg>
                Daftar riil {{ $riilTerkonfirmasi }}/{{ $riil->count() }} ditandatangani PPK
            </span>
        @endif
    </div>

    @php
        $keuangan = $usulan->keuangan;
        $rincian = $keuangan?->rincianBiaya ?? collect();
        $dokumen = $usulan->dokumen->last();
        $dokKeuangan = $keuangan?->dokumenKeuangan;

        $statusConfig = match($keuangan?->status) {
            'lunas'           => ['label' => 'Lunas',          'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
            'bayar sebagian'  => ['label' => 'Bayar Sebagian', 'class' => 'bg-blue-50 text-blue-700 border-blue-200',        'dot' => 'bg-blue-500'],
            'belum bayar'     => ['label' => 'Belum Bayar',    'class' => 'bg-amber-50 text-amber-700 border-amber-200',     'dot' => 'bg-amber-500'],
            default           => ['label' => 'Belum Ada',      'class' => 'bg-slate-100 text-slate-500 border-slate-200',    'dot' => 'bg-slate-400'],
        };
    @endphp

    {{-- Info Usulan Bar --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6 flex flex-wrap items-center gap-x-6 gap-y-2">
        <div class="flex items-center gap-3">
            <x-avatar :nama="$usulan->user?->nama" :foto="$usulan->user?->url_foto" />
            <div>
                <p class="text-sm font-bold text-slate-800">{{ $usulan->user?->nama ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ $usulan->user?->email ?? '—' }}</p>
            </div>
        </div>
        <div class="border-l border-slate-200 pl-6 hidden sm:block">
            <p class="text-xs text-slate-400">Kegiatan</p>
            <p class="text-sm font-semibold text-slate-700">{{ $usulan->kegiatan?->nama ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Tujuan</p>
            <p class="text-sm font-semibold text-slate-700">{{ $usulan->lokasi }} — {{ $usulan->instansi }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Periode</p>
            <p class="text-sm font-semibold text-slate-700">{{ $usulan->periode }} ({{ $usulan->durasi }} hari)</p>
        </div>
        <div class="ml-auto">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 border text-xs font-bold rounded-full {{ $statusConfig['class'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                {{ $statusConfig['label'] }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- LEFT: Rincian Biaya + Pembayaran --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Rincian Biaya --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Rincian Biaya Perjalanan</h3>
                        <p class="text-xs text-slate-400">{{ $rincian->count() }} komponen biaya</p>
                    </div>
                </div>

                @if($rincian->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-10">No</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Komponen Biaya</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-16">Vol.</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-16">Satuan</th>
                                <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Harga Satuan</th>
                                <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($rincian as $i => $item)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-3 text-slate-500 font-medium">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $item->komponen }}</td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $item->volume }}</td>
                                    <td class="px-4 py-3 text-center text-slate-600">{{ $item->satuan }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-800">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-sm font-bold text-slate-600">Total Estimasi</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">Rp {{ number_format($keuangan?->total ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-teal-700">Uang Muka</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-teal-700">Rp {{ number_format($keuangan?->uang_muka ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-slate-500">Sisa Bayar</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-slate-600">Rp {{ number_format($keuangan?->sisa ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="p-8 text-center">
                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-slate-400">Belum ada rincian biaya.</p>
                </div>
                @endif
            </div>

            {{-- Riwayat Pembayaran --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Riwayat Pembayaran</h3>
                        <p class="text-xs text-slate-400">Detail transfer uang muka & pelunasan sisa</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Jenis</th>
                                <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tanggal</th>
                                <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Nominal</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3">Bukti</th>
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            {{-- Uang Muka --}}
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-3.5 font-semibold text-slate-700">Uang Muka</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $keuangan?->tanggal_transfer?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-right font-semibold text-slate-800">Rp {{ number_format($keuangan?->uang_muka ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($dokKeuangan?->transfer_uang_muka)
                                        <a href="{{ asset('storage/' . $dokKeuangan->transfer_uang_muka) }}" target="_blank"
                                           class="text-xs text-teal-600 font-semibold hover:underline">Lihat</a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if(in_array($keuangan?->status, ['bayar sebagian', 'lunas']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">Dibayar</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">Pending</span>
                                    @endif
                                </td>
                            </tr>
                            {{-- Sisa --}}
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-3.5 font-semibold text-slate-700">Sisa Bayar</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $keuangan?->tanggal_pelunasan?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-right font-semibold text-slate-800">Rp {{ number_format($keuangan?->sisa ?? 0, 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($dokKeuangan?->transfer_sisa)
                                        <a href="{{ asset('storage/' . $dokKeuangan->transfer_sisa) }}" target="_blank"
                                           class="text-xs text-teal-600 font-semibold hover:underline">Lihat</a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($keuangan?->status === 'lunas')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">Dibayar</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Daftar pengeluaran riil: sudah ditandatangani PPK atau belum --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Daftar Pengeluaran Riil</h3>
                        <p class="text-xs text-slate-400">Status konfirmasi pelaksana dan tanda tangan PPK</p>
                    </div>
                </div>

                @forelse ($riil as $baris)
                    @php
                        $peserta = $baris['peserta'];
                        $daftar = $baris['daftar'];
                    @endphp

                    <div class="px-6 py-4 {{ ! $loop->last ? 'border-b border-slate-50' : '' }} flex flex-wrap items-start gap-3">
                        <x-avatar :nama="$peserta->nama" :foto="$peserta->user?->url_foto" ukuran="sm" />

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $peserta->nama }}</p>
                            <p class="text-xs text-slate-400">{{ $peserta->nip ?: 'NIP belum tercatat' }}</p>

                            @if ($daftar?->sudah_ditandatangani)
                                <p class="text-xs text-slate-500 mt-1.5">
                                    Ditandatangani {{ $daftar->ppk?->nama ?? 'PPK' }} pada
                                    {{ $daftar->ditandatangani_at->translatedFormat('d M Y, H:i') }}
                                </p>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Kode verifikasi
                                    <span class="font-mono font-bold text-slate-700">{{ $daftar->kode_verifikasi }}</span>
                                </p>
                            @elseif ($daftar?->sedangDisanggah())
                                <p class="text-xs text-red-600 mt-1.5">Disanggah pelaksana — nominal sedang diperbaiki tim keuangan.</p>
                            @elseif ($daftar?->sudahDisetujuiPegawai())
                                <p class="text-xs text-amber-700 mt-1.5">Sudah dikonfirmasi pelaksana, menunggu tanda tangan PPK.</p>
                            @elseif ($daftar?->sanggahKedaluwarsa())
                                <p class="text-xs text-amber-700 mt-1.5">
                                    Masa sanggah berakhir {{ $daftar->batas_sanggah->translatedFormat('d F Y') }},
                                    menunggu tanda tangan PPK.
                                </p>
                            @elseif ($daftar?->masaSanggahBerjalan())
                                <p class="text-xs text-amber-700 mt-1.5">
                                    Menunggu tanggapan pelaksana — sisa {{ $daftar->sisaHariSanggah() }} hari masa sanggah.
                                </p>
                            @elseif ($daftar && $daftar->total_riil > 0)
                                <p class="text-xs text-slate-500 mt-1.5">Nominal sudah disusun, belum dikirim ke pelaksana.</p>
                            @else
                                <p class="text-xs text-slate-400 mt-1.5">Daftar riil belum disusun tim keuangan.</p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $daftar?->status_badge ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $daftar?->status_label ?? 'Belum Disusun' }}
                            </span>
                            @if ($daftar && $daftar->total_riil > 0)
                                <p class="text-sm font-bold text-slate-800 mt-1.5">
                                    Rp {{ number_format($daftar->total_riil, 0, ',', '.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-slate-400">
                        Belum ada peserta tercatat pada perjalanan dinas ini.
                    </p>
                @endforelse
            </div>

        </div>

        {{-- RIGHT: Sidebar --}}
        <div class="xl:col-span-1 space-y-4">

            {{-- Ringkasan Keuangan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Ringkasan Keuangan</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-slate-500">Total Estimasi</span>
                        <span class="text-sm font-bold text-slate-800">Rp {{ number_format($keuangan?->total ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-slate-500">Uang Muka</span>
                        <span class="text-sm font-bold text-teal-700">Rp {{ number_format($keuangan?->uang_muka ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-slate-500">Sisa Bayar</span>
                        <span class="text-sm font-bold text-slate-600">Rp {{ number_format($keuangan?->sisa ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="border-t border-slate-100 pt-3 flex justify-between items-center">
                        <span class="text-xs font-semibold text-slate-600">Status</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 border text-xs font-bold rounded-full {{ $statusConfig['class'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                            {{ $statusConfig['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Kelengkapan Dokumen --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Kelengkapan Dokumen LPJ</h3>
                @if($dokumen)
                    @php
                        $dokFields = [
                            'Surat Tugas' => $dokumen->surat_tugas,
                            'SPPD' => $dokumen->sppd,
                            'Boarding Pass' => $dokumen->boarding_pass,
                            'Faktur/Invoice' => $dokumen->faktur,
                            'Bill Hotel' => $dokumen->bill_hotel,
                            'Kwitansi' => $dokumen->kwintasi,
                            'Laporan Hasil' => $dokumen->laporan_hasil,
                        ];
                        $filled = collect($dokFields)->filter()->count();
                        $total = count($dokFields);
                        $pct = $total > 0 ? round(($filled / $total) * 100) : 0;
                    @endphp
                    <div class="mb-3">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">{{ $filled }}/{{ $total }} dokumen</span>
                            <span class="font-bold {{ $pct === 100 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $pct }}%</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all {{ $pct === 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        @foreach($dokFields as $label => $value)
                            <div class="flex items-center justify-between py-1">
                                <span class="text-xs text-slate-600">{{ $label }}</span>
                                @if($value)
                                    <div class="flex items-center gap-2">
                                        <span class="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center">
                                            <svg class="w-2.5 h-2.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        <a href="{{ asset('storage/' . $value) }}" target="_blank" class="text-xs text-teal-600 font-semibold hover:underline">Lihat</a>
                                    </div>
                                @else
                                    <span class="w-4 h-4 rounded-full bg-slate-100 flex items-center justify-center">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-400 text-center py-4">Belum ada data dokumen.</p>
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>
                @can('melihat-keuangan')
                    <a href="{{ route('daftar-riil.show', $usulan->no_usulan) }}"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Kelola Daftar Riil
                    </a>
                @endcan
                <a href="{{ route('keuangan.detail', $usulan->no_usulan) }}"
                   class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Kelola Keuangan
                </a>
                <a href="{{ route('laporan') }}"
                   class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    Kembali ke Laporan
                </a>
            </div>

        </div>

    </div>

</div>

@endsection
