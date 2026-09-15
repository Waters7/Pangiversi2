<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ImporPengguna;
use App\Services\PengingatDokumen;
use App\Services\SesiPengguna;
use App\Services\SumberPegawaiCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdministrasiController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Halaman pengguna: daftar akun beserta saringan peran dan kehadiran.
     */
    public function index(Request $request, SesiPengguna $sesi): View
    {
        $search = $request->input('search');
        $roleFilter = $request->input('role');

        $query = User::with(['unit', 'atasan'])->withCount('usulan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }

        // Saringan tambahan: hanya yang sedang aktif, atau yang belum pernah masuk.
        $idAktif = $sesi->idAktif();
        $kehadiran = $request->input('kehadiran');

        $query
            ->when($kehadiran === 'aktif', fn ($q) => $q->whereIn('id', $idAktif->all() ?: [0]))
            ->when($kehadiran === 'belum-pernah', fn ($q) => $q->whereNull('login_terakhir_at'));

        $users = $query->latest()->paginate(10)->appends($request->query());

        $totalUsers = User::count();
        $totalAdmin = User::where('role', PeranPengguna::SuperAdministrator->value)->count();
        $totalPPK = User::where('role', PeranPengguna::Ppk->value)->count();
        $totalPegawai = User::whereIn('role', [
            PeranPengguna::DosenTendik->value,
            PeranPengguna::PegawaiEksternal->value,
            PeranPengguna::Outsourcing->value,
        ])->count();

        // Referensi untuk form penempatan pegawai.
        $unitKerja = UnitKerja::aktif()->orderBy('nama')->get();
        $calonAtasan = User::orderBy('nama')->get(['id', 'nama', 'jabatan']);

        return view('administrasi.pengguna', compact(
            'users',
            'search',
            'roleFilter',
            'kehadiran',
            'idAktif',
            'totalUsers',
            'totalAdmin',
            'totalPPK',
            'totalPegawai',
            'unitKerja',
            'calonAtasan',
        ) + [
            'jumlahAktif' => $idAktif->count(),
            'sesiTersedia' => $sesi->tersedia(),
            'belumPernahMasuk' => User::whereNull('login_terakhir_at')->count(),
            'menitAktif' => SesiPengguna::MENIT_AKTIF,
        ]);
    }

    /**
     * Halaman impor dan ekspor pengguna massal lewat CSV.
     */
    public function massal(): View
    {
        return view('administrasi.massal');
    }

    /**
     * Halaman pengaturan sistem: pengingat kelengkapan berkas dan — bagi
     * super administrator — kunci tanggal dikeluarkan SPD.
     */
    public function pengaturan(PengingatDokumen $pengingat): View
    {
        return view('administrasi.pengaturan', [
            'pengaturan' => Pengaturan::semua(),
            'kandidatPengingat' => $pengingat->kandidat()->count(),
        ]);
    }

    /**
     * Simpan pengaturan pengingat kelengkapan berkas pertanggungjawaban.
     */
    public function simpanPengaturan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pengingat_aktif' => ['nullable', 'boolean'],
            'pengingat_hari' => ['required', 'integer', 'min:0', 'max:90'],
            'pengingat_ulang' => ['required', 'integer', 'min:1', 'max:60'],
            'pengingat_maksimal' => ['required', 'integer', 'min:0', 'max:20'],
        ], [
            'pengingat_hari.max' => 'Tenggang pengingat paling lama 90 hari setelah perjalanan selesai.',
            'pengingat_ulang.min' => 'Jeda pengingat ulang minimal 1 hari.',
            'pengingat_maksimal.max' => 'Batas pengingat paling banyak 20 kali.',
        ]);

        Pengaturan::simpan([
            Pengaturan::PENGINGAT_AKTIF => $request->boolean('pengingat_aktif') ? '1' : '0',
            Pengaturan::PENGINGAT_HARI => $validated['pengingat_hari'],
            Pengaturan::PENGINGAT_ULANG => $validated['pengingat_ulang'],
            Pengaturan::PENGINGAT_MAKS => $validated['pengingat_maksimal'],
        ]);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengaturan pengingat dokumen diperbarui: {$validated['pengingat_hari']} hari setelah perjadin, "
                ."diulang tiap {$validated['pengingat_ulang']} hari, maksimal {$validated['pengingat_maksimal']} kali.",
        );

        return back()->with('success', 'Pengaturan pengingat berhasil disimpan.');
    }

    /**
     * Buka atau kunci tanggal dikeluarkan SPD bagi peran di luar pimpinan.
     *
     * Bawaannya terkunci: tanggal mengikuti hari pembuatan supaya dokumen
     * tidak berselisih dengan kapan surat benar-benar terbit. Untuk kasus
     * tertentu — nomor surat sudah tercatat di buku agenda pada tanggal yang
     * lebih awal — super administrator membukanya sementara, lalu mengunci
     * kembali. Tiap perubahannya tercatat pada jejak audit.
     */
    public function simpanKunciTanggalSpd(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Kunci tanggal SPD hanya diatur super administrator.');

        $terbuka = $request->boolean('tanggal_spd_terbuka');

        Pengaturan::simpan([Pengaturan::TANGGAL_SPD_TERBUKA => $terbuka ? '1' : '0']);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $terbuka
                ? 'Tanggal dikeluarkan SPD dibuka untuk seluruh peran (tanggal mundur diizinkan).'
                : 'Tanggal dikeluarkan SPD dikunci kembali: peran selain pimpinan mengikuti tanggal pembuatan.',
        );

        return back()->with('success', $terbuka
            ? 'Tanggal dikeluarkan SPD dibuka untuk seluruh peran. Jangan lupa mengunci kembali setelah selesai.'
            : 'Tanggal dikeluarkan SPD dikunci kembali.');
    }

    /**
     * Jalankan pengingat sekarang tanpa menunggu penjadwal harian.
     */
    public function jalankanPengingat(PengingatDokumen $pengingat): RedirectResponse
    {
        $hasil = $pengingat->jalankan();

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengingat dokumen dijalankan manual, {$hasil['terkirim']} notifikasi terkirim.",
        );

        return back()->with(
            'success',
            $hasil['terkirim'] > 0
                ? "{$hasil['terkirim']} pengingat dikirim ke pegawai yang berkasnya belum lengkap."
                : 'Tidak ada pengingat yang jatuh tempo hari ini.'
        );
    }

    /**
     * Store a new user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            $this->rules() + ['password' => ['required', 'string', 'min:8', 'confirmed']]
        );

        $user = User::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'] ?? null,
            'nip' => $validated['nip'],
            'role' => $validated['role'],
            'jabatan' => $validated['jabatan'] ?? null,
            'id_unit' => $validated['id_unit'] ?? null,
            'id_atasan' => $validated['id_atasan'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengguna baru \"{$user->nama}\" ({$user->role_label}) ditambahkan.",
        );

        return redirect()->route('administrasi')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Update user profile (nama, email, nip, role, penempatan).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->rules($user));

        // Seorang pegawai tidak boleh menjadi atasan bagi dirinya sendiri.
        if (($validated['id_atasan'] ?? null) == $user->id) {
            $validated['id_atasan'] = null;
        }

        $roleLama = $user->role;
        $user->update($validated);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $roleLama === $user->role
                ? "Data pengguna \"{$user->nama}\" diperbarui."
                : "Peran pengguna \"{$user->nama}\" diubah dari {$roleLama} menjadi {$user->role}.",
        );

        return redirect()->route('administrasi')
            ->with('success', "Data pengguna {$user->nama} berhasil diperbarui.");
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Kata sandi pengguna \"{$user->nama}\" diatur ulang oleh administrator.",
        );

        return redirect()->route('administrasi')
            ->with('success', "Password {$user->nama} berhasil diubah.");
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $nama = $user->nama;
        $user->delete();

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengguna \"{$nama}\" dihapus.",
        );

        return redirect()->route('administrasi')
            ->with('success', "Pengguna {$nama} berhasil dihapus.");
    }

    /**
     * Unduh seluruh pengguna sebagai CSV — sekaligus menjadi template impor.
     */
    public function export(): StreamedResponse
    {
        $namaBerkas = 'pengguna-pangi-'.now()->format('Y-m-d').'.csv';

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, 'Data pengguna diekspor ke CSV.');

        return response()->streamDownload(function (): void {
            $keluaran = fopen('php://output', 'w');

            // BOM agar Excel membaca karakter non-ASCII dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, SumberPegawaiCsv::KOLOM, escape: '\\');

            User::with(['unit:id,kode', 'atasan:id,nip'])
                ->orderBy('nama')
                ->chunk(200, function ($pengguna) use ($keluaran): void {
                    foreach ($pengguna as $item) {
                        fputcsv($keluaran, [
                            $item->nama,
                            $item->nip,
                            $item->email,
                            $item->no_hp,
                            $item->role,
                            $item->jabatan,
                            $item->unit?->kode,
                            $item->atasan?->nip,
                            $item->nama_bank,
                            $item->nomor_rekening,
                            $item->nama_rekening,
                            // Kata sandi tidak pernah ikut diekspor.
                            null,
                        ], escape: '\\');
                    }
                });

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Impor pengguna secara massal dari CSV.
     */
    public function import(Request $request, ImporPengguna $importer): RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'berkas.mimes' => 'Berkas impor harus berformat CSV.',
        ]);

        $sumber = new SumberPegawaiCsv($request->file('berkas'));
        $ringkasan = $importer->jalankan($sumber);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Impor pengguna dari {$sumber->nama()}: {$ringkasan['dibuat']} dibuat, {$ringkasan['diperbarui']} diperbarui, ".count($ringkasan['dilewati']).' dilewati.',
        );

        $pesan = "Impor selesai — {$ringkasan['dibuat']} pengguna baru, {$ringkasan['diperbarui']} diperbarui.";

        if ($ringkasan['dilewati'] !== []) {
            return redirect()->route('administrasi.massal')
                ->with('success', $pesan)
                ->with('impor_dilewati', $ringkasan['dilewati']);
        }

        return redirect()->route('administrasi.massal')->with('success', $pesan);
    }

    /**
     * Validation rules shared by store and update.
     *
     * @return array<string, list<mixed>>
     */
    private function rules(?User $user = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'nip' => ['required', 'string', 'max:50', Rule::unique('users', 'nip')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'id_unit' => ['nullable', 'exists:unit_kerja,id'],
            'id_atasan' => ['nullable', 'exists:users,id'],
        ];
    }
}
