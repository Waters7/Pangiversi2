@php
    $keuangan = $usulan->keuangan;

    // Transport lokal punya menunya sendiri: ia dipertanggungjawabkan lewat
    // Daftar Pengeluaran Riil, bukan lewat rincian biaya ini.
    $rincian = ($keuangan->rincianBiaya ?? collect())
        ->reject(fn ($baris) => $baris->kategori === \App\Enums\KategoriBiaya::TransportLokal)
        ->values();
    $dokKeuangan = $keuangan->dokumenKeuangan;
    $dokumen = $usulan->dokumen->last();
    $isAdmin = auth()->user()->isAdmin();

    // Angka yang sudah ditandatangani tidak boleh bergeser: dokumen
    // tercetak dan daftar nominatif menumpang di atasnya.
    $alasanKunci = app(\App\Services\PenguncianBerkas::class)->rincianBiaya($usulan);

    // Input rincian biaya terbatas pada tim keuangan dan bendahara.
    // Menyusun angka dan menyatakannya benar adalah dua kewenangan berbeda.
    $bisaValidasi = auth()->user()->bisaMemvalidasiBiaya() && $alasanKunci === null;

    $canEditRincian = auth()->user()->bisaMengelolaBiaya()
        && $alasanKunci === null
        && ($keuangan->status === 'belum bayar' || $isAdmin);
@endphp

@if ($alasanKunci)
    <div class="mb-5 flex items-start gap-3 px-5 py-3.5 bg-amber-50 border border-amber-200 rounded-xl">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <div>
            <p class="text-sm font-bold text-amber-800">Rincian biaya terkunci</p>
            <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">{{ $alasanKunci }}</p>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    {{-- ═══════════ LEFT COLUMN ═══════════ --}}
    <div class="xl:col-span-2 space-y-5">

        {{-- ── RINCIAN BIAYA PERJALANAN DINAS ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Rincian Biaya Perjalanan</h3>
                        <p class="text-xs text-slate-400">Komponen biaya sesuai ketentuan SBM / at cost</p>
                    </div>
                </div>
                @if($canEditRincian)
                    <button type="button" onclick="document.getElementById('tambah-rincian').classList.toggle('hidden')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-semibold rounded-xl transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Komponen
                    </button>
                @endif
            </div>

            {{-- Form Tambah Komponen --}}
            @if($canEditRincian)
                <div id="tambah-rincian" class="hidden border-b border-slate-100 bg-teal-50/30 px-6 py-4">
                    <form action="{{ route('keuangan.rincian.store', $usulan->no_usulan) }}" method="POST"
                          x-data="{
                            standar: {{ Js::from(($komponenBiaya ?? collect())->mapWithKeys(fn ($k) => [$k->nama => ['satuan' => $k->satuan, 'harga_satuan' => (int) $k->harga_satuan]])) }},
                            terapkanStandar(nama) {
                                const acuan = this.standar[nama];
                                if (! acuan) return;
                                this.$refs.satuan.value = acuan.satuan;
                                this.$refs.harga.value = acuan.harga_satuan;
                            }
                          }">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori <span class="text-red-500">*</span></label>
                                <select name="kategori" required
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                                    @foreach (($kategoriBiaya ?? []) as $nilai => $label)
                                        <option value="{{ $nilai }}" {{ old('kategori') === $nilai ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Komponen Biaya <span class="text-red-500">*</span></label>
                                <input type="text" name="komponen" required placeholder="cth. Uang harian, Tiket pesawat"
                                       value="{{ old('komponen') }}" list="daftar-komponen"
                                       @change="terapkanStandar($event.target.value)"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                                <datalist id="daftar-komponen">
                                    @foreach (($komponenBiaya ?? collect()) as $k)
                                        <option value="{{ $k->nama }}">{{ $k->satuan }} — Rp {{ number_format($k->harga_satuan, 0, ',', '.') }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Vol. <span class="text-red-500">*</span></label>
                                <input type="number" name="volume" required min="1" value="{{ old('volume', 1) }}"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Satuan <span class="text-red-500">*</span></label>
                                <select name="satuan" required x-ref="satuan"
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent bg-white">
                                    @foreach(['OH','OK','OB','Tiket','Paket','Hari','Kali'] as $s)
                                        <option value="{{ $s }}" {{ old('satuan') === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Satuan (Rp) <span class="text-red-500">*</span></label>
                                <input type="number" name="harga_satuan" required min="0" value="{{ old('harga_satuan') }}" placeholder="500000" x-ref="harga"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            </div>
                            <div class="sm:col-span-1 flex items-end">
                                <button type="submit"
                                        class="w-full px-3 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-lg transition">
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        @if($errors->any())
                            <div class="mt-2 text-xs text-red-500">
                                @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif
                    </form>
                </div>
            @endif

            {{-- Tabel Rincian --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3 w-10">No</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3 w-36">Kategori</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Komponen Biaya</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-20">Vol.</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-20">Satuan</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Harga Satuan</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-36">Status</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-20">Validasi</th>
                            @if($canEditRincian)
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-24">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($rincian as $i => $item)
                            <tr class="hover:bg-slate-50/60 transition" id="row-{{ $item->id }}">
                                <td class="px-6 py-3 text-slate-500 font-medium display-cell">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 display-cell"><span class="inline-block text-xs font-bold px-2 py-0.5 rounded-full {{ $item->kategori->badge() }}">{{ $item->kategori->label() }}</span></td>
                                <td class="px-4 py-3 font-semibold text-slate-700 display-cell">
                                    {{ $item->komponen }}
                                    @if ($item->dariDokumen())
                                        <span class="block text-[11px] font-normal text-slate-400 mt-0.5">Nominal dari pelaksana</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-slate-600 display-cell">{{ $item->volume }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 display-cell">{{ $item->satuan }}</td>
                                <td class="px-4 py-3 text-right text-slate-600 display-cell">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800 display-cell">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>

                                {{-- Hanya nominal dari dokumen pelaksana yang perlu
                                     divalidasi; baris yang ditulis tim keuangan sendiri
                                     sudah menjadi tanggung jawabnya. --}}
                                <td class="px-4 py-3 text-center display-cell">
                                    @if (! $item->dariDokumen())
                                        <span class="text-[11px] text-slate-400">Ditulis tim keuangan</span>
                                    @elseif ($item->divalidasi_at)
                                        <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                            Sudah divalidasi
                                        </span>
                                    @else
                                        <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                                            Belum diperiksa
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center display-cell">
                                    {{-- Validasi menyatakan nominalnya sudah diperiksa dan
                                         menggerakkan berkas ke pelaksana, jadi ia ditanya ulang
                                         sekali sebelum tercatat. --}}
                                    @if ($item->dariDokumen() && $bisaValidasi)
                                        <x-konfirmasi-validasi
                                            :nama="'validasi-'.$item->id"
                                            :aksi="$item->divalidasi_at
                                                ? route('keuangan.rincian.batal-validasi', [$usulan->no_usulan, $item->id])
                                                : route('keuangan.rincian.validasi', [$usulan->no_usulan, $item->id])"
                                            :metode="$item->divalidasi_at ? 'DELETE' : 'PUT'"
                                            :tervalidasi="(bool) $item->divalidasi_at"
                                            :komponen="$item->komponen"
                                            :nominal="(float) $item->jumlah" />
                                    @endif
                                </td>

                                @if($canEditRincian)
                                    <td class="px-4 py-3 display-cell">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" onclick="toggleEdit({{ $item->id }})"
                                                    class="p-1.5 rounded-lg hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition" title="Edit">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <form action="{{ route('keuangan.rincian.destroy', [$usulan->no_usulan, $item->id]) }}" method="POST"
                                                  onsubmit="return confirm('Hapus komponen ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600 transition" title="Hapus">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>

                            {{-- Inline Edit Row --}}
                            @if($canEditRincian)
                                <tr id="edit-{{ $item->id }}" class="hidden bg-blue-50/40">
                                    <td colspan="10" class="px-6 py-3">
                                        <form action="{{ route('keuangan.rincian.update', [$usulan->no_usulan, $item->id]) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                                <div class="sm:col-span-3">
                                                    <select name="kategori" required
                                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white">
                                                        @foreach (($kategoriBiaya ?? []) as $nilai => $label)
                                                            <option value="{{ $nilai }}" {{ $item->kategori->value === $nilai ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-3">
                                                    <input type="text" name="komponen" required value="{{ $item->komponen }}"
                                                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                                                </div>
                                                <div class="sm:col-span-1">
                                                    <input type="number" name="volume" required min="1" value="{{ $item->volume }}"
                                                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <select name="satuan" required
                                                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white">
                                                        @foreach(['OH','OK','OB','Tiket','Paket','Hari','Kali'] as $s)
                                                            <option value="{{ $s }}" {{ $item->satuan === $s ? 'selected' : '' }}>{{ $s }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-1">
                                                    <input type="number" name="harga_satuan" required min="0" value="{{ (int) $item->harga_satuan }}"
                                                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                                                </div>
                                                <div class="sm:col-span-2 flex gap-2">
                                                    <button type="submit" class="flex-1 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded-lg transition">Simpan</button>
                                                    <button type="button" onclick="toggleEdit({{ $item->id }})" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-600 text-xs font-semibold rounded-lg transition">Batal</button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $canEditRincian ? 10 : 9 }}" class="px-6 py-8 text-center text-slate-400 text-sm">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    Belum ada rincian biaya. Klik <strong>Tambah Komponen</strong> untuk memulai.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if($rincian->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            {{-- Tiap baris ringkasan menyisakan satu sel untuk kolom
                                 Validasi, dan satu lagi untuk Aksi bila tampil. --}}
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-right text-sm font-bold text-slate-600">Total Estimasi</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</td>
                                <td></td>
                                <td></td>
                                @if($canEditRincian) <td></td> @endif
                            </tr>
                            <tr>
                                <td colspan="6" class="px-6 py-2 text-right text-sm font-semibold text-teal-700">Uang Muka</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                                <td></td>
                                <td></td>
                                @if($canEditRincian) <td></td> @endif
                            </tr>
                            <tr>
                                <td colspan="6" class="px-6 py-2 text-right text-sm font-semibold text-slate-500">Sisa Bayar</td>
                                <td class="px-4 py-2 text-right text-sm font-semibold text-slate-500">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                                <td></td>
                                <td></td>
                                @if($canEditRincian) <td></td> @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── TRANSPORT LOKAL ──
             Dipertanggungjawabkan lewat Daftar Pengeluaran Riil dan dibayar
             saat pelunasan, jadi bukan baris rincian di atas — tetapi ia bagian
             dari biaya perjalanan, sehingga dicantumkan di sini dan ikut
             tercetak pada dokumen rincian biaya. --}}
        @php
            $pesertaRiil = $usulan->peserta->firstWhere('id_user', $usulan->id_user) ?? $usulan->peserta->first();
            $riilTransport = $pesertaRiil ? $usulan->daftarRiil->firstWhere('id_peserta', $pesertaRiil->id) : null;
            $barisTransport = $riilTransport?->rincian ?? collect();
            $totalTransport = (float) ($riilTransport?->total_riil ?? $barisTransport->sum('nominal'));
        @endphp

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13v6h-2a2 2 0 01-4 0H9a2 2 0 01-4 0H3v-6z"/><path d="M3 13h18"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Transport Lokal</h3>
                        <p class="text-xs text-slate-400">
                            Dari nota pelaksana — dipertanggungjawabkan lewat Daftar Pengeluaran Riil, dibayar saat pelunasan
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($riilTransport)
                        <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $riilTransport->status_badge }}">
                            {{ $riilTransport->status_label }}
                        </span>
                    @endif
                    @can('mengelola-biaya')
                        <a href="{{ route('keuangan.transport-lokal') }}"
                           class="text-xs font-bold text-teal-600 hover:text-teal-700">Periksa Transport Lokal →</a>
                    @endcan
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="px-6 py-3 font-semibold w-10">No</th>
                            <th class="px-4 py-3 font-semibold">Ruas / Uraian</th>
                            <th class="px-4 py-3 font-semibold text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($barisTransport as $baris)
                            <tr>
                                <td class="px-6 py-3 text-xs text-slate-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $baris->uraian }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums">Rp {{ number_format($baris->nominal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-6 text-center text-xs text-slate-400">
                                    Pelaksana belum mengisi nota transportasi lokal.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($barisTransport->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td colspan="2" class="px-6 py-3 text-right text-sm font-bold text-slate-600">Total Transport Lokal</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800 tabular-nums">Rp {{ number_format($totalTransport, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="px-6 py-2 text-right text-sm font-bold text-indigo-800">Total Biaya Perjalanan (rincian + transport lokal)</td>
                                <td class="px-4 py-2 text-right text-sm font-black text-indigo-900 tabular-nums">Rp {{ number_format((float) $keuangan->total + $totalTransport, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── RIWAYAT PEMBAYARAN ── --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Riwayat Pembayaran</h3>
                    <p class="text-xs text-slate-400">Rekam jejak transaksi keuangan perjalanan</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-6 py-3">Jenis</th>
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Tanggal</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3">Bukti</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        {{-- Uang Muka --}}
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-medium text-slate-700">Uang Muka</td>
                            <td class="px-4 py-3.5 text-slate-600">
                                {{ $keuangan->tanggal_transfer ? $keuangan->tanggal_transfer->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-center">
                                @if($dokKeuangan && $dokKeuangan->transfer_uang_muka)
                                    <a href="{{ route('berkas.lihat', $dokKeuangan->transfer_uang_muka) }}" target="_blank"
                                       class="text-xs text-teal-600 font-semibold hover:underline">Lihat</a>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($keuangan->status !== 'belum bayar')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Dibayar
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Menunggu
                                    </span>
                                @endif
                            </td>
                        </tr>
                        {{-- Sisa Bayar --}}
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-3.5 font-medium text-slate-700">Sisa Bayar</td>
                            <td class="px-4 py-3.5 text-slate-600">
                                {{ $keuangan->tanggal_pelunasan ? $keuangan->tanggal_pelunasan->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-700">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-center">
                                @if($dokKeuangan && $dokKeuangan->transfer_sisa)
                                    <a href="{{ route('berkas.lihat', $dokKeuangan->transfer_sisa) }}" target="_blank"
                                       class="text-xs text-teal-600 font-semibold hover:underline">Lihat</a>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($keuangan->status === 'lunas')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-100 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> Dibayar
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 text-slate-500 text-xs font-semibold rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Menunggu LPJ
                                    </span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ═══════════ RIGHT COLUMN ═══════════ --}}
    <div class="space-y-5">

        {{-- Checklist LPJ --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Checklist Dokumen LPJ</h3>
                    <p class="text-xs text-slate-400">Kelengkapan dokumen pertanggungjawaban</p>
                </div>
            </div>

            @php
                // Satu definisi kelengkapan untuk seluruh aplikasi; lihat
                // PenagihDokumen. Layar dan penagihan tidak boleh berbeda.
                $checklistItems = app(\App\Services\PenagihDokumen::class)->checklist($usulan);
                $totalLPJ = count($checklistItems);
                $filledLPJ = collect($checklistItems)->where('terpenuhi', true)->count();
                $lpjComplete = $filledLPJ === $totalLPJ;
            @endphp

            <div class="space-y-2 mb-4">
                @foreach($checklistItems as $check)
                    <div class="flex items-center justify-between gap-3 py-2.5 px-3 rounded-xl {{ $check['terpenuhi'] ? 'bg-teal-50/60' : 'bg-amber-50/60' }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if($check['terpenuhi'])
                                <div class="w-5 h-5 rounded-full bg-teal-500 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @else
                                <div class="w-5 h-5 rounded-full bg-amber-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01"/></svg>
                                </div>
                            @endif
                            <span class="text-sm text-slate-700 font-medium min-w-0">
                                {{ $check['label'] }}
                                @if ($check['catatan'])
                                    <span class="block text-[11px] font-normal {{ $check['terpenuhi'] ? 'text-slate-400' : 'text-amber-700' }}">
                                        {{ $check['catatan'] }}
                                    </span>
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0 max-w-[55%]">
                            <span class="text-xs font-semibold {{ $check['terpenuhi'] ? 'text-teal-600' : 'text-amber-600' }}">
                                {{ $check['terpenuhi'] ? 'Lengkap' : 'Belum lengkap' }}
                            </span>

                            {{-- Satu baris bisa membawa beberapa berkas: tiket punya boarding
                                 pass dan invoice, nota punya bukti per ruas. --}}
                            @foreach ($check['berkas'] as $berkas)
                                <a href="{{ route('berkas.lihat', $berkas['path']) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 bg-white border border-slate-200 hover:bg-slate-50 hover:border-teal-300 text-slate-600 hover:text-teal-700 text-[11px] font-bold rounded-lg transition"
                                   title="Buka {{ $berkas['label'] }} di tab baru">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                    </svg>
                                    {{ count($check['berkas']) > 1 ? $berkas['label'] : 'Lihat' }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Cetak rincian biaya format PMK --}}
            <a href="{{ route('keuangan.cetak-rincian', $usulan->no_usulan) }}"
               class="w-full mb-3 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Cetak Rincian Biaya
            </a>

            {{-- Mengirim rincian biaya sekaligus daftar riilnya kepada
                 pelaksana, membuka masa sanggah. Tombolnya diletakkan di sini
                 karena inilah langkah berikutnya setelah nominal divalidasi. --}}
            @php
                $pesertaUtama = $usulan->peserta->firstWhere('id_user', $usulan->id_user)
                    ?? $usulan->peserta->first();
                $berkasRiil = $pesertaUtama
                    ? $usulan->daftarRiil->firstWhere('id_peserta', $pesertaUtama->id)
                    : null;
                $belumDivalidasi = $rincian->whereNull('divalidasi_at')
                    ->filter(fn ($b) => $b->dariDokumen())
                    ->count();

                // Alasan berkas belum bisa dikirim, dari aturan yang sama dengan
                // penyimpanannya — termasuk transport lokal yang belum divalidasi,
                // yang dulu tidak tampak di sini sehingga tombolnya terlihat siap
                // padahal kirimannya ditolak.
                $alasanTertahan = $berkasRiil
                    ? app(\App\Services\PengirimanBerkas::class)->alasanBelumSiap($usulan, $berkasRiil)
                    : null;
            @endphp

            @can('mengelola-biaya')
                @if ($pesertaUtama && ! ($berkasRiil?->sudah_ditandatangani))
                    @if ($berkasRiil?->sudahDikirimKePegawai())
                        <div class="w-full mb-3 px-4 py-2.5 bg-teal-50 border border-teal-100 rounded-xl text-center">
                            <p class="text-xs font-bold text-teal-800">Sudah dikirim ke pelaksana</p>
                            <p class="text-[11px] text-teal-700 mt-0.5">{{ $berkasRiil->status_label }}</p>
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('daftar-riil.kirim-pegawai', [$usulan->no_usulan, $pesertaUtama]) }}"
                              class="mb-3"
                              x-data
                              @submit.prevent="if (confirm('Kirim rincian biaya dan daftar pengeluaran riil ke {{ addslashes($pesertaUtama->nama) }}?')) $el.submit()">
                            @csrf @method('PUT')
                            <button type="submit" @disabled($alasanTertahan !== null)
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl transition
                                           {{ $alasanTertahan !== null
                                                ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                                : 'bg-teal-500 hover:bg-teal-600 text-white' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                                </svg>
                                Kirim ke Pelaksana
                            </button>
                        </form>

                        @if ($alasanTertahan !== null)
                            <p class="-mt-1 mb-3 text-[11px] text-amber-700 text-center">
                                {{ $alasanTertahan }}
                            </p>
                        @else
                            <p class="-mt-1 mb-3 text-[11px] text-slate-400 text-center">
                                Seluruh nominal sudah divalidasi. Berkas ini biasanya terkirim sendiri saat validasi terakhir dicatat.
                            </p>
                        @endif
                    @endif
                @endif
            @endcan

            {{-- Progress --}}
            <div class="bg-slate-50 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-semibold text-slate-600">Kelengkapan LPJ</span>
                    <span class="text-xs font-bold {{ $lpjComplete ? 'text-teal-600' : 'text-amber-600' }}">{{ $filledLPJ }}/{{ $totalLPJ }}</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all {{ $lpjComplete ? 'bg-teal-500' : 'bg-amber-400' }}"
                         style="width: {{ ($filledLPJ / $totalLPJ) * 100 }}%"></div>
                </div>
            </div>
        </div>

        {{-- Ringkasan Keuangan --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 text-sm mb-4">Ringkasan</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Total Estimasi</span>
                    <span class="text-sm font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Uang Muka</span>
                    <span class="text-sm font-semibold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Sisa Bayar</span>
                    <span class="text-sm font-semibold text-slate-600">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</span>
                </div>
                <hr class="border-slate-100">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Status</span>
                    @php
                        $badgeConfig = match($keuangan->status) {
                            'belum bayar'    => ['label' => 'Belum Bayar',    'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                            'bayar sebagian' => ['label' => 'Bayar Sebagian', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                            'lunas'          => ['label' => 'Lunas',          'class' => 'bg-teal-50 text-teal-700 border-teal-200'],
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $badgeConfig['class'] }}">
                        {{ $badgeConfig['label'] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- [Admin] Koreksi Status Keuangan --}}
        @if($isAdmin)
        <div class="bg-white rounded-2xl border border-orange-200 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <h3 class="font-bold text-slate-800 text-sm">Koreksi Status</h3>
                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">Admin</span>
            </div>
            <form action="{{ route('keuangan.koreksi-status', $usulan->no_usulan) }}" method="POST"
                  onsubmit="return confirm('Yakin ingin mengubah status keuangan?')">
                @csrf
                @method('PUT')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status Keuangan</label>
                        <select name="status_keuangan" required
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-white">
                            <option value="belum bayar" {{ $keuangan->status === 'belum bayar' ? 'selected' : '' }}>Belum Bayar</option>
                            <option value="bayar sebagian" {{ $keuangan->status === 'bayar sebagian' ? 'selected' : '' }}>Bayar Sebagian</option>
                            <option value="lunas" {{ $keuangan->status === 'lunas' ? 'selected' : '' }}>Lunas</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="w-full px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold rounded-lg transition">
                        Koreksi Status
                    </button>
                </div>
            </form>
        </div>
        @endif

    </div>

</div>

@push('scripts')
<script>
    function toggleEdit(id) {
        const row = document.getElementById('row-' + id);
        const editRow = document.getElementById('edit-' + id);
        const displays = row.querySelectorAll('.display-cell');

        if (editRow.classList.contains('hidden')) {
            editRow.classList.remove('hidden');
            displays.forEach(el => el.classList.add('hidden'));
        } else {
            editRow.classList.add('hidden');
            displays.forEach(el => el.classList.remove('hidden'));
        }
    }
</script>
@endpush
