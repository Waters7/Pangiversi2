<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rincian Biaya Perjalanan Dinas — {{ $usulan->no_usulan }}</title>
    <style>
        /* Arial dipetakan dompdf ke Helvetica: font baku PDF yang sama
           dengan SPD, jadi seluruh dokumen cetak seragam dan lebih ringkas
           daripada DejaVu Sans yang lebar. */
        * { font-family: Arial, Helvetica, sans-serif; }
        @page { margin: 14mm 16mm 12mm; }
        body { font-size: 10pt; color: #000; margin: 0; line-height: 1.3; }
        .lampiran { text-align: right; font-size: 8pt; line-height: 1.4; margin-bottom: 3mm; }
        h1 { font-size: 12.5pt; text-align: center; text-transform: uppercase; margin: 0 0 3mm; letter-spacing: .4px; }
        .rujukan td { padding: 0.6mm 0; font-size: 10pt; }
        .rujukan td:first-child { width: 42mm; }
        table.biaya { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        table.biaya th, table.biaya td { border: 0.6pt solid #000; padding: 1.1mm 2mm; }
        table.biaya td.ket { font-size: 8.5pt; }
        table.biaya th { background: #f2f2f2; font-size: 9pt; text-transform: uppercase; text-align: center; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .kategori td { font-weight: bold; }
        .sub td { padding-left: 6mm; }
        .jumlah td { font-weight: bold; background: #f2f2f2; }
        .subjumlah td { font-style: italic; color: #333; }
        .kelompok td { background: #f7f7f7; font-weight: bold; font-size: 9pt; text-transform: uppercase; letter-spacing: .3px; }
        .terbilang { margin: 2.5mm 0 0; font-style: italic; }

        /* Blok tanda tangan disusun sebagai tabel tanpa garis dan tidak boleh
           terpotong halaman: satu halaman untuk seluruh dokumen. */
        table.ttd { width: 100%; margin-top: 4mm; page-break-inside: avoid; }
        table.ttd td { width: 50%; vertical-align: top; font-size: 10pt; text-align: center; line-height: 1.4; }
        /* Kotak tanda tangan bertinggi tetap: QR di dalamnya bila sudah terbit,
           kosong bila belum — semua kolom tanda tangan sejajar. */
        .kotak-ttd { height: 21mm; margin: 1mm 0; }
        .nama { font-weight: bold; text-decoration: underline; }

        /* Perhitungan SPD rampung di kiri, PPK di kanan — seperti pada
           lembar Lampiran II aslinya, sekaligus menghemat tinggi halaman. */
        table.rampung { width: 100%; margin-top: 3mm; page-break-inside: avoid; }
        table.rampung td.kolom { width: 50%; vertical-align: top; font-size: 10pt; }
        table.rampung td.kolom-ppk { text-align: center; line-height: 1.4; }
        .rampung h2 { font-size: 10pt; text-transform: uppercase; margin: 0 0 2mm; }
        table.hitung td { padding: 0.8mm 0; font-size: 10pt; }
        table.hitung td:first-child { width: 48mm; }
        .qr { width: 20mm; height: 20mm; margin-top: 0.5mm; }
        .kode-qr { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; letter-spacing: .4px; }
        .catatan-qr { font-size: 7pt; color: #444; line-height: 1.3; margin: 1mm 0 0; }
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
                <th style="width: 9mm;">No</th>
                <th>Perincian Biaya</th>
                <th style="width: 32mm;">Jumlah</th>
                <th style="width: 36mm;">Ket.</th>
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
                            <td class="tengah ket">{{ $item->keterangan && $item->keterangan !== $item->komponen ? str_replace('→', '-', $item->keterangan) : '' }}</td>
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

    <table class="rampung">
        <tr>
            <td class="kolom">
                <div class="rampung">
                    <h2>Perhitungan SPD Rampung</h2>
                    <table class="hitung">
                        <tr><td>Ditetapkan sejumlah</td><td>: Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</td></tr>
                        <tr><td>Yang telah dibayarkan semula</td><td>: Rp {{ number_format($dibayarkan, 0, ',', '.') }}</td></tr>
                        <tr><td>Sisa kurang / lebih</td><td>: Rp {{ number_format($totalKeseluruhan - $dibayarkan, 0, ',', '.') }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="kolom kolom-ppk">
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

</body>
</html>
