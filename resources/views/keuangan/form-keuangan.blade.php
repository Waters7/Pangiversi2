@php
    $keuangan = $usulan->keuangan;
    $rincian = $keuangan->rincianBiaya ?? collect();
    $dokKeuangan = $keuangan->dokumenKeuangan;
    $dokumen = $usulan->dokumen->last();
@endphp

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
                @if($keuangan->status === 'belum bayar')
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
            @if($keuangan->status === 'belum bayar')
                <div id="tambah-rincian" class="hidden border-b border-slate-100 bg-teal-50/30 px-6 py-4">
                    <form action="{{ route('keuangan.rincian.store', $usulan->no_usulan) }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Komponen Biaya <span class="text-red-500">*</span></label>
                                <input type="text" name="komponen" required placeholder="cth. Uang harian, Tiket pesawat"
                                       value="{{ old('komponen') }}"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Volume <span class="text-red-500">*</span></label>
                                <input type="number" name="volume" required min="1" value="{{ old('volume', 1) }}"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Satuan <span class="text-red-500">*</span></label>
                                <select name="satuan" required
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent bg-white">
                                    @foreach(['OH','OK','OB','Tiket','Paket','Hari','Kali'] as $s)
                                        <option value="{{ $s }}" {{ old('satuan') === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Satuan (Rp) <span class="text-red-500">*</span></label>
                                <input type="number" name="harga_satuan" required min="0" value="{{ old('harga_satuan') }}" placeholder="500000"
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
                            <th class="text-left text-xs font-bold text-slate-500 uppercase px-4 py-3">Komponen Biaya</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-20">Vol.</th>
                            <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-20">Satuan</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Harga Satuan</th>
                            <th class="text-right text-xs font-bold text-slate-500 uppercase px-4 py-3">Jumlah</th>
                            @if($keuangan->status === 'belum bayar')
                                <th class="text-center text-xs font-bold text-slate-500 uppercase px-4 py-3 w-24">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($rincian as $i => $item)
                            <tr class="hover:bg-slate-50/60 transition" id="row-{{ $item->id }}">
                                <td class="px-6 py-3 text-slate-500 font-medium display-cell">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700 display-cell">{{ $item->komponen }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 display-cell">{{ $item->volume }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 display-cell">{{ $item->satuan }}</td>
                                <td class="px-4 py-3 text-right text-slate-600 display-cell">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800 display-cell">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                @if($keuangan->status === 'belum bayar')
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
                            @if($keuangan->status === 'belum bayar')
                                <tr id="edit-{{ $item->id }}" class="hidden bg-blue-50/40">
                                    <td colspan="7" class="px-6 py-3">
                                        <form action="{{ route('keuangan.rincian.update', [$usulan->no_usulan, $item->id]) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                                <div class="sm:col-span-4">
                                                    <input type="text" name="komponen" required value="{{ $item->komponen }}"
                                                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                                                </div>
                                                <div class="sm:col-span-2">
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
                                                <div class="sm:col-span-2">
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
                                <td colspan="{{ $keuangan->status === 'belum bayar' ? 7 : 6 }}" class="px-6 py-8 text-center text-slate-400 text-sm">
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
                            <tr>
                                <td colspan="{{ $keuangan->status === 'belum bayar' ? 5 : 4 }}" class="px-6 py-3 text-right text-sm font-bold text-slate-600">Total Estimasi</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">Rp {{ number_format($keuangan->total, 0, ',', '.') }}</td>
                                @if($keuangan->status === 'belum bayar') <td></td> @endif
                            </tr>
                            <tr>
                                <td colspan="{{ $keuangan->status === 'belum bayar' ? 5 : 4 }}" class="px-6 py-2 text-right text-sm font-semibold text-teal-700">Uang Muka (80%)</td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                                @if($keuangan->status === 'belum bayar') <td></td> @endif
                            </tr>
                            <tr>
                                <td colspan="{{ $keuangan->status === 'belum bayar' ? 5 : 4 }}" class="px-6 py-2 text-right text-sm font-semibold text-slate-500">Sisa Bayar (20%)</td>
                                <td class="px-4 py-2 text-right text-sm font-semibold text-slate-500">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                                @if($keuangan->status === 'belum bayar') <td></td> @endif
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
                            <td class="px-6 py-3.5 font-medium text-slate-700">Uang Muka (80%)</td>
                            <td class="px-4 py-3.5 text-slate-600">
                                {{ $keuangan->tanggal_transfer ? $keuangan->tanggal_transfer->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-center">
                                @if($dokKeuangan && $dokKeuangan->transfer_uang_muka)
                                    <a href="{{ asset('storage/' . $dokKeuangan->transfer_uang_muka) }}" target="_blank"
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
                            <td class="px-6 py-3.5 font-medium text-slate-700">Sisa Bayar (20%)</td>
                            <td class="px-4 py-3.5 text-slate-600">
                                {{ $keuangan->tanggal_pelunasan ? $keuangan->tanggal_pelunasan->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-700">Rp {{ number_format($keuangan->sisa, 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-center">
                                @if($dokKeuangan && $dokKeuangan->transfer_sisa)
                                    <a href="{{ asset('storage/' . $dokKeuangan->transfer_sisa) }}" target="_blank"
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
                $checklistItems = [
                    ['label' => 'Surat Tugas',       'field' => $dokumen?->surat_tugas],
                    ['label' => 'SPPD',              'field' => $dokumen?->sppd],
                    ['label' => 'Boarding Pass',      'field' => $dokumen?->boarding_pass],
                    ['label' => 'Faktur / Invoice',   'field' => $dokumen?->faktur],
                    ['label' => 'Bill Hotel',         'field' => $dokumen?->bill_hotel],
                    ['label' => 'Kwitansi',           'field' => $dokumen?->kwintasi],
                    ['label' => 'Laporan Hasil',      'field' => $dokumen?->laporan_hasil],
                ];
                $totalLPJ = count($checklistItems);
                $filledLPJ = collect($checklistItems)->filter(fn($i) => $i['field'])->count();
                $lpjComplete = $filledLPJ === $totalLPJ;
            @endphp

            <div class="space-y-2 mb-4">
                @foreach($checklistItems as $check)
                    <div class="flex items-center justify-between py-2.5 px-3 rounded-xl {{ $check['field'] ? 'bg-teal-50/60' : 'bg-amber-50/60' }}">
                        <div class="flex items-center gap-2.5">
                            @if($check['field'])
                                <div class="w-5 h-5 rounded-full bg-teal-500 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @else
                                <div class="w-5 h-5 rounded-full bg-amber-400 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01"/></svg>
                                </div>
                            @endif
                            <span class="text-sm text-slate-700 font-medium">{{ $check['label'] }}</span>
                        </div>
                        <span class="text-xs font-semibold {{ $check['field'] ? 'text-teal-600' : 'text-amber-600' }}">
                            {{ $check['field'] ? 'Lengkap' : 'Belum upload' }}
                        </span>
                    </div>
                @endforeach
            </div>

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
                    <span class="text-sm text-slate-500">Uang Muka (80%)</span>
                    <span class="text-sm font-semibold text-teal-700">Rp {{ number_format($keuangan->uang_muka, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Sisa Bayar (20%)</span>
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
