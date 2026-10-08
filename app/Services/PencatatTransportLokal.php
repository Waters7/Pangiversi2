<?php

namespace App\Services;

use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\Usulan;
use Illuminate\Validation\ValidationException;

/**
 * Transport lokal yang dicatat tim keuangan sendiri.
 *
 * Transport lokal tidak punya tempat di rincian biaya: ia dipertanggung-
 * jawabkan lewat Daftar Pengeluaran Riil pelaksana. Dulu baris rincian yang
 * dipindah ke kategori Transport Lokal tersembunyi dari tabel dan cetakan
 * — tampak hilang — padahal masih menambah total. Kini baris itu benar-benar
 * pindah ke daftar riil: tampil pada tabel Transport Lokal, pada Periksa
 * Transport Lokal, dan tercetak bersama nota pelaksana.
 */
class PencatatTransportLokal
{
    public function __construct(
        private SinkronBiayaDokumen $sinkron,
        private PenguncianBerkas $kunci,
    ) {}

    public function tambah(Usulan $usulan, string $uraian, float $nominal): RincianDaftarRiil
    {
        $this->kunci->pastikanRiilTerbuka($usulan);

        $daftar = $this->sinkron->daftarRiilPelaksana($usulan);

        if (! $daftar) {
            throw ValidationException::withMessages([
                'kategori' => 'Usulan ini belum memiliki pelaksana pada daftar peserta, jadi transport lokalnya belum dapat dicatat.',
            ]);
        }

        $baris = $daftar->rincian()->create([
            'urutan' => (int) $daftar->rincian()->max('urutan') + 1,
            'uraian' => $uraian,
            'nominal' => $nominal,
            'sumber' => RincianDaftarRiil::SUMBER_MANUAL,
        ]);

        $daftar->hitungTotal(paksa: true);

        return $baris;
    }

    /**
     * Pindahkan baris rincian biaya tulisan tim keuangan ke transport lokal.
     *
     * Baris dari berkas pelaksana tidak dipindah: ia lahir dari tiket atau
     * bill hotel dan akan muncul lagi pada penyelarasan berikutnya.
     */
    public function pindahkan(Usulan $usulan, RincianBiaya $baris, string $uraian, float $nominal): RincianDaftarRiil
    {
        if ($baris->dariDokumen()) {
            throw ValidationException::withMessages([
                'kategori' => 'Baris dari berkas pelaksana tidak dapat dipindah ke transport lokal — biaya transport lokal diisi pelaksana sebagai nota transportasi.',
            ]);
        }

        $hasil = $this->tambah($usulan, $uraian, $nominal);

        $baris->forceDelete();
        $usulan->keuangan?->hitungTotal();

        return $hasil;
    }

    /**
     * Hapus baris transport lokal tulisan tim keuangan. Baris dari nota
     * pelaksana diubah lewat notanya, bukan dari sini.
     */
    public function hapus(Usulan $usulan, RincianDaftarRiil $baris): void
    {
        $this->kunci->pastikanRiilTerbuka($usulan);

        abort_if($baris->dariDokumen(), 403, 'Transport lokal dari nota pelaksana diubah lewat notanya.');
        abort_unless($baris->daftarRiil?->id_usulan === $usulan->id, 404);

        $daftar = $baris->daftarRiil;
        $baris->delete();
        $daftar->hitungTotal(paksa: true);
    }
}
