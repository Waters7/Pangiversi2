<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\Usulan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Ekspor Excel: Arsip Daftar Riil, Arsip Rincian Lengkap, List Daftar
 * Nominatif, dan Integrasi Mappalus, masing-masing seluruh baris pada saringan
 * yang sedang dipilih.
 */
class RekapArsipXlsx
{
    /**
     * @param  Collection<int, array{berkas: DaftarRiil, tahap: string, tanggal: ?Carbon}>  $baris
     * @param  array<string, string>  $labelTahap
     */
    public function daftarRiil(Collection $baris, array $labelTahap, string $periode): PenulisXlsx
    {
        return $this->tabel('ARSIP DAFTAR PENGELUARAN RIIL', $periode, [
            ['No.', 5, 'nomor'],
            ['No. Usulan', 22, 'tengah'],
            ['No. Surat Tugas', 28, 'teks'],
            ['Pelaksana', 28, 'teks'],
            ['Tujuan', 20, 'teks'],
            ['Berangkat', 12, 'tengah'],
            ['Tahap', 22, 'tengah'],
            ['Total Riil', 14, 'uang'],
            ['Disahkan PPK', 12, 'tengah'],
            ['Penandatangan', 28, 'teks'],
        ], $baris->map(function (array $item) use ($labelTahap): array {
            $berkas = $item['berkas'];

            return [
                $berkas->usulan?->no_usulan,
                $berkas->usulan?->no_tugas,
                $berkas->peserta?->nama,
                $berkas->usulan?->lokasi,
                $this->tanggal($item['tanggal']),
                $labelTahap[$item['tahap']] ?? $item['tahap'],
                (float) $berkas->total_riil,
                $this->tanggal($berkas->ditandatangani_at),
                $berkas->ppk?->nama,
            ];
        }));
    }

    /**
     * @param  Collection<int, array{berkas: DaftarRiil, usulan: Usulan, total: float, tanggal: ?CarbonInterface}>  $baris
     */
    public function rincianLengkap(Collection $baris, string $periode): PenulisXlsx
    {
        return $this->tabel('ARSIP RINCIAN BIAYA LENGKAP TANDA TANGAN', $periode, [
            ['No.', 5, 'nomor'],
            ['No. Usulan', 22, 'tengah'],
            ['No. Surat Tugas', 28, 'teks'],
            ['Pelaksana', 28, 'teks'],
            ['Tujuan', 20, 'teks'],
            ['Berangkat', 12, 'tengah'],
            ['Lengkap Pada', 12, 'tengah'],
            ['Total Rincian', 14, 'uang'],
            ['Total Riil', 14, 'uang'],
            ['Penandatangan', 28, 'teks'],
        ], $baris->map(fn (array $item) => [
            $item['usulan']->no_usulan,
            $item['usulan']->no_tugas,
            $item['berkas']->peserta?->nama,
            $item['usulan']->lokasi,
            $this->tanggal($item['usulan']->tanggal_mulai),
            $this->tanggal($item['tanggal']),
            (float) $item['total'],
            (float) $item['berkas']->total_riil,
            $item['berkas']->jalurRincian()->ppk()?->nama,
        ]));
    }

    /**
     * Satu baris per pelaksana. Daftar yang belum memuat pelaksana tetap
     * menempati satu baris, supaya tiap surat tugas pada list ikut terbawa.
     *
     * @param  Collection<int, array{nominatif: DaftarNominatif, baris: Collection<int, array<string, mixed>>}>  $daftar
     */
    public function nominatif(Collection $daftar, string $periode): PenulisXlsx
    {
        $isi = $daftar->flatMap(function (array $item): array {
            $nominatif = $item['nominatif'];
            $kepala = [
                $nominatif->no_tugas,
                $this->tanggal($nominatif->tanggal_tugas),
                $nominatif->status_label,
                $nominatif->kategoriPembiayaan?->nama,
                $nominatif->akunPembiayaan?->kode,
                $nominatif->timKerja?->nama,
            ];

            if ($item['baris']->isEmpty()) {
                return [[...$kepala, null, null, null, null, null, null, 0.0, 0.0, 0.0, 0.0, 0.0, null]];
            }

            return $item['baris']->map(fn (array $baris) => [
                ...$kepala,
                $baris['nama'],
                $baris['tujuan'],
                $baris['lamanya'],
                $this->tanggal($baris['berangkat']),
                $this->tanggal($baris['kembali']),
                $baris['maksud'],
                (float) $baris['tiket'],
                (float) $baris['transport'],
                (float) $baris['harian_jumlah'],
                (float) $baris['inap_jumlah'],
                (float) $baris['jumlah'],
                ($baris['usulan']->keuangan ?? new Keuangan)->status_label,
            ])->all();
        });

        return $this->tabel('LIST DAFTAR NOMINATIF', $periode, [
            ['No.', 5, 'nomor'],
            ['No. Surat Tugas', 28, 'teks'],
            ['Tanggal Surat', 12, 'tengah'],
            ['Status Daftar', 28, 'teks'],
            ['Sumber Dana', 14, 'tengah'],
            ['Kode Akun', 11, 'tengah'],
            ['Tim Kerja', 26, 'teks'],
            ['Nama Pelaksana', 28, 'teks'],
            ['Tujuan', 18, 'teks'],
            ['Lamanya (hari)', 9, 'tengah'],
            ['Berangkat', 12, 'tengah'],
            ['Kembali', 12, 'tengah'],
            ['Maksud Perjalanan', 40, 'teks'],
            ['Tiket', 13, 'uang'],
            ['Transport', 13, 'uang'],
            ['Uang Harian', 13, 'uang'],
            ['Uang Penginapan', 13, 'uang'],
            ['Jumlah Pembayaran', 15, 'uang'],
            ['Status Pembayaran', 18, 'tengah'],
        ], $isi);
    }

    /**
     * Data Integrasi Mappalus beserta catatan pengambilannya.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    public function mappalus(Collection $baris): PenulisXlsx
    {
        return $this->tabel('DATA INTEGRASI MAPPALUS', 'Dicetak '.now()->translatedFormat('d F Y, H:i'), [
            ['No.', 5, 'nomor'],
            ['No. Usulan', 22, 'tengah'],
            ['Keterangan', 52, 'teks'],
            ['Tim Kerja', 26, 'teks'],
            ['Kode Akun', 11, 'tengah'],
            ['Total Nominal', 15, 'uang'],
            ['Status', 16, 'tengah'],
            ['Jumlah Diambil', 9, 'tengah'],
            ['Pertama Diambil', 17, 'tengah'],
            ['Terakhir Diambil', 17, 'tengah'],
            ['Dikonfirmasi', 17, 'tengah'],
        ], $baris->map(fn (array $item) => [
            $item['data']['referensi'],
            $item['data']['keterangan'],
            $item['data']['tim_kerja'],
            $item['data']['kode_akun'],
            (float) $item['data']['total_nominal'],
            DataMappalus::LABEL[$item['status']],
            $item['catatan']?->jumlah_diambil ?? 0,
            $item['catatan']?->pertama_diambil_at?->format('d/m/Y H:i'),
            $item['catatan']?->diambil_at?->format('d/m/Y H:i'),
            $item['catatan']?->dikonfirmasi_at?->format('d/m/Y H:i'),
        ]));
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string}>  $kolom  Label, lebar, dan jenis tiap kolom.
     * @param  Collection<int, list<mixed>>  $isi  Nilai tiap baris, tanpa nomor urutnya.
     */
    private function tabel(string $judul, string $periode, array $kolom, Collection $isi): PenulisXlsx
    {
        $xlsx = new PenulisXlsx('Arsip');
        $xlsx->lebarKolom(array_column($kolom, 1));

        $akhir = chr(ord('A') + count($kolom) - 1);

        $xlsx->baris([['isi' => $judul, 'gaya' => 'judul']]);
        $xlsx->baris([['isi' => 'POLITEKNIK KESEHATAN KEMENKES MANADO', 'gaya' => 'subjudul']]);
        $xlsx->baris([['isi' => $periode, 'gaya' => 'subjudul']]);
        $xlsx->gabung("A1:{$akhir}1")->gabung("A2:{$akhir}2")->gabung("A3:{$akhir}3");

        $xlsx->barisKosong();
        $xlsx->baris(array_column($kolom, 0), 'kepala');
        $xlsx->tinggiBaris($xlsx->jumlahBaris(), 24);
        $xlsx->bekukan($xlsx->jumlahBaris());

        $baris = $isi->values();

        foreach ($baris as $urutan => $nilai) {
            $xlsx->baris(array_map(
                fn (mixed $isiSel, array $definisi) => $this->sel($isiSel, $definisi[2]),
                [$urutan + 1, ...$nilai],
                $kolom,
            ));
        }

        $xlsx->baris(array_map(function (array $definisi, int $indeks) use ($baris): array {
            return match (true) {
                $definisi[2] === 'uang' => [
                    'isi' => $baris->sum(fn (array $nilai) => $nilai[$indeks - 1]),
                    'gaya' => 'total',
                    'angka' => true,
                ],
                $indeks === 1 => ['isi' => 'JUMLAH', 'gaya' => 'total-teks'],
                default => ['isi' => '', 'gaya' => 'total-teks'],
            };
        }, $kolom, array_keys($kolom)));

        return $xlsx;
    }

    /**
     * @return array<string, mixed>
     */
    private function sel(mixed $isi, string $jenis): array
    {
        return match ($jenis) {
            'nomor' => ['isi' => $isi, 'gaya' => 'teks-tengah', 'angka' => true],
            'uang' => ['isi' => $isi, 'gaya' => 'angka', 'angka' => true],
            'tengah' => ['isi' => filled($isi) ? $isi : '—', 'gaya' => 'teks-tengah'],
            default => ['isi' => filled($isi) ? $isi : '—', 'gaya' => 'teks'],
        };
    }

    private function tanggal(mixed $nilai): ?string
    {
        return filled($nilai) ? Carbon::parse($nilai)->format('d/m/Y') : null;
    }
}
