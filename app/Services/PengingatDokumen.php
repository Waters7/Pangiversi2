<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Mengingatkan pelaksana perjalanan dinas yang berkas pertanggungjawabannya
 * belum lengkap, sesuai tenggang dan jeda yang diatur Super Administrator
 * pada menu Administrasi Sistem.
 */
class PengingatDokumen
{
    public function __construct(
        private NotifikasiService $notifikasi,
        private PenagihDokumen $penagih,
    ) {}

    /**
     * Kirim pengingat yang jatuh tempo hari ini.
     *
     * @return array{terkirim: int, dilewati: int}
     */
    public function jalankan(): array
    {
        if (! Pengaturan::aktif(Pengaturan::PENGINGAT_AKTIF)) {
            return ['terkirim' => 0, 'dilewati' => 0];
        }

        $kandidat = $this->kandidat();
        $terkirim = 0;

        foreach ($kandidat as $usulan) {
            if (! $this->sudahWaktunya($usulan)) {
                continue;
            }

            $this->ingatkan($usulan);
            $terkirim++;
        }

        return ['terkirim' => $terkirim, 'dilewati' => $kandidat->count() - $terkirim];
    }

    /**
     * Perjalanan yang sudah berakhir namun berkasnya belum lengkap.
     *
     * @return Collection<int, Usulan>
     */
    public function kandidat(): Collection
    {
        $tenggang = $this->tenggangHari();

        return Usulan::with('user', 'dokumen')
            ->whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->whereNotNull('id_user')
            ->whereDate('tanggal_selesai', '<=', today()->subDays($tenggang))
            ->get()
            ->reject(fn (Usulan $usulan) => $this->penagih->lengkap($usulan))
            ->values();
    }

    /**
     * Tenggang hari sejak perjalanan berakhir sampai berkas wajib lengkap.
     *
     * Nilainya diatur pada menu Administrasi Sistem — bawaannya 3 hari, itulah
     * "H+3" yang ditagihkan. Dashboard dan penagih notifikasi membaca angka
     * yang sama supaya batas yang ditampilkan tidak berbeda dari yang ditagih.
     */
    public function tenggangHari(): int
    {
        return max(0, Pengaturan::angka(Pengaturan::PENGINGAT_HARI));
    }

    /**
     * Tanggal paling lambat berkas pertanggungjawaban harus lengkap.
     */
    public function batasLaporan(Usulan $usulan): ?Carbon
    {
        return $usulan->tanggal_selesai
            ? Carbon::parse($usulan->tanggal_selesai)->startOfDay()->addDays($this->tenggangHari())
            : null;
    }

    /**
     * Sisa hari menuju batas laporan. Negatif berarti sudah lewat tenggang.
     */
    public function sisaHari(Usulan $usulan): ?int
    {
        $batas = $this->batasLaporan($usulan);

        return $batas ? (int) today()->diffInDays($batas, absolute: false) : null;
    }

    /**
     * Sudah lewat tenggang, belum melebihi batas, dan jeda ulangnya terpenuhi.
     */
    public function sudahWaktunya(Usulan $usulan): bool
    {
        $maksimal = Pengaturan::angka(Pengaturan::PENGINGAT_MAKS);

        if ($maksimal > 0 && $usulan->pengingat_terkirim >= $maksimal) {
            return false;
        }

        if ($usulan->pengingat_terakhir_at === null) {
            return true;
        }

        $jeda = max(1, Pengaturan::angka(Pengaturan::PENGINGAT_ULANG));

        return Carbon::parse($usulan->pengingat_terakhir_at)->addDays($jeda)->startOfDay()->isPast();
    }

    private function ingatkan(Usulan $usulan): void
    {
        $kurang = $this->penagih->berkasKurang($usulan);
        $selesai = Carbon::parse($usulan->tanggal_selesai);
        $hari = (int) $selesai->diffInDays(today(), absolute: true);

        $daftar = collect($kurang)->take(4)->implode(', ');
        if (count($kurang) > 4) {
            $daftar .= ', dan '.(count($kurang) - 4).' berkas lainnya';
        }

        $this->notifikasi->kirim(
            $usulan->user,
            'Berkas pertanggungjawaban belum lengkap',
            "Perjalanan dinas {$usulan->no_usulan} ke {$usulan->lokasi} berakhir {$hari} hari lalu. "
                ."Berkas yang belum diunggah: {$daftar}. Lengkapi agar pembayaran dapat diproses.",
            [
                'usulan' => $usulan,
                'tipe' => Notifikasi::TIPE_PERINGATAN,
                'url' => route('dokumen.show', $usulan->no_usulan),
            ],
        );

        $usulan->update([
            'pengingat_terakhir_at' => now(),
            'pengingat_terkirim' => $usulan->pengingat_terkirim + 1,
        ]);
    }
}
