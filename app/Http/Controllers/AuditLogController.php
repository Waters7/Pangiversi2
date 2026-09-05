<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $aksi = $request->input('aksi');
        $dari = $request->input('dari');
        $sampai = $request->input('sampai');

        $peran = $request->input('peran');

        // Peran dibaca dari akun pelakunya. Jejak tanpa pelaku — pekerjaan
        // terjadwal, misalnya — dikelompokkan tersendiri agar tidak hilang.
        $saringPeran = fn ($query) => $query->when(
            $peran,
            fn ($q) => $peran === 'sistem'
                ? $q->whereNull('id_user')
                : $q->whereHas('pelaku', fn ($p) => $p->where('role', $peran)),
        );

        $logs = AuditLog::with(['pelaku', 'usulan'])
            ->tap($saringPeran)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('deskripsi', 'like', "%{$search}%")
                        ->orWhere('catatan', 'like', "%{$search}%")
                        ->orWhereHas('pelaku', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('usulan', fn ($q) => $q->where('no_usulan', 'like', "%{$search}%"));
                });
            })
            ->when($aksi, fn ($query) => $query->where('aksi', $aksi))
            ->when($dari, fn ($query) => $query->whereDate('created_at', '>=', $dari))
            ->when($sampai, fn ($query) => $query->whereDate('created_at', '<=', $sampai))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totalLog = AuditLog::count();
        $logHariIni = AuditLog::whereDate('created_at', today())->count();
        $aksiOptions = AuditLog::aksiOptions();

        // Jumlah jejak per peran, untuk tab pengelompokan. Hanya peran yang
        // benar-benar meninggalkan jejak yang ditawarkan.
        $perPeran = AuditLog::query()
            ->leftJoin('users', 'users.id', '=', 'audit_logs.id_user')
            ->selectRaw('COALESCE(users.role, ?) as peran, count(*) as jumlah', ['sistem'])
            ->groupBy('peran')
            ->pluck('jumlah', 'peran')
            ->mapWithKeys(fn ($jumlah, $kunci) => [
                (string) $kunci => [
                    'label' => PeranPengguna::tryFrom((string) $kunci)?->label() ?? 'Sistem',
                    'jumlah' => (int) $jumlah,
                ],
            ])
            ->sortByDesc('jumlah');

        return view('audit-log.index', compact(
            'logs',
            'totalLog',
            'logHariIni',
            'aksiOptions',
            'search',
            'aksi',
            'peran',
            'perPeran',
            'dari',
            'sampai',
        ));
    }
}
