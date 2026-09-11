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

        /* Ruang tanda tangan bertinggi tetap: basah bagi KPPN, QR bagi PPK
           begitu daftarnya ditandatangani di aplikasi. */
        .ruang-ttd { height: 26mm; vertical-align: middle; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .qr { width: 22mm; height: 22mm; }
        .kode-qr { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; letter-spacing: .4px; }
        .catatan-qr { font-size: 7pt; color: #444; line-height: 1.3; margin: 1mm 0 0; }
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
            {{-- Lebar kolom dijumlahkan tepat 100%: dulu 115%, sehingga
                 kolom terakhir terdorong keluar tepi kertas. --}}
            <tr>
                <th rowspan="2" style="width:3%">NO.</th>
                <th rowspan="2" style="width:12%">NAMA</th>
                <th colspan="2" style="width:10%">TEMPAT</th>
                <th rowspan="2" style="width:8%">LAMANYA<br>PERJALANAN</th>
                <th rowspan="2" style="width:18%">MAKSUD PERJALANAN,<br>No. &amp; Tgl. SPPD / SURAT TUGAS</th>
                <th rowspan="2" style="width:7%">TIKET<br>(PP)</th>
                <th rowspan="2" style="width:6%">TRANSPORT</th>
                <th colspan="3" style="width:14%">UANG HARIAN / SAKU</th>
                <th colspan="3" style="width:14%">UANG PENGINAPAN</th>
                <th rowspan="2" style="width:8%">JUMLAH<br>PEMBAYARAN</th>
            </tr>
            <tr>
                <th style="width:5%">ASAL</th>
                <th style="width:5%">TUJUAN</th>
                <th style="width:3%">HARI</th>
                <th style="width:5.5%">BIAYA</th>
                <th style="width:5.5%">JUMLAH</th>
                <th style="width:3%">HARI</th>
                <th style="width:5.5%">BIAYA</th>
                <th style="width:5.5%">JUMLAH</th>
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
            {{-- KPPN menandatangani basah, jadi ruangnya dibiarkan kosong. --}}
            <td class="ruang-ttd"></td>
            <td class="ruang-ttd">
                @if ($qr)
                    <img src="{{ $qr }}" alt="QR verifikasi PPK" class="qr">
                @endif
            </td>
        </tr>
        <tr>
            <td class="nama-ttd">…………………………………..</td>
            <td class="nama-ttd">{{ $ppk?->nama ?? '…………………………………..' }}</td>
        </tr>
        <tr>
            <td>NIP. …………………………</td>
            <td>
                NIP. {{ $ppk?->nip ?? '…………………………' }}
                @if ($qr)
                    <br><span class="kode-qr">{{ $nominatif->kode_verifikasi }}</span>
                    <p class="catatan-qr">
                        Ditandatangani secara elektronik pada
                        {{ $nominatif->ditandatangani_at?->translatedFormat('d F Y, H:i') }} WITA.
                        Pindai QR untuk memeriksa keabsahan tanda tangan PPK.
                    </p>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
