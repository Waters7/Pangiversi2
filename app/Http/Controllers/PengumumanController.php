<?php

namespace App\Http\Controllers;

use App\Enums\Kemampuan;
use App\Models\AuditLog;
use App\Models\Pengumuman;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu Pengumuman: dibaca seluruh pengguna, dikelola tim keuangan dan super
 * administrator. Yang sedang tayang juga muncul pada popup saat masuk.
 */
class PengumumanController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): View
    {
        $kelola = $request->user()->punyaKemampuan(Kemampuan::MengelolaPengumuman);

        return view('pengumuman.index', [
            'kelola' => $kelola,
            'pengumuman' => Pengumuman::with('pembuat')
                ->when(! $kelola, fn ($query) => $query->tayang())
                ->latest('mulai_tayang')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('pengumuman.form', ['pengumuman' => new Pengumuman(['mulai_tayang' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pengumuman = Pengumuman::create($this->isian($request) + ['id_pembuat' => $request->user()->id]);

        $this->audit->catat(AuditLog::AKSI_DIBUAT, "Pengumuman \"{$pengumuman->judul}\" dibuat.", ['objek' => 'pengumuman']);

        return redirect()->route('pengumuman.index')->with('success', 'Pengumuman tersimpan.');
    }

    public function edit(Pengumuman $pengumuman): View
    {
        return view('pengumuman.form', ['pengumuman' => $pengumuman]);
    }

    public function update(Request $request, Pengumuman $pengumuman): RedirectResponse
    {
        $pengumuman->update($this->isian($request));

        $this->audit->catat(AuditLog::AKSI_DIPERBARUI, "Pengumuman \"{$pengumuman->judul}\" diubah.", ['objek' => 'pengumuman']);

        return redirect()->route('pengumuman.index')->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Pengumuman $pengumuman): RedirectResponse
    {
        $pengumuman->delete();

        $this->audit->catat(AuditLog::AKSI_DIHAPUS, "Pengumuman \"{$pengumuman->judul}\" dihapus.", ['objek' => 'pengumuman']);

        return redirect()->route('pengumuman.index')->with('success', 'Pengumuman dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function isian(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string', 'max:2000'],
            'mulai_tayang' => ['required', 'date'],
            'selesai_tayang' => ['nullable', 'date', 'after_or_equal:mulai_tayang'],
        ], [
            'selesai_tayang.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai tayang.',
        ]);
    }
}
