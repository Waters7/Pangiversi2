@extends('app')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('dashboard') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Dokumen</h1>
            <p class="text-xs text-slate-400 mt-0.5">Pilih & Upload seluruh dokumen pertanggungjawaban perjalanan dinas</p>
        </div>
    </div>

    @if ($usulan->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 19v-6a2 2 0 012-2h4a2 2 0 012 2v6m4-3a2 2 0 11-4 0 2 2 0 014 0zM16 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <p class="text-sm text-slate-500">Belum ada usulan perjalanan dinas yang disetujui dan selesai dilaksanakan.</p>
        </div>
    @else
        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

            <div class="px-6 py-4 border-b border-slate-100">
                <p class="text-sm font-bold text-slate-700">
                    Daftar perjalanan dinas
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3.5">Usulan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Kegiatan / Tujuan</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Periode</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Status</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3.5">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-50">
                        @foreach ($usulan as $item)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800 text-xs">{{ $item->no_usulan }}</p>
                                    <p class="text-xs text-slate-400">{{ $item->created_at->format('d M Y') }}</p>
                                </td>

                                <td class="px-4 py-4">
                                    <p class="text-sm text-slate-700">{{ $item->kegiatan->nama }}</p>
                                    <p class="text-xs text-slate-400">{{ $item->lokasi }}</p>
                                </td>

                                <td class="px-4 py-4 whitespace-nowrap">
                                    <p class="text-xs font-semibold text-slate-700">
                                        {{ $item->periode }}
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $item->durasi }} hari</p>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center">
                                        @php
                                            $statusConfig = match($item->status) {
                                                'draft'     => ['label' => 'Draft',     'class' => 'bg-slate-100 text-slate-600'],
                                                'diajukan'  => ['label' => 'Diajukan',  'class' => 'bg-blue-50 text-blue-600'],
                                                'menunggu'  => ['label' => 'Menunggu',  'class' => 'bg-yellow-50 text-yellow-600'],
                                                'disetujui' => ['label' => 'Disetujui', 'class' => 'bg-teal-50 text-teal-600'],
                                                'ditolak'   => ['label' => 'Ditolak',   'class' => 'bg-red-50 text-red-600'],
                                                'selesai'   => ['label' => 'Selesai',   'class' => 'bg-green-50 text-green-600'],
                                                default     => ['label' => ucfirst($item->status), 'class' => 'bg-slate-100 text-slate-600'],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusConfig['class'] }}">
                                            {{ $statusConfig['label'] }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center">
                                        <a href="{{ route('dokumen.show', $item->no_usulan) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-teal-50 text-slate-600 hover:text-teal-700 text-xs font-semibold transition border border-slate-200 hover:border-teal-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Upload
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>    
    @endif

    


</div>

@endsection