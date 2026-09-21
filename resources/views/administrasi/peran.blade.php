@extends('app')

@section('title', 'Peran & Hak Akses')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    @php
        $judulHalaman = 'Peran & Hak Akses';
        $subjudulHalaman = 'Tambah peran dan atur menu yang boleh dilihat, diubah, dan dihapus tiap peran';
    @endphp
    @include('administrasi.partials.kepala')

    @if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 bg-teal-50 border border-teal-100 rounded-xl"
         x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
        <svg class="w-5 h-5 text-teal-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-teal-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-teal-400 hover:text-teal-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    @if ($errors->any())
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
        <ul class="text-sm text-red-700 space-y-1">
            @foreach ($errors->all() as $error)
            <li class="flex items-start gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-5 items-start">

        {{-- ── Daftar peran + tambah peran ── --}}
        <div class="space-y-5">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">Daftar Peran</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $daftar->count() }} peran · pilih untuk mengatur hak aksesnya</p>
                </div>
                <div class="p-2 space-y-0.5">
                    @foreach ($daftar as $peran)
                        <a href="{{ route('administrasi.peran', ['peran' => $peran->kode]) }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition
                                  {{ $peran->is($terpilih) ? 'bg-teal-50 border border-teal-100' : 'hover:bg-slate-50 border border-transparent' }}">
                            <span class="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center text-xs font-bold
                                         {{ $peran->terkunci() ? 'bg-red-50 text-red-600' : ($peran->bawaan ? 'bg-slate-100 text-slate-600' : 'bg-violet-50 text-violet-600') }}">
                                {{ mb_strtoupper(mb_substr($peran->nama, 0, 1)) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-800 truncate">{{ $peran->nama }}</span>
                                <span class="block text-[11px] text-slate-400 truncate">
                                    <span class="font-mono">{{ $peran->kode }}</span>
                                    · {{ $peran->pengguna_count }} pengguna
                                    @unless ($peran->bawaan) · <span class="text-violet-600 font-semibold">buatan</span> @endunless
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-sm">Tambah Peran</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Peran baru mulai dengan hak mengajukan perjalanan dinas sendiri; selebihnya Anda atur.</p>
                </div>
                <form method="POST" action="{{ route('administrasi.peran.store') }}" class="p-5 space-y-3">
                    @csrf
                    <div>
                        <label for="nama_peran" class="block text-xs font-semibold text-slate-600 mb-1">Nama peran</label>
                        <input type="text" name="nama" id="nama_peran" required maxlength="100" value="{{ old('nama') }}"
                               placeholder="cth: Auditor Internal"
                               class="w-full px-3.5 py-2.5 rounded-xl text-sm border {{ $errors->has('nama') ? 'border-red-400' : 'border-slate-200' }} focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                        <p class="text-[11px] text-slate-400 mt-1">Kodenya dibuat otomatis dari nama, misalnya <span class="font-mono">auditor_internal</span>.</p>
                    </div>
                    <div>
                        <label for="keterangan_peran" class="block text-xs font-semibold text-slate-600 mb-1">Keterangan <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input type="text" name="keterangan" id="keterangan_peran" maxlength="255" value="{{ old('keterangan') }}"
                               placeholder="cth: Membaca laporan dan jejak audit"
                               class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                        Tambah Peran
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Matriks hak akses peran terpilih ── --}}
        @if ($terpilih)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" x-data="matriksHakAkses()">
            <form method="POST" action="{{ route('administrasi.peran.update', $terpilih) }}" id="formHakAkses">
                @csrf @method('PUT')

                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-800 text-sm">{{ $terpilih->nama }}</h3>
                            <span class="font-mono text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ $terpilih->kode }}</span>
                            @if ($terpilih->terkunci())
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-600 border border-red-100">Seluruh akses · tidak dapat diubah</span>
                            @elseif ($terpilih->bawaan)
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Peran bawaan</span>
                            @else
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-violet-50 text-violet-600 border border-violet-100">Peran buatan</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ $terpilih->pengguna_count }} pengguna memakai peran ini.
                            @if ($terpilih->terkunci())
                                Super Administrator selalu memegang seluruh hak akses supaya pengaturan ini tidak pernah terkunci dari dalam.
                            @else
                                Centang kemampuan pada kolom Lihat, Ubah, atau Hapus; perubahan berlaku begitu disimpan.
                            @endif
                        </p>
                    </div>

                    @unless ($terpilih->terkunci())
                        <div class="flex gap-2 shrink-0">
                            @unless ($terpilih->bawaan)
                                <button type="button" @click="$dispatch('buka-hapus-peran')"
                                        class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition">
                                    Hapus Peran
                                </button>
                            @endunless
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                                Simpan Hak Akses
                            </button>
                        </div>
                    @endunless
                </div>

                @unless ($terpilih->terkunci())
                <div class="px-6 py-4 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/60">
                    <div>
                        <label for="nama" class="block text-xs font-semibold text-slate-600 mb-1">Nama peran</label>
                        <input type="text" name="nama" id="nama" required maxlength="100" value="{{ old('nama', $terpilih->nama) }}"
                               class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-white border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                    <div>
                        <label for="keterangan" class="block text-xs font-semibold text-slate-600 mb-1">Keterangan</label>
                        <input type="text" name="keterangan" id="keterangan" maxlength="255" value="{{ old('keterangan', $terpilih->keterangan) }}"
                               class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-white border border-slate-200 focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                    </div>
                </div>
                @endunless

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="text-left px-6 py-3 font-semibold w-[38%]">Menu</th>
                                @foreach ($jenis as $j)
                                    <th class="text-left px-4 py-3 font-semibold">
                                        <span class="inline-flex items-center gap-1.5">
                                            @if ($j->value === 'lihat')
                                                <svg class="w-3.5 h-3.5 text-sky-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            @elseif ($j->value === 'ubah')
                                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            @else
                                                <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                                            @endif
                                            {{ $j->label() }}
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($menu as $m)
                                <tr class="align-top hover:bg-slate-50/60">
                                    <td class="px-6 py-3">
                                        <p class="font-semibold text-slate-800">{{ $m->label() }}</p>
                                        <p class="text-xs text-slate-400 leading-relaxed mt-0.5">{{ $m->keterangan() }}</p>
                                    </td>
                                    @foreach ($jenis as $j)
                                        <td class="px-4 py-3">
                                            @php $isiSel = $m->kemampuanJenis($j); @endphp
                                            @if ($isiSel === [])
                                                <span class="text-slate-300">—</span>
                                            @else
                                                <div class="space-y-1.5">
                                                    @foreach ($isiSel as $k)
                                                        <label class="flex items-start gap-2 cursor-pointer {{ $terpilih->terkunci() ? 'opacity-70 cursor-default' : '' }}">
                                                            <input type="checkbox" name="kemampuan[]" value="{{ $k->value }}"
                                                                   @checked($terpilih->punya($k)) @disabled($terpilih->terkunci())
                                                                   class="mt-0.5 w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-400">
                                                            <span class="text-xs text-slate-700 leading-snug">{{ $k->label() }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @unless ($terpilih->terkunci())
                <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/60">
                    <div class="flex gap-2 text-xs">
                        <button type="button" @click="centang(true)" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-semibold text-slate-600 hover:bg-slate-50 transition">Centang semua</button>
                        <button type="button" @click="centang(false)" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-semibold text-slate-600 hover:bg-slate-50 transition">Kosongkan</button>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold transition">
                        Simpan Hak Akses
                    </button>
                </div>
                @endunless
            </form>

            @unless ($terpilih->bawaan)
                <x-modal-konfirmasi
                    nama="hapus-peran"
                    judul="Hapus peran &quot;{{ $terpilih->nama }}&quot;?"
                    :aksi="route('administrasi.peran.destroy', $terpilih)"
                    metode="DELETE"
                    tombol="Ya, Hapus"
                    warna="red"
                    ikon="peringatan">
                    <p>Peran beserta seluruh hak aksesnya dihapus. Pengguna yang masih memakainya harus dipindahkan lebih dulu.</p>
                </x-modal-konfirmasi>
            @endunless
        </div>
        @endif
    </div>
</div>

<script>
    // Tombol bantu: centang atau kosongkan seluruh matriks sekaligus.
    function matriksHakAkses() {
        return {
            centang(nilai) {
                this.$root.querySelectorAll('input[name="kemampuan[]"]:not(:disabled)').forEach(el => el.checked = nilai);
            },
        };
    }
</script>
@endsection
