<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Usulan Perjalanan Dinas - {{ $usulan->no_usulan }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; }

        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #0d9488; padding-bottom: 16px; }
        .header h1 { font-size: 16px; font-weight: bold; color: #0d9488; margin-bottom: 2px; }
        .header p { font-size: 10px; color: #64748b; }

        .section { margin-bottom: 20px; }
        .section-title {
            font-size: 12px; font-weight: bold; color: #0f766e;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 10px;
        }

        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 5px 8px; vertical-align: top; }
        .info-table .label { width: 180px; color: #64748b; font-weight: 600; }
        .info-table .value { color: #1e293b; }

        .detail-table { border: 1px solid #e2e8f0; }
        .detail-table th {
            background: #f1f5f9; color: #475569; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 8px; text-align: left; border-bottom: 1px solid #e2e8f0;
        }
        .detail-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; }

        .badge {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 10px; font-weight: bold;
        }
        .badge-draft { background: #f1f5f9; color: #475569; }
        .badge-diajukan { background: #dbeafe; color: #1e40af; }
        .badge-menunggu { background: #fef3c7; color: #92400e; }
        .badge-disetujui { background: #d1fae5; color: #065f46; }
        .badge-ditolak { background: #fee2e2; color: #991b1b; }
        .badge-selesai { background: #ede9fe; color: #5b21b6; }

        .catatan-box {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;
            padding: 10px 14px; margin-top: 10px;
        }
        .catatan-box .catatan-label { font-weight: bold; color: #991b1b; font-size: 10px; margin-bottom: 4px; }
        .catatan-box .catatan-text { color: #dc2626; }

        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <h1>USULAN PERJALANAN DINAS</h1>
        <p>Sistem Informasi Perjalanan Dinas — Poltekkes Kemenkes</p>
    </div>

    {{-- DATA PEMOHON --}}
    <div class="section">
        <div class="section-title">Data Pemohon</div>
        <table class="info-table">
            <tr>
                <td class="label">Nama Pegawai</td>
                <td class="value">{{ $usulan->user->nama }}</td>
            </tr>
            <tr>
                <td class="label">NIP</td>
                <td class="value">{{ $usulan->user->nip }}</td>
            </tr>
            <tr>
                <td class="label">Email</td>
                <td class="value">{{ $usulan->user->email }}</td>
            </tr>
        </table>
    </div>

    {{-- DATA PERJALANAN --}}
    <div class="section">
        <div class="section-title">Data Perjalanan Dinas</div>
        <table class="info-table">
            <tr>
                <td class="label">No. Usulan</td>
                <td class="value">{{ $usulan->no_usulan }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    <span class="badge badge-{{ $usulan->status }}">{{ $usulan->status_text }}</span>
                </td>
            </tr>
            <tr>
                <td class="label">Jenis Kegiatan</td>
                <td class="value">{{ $usulan->kegiatan?->nama ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dasar Penugasan</td>
                <td class="value">{{ $usulan->no_tugas }}</td>
            </tr>
            <tr>
                <td class="label">Lokasi / Kota Tujuan</td>
                <td class="value">{{ $usulan->lokasi }}</td>
            </tr>
            <tr>
                <td class="label">Instansi Tujuan</td>
                <td class="value">{{ $usulan->instansi }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Mulai</td>
                <td class="value">{{ $usulan->tanggal_mulai_formatted }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Selesai</td>
                <td class="value">{{ $usulan->tanggal_selesai_formatted }}</td>
            </tr>
            <tr>
                <td class="label">Durasi</td>
                <td class="value">{{ $usulan->durasi }} hari</td>
            </tr>
            @if($usulan->uraian)
            <tr>
                <td class="label">Uraian Tujuan</td>
                <td class="value">{{ $usulan->uraian }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- CATATAN PENOLAKAN --}}
    @if($usulan->status === 'ditolak' && $usulan->catatan)
    <div class="section">
        <div class="catatan-box">
            <div class="catatan-label">Catatan Penolakan dari PPK</div>
            <div class="catatan-text">{{ $usulan->catatan }}</div>
        </div>
    </div>
    @endif

    {{-- LAMPIRAN --}}
    @php $dokumen = $usulan->dokumen->last(); @endphp
    @if($dokumen)
    <div class="section">
        <div class="section-title">Lampiran Dokumen</div>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Jenis Dokumen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Surat Tugas</td>
                    <td>{{ $dokumen->surat_tugas ? '✓ Tersedia' : '— Tidak ada' }}</td>
                </tr>
                <tr>
                    <td>Rundown Kegiatan</td>
                    <td>{{ $dokumen->rundown ? '✓ Tersedia' : '— Tidak ada' }}</td>
                </tr>
                <tr>
                    <td>Dokumen Pendukung</td>
                    <td>{{ $dokumen->dokumen_pendukung ? '✓ Tersedia' : '— Tidak ada' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        Dokumen ini dicetak secara otomatis oleh sistem pada {{ now()->translatedFormat('d F Y, H:i') }} WIB
    </div>

</body>
</html>
