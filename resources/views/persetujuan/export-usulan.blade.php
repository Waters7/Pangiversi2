@php
    use App\Enums\StatusUsulan;
    use App\Models\Persetujuan;

    $tanggal = fn ($nilai, string $pola = 'd F Y') => $nilai
        ? \Illuminate\Support\Carbon::parse($nilai)->translatedFormat($pola)
        : '—';

    $dokumen = $usulan->dokumen->last();
    $status = StatusUsulan::dari($usulan->status);

    // Persetujuan PPK tercatat saat usulan diajukan bersama SPD bertanda
    // tangan; itulah dasar keputusannya.
    $persetujuanPpk = $usulan->persetujuan
        ->first(fn (Persetujuan $p) => $p->keputusan === Persetujuan::KEPUTUSAN_SETUJU);

    $lampiran = [
        ['Surat Tugas', $dokumen?->surat_tugas],
        ['Surat Perjalanan Dinas bertanda tangan', $dokumen?->spd_ditandatangani],
        ['Rundown / jadwal kegiatan', $dokumen?->rundown],
        ['Dokumen pendukung', $dokumen?->dokumen_pendukung],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Usulan Perjalanan Dinas — {{ $usulan->no_usulan }}</title>
    <style>
        /* Arial dipetakan dompdf ke Helvetica: font baku PDF, tanpa berkas
           font yang ikut ditanam, sama dengan dokumen cetak lainnya. */
        * { font-family: Arial, Helvetica, sans-serif; }
        @page { margin: 16mm 18mm 16mm; }
        body { font-size: 10.5pt; color: #000; margin: 0; line-height: 1.35; }

        .kop { text-align: center; margin-bottom: 3mm; }
        .kop img { width: 82%; height: auto; }

        h1 { font-size: 13pt; text-align: center; text-transform: uppercase; letter-spacing: .3px; margin: 0; }
        .nomor { text-align: center; font-size: 10pt; margin: 1mm 0 3mm; }

        h2 {
            font-size: 10.5pt; text-transform: uppercase; letter-spacing: .3px;
            margin: 4mm 0 1mm; padding-bottom: 0.8mm; border-bottom: 0.6pt solid #000;
        }

        table { width: 100%; border-collapse: collapse; }
        .isian td { padding: 0.9mm 0; vertical-align: top; }
        .isian td.label { width: 46mm; }
        .isian td.titik { width: 4mm; }

        table.lampiran th, table.lampiran td { border: 0.6pt solid #000; padding: 1.6mm 2.5mm; }
        table.lampiran th { font-size: 9.5pt; text-align: left; background: #f2f2f2; }
        .tengah { text-align: center; }

        .catatan { margin-top: 2mm; padding: 2.5mm 3mm; border: 0.6pt solid #000; }
        .catatan .judul { font-weight: bold; margin-bottom: 1mm; }

        .kaki { margin-top: 5mm; font-size: 8pt; color: #333; border-top: 0.5pt solid #999; padding-top: 2mm; }
    </style>
</head>
<body>

    <div class="kop">
        <img src="{{ public_path('images/kop-surat-poltekkes.jpg') }}" alt="Kop Poltekkes Kemenkes Manado">
    </div>

    <h1>Usulan Perjalanan Dinas</h1>
    <p class="nomor">Nomor {{ $usulan->no_usulan }}</p>

    <h2>Pelaksana</h2>
    <table class="isian">
        <tr><td class="label">Nama</td><td class="titik">:</td><td>{{ $usulan->user?->nama ?? '—' }}</td></tr>
        <tr><td class="label">NIP</td><td class="titik">:</td><td>{{ $usulan->user?->nip ?? '—' }}</td></tr>
        <tr><td class="label">Jabatan</td><td class="titik">:</td><td>{{ $usulan->user?->jabatan ?? '—' }}</td></tr>
        <tr><td class="label">Unit Kerja</td><td class="titik">:</td><td>{{ $usulan->user?->unit?->nama ?? '—' }}</td></tr>
        @if ($usulan->peserta->count() > 1)
            <tr>
                <td class="label">Peserta Lain</td><td class="titik">:</td>
                <td>{{ $usulan->peserta->where('id_user', '!=', $usulan->id_user)->pluck('nama')->join(', ') }}</td>
            </tr>
        @endif
    </table>

    <h2>Perjalanan Dinas</h2>
    <table class="isian">
        <tr><td class="label">Dasar Penugasan</td><td class="titik">:</td><td>Surat Tugas {{ $usulan->no_tugas ?? '—' }}</td></tr>
        <tr><td class="label">Nomor SPD</td><td class="titik">:</td><td>{{ $usulan->no_spd ?? '—' }}</td></tr>
        <tr><td class="label">Kategori</td><td class="titik">:</td><td>{{ $usulan->kategoriPerjadin?->nama ?? '—' }}</td></tr>
        <tr><td class="label">Jenis Kegiatan</td><td class="titik">:</td><td>{{ $usulan->kegiatan?->nama ?? '—' }}</td></tr>
        <tr><td class="label">Tujuan</td><td class="titik">:</td><td>{{ $usulan->instansi }}, {{ $usulan->lokasi }}</td></tr>
        <tr>
            <td class="label">Waktu Pelaksanaan</td><td class="titik">:</td>
            <td>{{ $tanggal($usulan->tanggal_mulai) }} s.d. {{ $tanggal($usulan->tanggal_selesai) }} ({{ $usulan->durasi }} hari)</td>
        </tr>
        <tr><td class="label">Tahun Anggaran</td><td class="titik">:</td><td>{{ $usulan->tahunAnggaran?->tahun ?? '—' }}</td></tr>
        <tr><td class="label">Maksud Perjalanan</td><td class="titik">:</td><td>{{ $usulan->uraian ?: '—' }}</td></tr>
        <tr><td class="label">Status Usulan</td><td class="titik">:</td><td>{{ $status->label() }}</td></tr>
        <tr><td class="label">Tanggal Dibuat</td><td class="titik">:</td><td>{{ $tanggal($usulan->created_at) }}</td></tr>
    </table>

    @if ($persetujuanPpk)
        <h2>Persetujuan</h2>
        <table class="isian">
            <tr><td class="label">Disetujui oleh</td><td class="titik">:</td><td>{{ $persetujuanPpk->approver?->nama ?? 'Pejabat Pembuat Komitmen' }}</td></tr>
            <tr><td class="label">Tanggal</td><td class="titik">:</td><td>{{ $tanggal($persetujuanPpk->waktu_keputusan, 'd F Y, H:i') }} WITA</td></tr>
            @if ($persetujuanPpk->catatan)
                <tr><td class="label">Keterangan</td><td class="titik">:</td><td>{{ $persetujuanPpk->catatan }}</td></tr>
            @endif
        </table>
    @endif

    @if ($status === StatusUsulan::Ditolak && $usulan->catatan)
        <div class="catatan">
            <div class="judul">Catatan penolakan PPK</div>
            {{ $usulan->catatan }}
        </div>
    @endif

    <h2>Lampiran</h2>
    <table class="lampiran">
        <thead>
            <tr>
                <th style="width: 10mm" class="tengah">No.</th>
                <th>Dokumen</th>
                <th style="width: 32mm" class="tengah">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lampiran as $i => [$nama, $ada])
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $nama }}</td>
                    <td class="tengah">{{ $ada ? 'Terlampir' : 'Tidak ada' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="kaki">
        Dicetak dari aplikasi PANGI pada {{ now()->translatedFormat('d F Y, H:i') }} WITA.
        Data pada dokumen ini mengikuti isian usulan saat dicetak.
    </p>

</body>
</html>
