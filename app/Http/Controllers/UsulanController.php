<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\Kegiatan;
use App\Models\Usulan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsulanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $usulan = Usulan::with('user', 'kegiatan')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('no_tugas', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhere('instansi', 'like', "%{$search}%")
                        ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $totalUsulan = Usulan::count();
        $menunggu = Usulan::whereIn('status', ['menunggu', 'diajukan'])->count();
        $disetujui = Usulan::where('status', 'disetujui')->count();
        $ditolak = Usulan::where('status', 'ditolak')->count();
        $selesai = Usulan::where('status', 'selesai')->count();
        $draft = Usulan::where('status', 'draft')->count();

        return view('usulan.list-usulan', compact(
            'usulan',
            'draft',
            'totalUsulan',
            'menunggu',
            'disetujui',
            'ditolak',
            'selesai'
        ));
    }

    public function create()
    {
        $kegiatan = Kegiatan::orderBy('nama')->get();

        return view('usulan.add-usulan', compact('kegiatan'));
    }

    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');

        return view('usulan.detail-usulan', compact('usulan'));
    }

    public function edit(Usulan $usulan)
    {
        if ($usulan->status !== 'draft') {
            return redirect()->route('usulan.show', $usulan)
                ->with('error', 'Usulan hanya dapat diedit selama masih berstatus draft.');
        }

        $kegiatan = Kegiatan::orderBy('nama')->get();
        $usulan->load('dokumen');

        return view('usulan.edit-usulan', compact('usulan', 'kegiatan'));
    }

    public function update(Request $request, Usulan $usulan)
    {
        if ($usulan->status !== 'draft') {
            return redirect()->route('usulan.show', $usulan)
                ->with('error', 'Usulan hanya dapat diedit selama masih berstatus draft.');
        }

        $request->validate([
            'id_kegiatan' => ['required', 'exists:kegiatan,id'],
            'no_tugas' => ['required', 'string', 'max:255'],
            'lokasi' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'uraian' => ['nullable', 'string'],
            'surat_tugas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'lampiran_tor' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'lampiran_lain' => ['nullable', 'array', 'max:3'],
            'lampiran_lain.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $isDraft = $request->input('action') === 'draft';

        $usulan->update([
            'no_tugas' => $request->no_tugas,
            'status' => $isDraft ? 'draft' : 'diajukan',
            'lokasi' => $request->lokasi,
            'instansi' => $request->instansi,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'uraian' => $request->uraian,
            'id_kegiatan' => $request->id_kegiatan,
        ]);

        $message = $isDraft ? 'Draft usulan berhasil diperbarui.' : 'Usulan berhasil diperbarui dan diajukan.';

        return redirect()->route('usulan.show', $usulan)->with('success', $message);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_kegiatan' => ['required', 'exists:kegiatan,id'],
            'no_tugas' => ['required', 'string', 'max:255'],
            'lokasi' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'uraian' => ['nullable', 'string'],
            'surat_tugas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'rundown' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'dokumen_pendukung' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $year = now()->year;
        $count = Usulan::whereYear('created_at', $year)->count() + 1;
        $noUsulan = sprintf('USL-%d-%03d', $year, $count);

        $isDraft = $request->input('action') === 'draft';

        $usulan = Usulan::create([
            'no_usulan' => $noUsulan,
            'no_tugas' => $request->no_tugas,
            'status' => $isDraft ? 'draft' : 'diajukan',
            'lokasi' => $request->lokasi,
            'instansi' => $request->instansi,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'uraian' => $request->uraian,
            'id_kegiatan' => $request->id_kegiatan,
            'id_user' => Auth::id(),
        ]);

        Dokumen::create([
            'id_usulan' => $usulan->id,
            'surat_tugas_path' => $request->file('surat_tugas')->store('dokumen', 'public'),
            'rundown_path' => $request->hasFile('rundown')
                                            ? $request->file('rundown')->store('dokumen', 'public')
                                            : null,
            'dokumen_pendukung_path' => $request->hasFile('dokumen_pendukung')
                                            ? $request->file('dokumen_pendukung')->store('dokumen', 'public')
                                            : null,
        ]);

        $message = $isDraft ? 'Draft usulan berhasil disimpan.' : 'Usulan berhasil diajukan.';

        return redirect()->route('usulan.list')->with('success', $message);
    }

    public function destroy(Usulan $usulan)
    {
        if (! in_array($usulan->status, ['draft'])) {
            return back()->with('error', 'Usulan hanya dapat dihapus jika berstatus draft atau diajukan.');
        }

        $noUsulan = $usulan->no_usulan;
        $usulan->delete();

        return redirect()->route('usulan.list')->with('success', "Usulan {$noUsulan} berhasil dihapus.");
    }
}
