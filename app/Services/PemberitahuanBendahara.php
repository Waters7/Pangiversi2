<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Usulan;

/**
 * Pemberitahuan yang khusus ditujukan kepada bendahara, agar pekerjaan
 * pembayaran tidak perlu dipantau manual dari menu ke menu.
 *
 * Dua kejadian yang diberitahukan: rincian biaya selesai disusun sehingga
 * uang muka 80% siap ditransfer, dan berkas pertanggungjawaban lengkap
 * sehingga pelunasan dapat diproses.
 */
class PemberitahuanBendahara
{
    public function __construct(
        private NotifikasiService $notifikasi,
        private PenagihDokumen $penagih,
    ) {}

    /**
     * Rincian biaya sudah tersusun dan uang mukanya belum cair.
     *
     * Dikirim sekali saja per usulan: bila pemberitahuan yang sama sudah ada
     * dan belum dibaca, tidak diulang agar lonceng bendahara tidak penuh.
     */
    public function rincianSiapDibayar(Usulan $usulan): void
    {
        $keuangan = $usulan->keuangan;

        if (! $keuangan || $keuangan->total <= 0 || $keuangan->uangMukaTerbayar()) {
            return;
        }

        $judul = 'Rincian biaya siap dibayar 80%';

        if ($this->sudahDiberitahu($usulan, $judul)) {
            return;
        }

        $this->kirim(
            $usulan,
            $judul,
            "Rincian biaya {$usulan->no_usulan} atas nama {$usulan->user?->nama} sudah tersusun "
                .'sebesar Rp '.number_format((float) $keuangan->total, 0, ',', '.')
                .'. Uang muka 80% sebesar Rp '.number_format((float) $keuangan->uang_muka, 0, ',', '.')
                .' siap ditransfer.',
            Notifikasi::TIPE_INFO,
        );
    }

    /**
     * Seluruh berkas pertanggungjawaban sudah diunggah pelaksana.
     */
    public function berkasLengkap(Usulan $usulan): void
    {
        if (! $this->penagih->lengkap($usulan)) {
            return;
        }

        $keuangan = $usulan->keuangan;

        if ($keuangan?->sudahLunas()) {
            return;
        }

        $judul = 'Berkas pertanggungjawaban lengkap';

        if ($this->sudahDiberitahu($usulan, $judul)) {
            return;
        }

        $this->kirim(
            $usulan,
            $judul,
            "Seluruh berkas perjalanan dinas {$usulan->no_usulan} atas nama {$usulan->user?->nama} "
                .'sudah lengkap. Pelunasan sisa 20% dapat diproses.',
            Notifikasi::TIPE_SUKSES,
        );
    }

    private function kirim(Usulan $usulan, string $judul, string $pesan, string $tipe): void
    {
        $this->notifikasi->kirimKePeran(
            [User::ROLE_BENDAHARA],
            $judul,
            $pesan,
            [
                'usulan' => $usulan,
                'tipe' => $tipe,
                'url' => route('pembayaran', ['tahap' => 'uang-muka']),
            ],
        );
    }

    private function sudahDiberitahu(Usulan $usulan, string $judul): bool
    {
        return Notifikasi::where('id_usulan', $usulan->id)
            ->where('judul', $judul)
            ->exists();
    }
}
