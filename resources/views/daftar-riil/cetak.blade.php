<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Pengeluaran Riil — {{ $usulan->no_usulan }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1e293b; margin: 0; }
        .kop { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 18px; }
        .kop h1 { font-size: 14px; margin: 0 0 2px; text-transform: uppercase; }
        .kop p { margin: 0; font-size: 10px; color: #475569; }
        h2 { font-size: 13px; text-align: center; text-transform: uppercase; margin: 0 0 4px; }
        .nomor { text-align: center; font-size: 10px; color: #475569; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .identitas td { padding: 3px 0; vertical-align: top; }
        .identitas td:first-child { width: 150px; }
        .rincian th, .rincian td { border: 1px solid #94a3b8; padding: 6px 8px; }
        .rincian th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .total td { font-weight: bold; background: #f8fafc; }
        .pernyataan { margin: 14px 0; line-height: 1.6; text-align: justify; }
        .ttd { width: 100%; margin-top: 24px; }
        .ttd td { width: 50%; vertical-align: top; text-align: center; font-size: 11px; }
        .ruang-ttd { height: 60px; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .belum { color: #b45309; font-style: italic; }
        .qr { width: 90px; height: 90px; }
        .kode-verifikasi { font-family: DejaVu Sans Mono, monospace; font-size: 9px; letter-spacing: .5px; }
        .catatan-qr { font-size: 8px; color: #64748b; line-height: 1.4; margin-top: 3px; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>Politeknik Kesehatan Kemenkes Manado</h1>
        <p>Kementerian Kesehatan Republik Indonesia</p>
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
        <tr><td>Waktu Pelaksanaan</td><td>: {{ $usulan->periode }} ({{ $usulan->durasi }} hari)</td></tr>
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
                Mengetahui/Menyetujui,<br>
                Pejabat Pembuat Komitmen
                @if ($daftar->sudah_ditandatangani)
                    <div style="padding: 6px 0;">
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
                Manado, {{ now()->translatedFormat('d F Y') }}<br>
                Yang Melakukan Perjalanan Dinas
                @if ($daftar->sudahDisetujuiPegawai())
                    <div style="padding: 6px 0;">
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
