<?php

namespace App\Http\Controllers;

use App\Enums\JenisAkses;
use App\Enums\Kemampuan;
use App\Enums\MenuAplikasi;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\Peran;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Peran & Hak Akses — submenu Administrasi Sistem bagi super administrator:
 * menambah peran dan mengatur menu mana yang boleh dilihat, diubah, dan
 * dihapus tiap peran.
 */
class PeranController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    public function index(Request $request): View
    {
        // Peran bawaan baru dapat disunting setelah ada di tabel.
        Peran::sinkronBawaan();

        $daftar = Peran::urut();
        $terpilih = $daftar->firstWhere('kode', $request->query('peran')) ?? $daftar->first();

        return view('administrasi.peran', [
            'daftar' => $daftar,
            'terpilih' => $terpilih,
            'menu' => MenuAplikasi::cases(),
            'jenis' => JenisAkses::cases(),
        ]);
    }

    /**
     * Tambah peran baru. Kodenya diturunkan dari nama; hak aksesnya dimulai
     * dari yang paling dasar — mengajukan perjalanan dinas sendiri.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $kode = Peran::kodeDari($data['nama']);

        if ($kode === '' || Peran::where('kode', $kode)->exists()) {
            return back()->withInput()->withErrors(['nama' => 'Nama peran itu sudah dipakai atau tidak dapat dijadikan kode. Pilih nama lain.']);
        }

        $peran = Peran::create([
            'kode' => $kode,
            'nama' => trim($data['nama']),
            'keterangan' => $data['keterangan'] ?? null,
            'bawaan' => false,
        ]);
        $peran->aturHakAkses([Kemampuan::MengajukanUsulan]);

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Peran \"{$peran->nama}\" ({$peran->kode}) ditambahkan.");

        return redirect()
            ->route('administrasi.peran', ['peran' => $peran->kode])
            ->with('success', "Peran \"{$peran->nama}\" ditambahkan. Atur hak aksesnya di bawah, lalu simpan.");
    }

    /**
     * Simpan nama, keterangan, dan matriks hak akses sebuah peran.
     */
    public function update(Request $request, Peran $peran): RedirectResponse
    {
        abort_if($peran->terkunci(), 403, 'Hak akses Super Administrator tidak dapat diubah.');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'kemampuan' => ['nullable', 'array'],
            'kemampuan.*' => [Rule::enum(Kemampuan::class)],
        ]);

        $sebelum = array_map(fn (Kemampuan $k) => $k->value, $peran->kemampuan());
        $sesudah = array_values(array_unique($data['kemampuan'] ?? []));

        $peran->update(['nama' => trim($data['nama']), 'keterangan' => $data['keterangan'] ?? null]);
        $peran->aturHakAkses(array_map(fn (string $nilai) => Kemampuan::from($nilai), $sesudah));

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, $this->uraianPerubahan($peran, $sebelum, $sesudah));

        $diberiTahu = $this->beritahuPemegangPeran($request->user(), $peran, $sebelum, $sesudah);

        return redirect()
            ->route('administrasi.peran', ['peran' => $peran->kode])
            ->with('success', "Hak akses peran \"{$peran->nama}\" tersimpan."
                .($diberiTahu > 0 ? " {$diberiTahu} pengguna peran ini diberi tahu perubahannya." : ''));
    }

    /**
     * Peran buatan boleh dihapus selama tidak ada pengguna yang memakainya.
     */
    public function destroy(Peran $peran): RedirectResponse
    {
        abort_if($peran->bawaan, 403, 'Peran bawaan tidak dapat dihapus.');

        if ($peran->pengguna()->exists()) {
            return back()->withErrors(['peran' => "Peran \"{$peran->nama}\" masih dipakai pengguna. Pindahkan mereka ke peran lain dulu."]);
        }

        $nama = $peran->nama;
        $peran->delete();

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Peran \"{$nama}\" dihapus.");

        return redirect()->route('administrasi.peran')->with('success', "Peran \"{$nama}\" dihapus.");
    }

    /**
     * @param  list<string>  $sebelum
     * @param  list<string>  $sesudah
     */
    /**
     * Pengguna yang memegang peran ini diberi tahu hak akses apa saja yang
     * ditambahkan dan dicabut, beserta menunya — tanpa itu mereka tidak tahu
     * mengapa menunya berubah, atau bahwa ada menu yang kini terbuka.
     *
     * @param  list<string>  $sebelum
     * @param  list<string>  $sesudah
     * @return int Jumlah pengguna yang diberi tahu.
     */
    private function beritahuPemegangPeran(User $pengubah, Peran $peran, array $sebelum, array $sesudah): int
    {
        $uraikan = fn (array $nilai) => implode('; ', array_map(function (string $kode): string {
            $kemampuan = Kemampuan::from($kode);
            $menu = MenuAplikasi::untuk($kemampuan);

            return $kemampuan->label().($menu ? " (menu {$menu->label()})" : '');
        }, array_values($nilai)));

        $ditambah = array_diff($sesudah, $sebelum);
        $dicabut = array_diff($sebelum, $sesudah);

        if ($ditambah === [] && $dicabut === []) {
            return 0;
        }

        $pesan = "Hak akses peran {$peran->nama} diperbarui oleh {$pengubah->nama}.";

        if ($ditambah !== []) {
            $pesan .= ' Ditambahkan: '.$uraikan($ditambah).'.';
        }

        if ($dicabut !== []) {
            $pesan .= ' Dicabut: '.$uraikan($dicabut).'.';
        }

        $pesan .= ' Perubahan langsung berlaku; muat ulang halaman bila menunya belum berubah.';

        return $this->notifikasi->kirimKePeran(
            [$peran->kode],
            'Hak akses Anda diperbarui',
            $pesan,
            ['tipe' => Notifikasi::TIPE_INFO, 'url' => route('dashboard')],
        )->count();
    }

    private function uraianPerubahan(Peran $peran, array $sebelum, array $sesudah): string
    {
        $label = fn (string $nilai) => Kemampuan::from($nilai)->label();
        $ditambah = array_map($label, array_values(array_diff($sesudah, $sebelum)));
        $dicabut = array_map($label, array_values(array_diff($sebelum, $sesudah)));

        $bagian = [];
        if ($ditambah !== []) {
            $bagian[] = 'ditambah: '.implode(', ', $ditambah);
        }
        if ($dicabut !== []) {
            $bagian[] = 'dicabut: '.implode(', ', $dicabut);
        }

        return "Hak akses peran \"{$peran->nama}\" disimpan".($bagian !== [] ? ' — '.implode('; ', $bagian) : ' tanpa perubahan hak akses').'.';
    }
}
