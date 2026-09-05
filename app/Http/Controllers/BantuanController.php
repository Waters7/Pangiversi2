<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Models\ObrolanBantuan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Saluran bantuan antara pengguna dan administrator.
 *
 * Pengguna biasa melihat obrolannya sendiri; administrator melihat seluruhnya
 * dan menjawab. Satu pengontrol menangani keduanya karena halamannya memang
 * sama — yang berbeda hanya luas pandangannya.
 */
class BantuanController extends Controller
{
    public function __construct(private NotifikasiService $notifikasi) {}

    /**
     * Daftar obrolan: milik sendiri, atau seluruhnya bagi administrator.
     */
    public function index(Request $request): View
    {
        $pengguna = $request->user();
        $admin = $pengguna->isAdmin();
        $status = $request->input('status');

        $dasar = fn () => ObrolanBantuan::with('pelapor', 'usulan')
            ->when(! $admin, fn ($q) => $q->where('id_pelapor', $pengguna->id));

        $obrolan = $dasar()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount('pesan')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        // Jumlah per status dihitung dari seluruh obrolan yang boleh dilihat,
        // supaya tabnya tetap menunjukkan antrian penuh saat satu tab dipilih.
        $jumlah = collect(ObrolanBantuan::LABEL)
            ->map(fn (string $label, string $kunci) => (clone $dasar())->where('status', $kunci)->count())
            ->all();

        return view('bantuan.index', [
            'obrolan' => $obrolan,
            'status' => $status,
            'label' => ObrolanBantuan::LABEL,
            'jumlah' => $jumlah,
            'admin' => $admin,
            'usulanSaya' => $admin
                ? collect()
                : Usulan::where('id_user', $pengguna->id)->latest('tanggal_mulai')->take(20)->get(),
        ]);
    }

    /**
     * Laporkan kendala baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'judul' => ['required', 'string', 'min:5', 'max:150'],
            'isi' => ['required', 'string', 'min:10', 'max:2000'],
            'id_usulan' => ['nullable', 'exists:usulan,id'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'judul.required' => 'Beri judul singkat supaya kendalanya mudah dikenali.',
            'isi.required' => 'Ceritakan kendalanya.',
            'isi.min' => 'Uraikan kendalanya sedikit lebih rinci.',
        ]);

        $pengguna = $request->user();

        $obrolan = ObrolanBantuan::create([
            'id_pelapor' => $pengguna->id,
            'judul' => $validated['judul'],
            'id_usulan' => $validated['id_usulan'] ?? null,
            'status' => ObrolanBantuan::STATUS_TERBUKA,
        ]);

        $obrolan->balas($pengguna, $validated['isi'], $this->simpanLampiran($request));

        $this->beritahuAdmin($obrolan, "Kendala baru dari {$pengguna->nama}: {$obrolan->judul}");

        return redirect()->route('bantuan.show', $obrolan)
            ->with('success', 'Laporan terkirim. Administrator akan menjawabnya di sini.');
    }

    /**
     * Isi satu obrolan.
     */
    public function show(Request $request, ObrolanBantuan $obrolan): View
    {
        $this->pastikanBoleh($request, $obrolan);

        $obrolan->load('pesan.pengirim', 'pelapor', 'usulan', 'penyelesai');
        $obrolan->tandaiTerbaca($request->user());

        return view('bantuan.show', [
            'obrolan' => $obrolan,
            'admin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Kirim satu pesan pada obrolan yang sudah berjalan.
     */
    public function balas(Request $request, ObrolanBantuan $obrolan): RedirectResponse
    {
        $this->pastikanBoleh($request, $obrolan);

        $validated = $request->validate([
            'isi' => ['required', 'string', 'min:2', 'max:2000'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'isi.required' => 'Tulis pesannya lebih dulu.',
        ]);

        $pengguna = $request->user();
        $obrolan->balas($pengguna, $validated['isi'], $this->simpanLampiran($request));

        if ($pengguna->isAdmin()) {
            // Balasan administrator dikabarkan kepada pelapornya.
            if ($obrolan->pelapor) {
                $this->notifikasi->kirim(
                    $obrolan->pelapor,
                    'Balasan atas laporan kendala Anda',
                    "Administrator menjawab laporan \"{$obrolan->judul}\".",
                    ['tipe' => Notifikasi::TIPE_INFO, 'url' => route('bantuan.show', $obrolan)],
                );
            }
        } else {
            $this->beritahuAdmin($obrolan, "{$pengguna->nama} menambahkan pesan pada \"{$obrolan->judul}\".");
        }

        return back()->with('success', 'Pesan terkirim.');
    }

    /**
     * Nyatakan kendalanya sudah beres.
     *
     * Boleh dilakukan administrator maupun pelapornya sendiri — yang paling
     * tahu kendalanya sudah selesai justru orang yang mengalaminya.
     */
    public function selesaikan(Request $request, ObrolanBantuan $obrolan): RedirectResponse
    {
        $this->pastikanBoleh($request, $obrolan);

        $obrolan->selesaikan($request->user());

        if ($request->user()->isAdmin() && $obrolan->pelapor) {
            $this->notifikasi->kirim(
                $obrolan->pelapor,
                'Laporan kendala dinyatakan selesai',
                "Laporan \"{$obrolan->judul}\" ditandai selesai. Balas di sana bila masih ada kendala.",
                ['tipe' => Notifikasi::TIPE_SUKSES, 'url' => route('bantuan.show', $obrolan)],
            );
        }

        return back()->with('success', 'Obrolan ditandai selesai.');
    }

    /**
     * Pelapor hanya boleh membuka obrolannya sendiri.
     */
    private function pastikanBoleh(Request $request, ObrolanBantuan $obrolan): void
    {
        abort_unless(
            $request->user()->isAdmin() || $obrolan->id_pelapor === $request->user()->id,
            403,
            'Obrolan ini bukan milik Anda.'
        );
    }

    private function simpanLampiran(Request $request): ?string
    {
        return $request->hasFile('lampiran')
            ? $request->file('lampiran')->store('bantuan', 'public')
            : null;
    }

    private function beritahuAdmin(ObrolanBantuan $obrolan, string $pesan): void
    {
        $this->notifikasi->kirimKePeran(
            [User::ROLE_SUPER_ADMIN],
            'Laporan kendala pengguna',
            $pesan,
            ['tipe' => Notifikasi::TIPE_PERINGATAN, 'url' => route('bantuan.show', $obrolan)],
        );
    }
}
