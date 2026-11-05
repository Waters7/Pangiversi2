<?php

use App\Enums\KategoriBiaya;
use App\Models\DaftarRiil;
use App\Models\RincianBiaya;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Daftar pengeluaran riil kini hanya untuk perjalanan luar kota lintas
 * pulau; jalur lain memasukkan transport lokalnya ke rincian biaya.
 *
 * Transport lokal jalur lain yang berkasnya belum disikapi siapa pun
 * dipindahkan ke rincian biaya. Yang sudah ditandatangani pelaksana atau
 * PPK pada salah satu dokumennya dibiarkan: nominalnya sudah disetujui, jadi
 * berkas itu menyelesaikan alurnya seperti semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pindahkan();
    }

    public function pindahkan(): void
    {
        DaftarRiil::with('rincian', 'usulan.keuangan', 'usulan.kategoriPerjadin')
            ->where('total_riil', '>', 0)
            ->whereNull('disetujui_pegawai_at')
            ->whereNull('ditandatangani_at')
            ->whereNull('rincian_disetujui_at')
            ->whereNull('rincian_ditandatangani_at')
            ->get()
            ->filter(fn (DaftarRiil $daftar) => $daftar->usulan?->keuangan && ! $daftar->usulan->memakaiDaftarRiil())
            ->each(fn (DaftarRiil $daftar) => DB::transaction(fn () => $this->pindahkanSatu($daftar)));
    }

    private function pindahkanSatu(DaftarRiil $daftar): void
    {
        $keuangan = $daftar->usulan->keuangan;

        foreach ($daftar->rincian as $baris) {
            $keuangan->rincianBiaya()->create([
                'kategori' => KategoriBiaya::TransportLokalRincian->value,
                'komponen' => $baris->uraian,
                'volume' => 1,
                'satuan' => 'OK',
                'harga_satuan' => $baris->nominal,
                'jumlah' => $baris->nominal,
                'sumber' => $baris->dariDokumen() ? RincianBiaya::SUMBER_DOKUMEN : RincianBiaya::SUMBER_KEUANGAN,
                'kunci_sumber' => $baris->dariDokumen() ? $baris->kunci_sumber : null,
                'divalidasi_at' => $baris->dariDokumen() ? $daftar->divalidasi_at : null,
                'id_validator' => $baris->dariDokumen() ? $daftar->id_validator : null,
            ]);
        }

        $daftar->rincian()->delete();
        $daftar->update(['total_riil' => 0, 'divalidasi_at' => null, 'id_validator' => null]);
        $keuangan->hitungTotal();
    }

    /**
     * Tidak dikembalikan: baris yang sudah menjadi rincian biaya tidak lagi
     * terbedakan dari yang dicatat tim keuangan sesudahnya.
     */
    public function down(): void {}
};
