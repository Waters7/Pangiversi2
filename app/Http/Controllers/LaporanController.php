<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use App\Models\Usulan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    /**
     * Pusat Laporan — list semua pejadin yang disetujui dengan ringkasan anggaran.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status_keuangan');
        $bulan = $request->input('bulan');

        $query = Usulan::with('user', 'kegiatan', 'keuangan.rincianBiaya')
            ->whereIn('status', ['disetujui', 'selesai']);

        // Search
        $query->when($search, function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhere('instansi', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
            });
        });

        // Filter status keuangan
        $query->when($status, function ($q) use ($status) {
            $q->whereHas('keuangan', fn ($q) => $q->where('status', $status));
        });

        // Filter bulan
        $query->when($bulan, function ($q) use ($bulan) {
            $q->whereRaw("strftime('%Y-%m', tanggal_mulai) = ?", [$bulan]);
        });

        $usulan = $query->latest()->paginate(10)->withQueryString();

        // Statistik keseluruhan
        $allApproved = Usulan::whereIn('status', ['disetujui', 'selesai'])->pluck('id');

        $stats = [
            'total_pejadin' => $allApproved->count(),
            'total_anggaran' => Keuangan::whereIn('id_usulan', $allApproved)->sum('total'),
            'total_terbayar' => Keuangan::whereIn('id_usulan', $allApproved)
                ->where('status', '!=', 'belum bayar')->sum('uang_muka')
                + Keuangan::whereIn('id_usulan', $allApproved)
                    ->where('status', 'lunas')->sum('sisa'),
            'lunas' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'lunas')->count(),
            'sebagian' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'bayar sebagian')->count(),
            'belum' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'belum bayar')->count(),
        ];

        return view('laporan.index', compact('usulan', 'stats', 'search', 'status', 'bulan'));
    }

    /**
     * Detail laporan per pejadin.
     */
    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');

        return view('laporan.show', compact('usulan'));
    }

    /**
     * Export laporan ke CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->input('search');
        $status = $request->input('status_keuangan');
        $bulan = $request->input('bulan');

        $query = Usulan::with('user', 'kegiatan', 'keuangan')
            ->whereIn('status', ['disetujui', 'selesai']);

        $query->when($search, function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($status, function ($q) use ($status) {
            $q->whereHas('keuangan', fn ($q) => $q->where('status', $status));
        });

        $query->when($bulan, function ($q) use ($bulan) {
            $q->whereRaw("strftime('%Y-%m', tanggal_mulai) = ?", [$bulan]);
        });

        $data = $query->latest()->get();

        $filename = 'laporan-perjadin-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Header
            fputcsv($handle, [
                'No',
                'No. Usulan',
                'Pengusul',
                'Kegiatan',
                'Tujuan',
                'Instansi',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Durasi (Hari)',
                'Total Estimasi (Rp)',
                'Uang Muka 80% (Rp)',
                'Sisa 20% (Rp)',
                'Status Pembayaran',
                'Tgl Transfer UM',
                'Tgl Pelunasan',
            ], ';');

            // Data rows
            foreach ($data as $i => $item) {
                $keuangan = $item->keuangan;
                fputcsv($handle, [
                    $i + 1,
                    $item->no_usulan,
                    $item->user?->name ?? '—',
                    $item->kegiatan?->nama ?? '—',
                    $item->lokasi,
                    $item->instansi,
                    date('d/m/Y', strtotime($item->tanggal_mulai)),
                    date('d/m/Y', strtotime($item->tanggal_selesai)),
                    $item->durasi,
                    $keuangan?->total ?? 0,
                    $keuangan?->uang_muka ?? 0,
                    $keuangan?->sisa ?? 0,
                    $keuangan?->status ?? 'belum bayar',
                    $keuangan?->tanggal_transfer?->format('d/m/Y') ?? '—',
                    $keuangan?->tanggal_pelunasan?->format('d/m/Y') ?? '—',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export detail rincian biaya per pejadin ke CSV.
     */
    public function exportDetail(Usulan $usulan): StreamedResponse
    {
        $usulan->load('user', 'kegiatan', 'keuangan.rincianBiaya');

        $filename = 'rincian-biaya-'.$usulan->no_usulan.'.csv';

        return response()->streamDownload(function () use ($usulan) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            // Info header
            fputcsv($handle, ['Laporan Rincian Biaya Perjalanan Dinas'], ';');
            fputcsv($handle, ['No. Usulan', $usulan->no_usulan], ';');
            fputcsv($handle, ['Pengusul', $usulan->user?->name ?? '—'], ';');
            fputcsv($handle, ['Tujuan', $usulan->lokasi.' — '.$usulan->instansi], ';');
            fputcsv($handle, ['Periode', $usulan->periode], ';');
            fputcsv($handle, [], ';');

            // Column headers
            fputcsv($handle, [
                'No',
                'Komponen Biaya',
                'Volume',
                'Satuan',
                'Harga Satuan (Rp)',
                'Jumlah (Rp)',
            ], ';');

            $keuangan = $usulan->keuangan;
            $rincian = $keuangan?->rincianBiaya ?? collect();

            foreach ($rincian as $i => $item) {
                fputcsv($handle, [
                    $i + 1,
                    $item->komponen,
                    $item->volume,
                    $item->satuan,
                    $item->harga_satuan,
                    $item->jumlah,
                ], ';');
            }

            fputcsv($handle, [], ';');
            fputcsv($handle, ['', '', '', '', 'Total Estimasi', $keuangan?->total ?? 0], ';');
            fputcsv($handle, ['', '', '', '', 'Uang Muka (80%)', $keuangan?->uang_muka ?? 0], ';');
            fputcsv($handle, ['', '', '', '', 'Sisa Bayar (20%)', $keuangan?->sisa ?? 0], ';');
            fputcsv($handle, ['', '', '', '', 'Status Pembayaran', ucfirst($keuangan?->status ?? 'belum bayar')], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
