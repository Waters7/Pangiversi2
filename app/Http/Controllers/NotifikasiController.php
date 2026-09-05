<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'semua');

        $notifikasi = $request->user()
            ->notifikasi()
            ->with('usulan')
            ->when($filter === 'belum', fn ($query) => $query->belumDibaca())
            ->paginate(15)
            ->withQueryString();

        $totalBelumDibaca = $request->user()->notifikasi()->belumDibaca()->count();

        return view('notifikasi.index', compact('notifikasi', 'totalBelumDibaca', 'filter'));
    }

    /**
     * Tandai satu notifikasi sebagai dibaca lalu arahkan ke halaman terkait.
     */
    public function baca(Request $request, Notifikasi $notifikasi): RedirectResponse
    {
        abort_if($notifikasi->id_user !== $request->user()->id, 403);

        $notifikasi->tandaiDibaca();

        return redirect($notifikasi->url ?? route('notifikasi.index'));
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $request->user()->notifikasi()->belumDibaca()->update(['dibaca_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, Notifikasi $notifikasi): RedirectResponse
    {
        abort_if($notifikasi->id_user !== $request->user()->id, 403);

        $notifikasi->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }
}
