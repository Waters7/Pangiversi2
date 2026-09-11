<?php

namespace Tests;

use App\Enums\ArahTiket;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\LaporanPerjadin;
use App\Models\SpdPelaksana;
use App\Models\StatusHasil;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Terbitkan Surat Perjalanan Dinas untuk seorang pegawai.
     *
     * Usulan perjalanan dinas baru boleh diajukan setelah SPD-nya terbit,
     * karena SPD adalah dasar penugasannya. Pengujian yang menyoroti hal
     * lain memakai pembantu ini agar syarat itu terpenuhi tanpa mengulang
     * penyiapan yang sama di banyak berkas.
     */
    protected function terbitkanSpd(User $pengguna): SuratPerjalananDinas
    {
        $spd = SuratPerjalananDinas::create([
            'id_pembuat' => $pengguna->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => today(),
            'maksud' => 'Penugasan kedinasan.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today()->addWeek(),
            'tanggal_kembali' => today()->addWeek()->addDays(2),
            'lama_hari' => 3,
        ]);

        $spd->pelaksana()->create([
            'urutan' => 1,
            'id_user' => $pengguna->id,
            'nomor_surat' => SpdPelaksana::rakitNomor((string) $spd->id, now()->year),
            'nama' => $pengguna->nama,
            'nip' => $pengguna->nip,
        ]);

        return $spd;
    }

    /**
     * SPD milik seorang pegawai, diterbitkan bila ia memang belum punya.
     *
     * Formulir usulan mewajibkan pengusul menunjuk SPD yang mendasari
     * perjalanannya, jadi pengujian yang menyoroti hal lain memakai pembantu
     * ini untuk melengkapi isian tanpa menyalin penyiapan yang sama.
     */
    protected function spdMilik(User $pengguna): SuratPerjalananDinas
    {
        return SuratPerjalananDinas::whereHas('pelaksana', fn ($q) => $q->where('id_user', $pengguna->id))
            ->first() ?? $this->terbitkanSpd($pengguna);
    }

    /**
     * Lengkapi seluruh pertanggungjawaban sebuah perjalanan.
     *
     * Kelengkapan tidak lagi sekadar mengisi kolom berkas: tiket pergi dan
     * pulang, nota transportasi, rincian bill hotel, serta laporan yang sudah
     * diselesaikan ikut menentukan. Pengujian yang menyoroti hal lain memakai
     * pembantu ini agar tidak menyalin penyiapan yang sama.
     */
    protected function lengkapiPertanggungjawaban(Usulan $usulan): Usulan
    {
        Dokumen::updateOrCreate(
            ['id_usulan' => $usulan->id],
            array_fill_keys(Usulan::DOKUMEN_LPJ_WAJIB, 'dokumen/berkas.pdf') + [
                'surat_tugas' => 'dokumen/berkas.pdf',
                'bill_hotel_no_transaksi' => 'TRX-0001',
                'bill_hotel_nominal' => 850_000,
            ],
        );

        foreach (ArahTiket::urutan() as $arah) {
            $usulan->tiket()->updateOrCreate(['arah' => $arah->value], [
                'kota_asal' => 'Manado',
                'kota_tujuan' => 'Jakarta',
                'nomor_tiket' => 'TKT-'.mb_strtoupper($arah->value),
                'kode_booking' => 'ABC123',
                'harga' => 2_500_000,
                'boarding_pass' => 'dokumen/boarding-pass.pdf',
                'invoice' => 'dokumen/invoice-tiket.pdf',
            ]);
        }

        $usulan->notaTransport()->updateOrCreate(['urutan' => 1], ['nominal' => 150_000, 'bukti' => 'dokumen/nota-1.pdf']);

        $laporan = LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);
        $laporan->kegiatan()->firstOrCreate(['urutan' => 1], [
            'tanggal' => $usulan->tanggal_mulai,
            'uraian' => 'Mengikuti rapat koordinasi.',
        ]);
        $laporan->tindakLanjut()->firstOrCreate(['urutan' => 1], ['uraian' => 'Menyusun laporan internal.']);
        $laporan->update([
            'diselesaikan_at' => now(),
            'id_status_hasil' => StatusHasil::first()?->id,
        ]);

        return $usulan->fresh(['dokumen', 'tiket', 'notaTransport', 'laporan']);
    }

    /**
     * Tim keuangan menyatakan seluruh nominal sudah diperiksa.
     *
     * Dua hal terpisah: baris rincian biaya yang berasal dari dokumen, dan
     * transport lokal pada daftar riilnya. Keduanya menahan pengiriman
     * berkas ke pelaksana, jadi pengujian yang menyoroti tahap sesudahnya
     * memakai pembantu ini agar tidak menyalin penyiapan yang sama.
     */
    protected function validasiSeluruhNominal(Usulan $usulan, ?User $validator = null): void
    {
        $validator ??= User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $usulan->fresh('keuangan')->keuangan?->rincianBiaya()
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $validator->id]);

        DaftarRiil::where('id_usulan', $usulan->id)
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $validator->id]);
    }

    /**
     * Tandatangani berkas pertanggungjawaban sebuah usulan oleh kedua pihak.
     *
     * Sejak alur baru, daftar nominatif hanya terbit setelah rincian biaya
     * dan daftar pengeluaran riil disahkan pelaksana lalu PPK.
     */
    protected function tandatanganiBerkas(Usulan $usulan, ?User $ppk = null): DaftarRiil
    {
        $peserta = $usulan->peserta()->first() ?? $usulan->peserta()->create([
            'id_user' => $usulan->id_user,
            'nama' => $usulan->user?->nama ?? 'Pelaksana',
            'nip' => $usulan->user?->nip,
            'peran' => 'ketua',
        ]);

        $daftar = DaftarRiil::firstOrCreate(
            ['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id],
            ['total_riil' => 0],
        );

        $penandatangan = $ppk ?? User::factory()->ppk()->create();

        $daftar->update([
            'dikirim_ke_pegawai_at' => now()->subDays(2),
            'batas_sanggah' => today()->addDays(3),
            'disetujui_pegawai_at' => now()->subDay(),
            'ditandatangani_at' => now(),
            'id_ppk' => $penandatangan->id,
            'rincian_disetujui_at' => now()->subDay(),
            'rincian_ditandatangani_at' => now(),
            'rincian_id_ppk' => $penandatangan->id,
        ]);

        return $daftar->fresh();
    }

    /**
     * Laporan perjalanan dinas dikirim pelaksana dan dikonfirmasi pimpinan —
     * satu-satunya syarat pelunasan; daftar nominatif tidak menahannya.
     */
    protected function konfirmasiLaporan(Usulan $usulan, ?User $pimpinan = null): LaporanPerjadin
    {
        $laporan = LaporanPerjadin::firstOrCreate(['id_usulan' => $usulan->id]);

        if (! $laporan->sudahSelesai()) {
            $laporan->update(['diselesaikan_at' => now()]);
        }

        $laporan->kirim();
        $laporan->konfirmasi($pimpinan ?? User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'jabatan' => 'Direktur',
        ]));

        return $laporan->fresh();
    }

    /**
     * Terbitkan daftar nominatif surat tugas sebuah usulan, tandatangani,
     * lalu kirimkan ke tim keuangan.
     *
     * Daftarnya hanya memuat pelaksana yang berkasnya sudah disahkan PPK,
     * jadi berkas usulan ini ikut ditandatangani bila belum.
     */
    protected function terbitkanNominatif(Usulan $usulan, ?User $ppk = null): ?DaftarNominatif
    {
        if (! $usulan->no_tugas) {
            return null;
        }

        $penandatangan = $ppk ?? User::factory()->ppk()->create();

        $daftar = DaftarRiil::firstWhere('id_usulan', $usulan->id);

        if (! $daftar?->sudah_ditandatangani || ! $daftar->jalurRincian()->sudahDitandatangani()) {
            $this->tandatanganiBerkas($usulan, $penandatangan);
        }

        return DaftarNominatif::updateOrCreate(
            ['no_tugas' => $usulan->no_tugas],
            [
                'id_ppk' => $penandatangan->id,
                'ditandatangani_at' => now(),
                'dikirim_at' => now(),
            ],
        );
    }
}
