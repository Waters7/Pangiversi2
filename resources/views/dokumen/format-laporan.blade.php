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
        .kosong { height: 130px; }

        table.ttd { width: 100%; margin-top: 34px; }
        table.ttd td { width: 50%; text-align: center; font-size: 11pt; vertical-align: top; }
        .ruang-ttd { height: 62px; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        .petunjuk {
            margin-top: 26px;
            padding: 8px 10px;
            border: 1px dashed #888;
            font-size: 9pt;
            color: #444;
            line-height: 1.5;
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
            <td>{{ $usulan->no_tugas ?: '……………………………………………' }}</td>
        </tr>
        <tr>
            <td class="label">Nama</td>
            <td class="pemisah">:</td>
            <td>{{ $usulan->user?->nama ?? '……………………………………………' }}</td>
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
            <tr>
                <td class="nomor">1</td>
                <td>{{ $tempat }}</td>
                <td>{{ $hariTanggal }}</td>
                <td class="kosong"></td>
                <td></td>
            </tr>
            <tr>
                <td class="nomor">2</td>
                <td></td>
                <td></td>
                <td style="height:70px"></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>
                Mengetahui,<br>
                Direktur Poltekkes Kemenkes Manado
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $direktur?->nama ?? '…………………………………………' }}</span><br>
                NIP {{ $direktur?->nip ?? '…………………………………' }}
            </td>
            <td>
                Yang membuat
                <div class="ruang-ttd" style="height:82px"></div>
                <span class="nama-ttd">{{ $usulan->user?->nama ?? '…………………………………………' }}</span><br>
                NIP {{ $usulan->user?->nip ?? '…………………………………' }}
            </td>
        </tr>
    </table>

    <div class="petunjuk">
        <strong>Petunjuk pengisian.</strong>
        Kolom <em>Uraian Kegiatan</em> diisi rincian kegiatan yang benar-benar diikuti selama
        perjalanan dinas. Kolom <em>Rencana Tindak Lanjut</em> diisi langkah yang akan dikerjakan
        setelah kembali bertugas. Setelah ditandatangani, pindai berkas ini menjadi PDF lalu
        unggah pada menu <strong>Dokumen → Laporan Hasil Perjalanan</strong> aplikasi PANGI.
    </div>

</body>
</html>
