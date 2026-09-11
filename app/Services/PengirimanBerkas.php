<?php

namespace App\Services;

use App\Enums\KategoriBiaya;
use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Notifikasi;
use App\Models\PesertaUsulan;
use App\Models\Usulan;

/**
 * Mengirim rincian biaya dan daftar pengeluaran riil kepada pelaksana.
 *
 * Berkas berjalan ke pelaksana begitu tim keuangan selesai memeriksanya —
 * seluruh nominal rincian divalidasi dan transport lokalnya divalidasi —
 * tanpa menunggu tombol kirim ditekan terpisah. Dulu langkah itu manual,
 * dan berkas kerap berhenti di "sudah dicek" tanpa pernah sampai ke meja
 * pelaksana, sehingga tombol tanda tangannya tidak pernah muncul.
 *
 * Tombol kirim tetap ada untuk mengirim ulang setelah sanggahan atau
 * pengembalian PPK; keduanya memakai jalur yang sama di sini.
 */
class PengirimanBerkas
{
    public function __construct(
        private SinkronBiayaDokumen $sinkron,
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    /**
     * Alasan berkas belum dapat dikirim, atau null bila sudah siap.
     */
    public function alasanBelumSiap(Usulan $usulan, DaftarRiil $daftar): ?string
    {
        if ($daftar->sudah_ditandatangani || $daftar->jalurRincian()->sudahDitandatangani()) {
            return 'Berkas sudah ditandatangani PPK. Mintalah PPK mencabut tanda tangannya lebih dulu.';
        }

        if ($this->totalRincian($usulan) <= 0 && $daftar->total_riil <= 0) {
            return 'Belum ada nominal yang dapat dikirim. Periksa rincian biayanya lebih dulu.';
        }

        // Seluruh nominal dari dokumen pelaksana harus sudah diperiksa. Tanpa
        // penjagaan ini, pelaksana diminta menandatangani angka yang belum
        // tentu benar.
        $menunggu = $this->sinkron->menungguValidasi($usulan);

        if ($menunggu->isNotEmpty()) {
            return "Masih ada {$menunggu->count()} nominal yang belum divalidasi. "
                .'Periksa rincian biayanya lebih dulu sebelum dikirim ke pelaksana.';
        }

        // Transport lokal diperiksa terpisah: ia tidak masuk rincian biaya,
        // jadi tidak ikut terhitung pada penjagaan di atas.
        if (! $daftar->sudahDivalidasi()) {
            return 'Transport lokal belum divalidasi. Periksa notanya pada menu Keuangan → Transport Lokal.';
        }

        return null;
    }

    /**
     * Kirim berkas kepada pelaksana: membuka masa sanggah, mencatat jejak,
     * dan memberi tahu pelaksananya.
     */
    public function kirim(Usulan $usulan, PesertaUsulan $peserta, DaftarRiil $daftar, bool $otomatis = false): void
    {
        $daftar->kirimKePegawai();
        $daftar->refresh();

        $total = $this->totalRincian($usulan);

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Berkas pertanggungjawaban {$peserta->nama} pada usulan {$usulan->no_usulan} dikirim untuk diperiksa"
                .($otomatis ? ' — otomatis, seluruh nominalnya sudah divalidasi' : '')
                .". Masa sanggah sampai {$daftar->batas_sanggah->translatedFormat('d F Y')}.",
            ['usulan' => $usulan],
        );

        if ($peserta->user) {
            $this->notifikasi->kirim(
                $peserta->user,
                'Berkas pertanggungjawaban menunggu tanda tangan Anda',
                'Rincian biaya (Rp '.number_format($total, 0, ',', '.').') dan daftar pengeluaran riil (Rp '
                    .number_format($daftar->total_riil, 0, ',', '.').') perjalanan dinas '.$usulan->no_usulan
                    .' menunggu persetujuan Anda. Bila nominalnya tidak sesuai, ajukan sanggahan paling lambat '
                    .$daftar->batas_sanggah->translatedFormat('d F Y').'.',
                [
                    'usulan' => $usulan,
                    'tipe' => Notifikasi::TIPE_PERINGATAN,
                    'url' => route('rincian-saya.daftar-riil'),
                ],
            );
        }
    }

    /**
     * Kirim sendiri begitu seluruh pemeriksaan tim keuangan rampung.
     *
     * Dipanggil setelah tiap validasi. Berkas yang sudah pernah dikirim
     * tidak dikirim ulang dari sini — mengirim ulang mencabut sikap
     * pelaksana, dan itu keputusan yang harus diambil sengaja lewat tombol.
     *
     * @return bool Berkas benar-benar terkirim pada panggilan ini.
     */
    public function kirimBilaSiap(Usulan $usulan): bool
    {
        $peserta = $usulan->peserta()->where('id_user', $usulan->id_user)->first()
            ?? $usulan->peserta()->orderBy('id')->first();

        if (! $peserta) {
            return false;
        }

        $daftar = DaftarRiil::firstOrCreate(
            ['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id],
            ['total_riil' => 0],
        );

        if ($daftar->sudahDikirimKePegawai()) {
            return false;
        }

        $usulan->load('keuangan.rincianBiaya');

        if ($this->alasanBelumSiap($usulan, $daftar) !== null) {
            return false;
        }

        $this->kirim($usulan, $peserta, $daftar, otomatis: true);

        return true;
    }

    private function totalRincian(Usulan $usulan): float
    {
        return (float) ($usulan->keuangan?->rincianBiaya ?? collect())
            ->reject(fn ($baris) => $baris->kategori === KategoriBiaya::TransportLokal)
            ->sum('jumlah');
    }
}
