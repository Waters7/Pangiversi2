<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    /**
     * Display the master data overview.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $tab = $request->input('tab', 'usulan');

        // ── Stats ──
        $totalPegawai = User::count();
        $totalUsulan = Usulan::count();
        $totalKeuangan = Keuangan::count();

        $dokumenFields = ['surat_tugas', 'rundown', 'dokumen_pendukung', 'sppd', 'boarding_pass', 'faktur', 'kwintasi', 'bill_hotel', 'laporan_hasil'];
        $totalDokumen = 0;
        foreach ($dokumenFields as $field) {
            $totalDokumen += Dokumen::whereNotNull($field)->count();
        }

        // ── Pegawai ──
        $pegawaiQuery = User::withCount('usulan');
        if ($tab === 'pegawai' && $search) {
            $pegawaiQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $pegawai = $pegawaiQuery->latest()->paginate(10, ['*'], 'page')->appends($request->query());

        // ── Usulan Perdin ──
        $usulanQuery = Usulan::with(['user', 'kegiatan', 'keuangan']);
        if ($tab === 'usulan' && $search) {
            $usulanQuery->where(function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('kegiatan', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            });
        }
        if ($tab === 'usulan' && $statusFilter) {
            $usulanQuery->where('status', $statusFilter);
        }
        $usulanList = $usulanQuery->latest()->paginate(10, ['*'], 'page')->appends($request->query());

        // ── Keuangan ──
        $keuanganQuery = Keuangan::with(['usulan.user', 'usulan.kegiatan', 'rincianBiaya', 'dokumenKeuangan']);
        if ($tab === 'keuangan' && $search) {
            $keuanganQuery->whereHas('usulan', function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhereHas('kegiatan', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            });
        }
        if ($tab === 'keuangan' && $statusFilter) {
            $keuanganQuery->where('status', $statusFilter);
        }
        $keuanganList = $keuanganQuery->latest()->paginate(10, ['*'], 'page')->appends($request->query());

        // ── Dokumen ──
        $dokumenQuery = Dokumen::with(['usulan.user']);
        if ($tab === 'dokumen' && $search) {
            $dokumenQuery->whereHas('usulan', function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }
        $dokumenList = $dokumenQuery->latest()->paginate(10, ['*'], 'page')->appends($request->query());

        return view('master-data', compact(
            'totalPegawai',
            'totalUsulan',
            'totalKeuangan',
            'totalDokumen',
            'pegawai',
            'usulanList',
            'keuanganList',
            'dokumenList',
            'tab',
            'search',
            'statusFilter',
        ));
    }
}
