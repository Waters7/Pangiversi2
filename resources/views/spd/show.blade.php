@extends('app')

@section('title', 'Detail SPD')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

    @if (session('success'))
        <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('spd.index') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">← Daftar SPD</a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 mt-1">Surat Perjalanan Dinas</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $spd->ringkasan }}</p>
        </div>
        <div class="flex gap-2 shrink-0">
            <a href="{{ route('spd.cetak', $spd) }}"
               class="px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                Unduh PDF
            </a>
            <a href="{{ route('spd.edit', $spd) }}"
               class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                Ubah
            </a>
            <form method="POST" action="{{ route('spd.destroy', $spd) }}"
                  onsubmit="return confirm('Hapus SPD ini? Tindakan ini tidak dapat dibatalkan.')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-sm">Pelaksana</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($spd->pelaksana as $orang)
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-semibold text-slate-800 text-sm">{{ $orang->nama }}</p>
                        <span class="font-mono text-xs text-teal-700 shrink-0">{{ $orang->nomor_surat }}</span>
                    </div>
                    <p class="text-xs text-slate-500">NIP {{ $orang->nip ?: '—' }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $orang->pangkat_golongan ?: '—' }} · {{ $orang->jabatan_instansi ?: '—' }}
                        @if ($orang->tingkat_biaya) · Tingkat biaya {{ $orang->tingkat_biaya }} @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-sm">Rencana Perjalanan</h3>
        </div>
        <dl class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            @foreach ([
                'Maksud' => $spd->maksud,
                'Alat angkut' => $spd->alat_angkut,
                'Tempat berangkat' => $spd->tempat_berangkat,
                'Tempat tujuan' => $spd->tempat_tujuan,
                'Tanggal berangkat' => $spd->tanggal_berangkat?->translatedFormat('d F Y'),
                'Tanggal harus kembali' => $spd->tanggal_kembali?->translatedFormat('d F Y'),
                'Lamanya' => $spd->lama_hari.' hari',
                'Dikeluarkan di' => $spd->dikeluarkan_di.' · '.$spd->tanggal_surat?->translatedFormat('d F Y'),
                'Instansi pembebanan' => $spd->instansi_pembebanan ?: '—',
                'Akun' => $spd->akun_pembebanan ?: '—',
            ] as $label => $nilai)
                <div>
                    <dt class="text-xs text-slate-400 uppercase tracking-wide">{{ $label }}</dt>
                    <dd class="text-slate-700 mt-0.5">{{ $nilai }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($spd->pengikut->isNotEmpty())
            <div class="px-6 pb-5">
                <p class="text-xs text-slate-400 uppercase tracking-wide mb-2">Pengikut</p>
                <ul class="text-sm text-slate-700 space-y-1">
                    @foreach ($spd->pengikut as $ikut)
                        <li>· {{ $ikut->nama }}
                            @if ($ikut->tanggal_lahir) <span class="text-slate-400">({{ $ikut->tanggal_lahir->translatedFormat('d M Y') }})</span> @endif
                            @if ($ikut->keterangan) <span class="text-slate-400">— {{ $ikut->keterangan }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($spd->keterangan_lain)
            <div class="px-6 pb-5">
                <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Keterangan lain</p>
                <p class="text-sm text-slate-700">{{ $spd->keterangan_lain }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
