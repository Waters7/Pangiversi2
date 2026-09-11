<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rincian Biaya Perjalanan Dinas — {{ $usulan->no_usulan }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 0; }
        .lampiran { text-align: right; font-size: 9px; line-height: 1.5; margin-bottom: 14px; }
        h1 { font-size: 13px; text-align: center; text-transform: uppercase; margin: 0 0 14px; letter-spacing: .5px; }
        .rujukan td { padding: 2px 0; font-size: 11px; }
        .rujukan td:first-child { width: 170px; }
        table.biaya { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.biaya th, table.biaya td { border: 1px solid #475569; padding: 5px 8px; }
        table.biaya th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; text-align: center; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .kategori td { font-weight: bold; }
        .sub td { padding-left: 22px; }
        .jumlah td { font-weight: bold; background: #f8fafc; }
        .subjumlah td { font-style: italic; color: #334155; background: #fafafa; }
        .kelompok td { background: #f8fafc; font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
        .terbilang { margin-top: 8px; font-style: italic; }
        table.ttd { width: 100%; margin-top: 22px; }
        table.ttd td { width: 50%; vertical-align: top; font-size: 11px; text-align: center; line-height: 1.5; }
        /* Kotak tanda tangan bertinggi tetap: QR di dalamnya bila sudah terbit,
           kosong bila belum — semua kolom tanda tangan sejajar. */
        .kotak-ttd { height: 26mm; margin: 4px 0 2px; }
        .nama { font-weight: bold; text-decoration: underline; }
        .rampung { margin-top: 26px; }
        .rampung h2 { font-size: 11px; text-transform: uppercase; margin: 0 0 8px; }
        .rampung td { padding: 3px 0; }
        .rampung td:first-child { width: 220px; }
        .qr { width: 78px; height: 78px; margin-top: 4px; }
        .kode-qr { font-family: DejaVu Sans Mono, monospace; font-size: 8px; letter-spacing: .4px; }
        .catatan-qr { font-size: 7.5px; color: #64748b; line-height: 1.35; margin: 2px 0 0; }
    </style>
</head>
<body>

    <div class="lampiran">
        Lampiran II :<br>
        Peraturan Menteri Keuangan RI<br>
        Nomor : 113/PMK.05/2012<br>
        Tanggal : 3 Juli 2012<br>
        Tentang Perjalanan Dinas Jabatan Dalam Negeri<br>
        Bagi Pejabat Negara, Pegawai Negeri dan Pegawai Tidak Tetap
    </div>

    <h1>Rincian Biaya Perjalanan Dinas</h1>

    <table class="rujukan">
        <tr><td>Lampiran SPPD Nomor</td><td>: {{ $usulan->no_tugas }}</td></tr>
        <tr><td>Tanggal</td><td>: {{ \Carbon\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d F Y') }}</td></tr>
        <tr><td>Nama Pelaksana</td><td>: {{ $peserta?->nama ?? $usulan->user?->nama }}</td></tr>
        <tr><td>NIP</td><td>: {{ $peserta?->nip ?? $usulan->user?->nip ?? '-' }}</td></tr>
    </table>

    <table class="biaya">
        <thead>
            <tr>
                <th style="width: 36px;">No</th>
                <th>Perincian Biaya</th>
                <th style="width: 120px;">Jumlah</th>
                <th style="width: 110px;">Ket.</th>
            </tr>
        </thead>
        <tbody>
            @php
                use App\Enums\KategoriBiaya;

                $nomor = 0;

                // Susunan resmi dokumen: transportasi (pesawat/kereta/bus), uang
                // harian, transportasi lokal, biaya akomodasi, lalu lainnya. Tiap
                // kelompok berjudul, dan transport lokal — yang datang dari daftar
                // riil, bukan dari baris rincian — disisipkan pada urutannya.
                $judulKelompok = [
                    KategoriBiaya::Transport->value => 'Transportasi (Pesawat / Kereta / Bus)',
                    KategoriBiaya::UangHarian->value => 'Uang Harian',
                    KategoriBiaya::TransportLokal->value => 'Transportasi Lokal',
                    KategoriBiaya::Penginapan->value => 'Biaya Akomodasi',
                    KategoriBiaya::Lainnya->value => 'Biaya Lainnya',
                ];

                $adaIsi = $rincianPerKategori->flatten(1)->isNotEmpty() || $transportLokal->isNotEmpty();
            @endphp

            @foreach (KategoriBiaya::urutanCetak() as $kategori)
                @php
                    $baris = $kategori === KategoriBiaya::TransportLokal
                        ? $transportLokal
                        : ($rincianPerKategori->get($kategori->value) ?? collect());
                @endphp

                @continue($baris->isEmpty())

                <tr class="kelompok">
                    <td></td>
                    <td colspan="3">{{ $judulKelompok[$kategori->value] }}</td>
                </tr>

                @foreach ($baris as $item)
                    @php $nomor++; @endphp

                    @if ($kategori === KategoriBiaya::TransportLokal)
                        {{-- Nominal dari nota pelaksana lewat Daftar Pengeluaran Riil,
                             dibayarkan terpisah saat pelunasan — tetapi bagian dari
                             biaya perjalanan, jadi tercantum di sini. --}}
                        <tr>
                            <td class="tengah">{{ $nomor }}</td>
                            <td>{{ $item->uraian }}</td>
                            <td class="kanan">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                            <td class="tengah">Riil</td>
                        </tr>
                    @else
                        <tr class="kategori">
                            <td class="tengah">{{ $nomor }}</td>
                            {{-- Baris lama masih bernama "Biaya Hotel"; dokumen menyebutnya
                                 Uang Penginapan tanpa menulis ulang datanya. --}}
                            <td>{{ preg_replace('/^Biaya Hotel\b/', 'Uang Penginapan', $item->komponen) }}</td>
                            <td class="kanan">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                            <td class="tengah">{{ $item->keterangan && $item->keterangan !== $item->komponen ? $item->keterangan : '' }}</td>
                        </tr>

                        @if ($item->volume > 1)
                            <tr class="sub">
                                <td></td>
                                <td>{{ $item->volume }} {{ $item->satuan }} × Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                    @endif
                @endforeach

                @if ($kategori === KategoriBiaya::TransportLokal)
                    <tr class="subjumlah">
                        <td></td>
                        <td>Subtotal transportasi lokal (Daftar Pengeluaran Riil)</td>
                        <td class="kanan">Rp {{ number_format($totalTransportLokal, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                @endif
            @endforeach

            @unless ($adaIsi)
                <tr>
                    <td class="tengah" colspan="4">Belum ada rincian biaya tercatat</td>
                </tr>
            @endunless

            <tr class="jumlah">
                <td class="tengah" colspan="2">JUMLAH</td>
                <td class="kanan">Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <p class="terbilang">Terbilang : {{ ucfirst($terbilang) }}</p>

    {{-- Dua kolom tanda tangan bersusunan sama — tempat & tanggal, judul,
         kotak QR bertinggi tetap, jabatan, nama, NIP — supaya keduanya
         sejajar entah QR-nya sudah terbit atau belum. --}}
    @php
        $tanggalPelaksana ??= $daftarRiil?->rincian_disetujui_at ?? $daftarRiil?->disetujui_pegawai_at;
        $tanggalBendahara = $keuangan?->dikonfirmasi_bayar_at
            ?? ($keuangan?->tanggal_pelunasan ? \Carbon\Carbon::parse($keuangan->tanggal_pelunasan) : null);
    @endphp

    <table class="ttd">
        <tr>
            <td>
                Manado, {{ $tanggalBendahara?->translatedFormat('d F Y') ?? '……………………' }}<br>
                Telah dibayar sejumlah<br>
                Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}
                <div class="kotak-ttd">
                    @if ($qrBendahara)
                        {{-- Konfirmasi bendahara terbit setelah pembayaran lunas --}}
                        <img src="{{ $qrBendahara }}" alt="QR konfirmasi pembayaran" class="qr">
                    @endif
                </div>
                Bendahara Pengeluaran,<br>
                <span class="nama">{{ $bendahara?->nama ?? '……………………………' }}</span><br>
                NIP : {{ $bendahara?->nip ?? '…………………………' }}
                @if ($qrBendahara)
                    <br><span class="kode-qr">{{ $keuangan->kode_konfirmasi_bayar }}</span>
                    <p class="catatan-qr">
                        Pembayaran dikonfirmasi lunas pada
                        {{ $keuangan->dikonfirmasi_bayar_at?->translatedFormat('d F Y, H:i') }} WITA.<br>
                        Pindai QR untuk memeriksa nomor konfirmasi pembayaran.
                    </p>
                @endif
            </td>
            <td>
                Manado, {{ $tanggalPelaksana?->translatedFormat('d F Y') ?? '……………………' }}<br>
                Telah menerima jumlah uang<br>
                Sebesar Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}
                <div class="kotak-ttd">
                    @if ($qrPelaksana)
                        {{-- Kode yang sama dengan QR pelaksana pada daftar pengeluaran riil --}}
                        <img src="{{ $qrPelaksana }}" alt="QR konfirmasi pelaksana" class="qr">
                    @endif
                </div>
                Yang Menerima,<br>
                <span class="nama">{{ $peserta?->nama ?? $usulan->user?->nama }}</span><br>
                NIP : {{ $peserta?->nip ?? $usulan->user?->nip ?? '-' }}
                @if ($qrPelaksana)
                    <br><span class="kode-qr">{{ $daftarRiil->kode_konfirmasi }}</span>
                    <p class="catatan-qr">
                        Nominal dikonfirmasi pelaksana pada
                        {{ $daftarRiil->disetujui_pegawai_at?->translatedFormat('d F Y, H:i') }} WITA,
                        dan daftar pengeluaran riilnya sudah ditandatangani PPK.
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <div class="rampung">
        <h2>Perhitungan SPD Rampung</h2>
        <table>
            <tr><td>Ditetapkan sejumlah</td><td>: Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</td></tr>
            <tr><td>Yang telah dibayarkan semula</td><td>: Rp {{ number_format($dibayarkan, 0, ',', '.') }}</td></tr>
            <tr><td>Sisa kurang / lebih</td><td>: Rp {{ number_format($totalKeseluruhan - $dibayarkan, 0, ',', '.') }}</td></tr>
        </table>

        <table class="ttd">
            <tr>
                <td></td>
                <td>
                    {{-- Tanggal PPK menandatangani menurut sistem; sebelum itu
                         bertitik, bukan tanggal cetak yang bisa menyesatkan. --}}
                    Manado, {{ $daftarRiil?->ditandatangani_at?->translatedFormat('d F Y') ?? '……………………' }}<br>
                    Pejabat Pembuat Komitmen,
                    <div class="kotak-ttd">
                        @if ($qrPpk)
                            {{-- Kode yang sama dengan QR PPK pada daftar pengeluaran riil --}}
                            <img src="{{ $qrPpk }}" alt="QR verifikasi PPK" class="qr">
                        @endif
                    </div>
                    <span class="nama">{{ $daftarRiil?->ppk?->nama ?? $ppk?->nama ?? '……………………………' }}</span><br>
                    NIP : {{ $daftarRiil?->ppk?->nip ?? $ppk?->nip ?? '…………………………' }}
                    @if ($qrPpk)
                        <br><span class="kode-qr">{{ $daftarRiil->kode_verifikasi }}</span>
                        <p class="catatan-qr">
                            Ditandatangani secara elektronik pada
                            {{ $daftarRiil->ditandatangani_at?->translatedFormat('d F Y, H:i') }} WITA.<br>
                            Pindai QR untuk memeriksa keabsahan tanda tangan PPK.
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
