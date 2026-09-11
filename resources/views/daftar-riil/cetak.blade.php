<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Pengeluaran Riil — {{ $usulan->no_usulan }}</title>
    <style>
        /* Arial dipetakan dompdf ke Helvetica — seragam dengan dokumen lain. */
        * { font-family: Arial, Helvetica, sans-serif; }
        @page { margin: 14mm 18mm 14mm; }
        body { font-size: 10.5pt; color: #000; margin: 0; line-height: 1.35; }
        .kop { text-align: center; margin-bottom: 5mm; }
        .kop img { width: 82%; height: auto; }
        h2 { font-size: 13pt; text-align: center; text-transform: uppercase; margin: 0 0 1mm; letter-spacing: .3px; }
        .nomor { text-align: center; font-size: 9.5pt; margin: 0 0 4mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .identitas td { padding: 0.8mm 0; vertical-align: top; }
        .identitas td:first-child { width: 40mm; }
        .rincian th, .rincian td { border: 0.6pt solid #000; padding: 1.6mm 2.5mm; }
        .rincian th { background: #f2f2f2; font-size: 9.5pt; text-transform: uppercase; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .total td { font-weight: bold; background: #f2f2f2; }
        .pernyataan { margin: 3mm 0 4mm; line-height: 1.5; text-align: justify; }
        .ttd { width: 100%; margin-top: 6mm; page-break-inside: avoid; }
        .ttd td { width: 50%; vertical-align: top; text-align: center; font-size: 10.5pt; line-height: 1.4; }
        /* Kotak tanda tangan bertinggi tetap: QR di dalamnya bila sudah
           terbit, kosong bila belum — kedua kolom tetap sejajar. */
        .ruang-ttd { height: 24mm; margin: 1mm 0; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .belum { color: #555; font-style: italic; }
        .qr { width: 22mm; height: 22mm; }
        .kode-verifikasi { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; letter-spacing: .4px; }
        .catatan-qr { font-size: 7.5pt; color: #444; line-height: 1.3; margin: 1mm 0 0; }
    </style>
</head>
<body>

    <div class="kop">
        <img src="{{ public_path('images/kop-surat-poltekkes.jpg') }}" alt="Kop Poltekkes Kemenkes Manado">
    </div>

    <h2>Daftar Pengeluaran Riil</h2>
    <p class="nomor">Nomor Usulan: {{ $usulan->no_usulan }} &middot; Dasar Penugasan: {{ $usulan->no_tugas }}</p>

    <p class="pernyataan">
        Yang bertanda tangan di bawah ini menyatakan dengan sesungguhnya bahwa biaya perjalanan dinas
        di bawah ini benar-benar dikeluarkan untuk pelaksanaan perjalanan dinas dimaksud, dan apabila
        di kemudian hari terdapat kelebihan atas pembayaran tersebut, kami bersedia menyetorkan
        kelebihan tersebut ke Kas Negara.
    </p>

    <table class="identitas">
        <tr><td>Nama</td><td>: {{ $peserta->nama }}</td></tr>
        <tr><td>NIP</td><td>: {{ $peserta->nip ?? '-' }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $peserta->jabatan ?? '-' }}</td></tr>
        <tr><td>Peran dalam Tim</td><td>: {{ $peserta->peran_label }}</td></tr>
        <tr><td>Unit Kerja</td><td>: {{ $peserta->user?->unit?->nama ?? $usulan->user?->unit?->nama ?? '-' }}</td></tr>
        <tr><td>Tujuan Perjalanan</td><td>: {{ $usulan->lokasi }} ({{ $usulan->instansi }})</td></tr>
        <tr><td>Waktu Pelaksanaan</td><td>: {{ \Illuminate\Support\Carbon::parse($usulan->tanggal_mulai)->translatedFormat('d F Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($usulan->tanggal_selesai)->translatedFormat('d F Y') }} ({{ $usulan->durasi }} hari)</td></tr>
    </table>

    {{-- Hanya transport lokal. Komponen lain — tiket, uang harian, hotel —
         dipertanggungjawabkan pada dokumen rincian biaya (Lampiran II), dan
         tidak boleh ikut di sini agar nominalnya tidak terhitung dua kali. --}}
    <table class="rincian">
        <thead>
            <tr>
                <th style="width: 40px;">No.</th>
                <th>Rincian Biaya</th>
                <th style="width: 130px;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($daftar->rincian as $baris)
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td>{{ $baris->uraian }}</td>
                    <td class="kanan">{{ number_format($baris->nominal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="tengah">1</td>
                    <td>Transport Lokal</td>
                    <td class="kanan">{{ number_format($daftar->total_riil, 0, ',', '.') }}</td>
                </tr>
            @endforelse
            <tr class="total">
                <td class="tengah" colspan="2">Jumlah (Rp)</td>
                <td class="kanan">{{ number_format($daftar->total_riil, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if ($daftar->keterangan)
        <p><strong>Keterangan:</strong> {{ $daftar->keterangan }}</p>
    @endif

    <table class="ttd">
        <tr>
            <td>
                Manado, {{ $daftar->ditandatangani_at?->translatedFormat('d F Y') ?? '……………………' }}<br>
                Mengetahui/Menyetujui,<br>
                Pejabat Pembuat Komitmen
                @if ($daftar->sudah_ditandatangani)
                    <div class="ruang-ttd">
                        @if ($qr)
                            <img src="{{ $qr }}" alt="QR verifikasi" class="qr">
                        @endif
                    </div>
                    <span class="nama-ttd">{{ $daftar->ppk?->nama }}</span><br>
                    NIP. {{ $daftar->ppk?->nip ?? '-' }}<br>
                    <span class="kode-verifikasi">{{ $daftar->kode_verifikasi }}</span>
                    <p class="catatan-qr">
                        Ditandatangani secara elektronik pada
                        {{ $daftar->ditandatangani_at->translatedFormat('d F Y, H:i') }} WITA.<br>
                        Pindai QR untuk memeriksa keabsahan dokumen ini.
                    </p>
                @else
                    <div class="ruang-ttd"></div>
                    <span class="belum">Belum ditandatangani</span>
                @endif
            </td>
            <td>
                {{-- Tanggal pelaksana menyetujui menurut sistem; sebelum itu
                     bertitik, bukan tanggal cetak yang bisa menyesatkan. --}}
                Manado, {{ $daftar->disetujui_pegawai_at?->translatedFormat('d F Y') ?? '……………………' }}<br>
                <br>Yang Melakukan Perjalanan Dinas
                @if ($daftar->sudahDisetujuiPegawai())
                    <div class="ruang-ttd">
                        @if ($qrPelaksana)
                            <img src="{{ $qrPelaksana }}" alt="QR konfirmasi" class="qr">
                        @endif
                    </div>
                    <span class="nama-ttd">{{ $peserta->nama }}</span><br>
                    NIP. {{ $peserta->nip ?? '-' }}<br>
                    <span class="kode-verifikasi">{{ $daftar->kode_konfirmasi }}</span>
                    <p class="catatan-qr">
                        Nominal dikonfirmasi secara elektronik pada
                        {{ $daftar->disetujui_pegawai_at->translatedFormat('d F Y, H:i') }} WITA.<br>
                        Pindai QR untuk memeriksa nomor perjadin dan kode konfirmasinya.
                    </p>
                @else
                    <div class="ruang-ttd"></div>
                    <span class="nama-ttd">{{ $peserta->nama }}</span><br>
                    NIP. {{ $peserta->nip ?? '-' }}
                    @if ($daftar->sanggahKedaluwarsa())
                        <p class="catatan-qr">
                            Masa sanggah berakhir {{ $daftar->batas_sanggah->translatedFormat('d F Y') }}
                            tanpa tanggapan.
                        </p>
                    @endif
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
