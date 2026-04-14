<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        $myUsulan = Usulan::where('id_user', $user->id);

        $totalUsulan = (clone $myUsulan)->count();
        $menunggu = (clone $myUsulan)->whereIn('status', ['menunggu', 'diajukan'])->count();
        $disetujui = (clone $myUsulan)->where('status', 'disetujui')->count();
        $ditolak = (clone $myUsulan)->where('status', 'ditolak')->count();
        $selesai = (clone $myUsulan)->where('status', 'selesai')->count();

        $recentUsulan = Usulan::with('kegiatan')
            ->where('id_user', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $draftCount = (clone $myUsulan)->where('status', 'draft')->count();

        return view('dashboard', compact(
            'user',
            'totalUsulan',
            'menunggu',
            'disetujui',
            'ditolak',
            'selesai',
            'draftCount',
            'recentUsulan',
        ));
    }
}
