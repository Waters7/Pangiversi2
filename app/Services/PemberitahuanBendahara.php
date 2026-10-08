<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Usulan;

/**
 * Pemberitahuan yang khusus ditujukan kepada bendahara, agar pekerjaan
 * pembayaran tidak perlu dipantau manual dari menu ke menu.
 *
 * Kejadian yang diberitahukan: rincian biaya selesai disusun sehingga uang
 * muka 80% siap ditransfer, berkas pertanggungjawaban lengkap sehingga
 * pelunasan dapat diproses, dan berkas lengkap yang sudah ditandatangani
 * PPK sehingga perjadin tinggal dibayarkan.
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

        // Bila PPK sudah menandatangani lebih dulu, berkas yang baru lengkap
        // ini berarti perjadin siap dibayarkan — kabar yang lebih tegas itu
        // saja yang dikirim, bukan dua pemberitahuan sekaligus.
        if ($this->disahkanPpk($usulan)) {
            $this->siapDilunasi($usulan);

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

    /**
     * Berkas pertanggungjawaban lengkap dan kedua dokumennya — rincian biaya
     * dan daftar pengeluaran riil — sudah ditandatangani PPK: perjadin ini
     * tinggal dibayarkan.
     *
     * Dipanggil dari dua arah, sebab yang terakhir terpenuhi bisa salah
     * satunya: tanda tangan PPK, atau berkas yang baru lengkap (biasanya
     * laporan yang dikonfirmasi pimpinan). Dikirim sekali per usulan.
     */
    public function siapDilunasi(Usulan $usulan): void
    {
        $usulan->loadMissing('keuangan', 'daftarRiil', 'user');
        $keuangan = $usulan->keuangan;

        if (! $keuangan || $keuangan->sudahLunas()) {
            return;
        }

        if (! $this->disahkanPpk($usulan) || ! $this->penagih->lengkap($usulan)) {
            return;
        }

        $judul = 'Perjadin lengkap, segera dibayarkan';

        if ($this->sudahDiberitahu($usulan, $judul)) {
            return;
        }

        $belumDibayar = $keuangan->uangMukaTerbayar() ? (float) $keuangan->sisa : (float) $keuangan->total;
        $transportLokal = (float) $usulan->daftarRiil->reject->sudahDibayar()->sum('total_riil');

        $this->kirim(
            $usulan,
            $judul,
            "Perjalanan dinas {$usulan->no_usulan} atas nama {$usulan->user?->nama} sudah lengkap: seluruh berkas "
                .'pertanggungjawaban terunggah, dan rincian biaya serta daftar pengeluaran riilnya telah ditandatangani PPK. '
                .'Segera bayarkan '.($keuangan->uangMukaTerbayar() ? 'sisa pembayaran' : 'pembayarannya')
                .' sebesar Rp '.number_format($belumDibayar, 0, ',', '.')
                .($transportLokal > 0 ? ' ditambah transport lokal Rp '.number_format($transportLokal, 0, ',', '.') : '')
                .'.',
            Notifikasi::TIPE_PERINGATAN,
            route('keuangan.detail', $usulan->no_usulan),
        );
    }

    private function kirim(Usulan $usulan, string $judul, string $pesan, string $tipe, ?string $url = null): void
    {
        $this->notifikasi->kirimKePeran(
            [User::ROLE_BENDAHARA],
            $judul,
            $pesan,
            [
                'usulan' => $usulan,
                'tipe' => $tipe,
                'url' => $url ?? route('pembayaran', ['tahap' => 'uang-muka']),
            ],
        );
    }

    /**
     * Rincian biaya dan daftar pengeluaran riil seluruh peserta sudah
     * ditandatangani PPK.
     */
    private function disahkanPpk(Usulan $usulan): bool
    {
        $usulan->loadMissing('daftarRiil');

        return $usulan->daftarRiil->isNotEmpty()
            && $usulan->daftarRiil->every(fn ($daftar) => $daftar->disahkanPpkSeluruhnya());
    }

    private function sudahDiberitahu(Usulan $usulan, string $judul): bool
    {
        return Notifikasi::where('id_usulan', $usulan->id)
            ->where('judul', $judul)
            ->exists();
    }
}
