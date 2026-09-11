<?php

namespace App\Http\Controllers;

use App\Enums\StatusLaporanPerjadin;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\LaporanPerjadin;
use App\Models\Notifikasi;
use App\Models\TindakLanjut;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Meja pimpinan untuk laporan perjalanan dinas.
 *
 * Laporan yang dikirim pelaksana berakhir di sini: pimpinan membacanya,
 * lalu mengonfirmasi dan menandatanganinya, atau mengembalikannya dengan
 * catatan bila ada yang perlu direvisi. Konfirmasi itulah yang membuka
 * pelunasan pembayaran, jadi laporan yang mengendap di meja ini berarti
 * pelaksana menunggu uangnya.
 */
class LaporanPimpinanController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    /**
     * Daftar laporan perjalanan dinas seluruh pelaksana.
     *
     * Tab bawaannya laporan yang menunggu konfirmasi — itulah yang perlu
     * dikerjakan — tetapi seluruh laporan dapat dilihat lewat tab lain.
     */
    public function index(Request $request): View
    {
        $status = $this->statusDipilih($request, StatusLaporanPerjadin::MenungguKonfirmasi);
        $cari = $request->input('cari');

        return view('laporan-pimpinan.daftar', [
            'usulan' => $this->daftarUsulan($status, $cari),
            'status' => $status,
            'cari' => $cari,
            'jumlah' => $this->jumlahPerStatus(),
        ]);
    }

    /**
     * Rekap status konfirmasi: berapa laporan pada tiap tahap, dan daftar
     * lengkapnya untuk tahap yang dipilih.
     */
    public function status(Request $request): View
    {
        $status = $this->statusDipilih($request, null);
        $cari = $request->input('cari');

        return view('laporan-pimpinan.status', [
            'usulan' => $this->daftarUsulan($status, $cari),
            'status' => $status,
            'cari' => $cari,
            'jumlah' => $this->jumlahPerStatus(),
        ]);
    }

    /**
     * Seluruh rencana tindak lanjut hasil perjalanan dinas, agar pimpinan
     * dapat memantau apa yang dijanjikan pelaksana setelah kembali.
     */
    public function tindakLanjut(Request $request): View
    {
        $status = $request->input('status');

        $tindakLanjut = TindakLanjut::with('laporan.usulan.user')
            ->whereHas('laporan', fn (Builder $q) => $q->whereNotNull('dikirim_at'))
            ->when(
                array_key_exists((string) $status, StatusTindakLanjut::options()),
                fn (Builder $q) => $q->where('status', $status),
            )
            ->orderByRaw('CASE WHEN target_selesai IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_selesai')
            ->paginate(15)
            ->withQueryString();

        return view('laporan-pimpinan.tindak-lanjut', [
            'tindakLanjut' => $tindakLanjut,
            'status' => $status,
            'statusOptions' => StatusTindakLanjut::options(),
            'jumlahBelumSelesai' => TindakLanjut::belumSelesai()
                ->whereHas('laporan', fn (Builder $q) => $q->whereNotNull('dikirim_at'))
                ->count(),
            'jumlahTerlambat' => TindakLanjut::belumSelesai()
                ->whereHas('laporan', fn (Builder $q) => $q->whereNotNull('dikirim_at'))
                ->whereDate('target_selesai', '<', today())
                ->count(),
        ]);
    }

    /**
     * Isi laporan beserta tombol keputusannya.
     */
    public function show(Usulan $usulan): View
    {
        $laporan = $this->laporan($usulan)->load('kegiatan', 'tindakLanjut', 'statusHasil', 'pimpinan');
        $usulan->load('user.unit', 'kegiatan', 'kategoriPerjadin', 'keuangan');

        return view('laporan-pimpinan.lihat', compact('usulan', 'laporan'));
    }

    /**
     * Konfirmasi dan tandatangani laporan.
     */
    public function konfirmasi(Request $request, Usulan $usulan): RedirectResponse
    {
        // Laporan hanya ditandatangani Direktur Poltekkes Kemenkes Manado.
        // Wakil direktur tetap dapat membaca dan mengembalikannya, tetapi
        // tanda tangan pada dokumennya harus tanda tangan Direktur.
        abort_unless(
            $request->user()->isDirektur(),
            403,
            'Laporan perjalanan dinas hanya ditandatangani Direktur Poltekkes Kemenkes Manado.',
        );

        $laporan = $this->laporan($usulan);

        if (! $laporan->sudahDikirim()) {
            return back()->with('error', 'Laporan ini belum dikirim pelaksana, atau sudah dikonfirmasi.');
        }

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        $laporan->konfirmasi($request->user(), $data['catatan'] ?? null);

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Laporan perjalanan dinas {$usulan->no_usulan} dikonfirmasi dan ditandatangani pimpinan {$request->user()->nama}.",
            ['usulan' => $usulan, 'catatan' => $data['catatan'] ?? null],
        );

        if ($usulan->user) {
            $this->notifikasi->kirim(
                $usulan->user,
                'Laporan perjadin dikonfirmasi pimpinan',
                "Laporan perjalanan dinas {$usulan->no_usulan} sudah dikonfirmasi dan ditandatangani {$request->user()->nama}. Pelunasan pembayaran dapat diproses.",
                ['usulan' => $usulan, 'tipe' => Notifikasi::TIPE_SUKSES, 'url' => route('dokumen.laporan.edit', $usulan->no_usulan)],
            );
        }

        return redirect()
            ->route('laporan-perjadin.show', $usulan->no_usulan)
            ->with('success', 'Laporan dikonfirmasi dan ditandatangani. Pelaksana sudah diberi tahu.');
    }

    /**
     * Kembalikan laporan kepada pelaksana untuk direvisi.
     */
    public function kembalikan(Request $request, Usulan $usulan): RedirectResponse
    {
        $laporan = $this->laporan($usulan);

        if (! $laporan->sudahDikirim()) {
            return back()->with('error', 'Laporan ini belum dikirim pelaksana, atau sudah dikonfirmasi.');
        }

        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], [
            'catatan.required' => 'Tuliskan arahan revisinya agar pelaksana tahu yang harus diperbaiki.',
        ]);

        $laporan->kembalikan($request->user(), $data['catatan']);

        $this->audit->catat(
            AuditLog::AKSI_REVISI,
            "Laporan perjalanan dinas {$usulan->no_usulan} dikembalikan pimpinan {$request->user()->nama} untuk direvisi.",
            ['usulan' => $usulan, 'catatan' => $data['catatan']],
        );

        if ($usulan->user) {
            $this->notifikasi->kirim(
                $usulan->user,
                'Laporan perjadin perlu direvisi',
                "Laporan perjalanan dinas {$usulan->no_usulan} dikembalikan {$request->user()->nama}: {$data['catatan']}",
                ['usulan' => $usulan, 'tipe' => Notifikasi::TIPE_PERINGATAN, 'url' => route('dokumen.laporan.edit', $usulan->no_usulan)],
            );
        }

        return redirect()
            ->route('laporan-perjadin.index')
            ->with('success', 'Laporan dikembalikan ke pelaksana beserta arahan revisinya.');
    }

    /**
     * Cabut konfirmasi bila keliru, selama pembayarannya belum dilunasi.
     */
    public function batalKonfirmasi(Request $request, Usulan $usulan): RedirectResponse
    {
        $laporan = $this->laporan($usulan);

        if (! $laporan->sudahDikonfirmasi()) {
            return back()->with('error', 'Laporan ini belum dikonfirmasi.');
        }

        // Konfirmasi yang sudah dipakai sebagai dasar pelunasan tidak dicabut
        // lagi — dokumennya sudah menjadi bagian pertanggungjawaban uang.
        if ($usulan->keuangan?->sudahLunas()) {
            return back()->with('error', 'Pelunasan sudah dibayarkan atas dasar konfirmasi ini, jadi konfirmasinya tidak dapat dicabut.');
        }

        $laporan->batalkanKonfirmasi();

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Konfirmasi pimpinan atas laporan perjalanan dinas {$usulan->no_usulan} dicabut oleh {$request->user()->nama}.",
            ['usulan' => $usulan],
        );

        return redirect()
            ->route('laporan-perjadin.show', $usulan->no_usulan)
            ->with('success', 'Konfirmasi dicabut. Laporan kembali menunggu keputusan.');
    }

    // ── Pembantu ──

    private function laporan(Usulan $usulan): LaporanPerjadin
    {
        return LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);
    }

    private function statusDipilih(Request $request, ?StatusLaporanPerjadin $bawaan): ?StatusLaporanPerjadin
    {
        if (! $request->has('status')) {
            return $bawaan;
        }

        return StatusLaporanPerjadin::tryFrom((string) $request->input('status'));
    }

    /**
     * Usulan yang laporannya berada pada tahap yang dipilih.
     *
     * Disusun dari usulan, bukan dari tabel laporan: perjalanan yang
     * laporannya belum pernah dibuka pun harus tampak sebagai "belum
     * selesai", bukan lenyap dari rekap.
     *
     * @return LengthAwarePaginator<int, Usulan>
     */
    private function daftarUsulan(?StatusLaporanPerjadin $status, ?string $cari): LengthAwarePaginator
    {
        return Usulan::with('laporan.statusHasil', 'laporan.pimpinan', 'user.unit')
            ->whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->when($status, fn (Builder $q) => $this->saringStatus($q, $status))
            ->when($cari, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('no_usulan', 'like', "%{$cari}%")
                ->orWhere('no_tugas', 'like', "%{$cari}%")
                ->orWhere('lokasi', 'like', "%{$cari}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('nama', 'like', "%{$cari}%"))))
            ->orderByDesc('tanggal_selesai')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Saringan tiap status, sejalan dengan LaporanPerjadin::status().
     *
     * @param  Builder<Usulan>  $query
     */
    private function saringStatus(Builder $query, StatusLaporanPerjadin $status): void
    {
        match ($status) {
            StatusLaporanPerjadin::Dikonfirmasi => $query->whereHas('laporan', fn (Builder $l) => $l->dikonfirmasi()),
            StatusLaporanPerjadin::MenungguKonfirmasi => $query->whereHas('laporan', fn (Builder $l) => $l->menungguKonfirmasi()),
            StatusLaporanPerjadin::PerluRevisi => $query->whereHas('laporan', fn (Builder $l) => $l->perluRevisi()),
            StatusLaporanPerjadin::Selesai => $query->whereHas('laporan', fn (Builder $l) => $l
                ->whereNotNull('diselesaikan_at')->whereNull('dikirim_at')->whereNull('dikonfirmasi_at')),
            // Belum pernah dibuka sama sekali, atau dibuka tetapi belum melangkah
            // ke tahap mana pun — termasuk belum pernah dikembalikan.
            StatusLaporanPerjadin::Draf => $query->whereDoesntHave('laporan', fn (Builder $l) => $l
                ->where(fn (Builder $w) => $w
                    ->whereNotNull('diselesaikan_at')
                    ->orWhereNotNull('dikirim_at')
                    ->orWhereNotNull('dikonfirmasi_at')
                    ->orWhereNotNull('dikembalikan_at'))),
        };
    }

    /**
     * @return array<string, int>
     */
    private function jumlahPerStatus(): array
    {
        $jumlah = [];

        foreach (StatusLaporanPerjadin::cases() as $status) {
            $jumlah[$status->value] = Usulan::query()
                ->whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
                ->tap(fn (Builder $q) => $this->saringStatus($q, $status))
                ->count();
        }

        return $jumlah;
    }
}
