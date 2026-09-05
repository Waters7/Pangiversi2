@php
    $rupiah = fn ($n) => number_format((float) $n, 0, ',', '.');
    $tahun = $nominatif->tanggal_tugas?->format('Y') ?? now()->format('Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Nominatif {{ $nominatif->no_tugas }}</title>
    <style>
        @page { margin: 12mm 10mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0;
        }

        .judul {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 2mm;
        }

        .subjudul {
            text-align: center;
            font-size: 9pt;
            margin-bottom: 1mm;
        }

        .keterangan {
            font-size: 8pt;
            margin-bottom: 3mm;
        }

        table.isi {
            width: 100%;
            border-collapse: collapse;
        }

        table.isi th,
        table.isi td {
            border: 0.5pt solid #000;
            padding: 1.2mm 1.5mm;
            vertical-align: middle;
        }

        table.isi th {
            font-size: 7.5pt;
            text-align: center;
            font-weight: bold;
        }

        td.kiri { text-align: left; }
        td.tengah { text-align: center; }
        /* Rentang tanggal menemani angka harinya tanpa melebarkan kolom. */
        .rentang { font-size: 6.5pt; color: #333; white-space: nowrap; }
        td.kanan { text-align: right; }

        tr.total td {
            font-weight: bold;
            background: #eee;
        }

        /* Blok tanda tangan disusun sebagai tabel tanpa garis: DomPDF tidak
           menopang flexbox, dan float mudah pecah antar halaman. */
        table.ttd {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8mm;
        }

        table.ttd td {
            border: none;
            font-size: 8.5pt;
            vertical-align: top;
            width: 50%;
        }

        /* Ruang tanda tangan basah. */
        .ruang-ttd { height: 28mm; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>

    <p class="judul">NOMINATIF PERJADIN POLTEKKES KEMENKES MANADO TA {{ $tahun }}</p>
    <p class="subjudul">Surat Tugas No. {{ $nominatif->no_tugas }}</p>

    <p class="keterangan">
        @if ($nominatif->kategoriPembiayaan)
            Kategori Pembiayaan : {{ $nominatif->kategoriPembiayaan->kode }} — {{ $nominatif->kategoriPembiayaan->nama }}
        @endif
        @if ($nominatif->kategoriPembiayaan && $nominatif->akunPembiayaan)
            &nbsp;&nbsp;|&nbsp;&nbsp;
        @endif
        @if ($nominatif->akunPembiayaan)
            Akun Pembiayaan : {{ $nominatif->akunPembiayaan->kode }} — {{ $nominatif->akunPembiayaan->nama }}
        @endif
    </p>

    <table class="isi">
        <thead>
            <tr>
                <th rowspan="2" style="width:4%">NO.</th>
                <th rowspan="2" style="width:14%">NAMA</th>
                <th colspan="2" style="width:11%">TEMPAT</th>
                <th rowspan="2" style="width:8%">LAMANYA<br>PERJALANAN</th>
                <th rowspan="2" style="width:20%">MAKSUD PERJALANAN,<br>No. &amp; Tgl. SPPD / SURAT TUGAS</th>
                <th rowspan="2" style="width:8%">TIKET<br>(PP)</th>
                <th rowspan="2" style="width:7%">TRANSPORT</th>
                <th colspan="3" style="width:17%">UANG HARIAN / SAKU</th>
                <th colspan="3" style="width:17%">UANG PENGINAPAN</th>
                <th rowspan="2" style="width:9%">JUMLAH<br>PEMBAYARAN</th>
            </tr>
            <tr>
                <th>ASAL</th>
                <th>TUJUAN</th>
                <th>HARI</th>
                <th>BIAYA</th>
                <th>JUMLAH</th>
                <th>HARI</th>
                <th>BIAYA</th>
                <th>JUMLAH</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($baris as $item)
                <tr>
                    <td class="tengah">{{ $item['nomor'] }}</td>
                    <td class="kiri">{{ $item['nama'] }}</td>
                    <td class="tengah">{{ $item['asal'] }}</td>
                    <td class="tengah">{{ $item['tujuan'] }}</td>
                    <td class="tengah">
                        {{ $item['lamanya'] }} hari
                        @if ($item['berangkat'] && $item['kembali'])
                            <br><span class="rentang">{{ $item['berangkat']->translatedFormat('d M Y') }}</span>
                            <br><span class="rentang">s.d. {{ $item['kembali']->translatedFormat('d M Y') }}</span>
                        @endif
                    </td>
                    <td class="kiri">
                        {{ $item['maksud'] }}<br>
                        No. SPPD {{ $item['no_sppd'] ?? '—' }}
                        @if ($item['tanggal_sppd'])
                            , {{ $item['tanggal_sppd']->translatedFormat('d F Y') }}
                        @endif
                        <br>Surat Tugas {{ $item['no_tugas'] ?? '—' }}
                    </td>
                    <td class="kanan">{{ $rupiah($item['tiket']) }}</td>
                    <td class="kanan">{{ $rupiah($item['transport']) }}</td>
                    <td class="tengah">{{ $item['harian_hari'] }}</td>
                    <td class="kanan">{{ $rupiah($item['harian_biaya']) }}</td>
                    <td class="kanan">{{ $rupiah($item['harian_jumlah']) }}</td>
                    <td class="tengah">{{ $item['inap_hari'] }}</td>
                    <td class="kanan">{{ $rupiah($item['inap_biaya']) }}</td>
                    <td class="kanan">{{ $rupiah($item['inap_jumlah']) }}</td>
                    <td class="kanan">{{ $rupiah($item['jumlah']) }}</td>
                </tr>
            @endforeach

            <tr class="total">
                <td colspan="6" class="tengah">T O T A L</td>
                <td class="kanan">{{ $rupiah($total['tiket']) }}</td>
                <td class="kanan">{{ $rupiah($total['transport']) }}</td>
                <td colspan="2"></td>
                <td class="kanan">{{ $rupiah($total['harian_jumlah']) }}</td>
                <td colspan="2"></td>
                <td class="kanan">{{ $rupiah($total['inap_jumlah']) }}</td>
                <td class="kanan">{{ $rupiah($total['jumlah']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>Disahkan Oleh :</td>
            <td>Manado, {{ ($nominatif->ditandatangani_at ?? now())->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td>Kepala Seksi Pencairan Dana I KPPN Manado,</td>
            <td>Pejabat Pembuat Komitmen</td>
        </tr>
        <tr>
            <td class="ruang-ttd"></td>
            {{-- Dibiarkan kosong: daftar nominatif ditandatangani basah,
                 jadi ruangnya harus tersedia di atas kertas. --}}
            <td class="ruang-ttd"></td>
        </tr>
        <tr>
            <td class="nama-ttd">…………………………………..</td>
            <td class="nama-ttd">{{ $ppk?->nama ?? '…………………………………..' }}</td>
        </tr>
        <tr>
            <td>NIP. …………………………</td>
            <td>NIP. {{ $ppk?->nip ?? '…………………………' }}</td>
        </tr>
    </table>

</body>
</html>
