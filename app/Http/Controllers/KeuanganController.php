<?php

namespace App\Http\Controllers;

use App\Models\RincianBiaya;
use App\Models\Usulan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KeuanganController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $usulan = Usulan::with('user', 'kegiatan', 'keuangan')
            ->whereIn('status', ['disetujui', 'selesai'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('no_tugas', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhere('instansi', 'like', "%{$search}%")
                        ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('keuangan.keuangan', compact('usulan'));
    }

    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');

        if (! $usulan->keuangan) {
            $usulan->keuangan()->create([
                'total' => 0,
                'uang_muka' => 0,
                'sisa' => 0,
                'status' => 'belum bayar',
            ]);
            $usulan->load('keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');
        }

        if ($usulan->keuangan->status === 'lunas') {
            return view('keuangan.paid', compact('usulan'));
        }

        if ($usulan->keuangan->status === 'bayar sebagian') {
            return view('keuangan.partial-paid', compact('usulan'));
        }

        return view('keuangan.unpaid', compact('usulan'));
    }

    /**
     * Simpan rincian biaya baru.
     */
    public function storeRincian(Request $request, Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai', 403, 'Usulan sudah selesai.');

        $request->validate([
            'komponen' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);

        $keuangan = $usulan->keuangan;

        $keuangan->rincianBiaya()->create([
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
        ]);

        $keuangan->hitungTotal();

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil ditambahkan.');
    }

    /**
     * Update rincian biaya.
     */
    public function updateRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        abort_if($usulan->status === 'selesai', 403, 'Usulan sudah selesai.');

        $request->validate([
            'komponen' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);

        $rincian->update([
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
        ]);

        $usulan->keuangan->hitungTotal();

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil diperbarui.');
    }

    /**
     * Hapus rincian biaya.
     */
    public function destroyRincian(Usulan $usulan, RincianBiaya $rincian)
    {
        abort_if($usulan->status === 'selesai', 403, 'Usulan sudah selesai.');

        $rincian->delete();
        $usulan->keuangan->hitungTotal();

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil dihapus.');
    }

    /**
     * Konfirmasi pembayaran uang muka (belum bayar → bayar sebagian).
     */
    public function bayarUangMuka(Request $request, Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai', 403, 'Usulan sudah selesai.');

        $request->validate([
            'tanggal_transfer' => ['required', 'date'],
            'bukti_transfer' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $keuangan = $usulan->keuangan;
        $path = $request->file('bukti_transfer')->store('keuangan/uang-muka', 'public');

        $keuangan->update([
            'tanggal_transfer' => $request->tanggal_transfer,
            'status' => 'bayar sebagian',
        ]);

        $dokKeuangan = $keuangan->dokumenKeuangan;
        if ($dokKeuangan) {
            if ($dokKeuangan->transfer_uang_muka) {
                Storage::disk('public')->delete($dokKeuangan->transfer_uang_muka);
            }
            $dokKeuangan->update(['transfer_uang_muka' => $path]);
        } else {
            $keuangan->dokumenKeuangan()->create([
                'transfer_uang_muka' => $path,
                'transfer_sisa' => '',
            ]);
        }

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Pembayaran uang muka berhasil dikonfirmasi.');
    }

    /**
     * Konfirmasi pelunasan sisa (bayar sebagian → lunas).
     */
    public function bayarSisa(Request $request, Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai', 403, 'Usulan sudah selesai.');

        $request->validate([
            'tanggal_pelunasan' => ['required', 'date'],
            'bukti_pelunasan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $keuangan = $usulan->keuangan;
        $path = $request->file('bukti_pelunasan')->store('keuangan/pelunasan', 'public');

        $keuangan->update([
            'tanggal_pelunasan' => $request->tanggal_pelunasan,
            'status' => 'lunas',
        ]);

        $dokKeuangan = $keuangan->dokumenKeuangan;
        if ($dokKeuangan) {
            if ($dokKeuangan->transfer_sisa) {
                Storage::disk('public')->delete($dokKeuangan->transfer_sisa);
            }
            $dokKeuangan->update(['transfer_sisa' => $path]);
        }

        // Cek apakah semua dokumen sudah lengkap → selesai
        if ($usulan->checkCompletion()) {
            return redirect()->route('keuangan.detail', $usulan->no_usulan)
                ->with('success', 'Pembayaran lunas & dokumen lengkap. Usulan telah selesai.');
        }

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Pembayaran sisa berhasil dikonfirmasi. Status: Lunas.');
    }
}
