<?php

namespace App\Services;

use App\Enums\StatusUsulan;
use App\Models\PesertaUsulan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap bulanan pegawai yang berangkat perjalanan dinas, untuk keperluan
 * Tim SDM dan pimpinan. Berisi identitas dan jadwal saja — tanpa nominal
 * biaya maupun berkas pertanggungjawaban.
 */
class RekapJadwal
{
    private const LEBAR = [4, 26, 18, 24, 20, 15, 18, 24, 13, 13, 7, 14];

    /**
     * @param  Collection<int, PesertaUsulan>  $peserta
     */
    public function susun(Collection $peserta, string $judulPeriode): PenulisXlsx
    {
        $xlsx = new PenulisXlsx(mb_substr($judulPeriode, 0, 31));
        $xlsx->lebarKolom(self::LEBAR);

        $this->tulisJudul($xlsx, $judulPeriode, $peserta);
        $this->tulisKepala($xlsx);
        $this->tulisIsi($xlsx, $peserta);
        $this->tulisRingkasanUnit($xlsx, $peserta);

        return $xlsx;
    }

    /**
     * @param  Collection<int, PesertaUsulan>  $peserta
     */
    private function tulisJudul(PenulisXlsx $xlsx, string $judulPeriode, Collection $peserta): void
    {
        $fix = $peserta->filter(
            fn (PesertaUsulan $item) => StatusUsulan::dari($item->usulan?->status)->sudahFix()
        );

        $xlsx->baris([['isi' => 'REKAP JADWAL PERJALANAN DINAS PEGAWAI', 'gaya' => 'judul']]);
        $xlsx->baris([['isi' => 'POLITEKNIK KESEHATAN KEMENKES MANADO', 'gaya' => 'subjudul']]);
        $xlsx->baris([['isi' => 'Periode '.$judulPeriode, 'gaya' => 'subjudul']]);

        $xlsx->gabung('A1:L1')->gabung('A2:L2')->gabung('A3:L3');
        $xlsx->tinggiBaris(1, 20)->tinggiBaris(2, 16)->tinggiBaris(3, 16);

        $xlsx->barisKosong();
        $xlsx->baris([
            ['isi' => 'Jumlah pegawai', 'gaya' => 'tebal'],
            ['isi' => $peserta->pluck('nama')->unique()->count(), 'gaya' => 'polos', 'angka' => true],
            ['isi' => 'Jumlah perjalanan', 'gaya' => 'tebal'],
            ['isi' => $peserta->pluck('id_usulan')->unique()->count(), 'gaya' => 'polos', 'angka' => true],
            ['isi' => 'Sudah fix', 'gaya' => 'tebal'],
            ['isi' => $fix->count(), 'gaya' => 'polos', 'angka' => true],
            ['isi' => 'Masih diajukan', 'gaya' => 'tebal'],
            ['isi' => $peserta->count() - $fix->count(), 'gaya' => 'polos', 'angka' => true],
        ]);
    }

    private function tulisKepala(PenulisXlsx $xlsx): void
    {
        $xlsx->barisKosong();

        $xlsx->baris([
            'No.', 'Nama Pegawai', 'NIP', 'Unit Kerja', 'Jabatan', 'Peran',
            'No. Pengajuan', 'Tujuan / Instansi', 'Berangkat', 'Kembali', 'Hari', 'Kepastian',
        ], 'kepala');

        $xlsx->tinggiBaris($xlsx->jumlahBaris(), 24);
        $xlsx->bekukan($xlsx->jumlahBaris());
    }

    /**
     * @param  Collection<int, PesertaUsulan>  $peserta
     */
    private function tulisIsi(PenulisXlsx $xlsx, Collection $peserta): void
    {
        if ($peserta->isEmpty()) {
            $xlsx->baris([['isi' => 'Tidak ada keberangkatan pada periode ini.', 'gaya' => 'teks']]);

            return;
        }

        foreach ($peserta->values() as $urutan => $item) {
            $usulan = $item->usulan;
            $status = StatusUsulan::dari($usulan?->status);

            $mulai = $usulan?->tanggal_mulai ? Carbon::parse($usulan->tanggal_mulai) : null;
            $selesai = $usulan?->tanggal_selesai ? Carbon::parse($usulan->tanggal_selesai) : null;

            $xlsx->baris([
                ['isi' => $urutan + 1, 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $item->nama, 'gaya' => 'teks'],
                ['isi' => $item->nip ?: '—', 'gaya' => 'teks'],
                ['isi' => $item->user?->unit?->nama ?? '—', 'gaya' => 'teks'],
                ['isi' => $item->jabatan ?: '—', 'gaya' => 'teks'],
                ['isi' => ucfirst((string) $item->peran), 'gaya' => 'teks-tengah'],
                ['isi' => $usulan?->no_usulan ?? '—', 'gaya' => 'teks-tengah'],
                ['isi' => trim(($usulan?->lokasi ?? '—').' — '.($usulan?->instansi ?? '')), 'gaya' => 'teks'],
                ['isi' => $mulai?->format('d/m/Y') ?? '—', 'gaya' => 'teks-tengah'],
                ['isi' => $selesai?->format('d/m/Y') ?? '—', 'gaya' => 'teks-tengah'],
                ['isi' => $mulai && $selesai ? $mulai->diffInDays($selesai) + 1 : '', 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $status->sudahFix() ? 'Fix' : 'Diajukan', 'gaya' => 'teks-tengah'],
            ]);
        }
    }

    /**
     * Ringkasan per unit kerja, supaya pimpinan langsung melihat sebarannya.
     *
     * @param  Collection<int, PesertaUsulan>  $peserta
     */
    private function tulisRingkasanUnit(PenulisXlsx $xlsx, Collection $peserta): void
    {
        if ($peserta->isEmpty()) {
            return;
        }

        $xlsx->barisKosong(2);
        $xlsx->baris([['isi' => 'REKAP PER UNIT KERJA', 'gaya' => 'tebal']]);
        $xlsx->baris(['Unit Kerja', 'Pegawai', 'Perjalanan'], 'kepala');

        $perUnit = $peserta
            ->groupBy(fn (PesertaUsulan $item) => $item->user?->unit?->nama ?? 'Tanpa Unit')
            ->map(fn (Collection $baris) => [
                'pegawai' => $baris->pluck('nama')->unique()->count(),
                'perjalanan' => $baris->pluck('id_usulan')->unique()->count(),
            ])
            ->sortByDesc('pegawai');

        foreach ($perUnit as $unit => $angka) {
            $xlsx->baris([
                ['isi' => $unit, 'gaya' => 'teks'],
                ['isi' => $angka['pegawai'], 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $angka['perjalanan'], 'gaya' => 'teks-tengah', 'angka' => true],
            ]);
        }
    }
}
