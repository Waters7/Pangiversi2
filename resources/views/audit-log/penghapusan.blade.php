@extends('app')

@section('title', 'Penghapusan Usulan & SPD')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    <x-flash />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Penghapusan Usulan &amp; SPD</h1>
        <p class="text-xs text-slate-400 mt-0.5">
            Usulan perjadin dan Surat Perjalanan Dinas yang dihapus — apa isinya, siapa yang menghapus, kapan, dan dari mana
        </p>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['label' => 'Total Penghapusan', 'nilai' => $perObjek->sum(), 'warna' => 'bg-slate-100 text-slate-700'],
            ['label' => 'Usulan Perjadin', 'nilai' => $perObjek[\App\Models\AuditLog::OBJEK_USULAN] ?? 0, 'warna' => 'bg-red-100 text-red-700'],
            ['label' => 'SPD', 'nilai' => $perObjek[\App\Models\AuditLog::OBJEK_SPD] ?? 0, 'warna' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Bulan Ini', 'nilai' => $bulanIni, 'warna' => 'bg-teal-100 text-teal-700'],
        ] as $kartu)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl {{ $kartu['warna'] }} flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 6h18M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2m2 0v14a2 2 0 01-2 2H8a2 2 0 01-2-2V6"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800">{{ number_format($kartu['nilai'], 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-400">{{ $kartu['label'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Saringan --}}
    <form method="GET" action="{{ route('audit-log.penghapusan') }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="relative md:col-span-2">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nomor, nama pelaksana, tujuan, atau penghapus..."
                           class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                </div>
                <input type="date" name="dari" value="{{ $dari }}" title="Dihapus sejak tanggal"
                       class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                <input type="date" name="sampai" value="{{ $sampai }}" title="Dihapus sampai tanggal"
                       class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">Filter</button>
                @if ($search || $dari || $sampai)
                    <a href="{{ route('audit-log.penghapusan', array_filter(['objek' => $objek])) }}"
                       class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">Reset</a>
                @endif
            </div>
        </div>
        <input type="hidden" name="objek" value="{{ $objek }}">
    </form>

    <x-tab-status
        :aksi="route('audit-log.penghapusan')"
        kunci="objek"
        :terpilih="$objek"
        :tab="collect(['' => ['label' => 'Semua', 'jumlah' => $perObjek->sum()]])
                ->merge(collect($objekOptions)->map(fn ($label, $kunci) => ['label' => $label, 'jumlah' => $perObjek[$kunci] ?? 0]))
                ->all()" />

    {{-- Daftar penghapusan --}}
    <div class="space-y-3">
        @forelse ($logs as $log)
            @php
                $isi = $log->cuplikan ?? [];
                $spd = $log->objek === \App\Models\AuditLog::OBJEK_SPD;
                $mulai = filled($isi['tanggal_mulai'] ?? null) ? \Carbon\Carbon::parse($isi['tanggal_mulai']) : null;
                $selesai = filled($isi['tanggal_selesai'] ?? null) ? \Carbon\Carbon::parse($isi['tanggal_selesai']) : null;
                $peran = \App\Enums\PeranPengguna::tryFrom((string) $log->pelaku?->role)?->label();
            @endphp

            <article class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 flex flex-col md:flex-row md:items-start gap-4">
                    <div class="w-11 h-11 rounded-xl {{ $spd ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center shrink-0">
                        @if ($spd)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l6-6M9 9l6 6"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M10 12l4 4M14 12l-4 4"/>
                            </svg>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ $spd ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700' }}">
                                {{ $log->objek_label }}
                            </span>
                            <p class="font-mono text-sm font-bold text-slate-800 break-all">
                                {{ $isi['nomor'] ?? \Illuminate\Support\Str::of($log->deskripsi)->after(' ')->beforeLast(' dihapus.') }}
                            </p>
                            @if ($log->status_lama)
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">
                                    Status saat dihapus: {{ $log->status_lama_label }}
                                </span>
                            @endif
                        </div>

                        @if ($isi === [])
                            <p class="mt-2 text-xs text-slate-400">
                                Dihapus sebelum rincian penghapusan direkam, jadi isinya tidak tersimpan.
                            </p>
                        @else
                            @if (! empty($isi['pelaksana']))
                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    @foreach ($isi['pelaksana'] as $nama)
                                        <span class="inline-flex items-center gap-1.5 pl-1 pr-2.5 py-0.5 rounded-full bg-slate-50 border border-slate-100 text-xs text-slate-700">
                                            <x-avatar :nama="$nama" ukuran="sm" class="w-5! h-5! text-[9px]!" />
                                            {{ $nama }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
                                @if (filled($isi['tujuan'] ?? null))
                                    <div class="flex gap-2"><dt class="text-slate-400 w-28 shrink-0">Tujuan</dt><dd class="text-slate-700 font-semibold">{{ $isi['tujuan'] }}</dd></div>
                                @endif
                                @if ($mulai)
                                    <div class="flex gap-2">
                                        <dt class="text-slate-400 w-28 shrink-0">Tanggal perjalanan</dt>
                                        <dd class="text-slate-700 font-semibold">
                                            {{ $mulai->translatedFormat('d M Y') }}@if ($selesai && ! $selesai->isSameDay($mulai)) — {{ $selesai->translatedFormat('d M Y') }}@endif
                                        </dd>
                                    </div>
                                @endif
                                @foreach ($isi['rincian'] ?? [] as $label => $nilai)
                                    <div class="flex gap-2"><dt class="text-slate-400 w-28 shrink-0">{{ $label }}</dt><dd class="text-slate-700 break-words">{{ $nilai }}</dd></div>
                                @endforeach
                            </dl>

                            @if (filled($isi['maksud'] ?? null))
                                <p class="mt-3 text-xs text-slate-500 leading-relaxed bg-slate-50 rounded-lg px-3 py-2">
                                    <span class="font-semibold text-slate-600">Maksud:</span> {{ $isi['maksud'] }}
                                </p>
                            @endif
                        @endif
                    </div>

                    <div class="md:w-52 shrink-0 md:text-right border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-4">
                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Dihapus oleh</p>
                        <p class="text-sm font-bold text-slate-700 mt-0.5">{{ $log->pelaku?->nama ?? 'Sistem' }}</p>
                        @if ($peran)
                            <p class="text-xs text-slate-400">{{ $peran }}</p>
                        @endif
                        <p class="text-xs text-slate-600 mt-2">{{ $log->created_at->translatedFormat('d M Y, H:i') }}</p>
                        <p class="text-[11px] text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
                        <p class="text-[11px] text-slate-400 mt-1 truncate" title="{{ $log->user_agent }}">IP {{ $log->ip_address ?? '—' }}</p>
                    </div>
                </div>
            </article>
        @empty
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-12 text-center">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M3 6h18M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2m2 0v14a2 2 0 01-2 2H8a2 2 0 01-2-2V6"/>
                </svg>
                <p class="text-sm text-slate-400 mt-2">Belum ada penghapusan yang cocok</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        <x-pagination :paginator="$logs" />
    </div>

</div>

@endsection
