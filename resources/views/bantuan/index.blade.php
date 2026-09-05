@extends('app')

@section('title', 'Bantuan')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7" x-data="{ formBaru: {{ $errors->any() ? 'true' : 'false' }} }">

    <x-flash />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                {{ $admin ? 'Laporan Kendala Pengguna' : 'Bantuan' }}
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">
                @if ($admin)
                    Kendala yang dilaporkan pengguna. Jawab di sini; pelapornya menerima pemberitahuan.
                @else
                    Laporkan kendala yang Anda temui saat memakai PANGI. Administrator menjawabnya di halaman ini.
                @endif
            </p>
        </div>

        @unless ($admin)
            <button type="button" @click="formBaru = ! formBaru"
                    class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition shadow-sm shadow-teal-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Laporkan Kendala
            </button>
        @endunless
    </div>

    @unless ($admin)
        <div x-show="formBaru" x-transition x-cloak
             class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
            <form method="POST" action="{{ route('bantuan.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Judul singkat <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul" value="{{ old('judul') }}" required
                           placeholder="Contoh: Tombol unggah SPPD tidak dapat ditekan"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('judul') ? 'border-red-500' : 'border-slate-200' }}">
                    @error('judul') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Ceritakan kendalanya <span class="text-red-500">*</span>
                    </label>
                    <textarea name="isi" rows="4" required
                              placeholder="Apa yang Anda lakukan, apa yang terjadi, dan apa yang Anda harapkan."
                              class="w-full px-4 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition {{ $errors->has('isi') ? 'border-red-500' : 'border-slate-200' }}">{{ old('isi') }}</textarea>
                    @error('isi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Perjalanan dinas terkait</label>
                        <select name="id_usulan"
                                class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                            <option value="">Tidak menyangkut berkas tertentu</option>
                            @foreach ($usulanSaya as $item)
                                <option value="{{ $item->id }}" @selected(old('id_usulan') == $item->id)>
                                    {{ $item->no_usulan }} — {{ $item->lokasi }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Membantu administrator menemukan berkasnya lebih cepat.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tangkapan layar atau berkas</label>
                        <input type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-600 file:text-xs file:font-semibold">
                        <p class="text-[11px] text-slate-400 mt-1">PDF, JPG, atau PNG — maks. 2 MB.</p>
                        @error('lampiran') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="formBaru = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition">
                        Kirim Laporan
                    </button>
                </div>
            </form>
        </div>
    @endunless

    @php
        $tab = ['' => ['label' => 'Semua', 'jumlah' => array_sum($jumlah)]];

        foreach ($label as $kunci => $teks) {
            $tab[$kunci] = [
                'label' => $teks,
                'jumlah' => $jumlah[$kunci] ?? 0,
                'badge' => match ($kunci) {
                    \App\Models\ObrolanBantuan::STATUS_TERBUKA => 'bg-amber-100 text-amber-700',
                    \App\Models\ObrolanBantuan::STATUS_DIJAWAB => 'bg-blue-100 text-blue-700',
                    default => 'bg-emerald-100 text-emerald-700',
                },
            ];
        }
    @endphp

    <x-tab-status :aksi="route('bantuan.index')" :terpilih="$status" :tab="$tab" />

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse ($obrolan as $item)
                @php $belum = $item->belumDibaca(auth()->user()); @endphp

                <a href="{{ route('bantuan.show', $item) }}"
                   class="flex items-start gap-4 px-6 py-4 hover:bg-slate-50/60 transition">
                    <div class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center text-xs font-bold
                                {{ $belum > 0 ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ strtoupper(substr($item->pelapor?->nama ?? '?', 0, 1)) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-800 truncate">{{ $item->judul }}</p>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->status_badge }}">
                                {{ $item->status_label }}
                            </span>
                            @if ($belum > 0)
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-teal-500 text-white">
                                    {{ $belum }} baru
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-slate-400 mt-1">
                            @if ($admin)
                                {{ $item->pelapor?->nama ?? '—' }} ·
                            @endif
                            {{ $item->pesan_count }} pesan ·
                            diperbarui {{ $item->updated_at?->diffForHumans() }}
                            @if ($item->usulan)
                                · <span class="font-mono">{{ $item->usulan->no_usulan }}</span>
                            @endif
                        </p>
                    </div>

                    <svg class="w-4 h-4 text-slate-300 shrink-0 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            @empty
                <div class="px-6 py-16 text-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                    </svg>
                    <p class="text-sm font-semibold text-slate-500">
                        {{ $status ? 'Tidak ada obrolan pada kelompok ini' : 'Belum ada laporan kendala' }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1.5 max-w-sm mx-auto leading-relaxed">
                        {{ $admin
                            ? 'Laporan dari pengguna akan muncul di sini begitu masuk.'
                            : 'Kalau menemui kendala saat memakai PANGI, laporkan lewat tombol di atas.' }}
                    </p>
                </div>
            @endforelse
        </div>

        @if ($obrolan->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">{{ $obrolan->links() }}</div>
        @endif
    </div>

</div>

@endsection
