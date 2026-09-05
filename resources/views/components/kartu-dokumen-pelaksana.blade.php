@props(['entri', 'jenis'])

@php
    /** @var \App\Models\DaftarRiil $item */
    $item = $entri['berkas'];
    $jalur = $entri['jalur'];
    $rincian = $entri['rincian'];
    $usulan = $item->usulan;
    $total = $jalur->total();
    $kotak = $jenis.'-setuju-'.$item->id;
@endphp

{{-- Satu berkas milik pelaksana: nominalnya, sikapnya, dan aksinya.
     Dipakai kedua submenu karena bentuk kartunya sama — yang berbeda
     hanya isi tabelnya, yang sudah disiapkan pengontrolnya. --}}
<div class="bg-white rounded-2xl border-2 {{ $jalur->masaSanggahBerjalan() ? 'border-amber-200' : 'border-slate-100' }} shadow-sm overflow-hidden mb-4"
     x-data="{ formSanggah: false }">

    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-slate-800">{{ $usulan?->no_usulan }}</p>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $usulan?->lokasi }}
                @if ($usulan?->tanggal_mulai)
                    · {{ \Carbon\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d M Y') }}
                    — {{ \Carbon\Carbon::parse($usulan->tanggal_selesai)->translatedFormat('d M Y') }}
                @endif
            </p>
        </div>
        <span class="self-start inline-block text-xs font-bold px-2.5 py-1 rounded-full {{ $jalur->statusBadge() }}">
            {{ $jalur->statusLabel() }}
        </span>
    </div>

    <div class="p-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-5">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $jalur->nama() }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">
                    Rp {{ number_format($total, 0, ',', '.') }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    @if ($jenis === \App\Services\JalurPersetujuan::RINCIAN)
                        Lampiran II · {{ $rincian->count() }} komponen, tanpa transport lokal
                    @else
                        Lampiran IX · {{ $rincian->count() }} baris transport lokal
                    @endif
                </p>
                @if ($jenis !== \App\Services\JalurPersetujuan::RINCIAN && $item->keterangan)
                    <p class="text-xs text-slate-500 mt-1">{{ $item->keterangan }}</p>
                @endif
            </div>

            @if ($jalur->masaSanggahBerjalan())
                <div class="text-left sm:text-right">
                    <p class="text-xs text-slate-400">Batas sanggah</p>
                    <p class="text-sm font-bold text-amber-700">
                        {{ $item->batas_sanggah->translatedFormat('d F Y') }}
                    </p>
                    <p class="text-xs text-slate-400 mt-0.5">sisa {{ $item->sisaHariSanggah() }} hari</p>
                </div>
            @endif
        </div>

        {{-- Isi dokumennya --}}
        @if ($rincian->isNotEmpty())
            <div class="mb-5 rounded-xl border border-slate-200 overflow-hidden">
                <p class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                    {{ $jalur->nama() }}
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-white text-slate-500 text-xs uppercase tracking-wide text-left border-b border-slate-100">
                                <th class="px-4 py-2 font-semibold w-10">No</th>
                                <th class="px-4 py-2 font-semibold">
                                    {{ $jenis === \App\Services\JalurPersetujuan::RINCIAN ? 'Perincian Biaya' : 'Uraian Transportasi' }}
                                </th>
                                <th class="px-4 py-2 font-semibold text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rincian as $i => $baris)
                                <tr>
                                    <td class="px-4 py-2.5 text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($jenis === \App\Services\JalurPersetujuan::RINCIAN)
                                            <p class="font-semibold text-slate-700">{{ $baris->komponen }}</p>
                                            @if ($baris->volume && $baris->satuan)
                                                <p class="text-xs text-slate-400 mt-0.5">
                                                    {{ $baris->volume }} {{ $baris->satuan }} ×
                                                    Rp {{ number_format($baris->harga_satuan, 0, ',', '.') }}
                                                </p>
                                            @endif
                                        @else
                                            <p class="font-semibold text-slate-700">{{ $baris->uraian }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-800">
                                        {{ number_format($jenis === \App\Services\JalurPersetujuan::RINCIAN ? $baris->jumlah : $baris->nominal, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                            <tr>
                                <td colspan="2" class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Jumlah</td>
                                <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ number_format($total, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Sanggahan yang sudah diajukan --}}
        @if ($jalur->sedangDisanggah())
            <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
                <p class="text-xs font-bold text-red-800 mb-1">Sanggahan Anda sedang ditinjau tim keuangan</p>
                <p class="text-xs text-red-700 leading-relaxed">{{ $jalur->sanggahan() }}</p>
                <p class="text-[11px] text-red-500 mt-1.5">
                    Diajukan {{ $jalur->waktu('disanggah')->translatedFormat('d M Y, H:i') }}
                </p>
            </div>
        @endif

        {{-- Aksi selama masa sanggah --}}
        @if ($jalur->masaSanggahBerjalan())
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="$dispatch('buka-{{ $kotak }}')"
                        class="px-5 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-teal-200">
                    Setuju &amp; Tandatangani
                </button>

                <button type="button" @click="formSanggah = !formSanggah"
                        class="px-5 py-2.5 bg-white border border-amber-200 hover:bg-amber-50 text-amber-700 text-sm font-semibold rounded-xl transition">
                    Sanggah Nominal
                </button>
            </div>

            <x-modal-konfirmasi
                :nama="$kotak"
                :judul="'Tandatangani '.$jalur->nama().' ini?'"
                :aksi="route('daftar-riil.setuju', [$usulan, $item->peserta, $jenis])"
                tombol="Ya, Tandatangani"
                warna="teal">
                <p>
                    Apakah Anda yakin ini sudah benar dikerjakan? Anda menandatangani
                    <strong class="text-slate-700">{{ $jalur->nama() }}</strong>
                    usulan <strong class="text-slate-700">{{ $usulan->no_usulan }}</strong>
                    senilai <strong class="text-slate-700">Rp {{ number_format($total, 0, ',', '.') }}</strong>.
                </p>
                <p class="text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                    Setelah ditandatangani, nominalnya tidak dapat disanggah lagi dan
                    dokumen ini diteruskan kepada PPK untuk diverifikasi.
                </p>
            </x-modal-konfirmasi>

            <div x-show="formSanggah" x-transition x-cloak class="mt-4 pt-4 border-t border-slate-100">
                <form method="POST" action="{{ route('daftar-riil.sanggah', [$usulan, $item->peserta, $jenis]) }}">
                    @csrf
                    @method('PUT')

                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Bagian mana yang tidak sesuai? <span class="text-red-500">*</span>
                    </label>
                    <textarea name="sanggahan" rows="3" required minlength="10"
                              placeholder="Contoh: uang harian dihitung 4 hari, sedangkan SPD menyebut 3 hari."
                              class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent transition {{ $errors->has('sanggahan') ? 'border-red-500' : 'border-slate-200' }}">{{ old('sanggahan') }}</textarea>
                    @error('sanggahan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                    <p class="mt-2 text-xs text-slate-500">
                        Sanggahan mengembalikan dokumen ini kepada tim keuangan untuk diperbaiki,
                        tanpa mengganggu dokumen satunya.
                    </p>

                    <div class="flex justify-end gap-2 mt-3">
                        <button type="button" @click="formSanggah = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition">
                            Kirim Sanggahan
                        </button>
                    </div>
                </form>
            </div>
        @elseif ($jalur->sudahDitandatangani())
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs text-slate-500">
                    Ditandatangani {{ $jalur->ppk()?->nama }} pada
                    {{ $jalur->waktu('ditandatangani')->translatedFormat('d M Y, H:i') }}
                </span>
                <a href="{{ $jenis === \App\Services\JalurPersetujuan::RINCIAN
                        ? route('keuangan.cetak-rincian', $usulan->no_usulan)
                        : route('daftar-riil.cetak', [$usulan, $item->peserta]) }}"
                   class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                    Unduh PDF
                </a>
            </div>
        @elseif ($jalur->sudahDisetujui())
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1">
                    <p class="text-xs text-slate-500">
                        Anda menyetujui pada {{ $jalur->waktu('disetujui')->translatedFormat('d M Y, H:i') }}.
                        Menunggu tanda tangan PPK.
                    </p>
                    @if ($jalur->kodeKonfirmasi())
                        <p class="text-[11px] text-slate-500 mt-1.5">
                            Kode konfirmasi tanda tangan Anda:
                            <span class="font-mono font-bold text-teal-700">{{ $jalur->kodeKonfirmasi() }}</span>
                        </p>
                    @endif
                </div>
                <a href="{{ $jenis === \App\Services\JalurPersetujuan::RINCIAN
                        ? route('keuangan.cetak-rincian', $usulan->no_usulan)
                        : route('daftar-riil.cetak', [$usulan, $item->peserta]) }}"
                   class="shrink-0 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition text-center">
                    Unduh PDF
                </a>
            </div>
        @elseif ($jalur->sanggahKedaluwarsa())
            <p class="text-xs text-slate-500">
                Masa sanggah berakhir {{ $item->batas_sanggah->translatedFormat('d F Y') }} tanpa tanggapan,
                sehingga nominal dianggap diterima.
            </p>
        @endif
    </div>
</div>
