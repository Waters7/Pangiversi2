<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Perjalanan Dinas — {{ $usulan->no_usulan }}</title>
    <style>
        @page { margin: 12mm 20mm 20mm; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.45;
        }

        .kop { text-align: center; margin-bottom: 4mm; }
        .kop img { width: 100%; height: auto; }

        .judul {
            text-align: center;
            font-weight: bold;
            font-size: 11.5pt;
            line-height: 1.4;
            margin: 0 0 14px;
        }
        .judul .tentang { display: block; margin-top: 8px; }

        h3 { font-size: 11pt; font-weight: bold; margin: 14px 0 4px; }
        /* Indentasi gantung: nomor di 22px, judul dan uraiannya rata di 42px. */
        .butir { margin: 8px 0 2px 42px; text-indent: -20px; }
        .isi { margin: 0 0 6px; text-align: justify; }
        .isi.menjorok { margin-left: 42px; }
        .rujukan { font-size: 9pt; font-style: italic; color: #333; }
        .isi.menjorok + .rujukan, .rujukan.menjorok { margin-left: 42px; }

        ol.tindak { margin: 2px 0 6px 18px; padding: 0; }
        ol.tindak li { margin-bottom: 2px; }
        .kecil { font-size: 9.5pt; }

        table.ttd { width: 100%; margin-top: 30px; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; font-size: 11pt; vertical-align: top; line-height: 1.35; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        /* Kotak tanda tangan bertinggi tetap: berisi QR bila sudah terbit,
           kosong bila belum — kedua kolom selalu sejajar. */
        .kotak-ttd { height: 30mm; margin: 6px 0 4px; }
        .qr { width: 26mm; height: 26mm; margin: 2mm auto 0; }
        .kode-verifikasi { font-family: monospace; font-size: 9pt; letter-spacing: .5px; }
        .catatan-qr { font-size: 8pt; color: #444; line-height: 1.35; margin: 2px 0 0; }
        .belum { font-style: italic; color: #777; font-size: 9pt; }

        .catatan-terbit { margin-top: 18px; font-size: 8.5pt; color: #444; }

        /* Lampiran: tiap foto dokumentasi utuh di satu halaman bersama judulnya. */
        .lampiran { page-break-before: always; }
        .lampiran h2 { text-align: center; font-size: 11.5pt; margin: 0 0 14px; }
        .foto { page-break-inside: avoid; text-align: center; margin-bottom: 16px; }
        .foto p { font-size: 10pt; font-weight: bold; margin: 0 0 6px; text-align: left; }
        .foto img { max-width: 100%; max-height: 105mm; }
    </style>
</head>
<body>

    <div class="kop">
        <img src="{{ public_path('images/kop-surat-poltekkes.jpg') }}" alt="Kop Poltekkes Kemenkes Manado">
    </div>

    <p class="judul">
        LAPORAN PERJALANAN DINAS
        <span class="tentang">TENTANG<br>{{ $laporan->tentang ?: $maksud }}</span>
    </p>

    @php
        // Nomor lampiran tiap bagian yang berfoto, untuk rujukan di badan laporan.
        $nomorLampiran = collect($lampiran)->mapWithKeys(fn ($foto) => [$foto['bagian']->value => $foto['nomor']]);
        $teks = fn (\App\Enums\BagianLaporan $bagian) => nl2br(e($laporan->isiBagian($bagian) ?? '-'));
    @endphp

    <h3>A. Pendahuluan</h3>
    @foreach (array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => $bagian->pendahuluan())) as $bagian)
        <p class="butir">{{ $loop->iteration }}.&nbsp;&nbsp;{{ $bagian->judul() }}</p>
        <p class="isi menjorok">{!! $teks($bagian) !!}</p>
        @if ($nomorLampiran->has($bagian->value))
            <p class="rujukan menjorok">Dokumentasi: Lampiran {{ $nomorLampiran[$bagian->value] }}</p>
        @endif
    @endforeach

    @foreach (array_values(array_filter(\App\Enums\BagianLaporan::cases(), fn ($bagian) => ! $bagian->pendahuluan())) as $bagian)
        <h3>{{ $bagian->nomor() }}. {{ $bagian->judul() }}</h3>
        <p class="isi">{!! $teks($bagian) !!}</p>

        @if ($bagian === \App\Enums\BagianLaporan::Simpulan && $laporan->tindakLanjut->isNotEmpty())
            <p class="isi">Rencana tindak lanjut:</p>
            <ol class="tindak">
                @foreach ($laporan->tindakLanjut as $tindak)
                    <li>
                        {{ $tindak->uraian }}
                        @if ($tindak->penanggung_jawab || $tindak->target_selesai)
                            <span class="kecil">
                                ({{ collect([
                                    $tindak->penanggung_jawab ? 'PJ: '.$tindak->penanggung_jawab : null,
                                    $tindak->target_selesai ? 'target '.$tindak->target_selesai->translatedFormat('d F Y') : null,
                                ])->filter()->implode(', ') }})
                            </span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($nomorLampiran->has($bagian->value))
            <p class="rujukan">Dokumentasi: Lampiran {{ $nomorLampiran[$bagian->value] }}</p>
        @endif
    @endforeach

    {{-- Tanda tangan elektronik, sama dengan format lama: QR pelaksana terbit
         saat laporan dikirim, QR Direktur saat dikonfirmasi. --}}
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

    {{-- Lampiran: seluruh foto dokumentasi yang diunggah, urut menurut bagiannya. --}}
    @if ($lampiran !== [])
        <div class="lampiran">
            <h2>LAMPIRAN<br>DOKUMENTASI KEGIATAN</h2>
            @foreach ($lampiran as $foto)
                <div class="foto">
                    <p>Lampiran {{ $foto['nomor'] }}: {{ $foto['bagian']->label() }}</p>
                    <img src="{{ $foto['gambar'] }}" alt="Dokumentasi {{ $foto['bagian']->judul() }}">
                </div>
            @endforeach
        </div>
    @endif

</body>
</html>
