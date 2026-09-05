<?php

namespace App\Http\Controllers;

use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\SuratPerjalananDinas;
use App\Models\Usulan;
use App\Services\AntreanPeran;
use App\Services\PenagihDokumen;
use App\Services\PengingatDokumen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Halaman muka: apa yang menunggu tindakan pengguna, lalu perjalanan
 * dinasnya sendiri — jadwalnya, tagihan berkasnya, dan pembayarannya.
 */
class DashboardController extends Controller
{
    /** Perjalanan belum dimulai. */
    private const FASE_AKAN = 'akan';

    /** Perjalanan sedang berlangsung hari ini. */
    private const FASE_BERJALAN = 'berjalan';

    /** Perjalanan sudah berakhir, berkas pertanggungjawaban belum lengkap. */
    private const FASE_LAPORAN = 'laporan';

    /** Perjalanan berakhir dan berkasnya sudah lengkap. */
    private const FASE_BERES = 'beres';

    public function __construct(
        private PengingatDokumen $pengingat,
        private PenagihDokumen $penagih,
        private AntreanPeran $antrean,
    ) {}

    public function __invoke()
    {
        $user = Auth::user();

        $myUsulan = Usulan::where('id_user', $user->id);

        $totalUsulan = (clone $myUsulan)->count();
        $selesai = (clone $myUsulan)->where('status', StatusUsulan::Selesai->value)->count();

        $recentUsulan = Usulan::with('kegiatan')
            ->where('id_user', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        // Perjalanan yang sudah pasti berlangsung. Satu kueri dipakai untuk
        // tiga hal sekaligus: jadwal, tagihan berkas, dan status pembayaran.
        //
        // Dashboard memantau yang sedang berjalan, bukan mengarsipkan. Tanpa
        // batas tahun, perjalanan lama yang sudah tuntas ikut dimuat terus dan
        // dashboard bertambah lambat tiap tahun tanpa pernah kembali ringan.
        // Yang belum selesai tetap ditarik berapa pun umurnya — justru itu
        // yang perlu dilihat.
        //
        // Perbandingan untainya langsung, bukan whereYear(), karena kolomnya
        // bertipe teks berformat Y-m-d: cara ini memberi hasil yang sama pada
        // SQLite maupun MySQL dan masih dapat memakai indeks.
        $tahunPantau = (int) today()->year;

        $perjalanan = Usulan::with([
            'dokumen', 'keuangan', 'tiket', 'notaTransport', 'laporan', 'kategoriPerjadin',
        ])
            ->where('id_user', $user->id)
            ->whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->whereNotNull('tanggal_mulai')
            ->where(fn ($query) => $query
                ->where('tanggal_mulai', '>=', $tahunPantau.'-01-01')
                ->orWhere('status', StatusUsulan::Disetujui->value))
            ->orderBy('tanggal_mulai')
            ->get()
            ->each(fn (Usulan $item) => $this->tandaiPemantauan($item));

        $akanBerangkat = $this->fase($perjalanan, self::FASE_AKAN);
        $sedangBerjalan = $this->fase($perjalanan, self::FASE_BERJALAN);

        $perluLaporan = $this->fase($perjalanan, self::FASE_LAPORAN)
            ->sortBy('sisa_hari_laporan')
            ->values();

        $laporanTerlambat = $perluLaporan
            ->filter(fn (Usulan $item) => $item->sisa_hari_laporan < 0)
            ->values();

        // Surat Perjalanan Dinas milik pengguna: yang dibuatnya sendiri atau
        // yang mencantumkan namanya sebagai pelaksana.
        $spdSaya = SuratPerjalananDinas::where(function ($query) use ($user) {
            $query->where('id_pembuat', $user->id)
                ->orWhereHas('pelaksana', fn ($q) => $q->where('id_user', $user->id));
        });

        $totalSpd = (clone $spdSaya)->count();

        $spdTerbaru = (clone $spdSaya)
            ->with('pelaksana')
            ->latest()
            ->limit(3)
            ->get();

        return view('dashboard', compact(
            'user',
            'totalUsulan',
            'selesai',
            'recentUsulan',
            'akanBerangkat',
            'sedangBerjalan',
            'perluLaporan',
            'laporanTerlambat',
            'totalSpd',
            'spdTerbaru',
        ) + [
            'tenggangLaporan' => $this->pengingat->tenggangHari(),
            'tahunPantau' => $tahunPantau,
            // Berkas yang menunggu tindakan peran ini, bukan perjalanannya
            // sendiri. Kosong bagi pelaksana biasa, dan panelnya menghilang.
            'antrean' => $this->antrean->panel($user),
            'pembayaran' => $this->ringkasanPembayaran($perjalanan),
            // Usulan perjadin baru boleh diajukan setelah SPD terbit.
            'bolehMengajukan' => $totalSpd > 0,
        ]);
    }

    /**
     * Lekatkan keterangan pemantauan pada sebuah perjalanan, supaya Blade
     * tidak menghitung ulang tanggal dan kelengkapan berkas per baris.
     */
    private function tandaiPemantauan(Usulan $usulan): void
    {
        $mulai = Carbon::parse($usulan->tanggal_mulai)->startOfDay();
        $akhir = Carbon::parse($usulan->tanggal_selesai ?? $usulan->tanggal_mulai)->startOfDay();

        $usulan->berkas_kurang = $this->penagih->berkasKurang($usulan);
        $usulan->berkas_lengkap = $usulan->berkas_kurang === [];
        $usulan->batas_laporan = $this->pengingat->batasLaporan($usulan);
        $usulan->sisa_hari_laporan = $this->pengingat->sisaHari($usulan);
        $usulan->hari_menuju_berangkat = (int) today()->diffInDays($mulai, absolute: false);

        $usulan->fase = match (true) {
            today()->lt($mulai) => self::FASE_AKAN,
            today()->lte($akhir) => self::FASE_BERJALAN,
            $usulan->berkas_lengkap => self::FASE_BERES,
            default => self::FASE_LAPORAN,
        };
    }

    /**
     * @param  Collection<int, Usulan>  $perjalanan
     * @return Collection<int, Usulan>
     */
    private function fase(Collection $perjalanan, string $fase): Collection
    {
        return $perjalanan->filter(fn (Usulan $item) => $item->fase === $fase)->values();
    }

    /**
     * Sampai mana bendahara memproses pembayaran perjalanan pengguna ini.
     *
     * Perjalanan yang belum punya catatan keuangan dihitung sebagai belum
     * diproses, bukan diabaikan — justru itu yang perlu dipantau pengusul.
     *
     * @param  Collection<int, Usulan>  $perjalanan
     * @return array{belum: int, sebagian: int, lunas: int, total: int}
     */
    private function ringkasanPembayaran(Collection $perjalanan): array
    {
        $status = $perjalanan->map(fn (Usulan $item) => $item->keuangan?->status ?? Keuangan::STATUS_BELUM);

        return [
            'belum' => $status->filter(fn (string $s) => $s === Keuangan::STATUS_BELUM)->count(),
            'sebagian' => $status->filter(fn (string $s) => $s === Keuangan::STATUS_SEBAGIAN)->count(),
            'lunas' => $status->filter(fn (string $s) => $s === Keuangan::STATUS_LUNAS)->count(),
            'total' => $status->count(),
        ];
    }
}
