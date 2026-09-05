<?php

namespace App\Http\Controllers;

use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\TindakLanjut;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\FormatLaporanPerjadin;
use App\Services\PemberitahuanBendahara;
use App\Services\PenagihDokumen;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Laporan perjalanan dinas yang disusun langsung di aplikasi.
 *
 * Sebelumnya pelaksana mengunduh format kosong, mengetiknya di luar, lalu
 * mengunggah hasilnya sebagai berkas mati. Kini kegiatan dan rencana tindak
 * lanjutnya tersimpan sebagai data, sehingga dokumennya terbit sendiri dan
 * tindak lanjutnya dapat direkap serta ditagih.
 */
class LaporanPerjadinController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private PenagihDokumen $penagih,
        private PemberitahuanBendahara $bendahara,
    ) {}

    /**
     * Daftar laporan perjalanan dinas milik pengguna.
     *
     * Disusun dari usulannya, bukan dari tabel laporan: perjalanan yang
     * laporannya belum pernah dibuka pun harus terlihat, justru itu yang
     * perlu dikerjakan.
     */
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();
        $cari = $request->input('cari');

        $usulan = Usulan::with('laporan.statusHasil', 'user')
            ->when(! $isAdmin, fn ($q) => $q->where('id_user', Auth::id()))
            ->whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('no_usulan', 'like', "%{$cari}%")
                ->orWhere('no_tugas', 'like', "%{$cari}%")
                ->orWhere('lokasi', 'like', "%{$cari}%")
                ->orWhereHas('user', fn ($u) => $u->where('nama', 'like', "%{$cari}%"))))
            ->orderByDesc('tanggal_selesai')
            ->paginate(15)
            ->withQueryString();

        return view('dokumen.daftar-laporan', [
            'usulan' => $usulan,
            'cari' => $cari,
            'jumlahBelumSelesai' => (clone $usulan)->getCollection()
                ->reject(fn (Usulan $item) => $item->laporan?->sudahSelesai() === true)
                ->count(),
        ]);
    }

    /**
     * Laporan yang sudah tersusun, tanpa dapat disunting.
     */
    public function show(Request $request, Usulan $usulan): View
    {
        $this->pastikanBoleh($request, $usulan);

        $laporan = $this->laporan($usulan)->load('kegiatan', 'tindakLanjut', 'statusHasil');
        $usulan->load('user.unit', 'kegiatan', 'kategoriPerjadin');

        return view('dokumen.laporan-lihat', compact('usulan', 'laporan'));
    }

    /**
     * Formulir penyusunan laporan untuk satu perjalanan.
     */
    public function edit(Request $request, Usulan $usulan): View
    {
        $this->pastikanBoleh($request, $usulan);

        $laporan = $this->laporan($usulan);
        $usulan->load('user.unit', 'kegiatan', 'kategoriPerjadin');

        return view('dokumen.laporan', [
            'usulan' => $usulan,
            'laporan' => $laporan->load('kegiatan', 'tindakLanjut'),
            'statusTindakLanjut' => StatusTindakLanjut::options(),
            'statusHasil' => StatusHasil::pilihan(),
            'hariPerjalanan' => $this->hariPerjalanan($usulan, $laporan),
        ]);
    }

    /**
     * Simpan isi laporan. Baris kegiatan dan tindak lanjut ditulis ulang
     * seluruhnya: barisnya dapat bertambah, berkurang, atau berpindah urutan,
     * sehingga menyamakan satu per satu lebih rumit daripada menyusun ulang.
     */
    public function update(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanBoleh($request, $usulan);
        $this->pastikanMasihBolehDisunting($usulan);

        $data = $request->validate([
            'id_status_hasil' => ['nullable', 'exists:status_hasil,id'],
            'kesimpulan' => ['nullable', 'string', 'max:5000'],

            // Berkunci tanggal: satu uraian per hari perjalanan dinas.
            'kegiatan' => ['array'],
            'kegiatan.*' => ['nullable', 'string', 'max:2000'],

            'tindak_lanjut' => ['array'],
            'tindak_lanjut.*.uraian' => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut.*.penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'tindak_lanjut.*.target_selesai' => ['nullable', 'date'],
            'tindak_lanjut.*.status' => ['nullable', Rule::in(array_keys(StatusTindakLanjut::options()))],
        ]);

        $laporan = $this->laporan($usulan);

        DB::transaction(function () use ($laporan, $data): void {
            $laporan->update([
                'id_status_hasil' => $data['id_status_hasil'] ?? null,
                'kesimpulan' => $data['kesimpulan'] ?? null,
            ]);

            $laporan->kegiatan()->delete();
            $laporan->tindakLanjut()->delete();

            // Kuncinya tanggal, bukan urutan baris: hari yang dikosongkan
            // tidak menggeser uraian hari-hari sesudahnya.
            $urutan = 0;
            foreach ($this->tanggalPerjalanan($laporan->usulan) as $tanggal) {
                $uraian = $data['kegiatan'][$tanggal] ?? null;

                if (blank($uraian)) {
                    continue;
                }

                $laporan->kegiatan()->create([
                    'urutan' => ++$urutan,
                    'tanggal' => $tanggal,
                    'uraian' => trim($uraian),
                ]);
            }

            $urutan = 0;
            foreach ($data['tindak_lanjut'] ?? [] as $baris) {
                if (blank($baris['uraian'] ?? null)) {
                    continue;
                }

                $laporan->tindakLanjut()->create([
                    'urutan' => ++$urutan,
                    'uraian' => trim($baris['uraian']),
                    'penanggung_jawab' => $baris['penanggung_jawab'] ?? null,
                    'target_selesai' => $baris['target_selesai'] ?? null,
                    'status' => $baris['status'] ?? StatusTindakLanjut::Rencana->value,
                ]);
            }
        });

        return redirect()
            ->route('dokumen.laporan.edit', $usulan->no_usulan)
            ->with('success', 'Isi laporan tersimpan.');
    }

    /**
     * Nyatakan laporan selesai — sejak itu dokumennya terbit dan terhitung
     * sebagai berkas pertanggungjawaban yang lengkap.
     */
    public function selesaikan(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanBoleh($request, $usulan);

        $laporan = $this->laporan($usulan);

        if (! $laporan->layakDiselesaikan()) {
            return back()->with('error', 'Isi dulu sedikitnya satu uraian kegiatan, satu rencana tindak lanjut, dan pilih status hasil yang dicapai.');
        }

        $laporan->update(['diselesaikan_at' => now()]);

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Laporan perjalanan dinas {$usulan->no_usulan} diselesaikan.",
            ['usulan' => $usulan],
        );

        $segar = $usulan->fresh(['dokumen', 'tiket', 'notaTransport', 'laporan', 'keuangan', 'user']);
        $segar->checkCompletion();

        // Menyelesaikan laporan kerap menjadi langkah terakhir yang membuat
        // berkas lengkap, jadi bendahara diberi tahu dari sini juga —
        // bukan hanya saat berkas diunggah lewat menu Dokumen.
        $this->bendahara->berkasLengkap($segar);

        return redirect()
            ->route('dokumen.laporan.edit', $usulan->no_usulan)
            ->with('success', 'Laporan dinyatakan selesai dan dokumennya sudah dapat dicetak.');
    }

    /**
     * Buka kembali laporan yang sudah diselesaikan agar dapat diperbaiki.
     */
    public function bukaKembali(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanBoleh($request, $usulan);

        $this->laporan($usulan)->update(['diselesaikan_at' => null]);

        return redirect()
            ->route('dokumen.laporan.edit', $usulan->no_usulan)
            ->with('success', 'Laporan dibuka kembali untuk diperbaiki.');
    }

    /**
     * Dokumen laporan yang terbit dari isian di aplikasi.
     */
    public function cetak(Request $request, Usulan $usulan, FormatLaporanPerjadin $format): Response
    {
        $this->pastikanBoleh($request, $usulan);

        $laporan = $this->laporan($usulan)->load('kegiatan', 'tindakLanjut');

        abort_unless(
            $laporan->sudahSelesai(),
            403,
            'Laporan belum dinyatakan selesai, dokumennya belum dapat dicetak.',
        );

        $pdf = Pdf::loadView('dokumen.laporan-cetak', $format->data($usulan) + [
            'laporan' => $laporan,
        ]);

        return $pdf->download("Laporan-Perjadin_{$usulan->no_usulan}.pdf");
    }

    /**
     * Rekap seluruh rencana tindak lanjut milik pengguna, agar hasil
     * perjalanan dinas tidak berhenti sebagai berkas yang tersimpan.
     */
    public function daftarTindakLanjut(Request $request): View
    {
        $status = $request->input('status');
        $isAdmin = $request->user()->isAdmin();

        $tindakLanjut = TindakLanjut::with('laporan.usulan.user')
            ->whereHas('laporan.usulan', fn ($q) => $isAdmin ? $q : $q->where('id_user', Auth::id()))
            ->when(
                array_key_exists((string) $status, StatusTindakLanjut::options()),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByRaw('CASE WHEN target_selesai IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_selesai')
            ->paginate(15)
            ->withQueryString();

        return view('dokumen.tindak-lanjut', [
            'tindakLanjut' => $tindakLanjut,
            'status' => $status,
            'statusOptions' => StatusTindakLanjut::options(),
            'jumlahBelumSelesai' => TindakLanjut::belumSelesai()
                ->whereHas('laporan.usulan', fn ($q) => $isAdmin ? $q : $q->where('id_user', Auth::id()))
                ->count(),
        ]);
    }

    /**
     * Perbarui perkembangan sebuah tindak lanjut dari halaman rekap.
     */
    public function ubahStatusTindakLanjut(Request $request, TindakLanjut $tindakLanjut): RedirectResponse
    {
        $usulan = $tindakLanjut->laporan?->usulan;

        abort_if($usulan === null, 404);
        $this->pastikanBoleh($request, $usulan);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(StatusTindakLanjut::options()))],
        ]);

        $tindakLanjut->update($data);

        return back()->with('success', 'Perkembangan tindak lanjut diperbarui.');
    }

    // ── Pembantu ──

    private function laporan(Usulan $usulan): LaporanPerjadin
    {
        return LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);
    }

    /**
     * Tanggal tiap hari perjalanan, dari berangkat sampai kembali.
     *
     * @return list<string>
     */
    private function tanggalPerjalanan(?Usulan $usulan): array
    {
        if (! $usulan?->tanggal_mulai) {
            return [];
        }

        $mulai = Carbon::parse($usulan->tanggal_mulai)->startOfDay();
        $selesai = Carbon::parse($usulan->tanggal_selesai ?? $usulan->tanggal_mulai)->startOfDay();

        // Perjalanan yang tanggalnya terbalik disikapi sebagai satu hari,
        // bukan rentang kosong yang membuat formulirnya tidak dapat diisi.
        if ($selesai->lessThan($mulai)) {
            $selesai = $mulai->copy();
        }

        return collect(CarbonPeriod::create($mulai, $selesai))
            ->map(fn (Carbon $hari) => $hari->toDateString())
            ->all();
    }

    /**
     * Baris formulir per hari perjalanan, sudah berisi uraian yang tersimpan.
     *
     * @return list<array{tanggal: Carbon, uraian: string}>
     */
    private function hariPerjalanan(Usulan $usulan, LaporanPerjadin $laporan): array
    {
        $tersimpan = $laporan->kegiatan
            ->filter(fn ($baris) => $baris->tanggal !== null)
            ->keyBy(fn ($baris) => $baris->tanggal->toDateString());

        return collect($this->tanggalPerjalanan($usulan))
            ->map(fn (string $tanggal) => [
                'tanggal' => Carbon::parse($tanggal),
                'uraian' => $tersimpan->get($tanggal)?->uraian ?? '',
            ])
            ->all();
    }

    private function pastikanBoleh(Request $request, Usulan $usulan): void
    {
        abort_if(
            $usulan->id_user !== $request->user()->id && ! $request->user()->isAdmin(),
            403,
            'Laporan ini bukan milik Anda.',
        );
    }

    /**
     * Dijaga di penyimpanan, bukan hanya dengan menyembunyikan tombolnya:
     * tanpa ini laporan yang perjalanannya sudah ditutup masih dapat
     * diubah dengan mengirim langsung ke alamat penyimpanannya.
     */
    private function pastikanMasihBolehDisunting(Usulan $usulan): void
    {
        abort_unless(
            $this->laporan($usulan)->bolehDisunting(),
            403,
            'Perjalanan dinas ini sudah ditutup, laporannya tidak dapat diubah lagi.',
        );
    }
}
