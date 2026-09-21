<?php

namespace App\Http\Controllers;

use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\LokasiTujuan;
use App\Models\Notifikasi;
use App\Models\SuratPerjalananDinas;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Models\Usulan;
use App\Rules\NomorSpdUnik;
use App\Services\AuditService;
use App\Services\EkspresiTanggal;
use App\Services\NotifikasiService;
use App\Services\PelacakUsulan;
use App\Services\PenomoranPerjadin;
use App\Services\WorkflowUsulan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsulanController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private WorkflowUsulan $workflow,
        private NotifikasiService $notifikasi,
        private PenomoranPerjadin $penomoran,
        private EkspresiTanggal $tanggal,
        private PelacakUsulan $pelacak,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $isAdmin = $request->user()->isAdmin();

        // Usulan milik sendiri maupun usulan kelompok yang mendaftarkan pengguna
        // ini sebagai peserta — keduanya masuk ke akun yang bersangkutan.
        // Dikurung sendiri: tanpa kurung, OR di dalamnya membatalkan filter
        // pencarian dan status yang di-AND-kan sesudahnya.
        $terkaitSaya = function ($query) {
            $query->where(function ($query) {
                $query->where('id_user', Auth::id())
                    ->orWhereHas('peserta', fn ($q) => $q->where('id_user', Auth::id()));
            });
        };

        $myUsulan = Usulan::when(! $isAdmin, $terkaitSaya);

        $usulan = Usulan::with('user', 'kegiatan', 'kategoriPerjadin', 'peserta', 'pembuat')
            ->when(! $isAdmin, $terkaitSaya)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('no_tugas', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhere('instansi', 'like', "%{$search}%")
                        ->orWhereHas('kategoriPerjadin', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($tahun, fn ($query) => $query->whereYear('tanggal_mulai', $tahun))
            ->when($bulan, fn ($query) => $query->whereMonth('tanggal_mulai', $bulan))
            ->orderByDesc('tanggal_mulai')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Langkah berikutnya dilekatkan di sini, bukan dihitung Blade per baris:
        // pelacaknya disiapkan sekali untuk seluruh halaman supaya tidak ada
        // kueri tambahan per usulan.
        $this->pelacak->siapkan($usulan->getCollection());

        $usulan->getCollection()->each(function (Usulan $item): void {
            $item->langkah_berikutnya = $this->pelacak->langkahBerikutnya($item);
        });

        $totalUsulan = (clone $myUsulan)->count();
        $menunggu = (clone $myUsulan)->menungguKeputusan()->count();
        $disetujui = (clone $myUsulan)->where('status', StatusUsulan::Disetujui->value)->count();
        $ditolak = (clone $myUsulan)->where('status', StatusUsulan::Ditolak->value)->count();
        $selesai = (clone $myUsulan)->where('status', StatusUsulan::Selesai->value)->count();
        $draft = (clone $myUsulan)->where('status', StatusUsulan::Draft->value)->count();
        $perluRevisi = (clone $myUsulan)->where('status', StatusUsulan::PerluRevisi->value)->count();
        $statusOptions = StatusUsulan::options();

        $tahunTersedia = (clone $myUsulan)
            ->whereNotNull('tanggal_mulai')
            ->selectRaw($this->tanggal->tahun('tanggal_mulai').' as tahun')
            ->distinct()
            ->pluck('tahun')
            ->filter()
            ->map(fn ($nilai) => (int) $nilai)
            ->sortDesc()
            ->values();

        $jumlahBulan = (clone $myUsulan)
            ->whereNotNull('tanggal_mulai')
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->selectRaw($this->tanggal->bulan('tanggal_mulai').' as bulan, count(*) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan')
            ->mapWithKeys(fn ($jumlah, $kunci) => [(int) $kunci => (int) $jumlah]);

        return view('usulan.list-usulan', compact(
            'usulan',
            'draft',
            'totalUsulan',
            'menunggu',
            'disetujui',
            'ditolak',
            'selesai',
            'perluRevisi',
            'statusOptions',
            'tahun',
            'bulan',
            'tahunTersedia',
            'jumlahBulan',
        ));
    }

    public function create(Request $request)
    {
        $lokasiTujuan = LokasiTujuan::aktif()->orderBy('nama')->get();

        return view('usulan.add-usulan', compact('lokasiTujuan') + [
            'kategoriPerjadin' => KategoriPerjadin::terkelompok(),
            'jenisKegiatan' => Kegiatan::orderBy('nama')->get(),
            'spdTerkait' => $this->bekalSpd($request->user()),
        ]);
    }

    /**
     * Surat Perjalanan Dinas yang mencantumkan pengguna ini sebagai
     * pelaksana — itulah yang boleh mendasari usulannya. SPD yang ia
     * buatkan untuk orang lain bukan miliknya untuk diusulkan.
     *
     * @return Builder<SuratPerjalananDinas>
     */
    private function spdMilik(User $pengguna)
    {
        return SuratPerjalananDinas::whereHas('pelaksana', fn ($q) => $q->where('id_user', $pengguna->id));
    }

    /**
     * Isi SPD yang dipakai formulir usulan untuk mengisi sendiri kolom
     * yang sudah ditulis saat pembuatan SPD.
     *
     * Disiapkan di sini, bukan di Blade, supaya tampilan tidak perlu
     * menyusun ulang bentuk datanya setiap kali formulir dibuka.
     *
     * @return list<array<string, mixed>>
     */
    private function bekalSpd(User $pengguna): array
    {
        return $this->spdMilik($pengguna)
            ->with('pelaksana')
            ->latest()
            ->get()
            ->map(fn (SuratPerjalananDinas $spd) => [
                'id' => $spd->id,
                // Nomor milik pengguna ini sendiri — orang kedua pada SPD
                // yang sama punya nomornya sendiri, bukan nomor orang pertama.
                'nomor' => $spd->nomorUntuk($pengguna) ?? 'Tanpa nomor',
                'tempat_berangkat' => $spd->tempat_berangkat,
                'tempat_tujuan' => $spd->tempat_tujuan,
                'tanggal_berangkat' => $spd->tanggal_berangkat?->toDateString(),
                'tanggal_kembali' => $spd->tanggal_kembali?->toDateString(),
                'lama_hari' => $spd->lama_hari,
                'maksud' => $spd->maksud,
                'alat_angkut' => $spd->alat_angkut,
                'instansi_pembebanan' => $spd->instansi_pembebanan,
                // Rekan sepelaksana pada SPD yang sama — ditampilkan sebagai
                // keterangan; masing-masing mengajukan usulannya sendiri.
                'rekan' => $spd->pelaksana
                    ->filter(fn ($orang) => $orang->id_user && $orang->id_user !== $pengguna->id)
                    ->map(fn ($orang) => ['id' => $orang->id_user, 'nama' => $orang->nama])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function show(Usulan $usulan)
    {
        $usulan->load(
            'user.unit',
            'kegiatan',
            'kategoriPerjadin',
            'dokumen',
            'keuangan.rincianBiaya',
            'keuangan.dokumenKeuangan',
            'peserta.user',
            'pembuat',
            'serombongan.user',
            'tahunAnggaran',
            'lokasiTujuan',
            'riwayat.pelaku',
        );

        // Kandidat peserta: pegawai yang belum terdaftar pada usulan ini.
        $calonPeserta = User::whereNotIn('id', $usulan->peserta->pluck('id_user')->filter())
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'jabatan']);

        return view('usulan.detail-usulan', compact('usulan', 'calonPeserta'));
    }

    public function edit(Usulan $usulan)
    {
        if (! $usulan->bolehDisunting() && ! request()->user()->isAdmin()) {
            return redirect()->route('usulan.show', $usulan)
                ->with('error', 'Usulan hanya dapat diedit selama berstatus draft, ditolak, atau perlu revisi.');
        }

        $lokasiTujuan = LokasiTujuan::aktif()->orderBy('nama')->get();
        $usulan->load('dokumen');

        return view('usulan.edit-usulan', compact('usulan', 'lokasiTujuan') + [
            'kategoriPerjadin' => KategoriPerjadin::terkelompok(),
            'jenisKegiatan' => Kegiatan::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Usulan $usulan)
    {
        if (! $usulan->bolehDisunting() && ! $request->user()->isAdmin()) {
            return redirect()->route('usulan.show', $usulan)
                ->with('error', 'Usulan hanya dapat diedit selama berstatus draft, ditolak, atau perlu revisi.');
        }

        // Berkas SPD bertanda tangan boleh dilewati hanya bila sudah pernah
        // diunggah; nomornya tetap wajib karena ikut tercatat pada jejak audit.
        $sudahAdaSpd = filled($usulan->dokumen()->latest('id')->value('spd_ditandatangani'));

        $request->validate([
            'id_kegiatan' => ['required', 'exists:kegiatan,id'],
            'id_kategori_perjadin' => ['required', 'exists:kategori_perjadin,id'],
            'no_tugas' => ['required', 'string', 'max:255'],
            'no_spd' => ['required', 'string', 'max:255', new NomorSpdUnik($usulan)],
            'spd_ditandatangani' => [$sudahAdaSpd ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'lokasi' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'uraian' => ['nullable', 'string'],
            'surat_tugas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'rundown' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'dokumen_pendukung' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'no_spd.required' => 'Nomor Surat Perjalanan Dinas wajib diisi sebelum usulan dikirim.',
            'spd_ditandatangani.required' => 'Unggah Surat Perjalanan Dinas yang sudah ditandatangani sebelum usulan dikirim.',
        ]);

        $isDraft = $request->input('action') === 'draft';
        $statusLama = $usulan->status;

        $usulan->update([
            'no_tugas' => $request->no_tugas,
            'no_spd' => trim($request->no_spd),
            'status' => StatusUsulan::Draft->value,
            'lokasi' => $request->lokasi,
            'id_lokasi' => $this->resolveLokasi($request->lokasi),
            'instansi' => $request->instansi,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'uraian' => $request->uraian,
            'id_kegiatan' => $request->id_kegiatan,
            'id_kategori_perjadin' => $request->id_kategori_perjadin,
            'catatan' => null,
        ]);

        // Update dokumen jika ada file baru yang diunggah
        $dokumen = $usulan->dokumen()->latest('id')->first();
        if ($dokumen) {
            $docData = [];

            if ($request->hasFile('surat_tugas')) {
                $docData['surat_tugas'] = $request->file('surat_tugas')->store('dokumen/surat-tugas', 'public');
            }

            if ($request->hasFile('spd_ditandatangani')) {
                $docData['spd_ditandatangani'] = $request->file('spd_ditandatangani')->store('dokumen/spd', 'public');
            }

            if ($request->hasFile('rundown')) {
                $docData['rundown'] = $request->file('rundown')->store('dokumen/rundown', 'public');
            }

            if ($request->hasFile('dokumen_pendukung')) {
                $docData['dokumen_pendukung'] = $request->file('dokumen_pendukung')->store('dokumen/dokumen-pendukung', 'public');
            }

            if ($docData !== []) {
                $dokumen->update($docData);
            }
        }

        $this->audit->catatPerubahanStatus(
            $usulan,
            AuditLog::AKSI_DIPERBARUI,
            "Usulan {$usulan->no_usulan} diperbarui.",
            $statusLama,
        );

        if (! $isDraft) {
            $this->workflow->ajukan($usulan);
        }

        $message = $isDraft ? 'Draft usulan berhasil diperbarui.' : 'Usulan berhasil diperbarui dan diajukan.';

        return redirect()->route('usulan.show', $usulan)->with('success', $message);
    }

    public function store(Request $request)
    {
        $request->validate([
            // SPD dari aplikasi tidak wajib: memilihnya hanya menyalin isian.
            // Dasar penugasannya adalah SPD bertanda tangan yang diunggah.
            'id_spd' => ['nullable', Rule::in($this->spdMilik($request->user())->pluck('id'))],
            // SPD yang sudah ditandatangani lewat SRIKANDI beserta nomor
            // resminya — dasar persetujuan PPK yang tercatat pada jejak audit.
            // Nomornya unik: nomor yang sudah dipakai usulan lain ditolak.
            'no_spd' => ['required', 'string', 'max:255', new NomorSpdUnik],
            'spd_ditandatangani' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_kegiatan' => ['required', 'exists:kegiatan,id'],
            'id_kategori_perjadin' => ['required', 'exists:kategori_perjadin,id'],
            'no_tugas' => ['required', 'string', 'max:255'],
            'lokasi' => ['required', 'string', 'max:255'],
            'instansi' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'uraian' => ['nullable', 'string'],
            'surat_tugas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'rundown' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'dokumen_pendukung' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'id_spd.in' => 'Surat Perjalanan Dinas itu bukan milik Anda.',
            'no_spd.required' => 'Nomor Surat Perjalanan Dinas wajib diisi sebelum usulan dikirim.',
            'spd_ditandatangani.required' => 'Unggah Surat Perjalanan Dinas yang sudah ditandatangani sebelum usulan dikirim.',
            'id_kegiatan.required' => 'Pilih jenis kegiatan perjalanan dinas ini.',
        ]);

        $isDraft = $request->input('action') === 'draft';
        $pengusul = $request->user();

        // Pengajuan selalu perorangan: tiap pelaksana mengunggah SPD bertanda
        // tangannya sendiri, jadi tidak ada lagi usulan yang dibuatkan untuk
        // orang lain dan menunggu konfirmasi pemiliknya.
        $berkas = $this->simpanBerkasPengajuan($request);

        $usulan = DB::transaction(
            fn () => $this->buatUsulanUntuk($request, $pengusul, $pengusul, null, $berkas)
        );

        if (! $isDraft) {
            $this->workflow->ajukan($usulan);
        }

        return redirect()->route('usulan.list')->with(
            'success',
            $isDraft ? 'Draft usulan berhasil disimpan.' : 'Pengajuan perjadin berhasil dikirim.',
        );
    }

    /**
     * Simpan berkas pengajuan sekali, lalu dipakai ulang oleh tiap usulan.
     *
     * @return array<string, string|null>
     */
    private function simpanBerkasPengajuan(Request $request): array
    {
        return [
            'surat_tugas' => $request->file('surat_tugas')->store('dokumen/surat-tugas', 'public'),
            'spd_ditandatangani' => $request->file('spd_ditandatangani')->store('dokumen/spd', 'public'),
            'rundown' => $request->hasFile('rundown')
                ? $request->file('rundown')->store('dokumen/rundown', 'public')
                : null,
            'dokumen_pendukung' => $request->hasFile('dokumen_pendukung')
                ? $request->file('dokumen_pendukung')->store('dokumen/dokumen-pendukung', 'public')
                : null,
        ];
    }

    /**
     * Buat satu usulan lengkap — nomor, peserta, dokumen, dan jejak audit —
     * atas nama seorang pegawai.
     *
     * @param  array<string, string|null>  $berkas
     */
    private function buatUsulanUntuk(
        Request $request,
        User $pemilik,
        User $pengusul,
        ?string $kodeRombongan,
        array $berkas,
    ): Usulan {
        $dibuatkan = $pemilik->id !== $pengusul->id;

        $usulan = Usulan::create([
            'no_usulan' => $this->penomoran->nomorPerjadin($pemilik, $request->tanggal_mulai),
            'no_tugas' => $request->no_tugas,
            'status' => StatusUsulan::Draft->value,
            'jenis_pengajuan' => $kodeRombongan ? Usulan::PENGAJUAN_KELOMPOK : Usulan::PENGAJUAN_PERSONAL,
            'kode_rombongan' => $kodeRombongan,
            'lokasi' => $request->lokasi,
            'id_lokasi' => $this->resolveLokasi($request->lokasi),
            'instansi' => $request->instansi,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'uraian' => $request->uraian,
            'id_kegiatan' => $request->id_kegiatan,
            'id_kategori_perjadin' => $request->id_kategori_perjadin,
            'id_spd' => $request->id_spd ?: null,
            'no_spd' => trim($request->no_spd),
            'id_tahun_anggaran' => TahunAnggaran::aktif()?->id,
            'id_user' => $pemilik->id,
            'id_pembuat' => $pengusul->id,
            // Usulan yang dibuatkan orang lain menunggu kesediaan pemiliknya.
            'konfirmasi' => $dibuatkan ? Usulan::KONFIRMASI_MENUNGGU : Usulan::KONFIRMASI_DIKONFIRMASI,
            'dikonfirmasi_at' => $dibuatkan ? null : now(),
        ]);

        $usulan->peserta()->create([
            'id_user' => $pemilik->id,
            'nama' => $pemilik->nama,
            'nip' => $pemilik->nip,
            'jabatan' => $pemilik->jabatan,
            'peran' => 'ketua',
        ]);

        Dokumen::create(['id_usulan' => $usulan->id] + $berkas);

        $this->audit->catat(
            AuditLog::AKSI_DIBUAT,
            $dibuatkan
                ? "Draft usulan {$usulan->no_usulan} dibuatkan {$pengusul->nama} untuk {$pemilik->nama}."
                : "Draft usulan {$usulan->no_usulan} dibuat.",
            ['usulan' => $usulan, 'status_baru' => $usulan->status],
        );

        return $usulan;
    }

    /**
     * Pemilik usulan menyatakan bersedia berangkat.
     */
    public function konfirmasi(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanPemilikBolehKonfirmasi($request, $usulan);

        $usulan->konfirmasiBerangkat();

        $this->audit->catat(
            AuditLog::AKSI_PESERTA,
            "{$request->user()->nama} menyatakan bersedia berangkat pada usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        // Baru setelah dikonfirmasi, usulan dicatat sebagai berlaku.
        if ($usulan->status_enum->bolehDisunting()) {
            $this->workflow->ajukan($usulan->fresh());
        }

        $this->beritahuPembuat($usulan, 'Usulan dikonfirmasi', "{$usulan->user?->nama} bersedia berangkat pada usulan {$usulan->no_usulan}.", Notifikasi::TIPE_SUKSES);

        return back()->with('success', 'Kesediaan dikonfirmasi. Usulan perjalanan dinas Anda berlaku.');
    }

    /**
     * Pemilik usulan mengundurkan diri sebelum usulannya berlaku.
     */
    public function batalKonfirmasi(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanPemilikBolehKonfirmasi($request, $usulan);

        $validated = $request->validate([
            'alasan_batal' => ['nullable', 'string', 'max:500'],
        ]);

        $statusLama = $usulan->status;
        $usulan->batalkanKeikutsertaan($validated['alasan_batal'] ?? null);

        $this->audit->catatPerubahanStatus(
            $usulan,
            AuditLog::AKSI_DIBATALKAN,
            "{$request->user()->nama} membatalkan keikutsertaannya pada usulan {$usulan->no_usulan}.",
            $statusLama,
            $validated['alasan_batal'] ?? null,
        );

        $this->beritahuPembuat($usulan, 'Peserta mengundurkan diri', "{$usulan->user?->nama} membatalkan usulan {$usulan->no_usulan}.", Notifikasi::TIPE_PERINGATAN);

        return back()->with('success', 'Keikutsertaan Anda berhasil dibatalkan.');
    }

    /**
     * Kirim usulan yang masih berstatus draf agar berlaku,
     * tanpa harus membuka kembali formulir penyuntingan.
     */
    public function ajukan(Request $request, Usulan $usulan): RedirectResponse
    {
        abort_unless(
            $usulan->id_user === $request->user()->id || $request->user()->isAdmin(),
            403,
            'Hanya pemilik usulan yang dapat mengirimkannya.'
        );

        abort_unless(
            $usulan->status_enum->bolehDisunting(),
            403,
            'Usulan ini sudah tidak berada pada tahap pengajuan.'
        );

        if ($usulan->menungguKonfirmasi()) {
            return back()->with('error', 'Konfirmasi kesediaan Anda lebih dulu sebelum usulan dikirim ke PPK.');
        }

        // Draf lama mungkin dibuat sebelum SPD bertanda tangan diwajibkan;
        // ia baru boleh berjalan setelah dilengkapi lewat formulir ubah.
        if (! $usulan->punyaSpdBertandaTangan()) {
            return back()->with('error', 'Lengkapi nomor dan berkas Surat Perjalanan Dinas yang sudah ditandatangani lebih dulu sebelum pengajuan dikirim.');
        }

        $this->workflow->ajukan($usulan);

        return back()->with('success', 'Pengajuan perjadin dikirim dan tercatat berlaku.');
    }

    private function pastikanPemilikBolehKonfirmasi(Request $request, Usulan $usulan): void
    {
        abort_unless(
            $usulan->id_user === $request->user()->id,
            403,
            'Hanya pemilik usulan yang dapat mengubah kesediaannya.'
        );

        abort_unless(
            $usulan->konfirmasiMasihTerbuka(),
            403,
            'Kesediaan tidak dapat diubah lagi pada tahap ini.'
        );
    }

    private function beritahuPembuat(Usulan $usulan, string $judul, string $pesan, string $tipe): void
    {
        if (! $usulan->pembuat || $usulan->id_pembuat === $usulan->id_user) {
            return;
        }

        $this->notifikasi->kirim($usulan->pembuat, $judul, $pesan, [
            'usulan' => $usulan,
            'tipe' => $tipe,
        ]);
    }

    public function destroy(Usulan $usulan)
    {
        if (! $usulan->bolehDisunting() && ! request()->user()->isAdmin()) {
            return back()->with('error', 'Usulan hanya dapat dihapus jika berstatus draft, ditolak, atau perlu revisi.');
        }

        $noUsulan = $usulan->no_usulan;

        // Dicatat sebelum penghapusan agar relasi audit tidak ikut hilang.
        $this->audit->catat(
            AuditLog::AKSI_DIHAPUS,
            "Usulan {$noUsulan} dihapus.",
            ['status_lama' => $usulan->status],
        );

        $usulan->delete();

        return redirect()->route('usulan.list')->with('success', "Usulan {$noUsulan} berhasil dihapus.");
    }

    /**
     * Cocokkan lokasi yang diketik pengusul dengan master lokasi tujuan.
     * Mengembalikan null bila lokasi tidak terdaftar sebagai referensi.
     */
    private function resolveLokasi(?string $lokasi): ?int
    {
        if (! $lokasi) {
            return null;
        }

        return LokasiTujuan::whereRaw('LOWER(nama) = ?', [mb_strtolower($lokasi)])->value('id');
    }
}
