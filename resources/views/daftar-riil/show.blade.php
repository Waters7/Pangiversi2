@extends('app')

@section('title', 'Daftar Riil')

@section('content')

<div class="flex-1 px-4 md:px-8 py-7">

    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        {{-- Halaman ini dicapai dari dua arah: PPK lewat Persetujuan, Tim SDM
             lewat Laporan. Tombol kembali mengikuti hak akses pembacanya. --}}
        <a href="{{ auth()->user()->can('menandatangani-daftar-riil') ? route('persetujuan.daftar-riil') : route('laporan.daftar-riil') }}"
           class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Daftar Pengeluaran Riil</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $usulan->no_usulan }} · {{ $usulan->lokasi }}</p>
        </div>
    </div>

    <x-flash />

    {{-- Rincian biaya perjalanan dinas kini bermenu sendiri di Persetujuan:
         ia dokumen yang berbeda (Lampiran II), memuat seluruh komponen
         kecuali transport lokal. --}}
    <a href="{{ route('persetujuan.rincian-biaya') }}"
       class="flex items-center gap-2.5 mb-5 px-5 py-3 bg-white rounded-2xl border border-slate-100 shadow-sm hover:bg-slate-50 transition">
        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M12 7v10"/>
        </svg>
        <span class="text-xs text-slate-500">
            Komponen biaya lainnya ada pada
            <span class="font-semibold text-teal-600">Rincian Biaya Perjalanan Dinas</span> →
        </span>
    </a>

    <div class="space-y-4">
        @forelse ($daftar as $item)
            @php $peserta = $usulan->peserta->firstWhere('id', $item->id_peserta); @endphp

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">{{ $peserta?->nama }}</h3>
                        <p class="text-xs text-slate-400">
                            {{ $peserta?->nip ?? 'NIP tidak tercatat' }}
                            @if ($peserta?->jabatan) · {{ $peserta->jabatan }} @endif
                        </p>
                    </div>
                    <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full self-start {{ $item->status_badge }}">
                        {{ $item->status_label }}
                    </span>
                </div>

                <div class="p-6">
                    @if ($item->sudah_ditandatangani)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <p class="text-xs text-slate-400">Total Pengeluaran Riil</p>
                                <p class="text-lg font-bold text-slate-800">Rp {{ number_format($item->total_riil, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Ditandatangani Oleh</p>
                                <p class="text-sm font-semibold text-slate-700">{{ $item->ppk?->nama ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Waktu</p>
                                <p class="text-sm font-semibold text-slate-700">{{ $item->ditandatangani_at->translatedFormat('d M Y, H:i') }}</p>
                            </div>
                        </div>

                        @if ($item->keterangan)
                            <p class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 mb-4">
                                <span class="font-semibold">Keterangan:</span> {{ $item->keterangan }}
                            </p>
                        @endif
                    @else
                        {{-- Input nominal hanya untuk tim keuangan dan bendahara --}}
                        @can('mengelola-biaya')
                            {{-- Nominal kini dirinci baris demi baris seperti pada
                                 Lampiran IX; totalnya jumlah barisnya, bukan angka
                                 yang diketik terpisah. --}}
                            <div class="mb-4 rounded-xl border border-slate-200 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 border-b border-slate-200">
                                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                                            <th class="px-4 py-2.5 font-semibold w-12">No.</th>
                                            <th class="px-4 py-2.5 font-semibold">Rincian Biaya</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Jumlah (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($item->rincian as $baris)
                                            <tr>
                                                <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                                <td class="px-4 py-2.5 text-slate-700">
                                                    {{ $baris->uraian }}
                                                    @if ($baris->dariDokumen())
                                                        <span class="ml-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700">dari nota pelaksana</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ number_format($baris->nominal, 0, ',', '.') }}</td>

                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-4 py-6 text-center text-xs text-slate-400">
                                                    Belum ada rincian. Nota transportasi pelaksana masuk sendiri ke sini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="bg-slate-50 border-t border-slate-200">
                                        <tr>
                                            <td colspan="2" class="px-4 py-2.5 text-right text-xs font-bold text-slate-600">Jumlah</td>
                                            <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ number_format($item->total_riil, 0, ',', '.') }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>


                            <form method="POST" action="{{ route('daftar-riil.simpan', [$usulan->no_usulan, $peserta]) }}" class="mb-4">
                                @csrf
                                @method('PUT')
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Keterangan</label>
                                <div class="flex gap-3">
                                    <input type="text" name="keterangan" value="{{ $item->keterangan }}"
                                           placeholder="cth. transport lokal tanpa bukti kuitansi"
                                           class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 focus:border-transparent transition">
                                    <button type="submit"
                                            class="px-5 py-2.5 border border-slate-200 bg-white text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition shrink-0">
                                        Simpan
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="mb-4">
                                <p class="text-xs text-slate-400">Total Pengeluaran Riil</p>
                                <p class="text-lg font-bold text-slate-800">Rp {{ number_format($item->total_riil, 0, ',', '.') }}</p>
                            </div>
                        @endcan
                    @endif

                    {{-- Jejak persetujuan pelaksana --}}
                    @if ($item->exists && $item->sudahDikirimKePegawai())
                        <div class="mb-4 px-4 py-3 rounded-xl {{ $item->sedangDisanggah() ? 'bg-red-50 border border-red-100' : 'bg-slate-50 border border-slate-200' }}">
                            @if ($item->sedangDisanggah())
                                <p class="text-xs font-bold text-red-800 mb-1">
                                    Disanggah pelaksana pada {{ $item->disanggah_at->translatedFormat('d M Y, H:i') }}
                                </p>
                                <p class="text-xs text-red-700 leading-relaxed">{{ $item->sanggahan }}</p>
                                <p class="text-[11px] text-red-600 mt-2">
                                    Perbaiki nominalnya, lalu kirim ulang untuk diperiksa kembali.
                                </p>
                            @elseif ($item->sudahDisetujuiPegawai())
                                <p class="text-xs text-slate-600">
                                    Disetujui pelaksana pada {{ $item->disetujui_pegawai_at->translatedFormat('d M Y, H:i') }} —
                                    siap ditandatangani PPK.
                                </p>
                                @if ($item->kode_konfirmasi)
                                    <p class="text-[11px] text-slate-500 mt-1.5">
                                        Kode konfirmasi
                                        <span class="font-mono font-bold text-slate-700">{{ $item->kode_konfirmasi }}</span>
                                        — tercetak sebagai QR pada tanda tangan pelaksana.
                                    </p>
                                @endif
                            @elseif ($item->sanggahKedaluwarsa())
                                <p class="text-xs text-slate-600">
                                    Masa sanggah berakhir {{ $item->batas_sanggah->translatedFormat('d F Y') }} tanpa tanggapan.
                                    Nominal dianggap diterima.
                                </p>
                            @else
                                <p class="text-xs text-amber-800">
                                    Menunggu tanggapan pelaksana. Masa sanggah sampai
                                    <strong>{{ $item->batas_sanggah->translatedFormat('d F Y') }}</strong>
                                    (sisa {{ $item->sisaHariSanggah() }} hari).
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-100">
                        @if ($item->exists)
                            <a href="{{ route('daftar-riil.cetak', [$usulan->no_usulan, $peserta]) }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                </svg>
                                Cetak PDF
                            </a>
                        @endif

                        {{-- Mengirim ke pelaksana adalah kewenangan PPK: ia yang
                             menyetujui isinya sebelum ditandatangani. --}}
                        @can('menandatangani-daftar-riil')
                            @if ($item->exists && $item->total_riil > 0 && ! $item->sudah_ditandatangani && ! $item->sudahDisetujuiPegawai())
                                <form method="POST" action="{{ route('daftar-riil.kirim-pegawai', [$usulan->no_usulan, $peserta]) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-teal-200 hover:bg-teal-50 text-teal-700 text-xs font-bold rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                        </svg>
                                        {{ $item->sudahDikirimKePegawai() ? 'Kirim Ulang ke Pelaksana' : 'Kirim ke Pelaksana' }}
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @can('menandatangani-daftar-riil')
                            @php $kotakTtd = 'ttd-ppk-'.$peserta->id; @endphp

                            @if ($item->sudah_ditandatangani)
                                <button type="button" @click="$dispatch('buka-batal-{{ $kotakTtd }}')"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-amber-200 hover:bg-amber-50 text-amber-700 text-xs font-semibold rounded-lg transition">
                                    Batalkan Tanda Tangan
                                </button>

                                <x-modal-konfirmasi
                                    :nama="'batal-'.$kotakTtd"
                                    judul="Batalkan tanda tangan?"
                                    :aksi="route('daftar-riil.batal-tanda-tangan', [$usulan->no_usulan, $peserta])"
                                    metode="DELETE"
                                    tombol="Ya, Batalkan"
                                    warna="amber"
                                    ikon="peringatan">
                                    <p>
                                        Tanda tangan PPK atas daftar pengeluaran riil
                                        <strong class="text-slate-700">{{ $peserta->nama }}</strong> akan dicabut
                                        agar nominalnya dapat dikoreksi kembali.
                                    </p>
                                    <p class="text-xs bg-red-50 text-red-700 border border-red-100 rounded-lg px-3 py-2">
                                        Kode verifikasi <strong class="font-mono">{{ $item->kode_verifikasi }}</strong> ikut dicabut.
                                        Dokumen yang terlanjur dicetak tidak akan lagi tervalidasi saat QR-nya dipindai.
                                    </p>
                                </x-modal-konfirmasi>
                            @else
                                @if ($item->siapDitandatanganiPpk())
                                    <button type="button" @click="$dispatch('buka-{{ $kotakTtd }}')"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-xs font-bold rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Tandatangani
                                    </button>

                                    <x-modal-konfirmasi
                                        :nama="$kotakTtd"
                                        judul="Tandatangani sebagai PPK?"
                                        :aksi="route('daftar-riil.tanda-tangan', [$usulan->no_usulan, $peserta])"
                                        tombol="Ya, Tandatangani"
                                        warna="teal">
                                        <p>
                                            Daftar pengeluaran riil
                                            <strong class="text-slate-700">{{ $peserta->nama }}</strong> pada usulan
                                            <strong class="text-slate-700">{{ $usulan->no_usulan }}</strong> sebesar
                                            <strong class="text-slate-700">Rp {{ number_format($item->total_riil, 0, ',', '.') }}</strong>
                                            akan ditandatangani atas nama Anda.
                                        </p>
                                        <p class="text-xs bg-teal-50 text-teal-800 border border-teal-100 rounded-lg px-3 py-2">
                                            Sistem menerbitkan kode verifikasi beserta QR-nya, dan nominal terkunci
                                            dari perubahan. Tanda tangan masih dapat dibatalkan bila diperlukan.
                                        </p>
                                        @if ($item->sanggahKedaluwarsa())
                                            <p class="text-xs bg-amber-50 text-amber-800 border border-amber-100 rounded-lg px-3 py-2">
                                                Pelaksana tidak menanggapi sampai masa sanggah berakhir
                                                {{ $item->batas_sanggah->translatedFormat('d F Y') }}, sehingga
                                                nominalnya dianggap diterima.
                                            </p>
                                        @endif
                                    </x-modal-konfirmasi>
                                @else
                                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 text-slate-400 text-xs font-semibold rounded-lg cursor-not-allowed"
                                          title="{{ $item->sedangDisanggah()
                                                    ? 'Selesaikan sanggahan pelaksana lebih dulu'
                                                    : 'Menunggu persetujuan pelaksana atau berakhirnya masa sanggah' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                                        </svg>
                                        Belum dapat ditandatangani
                                    </span>
                                @endif
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-6 py-12 text-center">
                <p class="text-sm text-slate-400">Usulan ini belum memiliki peserta perjalanan.</p>
            </div>
        @endforelse
    </div>

</div>

@endsection
