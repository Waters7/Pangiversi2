@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('usulan.list') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Detail Usulan</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }}</p>
        </div>
        <div class="ml-auto">
            <span class="inline-block text-xs font-bold px-3 py-1.5 rounded-full {{ $usulan->status_badge }}">
                {{ $usulan->status_text }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Kiri: Informasi Utama --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Data Perjalanan --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Data Perjalanan Dinas</h3>
                        <p class="text-xs text-slate-400">Informasi utama usulan</p>
                    </div>
                </div>

                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">No. Usulan</dt>
                            <dd class="text-sm font-bold text-slate-800">{{ $usulan->no_usulan }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Jenis Kegiatan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->kegiatan?->nama ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Dasar Penugasan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->no_tugas }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Lokasi / Kota Tujuan</dt>
                            <dd class="text-sm text-slate-800 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="3"/>
                                </svg>
                                {{ $usulan->lokasi }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Instansi Tujuan</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->instansi }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Mulai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_mulai)) }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Tanggal Selesai</dt>
                            <dd class="text-sm text-slate-800">{{ date('d F Y', strtotime($usulan->tanggal_selesai)) }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Durasi</dt>
                            <dd class="text-sm font-bold text-slate-800">{{ $usulan->durasi }} hari</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Periode</dt>
                            <dd class="text-sm text-slate-800">{{ $usulan->periode }}</dd>
                        </div>

                        @if($usulan->uraian)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Uraian Tujuan</dt>
                            <dd class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $usulan->uraian }}</dd>
                        </div>
                        @endif

                    </dl>
                </div>
            </div>

            {{-- Penolakan (jika ditolak) --}}
            @if($usulan->status === 'ditolak' && $usulan->catatan)
            <div class="bg-red-50 rounded-2xl border border-red-100 p-5 flex gap-3">
                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <p class="text-sm font-bold text-red-700 mb-1">Catatan Penolakan dari PPK</p>
                    <p class="text-sm text-red-600 leading-relaxed">{{ $usulan->catatan }}</p>
                </div>
            </div>
            @endif

            {{-- Lampiran Dokumen --}}
            @php $dokumen = $usulan->dokumen->last(); @endphp
            @if($dokumen)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Lampiran Dokumen</h3>
                        <p class="text-xs text-slate-400">Dokumen yang dilampirkan saat pengajuan</p>
                    </div>
                </div>
                <div class="p-6 space-y-1">
                    @php
                        $files = [
                            'Surat Tugas'       => $dokumen->surat_tugas,
                            'Rundown Kegiatan'  => $dokumen->rundown,
                            'Dokumen Pendukung' => $dokumen->dokumen_pendukung,
                        ];
                    @endphp
                    @foreach($files as $label => $path)
                        <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">{{ $label }}</p>
                                    @if($path)
                                        <p class="text-xs text-slate-400">{{ basename($path) }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($path)
                                <a href="{{ asset('storage/' . $path) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 text-xs font-semibold rounded-lg transition border border-teal-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Lihat
                                </a>
                            @else
                                <span class="text-xs text-slate-400 italic">Tidak ada</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Estimasi Biaya & Status Pembayaran (hanya untuk usulan disetujui) --}}
            @if($usulan->status === 'disetujui' && $usulan->keuangan)
            @php
                $keuangan = $usulan->keuangan;
                $rincian = $keuangan->rincianBiaya ?? collect();
                $statusPayment = match($keuangan->status) {
                    'lunas'           => ['label' => 'Lunas',           'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                    'bayar sebagian'  => ['label' => 'Bayar Sebagian',  'class' => 'bg-blue-50 text-blue-700 border-blue-200',        'dot' => 'bg-blue-500'],
                    default           => ['label' => 'Belum Dibayar',   'class' => 'bg-amber-50 text-amber-700 border-amber-200',     'dot' => 'bg-amber-500'],
                };
            @endphp
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Estimasi Biaya & Pembayaran</h3>
                            <p class="text-xs text-slate-400">Rincian biaya perjalanan dinas Anda</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 border text-xs font-bold rounded-full {{ $statusPayment['class'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusPayment['dot'] }}"></span>
                        {{ $statusPayment['label'] }}
                    </span>
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
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-teal-700">Uang Muka (80%)</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-2 text-right text-sm font-semibold text-slate-500">Sisa Bayar (20%)</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-slate-600">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="p-6 text-center">
                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-slate-400">Rincian biaya belum ditentukan oleh admin.</p>
                </div>
                @endif

                {{-- Ringkasan Pembayaran --}}
                @if($keuangan->status !== 'belum bayar')
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @if($keuangan->tanggal_transfer)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Uang Muka Ditransfer</p>
                            <p class="text-sm font-semibold text-slate-700">{{ $keuangan->tanggal_transfer->format('d/m/Y') }}</p>
                            <p class="text-xs font-bold text-teal-600">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</p>
                        </div>
                        @endif
                        @if($keuangan->status === 'lunas' && $keuangan->tanggal_pelunasan)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Pelunasan Sisa</p>
                            <p class="text-sm font-semibold text-slate-700">{{ $keuangan->tanggal_pelunasan->format('d/m/Y') }}</p>
                            <p class="text-xs font-bold text-emerald-600">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</p>
                        </div>
                        @endif
                        @if($keuangan->dokumenKeuangan?->transfer_uang_muka)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Bukti Uang Muka</p>
                            <a href="{{ asset('storage/' . $keuangan->dokumenKeuangan->transfer_uang_muka) }}" target="_blank"
                               class="text-xs text-teal-600 font-semibold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Lihat File
                            </a>
                        </div>
                        @endif
                        @if($keuangan->dokumenKeuangan?->transfer_sisa)
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Bukti Pelunasan</p>
                            <a href="{{ asset('storage/' . $keuangan->dokumenKeuangan->transfer_sisa) }}" target="_blank"
                               class="text-xs text-emerald-600 font-semibold hover:underline flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Lihat File
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- Timeline Status --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm">Riwayat Status</h3>
                </div>
                <div class="p-6">
                    @php
                        $allStatuses = ['draft', 'diajukan', 'menunggu', 'disetujui', 'selesai'];
                        $currentIndex = array_search($usulan->status, $allStatuses);
                        $isTolak = $usulan->status === 'ditolak';
                    @endphp
                    <ol class="relative border-l border-slate-200 ml-3 space-y-6">
                        @foreach($allStatuses as $i => $s)
                            @php
                                $isDone = !$isTolak && $currentIndex !== false && $i <= $currentIndex;
                                $isCurrent = !$isTolak && $i === $currentIndex;
                            @endphp
                            <li class="ml-6">
                                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white
                                    {{ $isDone ? 'bg-teal-500' : 'bg-slate-200' }}">
                                    @if($isDone)
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    @endif
                                </span>
                                <p class="text-xs font-semibold {{ $isCurrent ? 'text-teal-600' : ($isDone ? 'text-slate-700' : 'text-slate-400') }}">
                                    {{ ucfirst($s) }}
                                    @if($isCurrent) <span class="ml-1 text-[10px] font-bold bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded-full">Saat ini</span> @endif
                                </p>
                            </li>
                        @endforeach
                        @if($isTolak)
                            <li class="ml-6">
                                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white bg-red-500">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                </span>
                                <p class="text-xs font-semibold text-red-600">
                                    Ditolak <span class="ml-1 text-[10px] font-bold bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">Saat ini</span>
                                </p>
                            </li>
                        @endif
                    </ol>
                </div>
            </div>

        </div>

        {{-- Kanan: Sidebar Info --}}
        <div class="xl:col-span-1 space-y-4">

            {{-- Pengusul --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Informasi Pengusul</h3>
                <div class="flex items-center gap-3 mb-4">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($usulan->user?->nama ?? 'U') }}&background=14b8a6&color=fff"
                         class="w-10 h-10 rounded-full shrink-0" alt="avatar">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $usulan->user?->nama ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $usulan->user?->email ?? '—' }}</p>
                    </div>
                </div>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Dibuat</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-400">Diperbarui</dt>
                        <dd class="font-semibold text-slate-700">{{ $usulan->updated_at->format('d M Y, H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Aksi --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-3">
                <h3 class="font-bold text-slate-800 text-sm mb-1">Tindakan</h3>

                @if(in_array($usulan->status, ['draft']))
                    <a href="#"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Ajukan Sekarang
                    </a>
                    <a href="{{ route('usulan.edit', $usulan) }}"
                       class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Usulan
                    </a>
                @endif

                <a href="{{ route('usulan.list') }}"
                   class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                    Kembali ke List
                </a>

                @if(in_array($usulan->status, ['draft', 'diajukan']))
                    <form method="POST" action="{{ route('usulan.destroy', $usulan) }}"
                          x-data
                          @submit.prevent="if(confirm('Hapus usulan {{ $usulan->no_usulan }}?\nTindakan ini tidak dapat dibatalkan.')) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-5 py-2.5 border border-red-200 bg-white text-red-600 text-sm font-semibold rounded-xl hover:bg-red-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                            </svg>
                            Hapus Usulan
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>

</div>

@endsection
