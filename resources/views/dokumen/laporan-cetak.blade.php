<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Perjalanan Dinas — {{ $usulan->no_usulan }}</title>
    <style>
        @page { margin: 25mm 20mm; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #000;
            line-height: 1.4;
        }

        .judul {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            line-height: 1.35;
            margin-bottom: 18px;
        }

        table.identitas { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.identitas td { vertical-align: top; padding: 1px 0; font-size: 11pt; }
        table.identitas td.label { width: 33%; }
        table.identitas td.pemisah { width: 3%; }

        table.kegiatan { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.kegiatan th,
        table.kegiatan td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 10.5pt;
            vertical-align: top;
        }
        table.kegiatan th { text-align: center; font-weight: bold; }
        table.kegiatan td.nomor { text-align: center; width: 5%; }

        .bagian { margin-top: 14px; font-size: 11pt; }
        .bagian h3 { font-size: 11pt; margin: 0 0 4px; }
        .bagian p { margin: 0 0 6px; text-align: justify; }

        table.ttd { width: 100%; margin-top: 34px; }
        table.ttd td { width: 50%; text-align: center; font-size: 11pt; vertical-align: top; line-height: 1.35; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        /* QR tanda tangan: 26 mm, cukup dipindai dari kertas tanpa mendesak
           kolom nama di bawahnya. Kodenya ditulis juga sebagai teks untuk
           diketik ulang bila QR gagal dipindai. */
        /* Kotak tanda tangan bertinggi tetap: berisi QR bila sudah terbit,
           kosong bila belum — kedua kolom selalu sejajar. */
        .kotak-ttd { height: 30mm; margin: 6px 0 4px; }
        .qr { width: 26mm; height: 26mm; margin: 2mm auto 0; }
        .kode-verifikasi { font-family: monospace; font-size: 9pt; letter-spacing: .5px; }
        .catatan-qr { font-size: 8pt; color: #444; line-height: 1.35; margin: 2px 0 0; }
        .belum { font-style: italic; color: #777; font-size: 9pt; }

        .catatan-terbit {
            margin-top: 22px;
            font-size: 9pt;
            color: #444;
        }
    </style>
</head>
<body>

    <p class="judul">
        LAPORAN PERJALANAN DINAS<br>
        PEGAWAI POLTEKKES KEMENKES MANADO
    </p>

    <table class="identitas">
        <tr>
            <td class="label">Surat Tugas</td>
            <td class="pemisah">:</td>
            <td>{{ $usulan->no_tugas ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Nama</td>
            <td class="pemisah">:</td>
            <td>{{ $usulan->user?->nama ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">TMT</td>
            <td class="pemisah">:</td>
            <td>{{ $tmt }}</td>
        </tr>
        <tr>
            <td class="label">Maksud Perjalanan Dinas</td>
            <td class="pemisah">:</td>
            <td>{{ $maksud }}</td>
        </tr>
    </table>

    <div class="bagian">
        <h3>Dasar Pelaksanaan</h3>
        <p>{{ $laporan->dasarPelaksanaan() }}</p>
    </div>

    {{-- Kegiatan dan tindak lanjut disandingkan baris demi baris seperti pada
         format baku. Bila jumlahnya tidak sama, sisi yang lebih pendek
         dibiarkan kosong, bukan dipaksa berpasangan. --}}
    @php
        $kegiatan = $laporan->kegiatan->values();
        $tindak = $laporan->tindakLanjut->values();
        $baris = max($kegiatan->count(), $tindak->count());
    @endphp

    <table class="kegiatan">
        <thead>
            <tr>
                <th style="width:5%">NO</th>
                <th style="width:17%">TEMPAT KEGIATAN</th>
                <th style="width:17%">HARI, TANGGAL</th>
                <th style="width:30%">URAIAN KEGIATAN</th>
                <th style="width:31%">RENCANA TINDAK LANJUT</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $baris; $i++)
                <tr>
                    <td class="nomor">{{ $i + 1 }}</td>
                    {{-- Tempat per hari dari isian laporan; baris lama tanpa tempat
                         memakai instansi dan lokasi usulannya. --}}
                    <td>{{ $kegiatan[$i]->tempat ?? ($i === 0 ? $tempat : '') }}</td>
                    <td>{{ $kegiatan[$i]->tanggal?->translatedFormat('l, d F Y') ?? ($i === 0 ? $hariTanggal : '') }}</td>
                    <td>{{ $kegiatan[$i]->uraian ?? '' }}</td>
                    <td>
                        @if (isset($tindak[$i]))
                            {{ $tindak[$i]->uraian }}
                            @if ($tindak[$i]->penanggung_jawab || $tindak[$i]->target_selesai)
                                <br><span style="font-size:9.5pt">
                                    @if ($tindak[$i]->penanggung_jawab)
                                        PJ: {{ $tindak[$i]->penanggung_jawab }}
                                    @endif
                                    @if ($tindak[$i]->target_selesai)
                                        · Target {{ $tindak[$i]->target_selesai->translatedFormat('d F Y') }}
                                    @endif
                                </span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>

    @if ($laporan->statusHasil)
        <div class="bagian">
            <h3>Hasil yang Dicapai</h3>
            <p><strong>{{ $laporan->statusHasil->nama }}</strong>@if ($laporan->statusHasil->keterangan) — {{ $laporan->statusHasil->keterangan }}@endif</p>
        </div>
    @endif

    @if (filled($laporan->kesimpulan))
        <div class="bagian">
            <h3>Kesimpulan dan Saran</h3>
            <p>{{ $laporan->kesimpulan }}</p>
        </div>
    @endif

    {{-- Tanda tangan elektronik. QR pelaksana terbit saat laporan dikirim,
         QR Direktur saat dikonfirmasi; sebelum itu kotaknya dibiarkan kosong
         dengan tinggi yang sama supaya kedua kolom sejajar dan dokumen tetap
         dapat dicetak sebagai draf.

         Laporan hanya ditandatangani Direktur Poltekkes Kemenkes Manado —
         bukan wakil direktur — jadi label kolom kirinya tetap, dan nama yang
         tercetak adalah Direktur yang mengonfirmasinya. --}}
    @php
        $penandatangan = $laporan->pimpinan ?? $direktur;
        $tanggalPelaksana = $laporan->dikirim_at ?? $laporan->diselesaikan_at;
    @endphp

    <table class="ttd">
        <tr>
            <td>
                Mengetahui,<br>
                Direktur Poltekkes Kemenkes Manado
                <div class="kotak-ttd">
                    @if ($laporan->sudahDikonfirmasi() && $qrPimpinan)
                        <img src="{{ $qrPimpinan }}" alt="QR tanda tangan Direktur" class="qr">
                    @endif
                </div>
                <span class="nama-ttd">{{ $penandatangan?->nama ?? '…………………………………………' }}</span><br>
                NIP {{ $penandatangan?->nip ?? '…………………………………' }}
                @if ($laporan->sudahDikonfirmasi())
                    <br><span class="kode-verifikasi">{{ $laporan->kode_pimpinan }}</span>
                    <p class="catatan-qr">
                        Ditandatangani secara elektronik pada
                        {{ $laporan->dikonfirmasi_at->translatedFormat('d F Y, H:i') }} WITA.
                    </p>
                @elseif ($laporan->sudahDikirim())
                    <p class="catatan-qr"><span class="belum">Menunggu konfirmasi Direktur</span></p>
                @endif
            </td>
            <td>
                {{ $tempatTandaTangan ?? 'Manado' }}, {{ $tanggalPelaksana?->translatedFormat('d F Y') ?? '……………………' }}<br>
                Yang membuat laporan
                <div class="kotak-ttd">
                    @if ($laporan->dikirim_at && $qrPelaksana)
                        <img src="{{ $qrPelaksana }}" alt="QR tanda tangan pelaksana" class="qr">
                    @endif
                </div>
                <span class="nama-ttd">{{ $usulan->user?->nama ?? '…………………………………………' }}</span><br>
                NIP {{ $usulan->user?->nip ?? '…………………………………' }}
                @if ($laporan->dikirim_at)
                    <br><span class="kode-verifikasi">{{ $laporan->kode_pelaksana }}</span>
                    <p class="catatan-qr">
                        Ditandatangani secara elektronik pada
                        {{ $laporan->dikirim_at->translatedFormat('d F Y, H:i') }} WITA.
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <p class="catatan-terbit">
        Dokumen ini terbit dari isian Laporan Perjalanan Dinas pada aplikasi PANGI,
        dinyatakan selesai {{ $laporan->diselesaikan_at?->translatedFormat('d F Y H:i') }} WITA.
        @if ($laporan->kode_pelaksana || $laporan->kode_pimpinan)
            Keabsahan tanda tangan elektronik dapat diperiksa dengan memindai QR
            atau mengetik kodenya pada halaman verifikasi PANGI.
        @endif
    </p>

</body>
</html>
