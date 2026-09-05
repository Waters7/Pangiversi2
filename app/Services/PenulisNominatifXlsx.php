<?php

namespace App\Services;

use App\Enums\KategoriBiaya;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun Daftar Nominatif Perjalanan Dinas Pegawai — rekap bulanan yang
 * disahkan KPPN — mengikuti format berkas Excel yang selama ini dipakai
 * bagian keuangan Poltekkes Kemenkes Manado.
 *
 * Setiap pegawai menempati tiga baris: baris pertama memuat angka, baris
 * kedua tanggal dan lanjutan maksud perjalanan, baris ketiga dasar
 * penugasannya.
 */
class PenulisNominatifXlsx
{
    /** Kedudukan satuan kerja, dipakai sebagai kolom "TEMPAT ASAL". */
    private const KOTA_ASAL = 'Manado';

    /** Lebar tiap kolom A–O dalam satuan karakter Excel. */
    private const LEBAR = [
        4,    // A No.
        24,   // B Nama
        11,   // C Asal
        13,   // D Tujuan
        13,   // E Lamanya
        42,   // F Maksud
        12,   // G Tiket
        11,   // H Transport
        6,    // I Hari
        11,   // J Biaya
        12,   // K Jumlah
        6,    // L Hari
        11,   // M Biaya
        12,   // N Jumlah
        14,   // O Jumlah pembayaran
    ];

    /**
     * @param  Collection<int, Usulan>  $usulan  Sudah memuat user, kegiatan, dan keuangan.rincianBiaya.
     * @param  User|null  $pegawai  Diisi bila rekapnya hanya untuk satu pegawai.
     */
    public function susun(Collection $usulan, string $judulPeriode, ?User $pegawai = null): PenulisXlsx
    {
        $xlsx = new PenulisXlsx($pegawai ? 'Rekap Pegawai' : $judulPeriode);
        $xlsx->lebarKolom(self::LEBAR);

        $this->tulisJudul($xlsx, $judulPeriode, $pegawai);
        $this->tulisKepala($xlsx);

        $total = $this->tulisIsi($xlsx, $usulan);

        $this->tulisTotal($xlsx, $total);
        $this->tulisTandaTangan($xlsx);

        return $xlsx;
    }

    private function tulisJudul(PenulisXlsx $xlsx, string $judulPeriode, ?User $pegawai = null): void
    {
        $judul = $pegawai
            ? 'REKAP PERJALANAN DINAS PER PEGAWAI'
            : 'DAFTAR NOMINATIF PERJALANAN DINAS PEGAWAI';

        $xlsx->baris([['isi' => $judul, 'gaya' => 'judul']]);
        $xlsx->baris([['isi' => 'PADA POLITEKNIK KESEHATAN KEMENKES MANADO', 'gaya' => 'subjudul']]);
        $xlsx->baris([['isi' => 'Periode '.$judulPeriode, 'gaya' => 'subjudul']]);

        $xlsx->gabung('A1:O1')->gabung('A2:O2')->gabung('A3:O3');
        $xlsx->tinggiBaris(1, 20)->tinggiBaris(2, 16)->tinggiBaris(3, 16);

        if (! $pegawai) {
            return;
        }

        // Identitas pegawai ditulis sekali di atas tabel, karena seluruh
        // barisnya memang milik orang yang sama.
        $xlsx->barisKosong();
        $xlsx->baris([
            ['isi' => 'Nama', 'gaya' => 'tebal'],
            ['isi' => $pegawai->nama, 'gaya' => 'polos'],
            '', '',
            ['isi' => 'NIP', 'gaya' => 'tebal'],
            ['isi' => $pegawai->nip, 'gaya' => 'polos'],
        ]);
        $xlsx->baris([
            ['isi' => 'Unit Kerja', 'gaya' => 'tebal'],
            ['isi' => $pegawai->unit?->nama ?? '—', 'gaya' => 'polos'],
            '', '',
            ['isi' => 'Jabatan', 'gaya' => 'tebal'],
            ['isi' => $pegawai->jabatan ?? '—', 'gaya' => 'polos'],
        ]);
    }

    /**
     * Kepala tabel tiga tingkat, persis seperti berkas acuannya.
     */
    private function tulisKepala(PenulisXlsx $xlsx): void
    {
        $xlsx->barisKosong();

        // Baris kepala dihitung dari posisi berjalan, karena rekap per pegawai
        // menyisipkan blok identitas di atasnya.
        $b1 = $xlsx->jumlahBaris() + 1;
        $b2 = $b1 + 1;
        $b3 = $b1 + 2;

        $xlsx->baris([
            'No.', 'NAMA / GOL', 'TEMPAT', '', 'LAMANYA', 'MAKSUD PERJALANAN,',
            'TIKET', 'TRANSPORT', 'UANG HARIAN/SAKU', '', '',
            'UANG PENGINAPAN /', '', '', 'JUMLAH',
        ], 'kepala');

        $xlsx->baris([
            '', '', 'ASAL', 'TUJUAN', 'PERJALANAN',
            'No. SPPD dan Tgl. SPPD / SURAT TUGAS', '(PP)', '',
            '', '', '', 'UANG PENYELENGGARA', '', '', 'PEMBAYARAN',
        ], 'kepala');

        $xlsx->baris([
            '', '', '', '', '', '', '', '',
            'HARI', 'BIAYA', 'JUMLAH', 'HARI', 'BIAYA', 'JUMLAH', '',
        ], 'kepala');

        // Kolom E, G, dan O sengaja tidak digabung karena masing-masing
        // memuat teks pada dua baris.
        foreach (['A', 'B', 'H'] as $kolom) {
            $xlsx->gabung("{$kolom}{$b1}:{$kolom}{$b3}");
        }

        $xlsx
            ->gabung("C{$b1}:D{$b1}")
            ->gabung("I{$b1}:K{$b1}")->gabung("I{$b2}:K{$b2}")
            ->gabung("L{$b1}:N{$b1}")->gabung("L{$b2}:N{$b2}");

        $xlsx->tinggiBaris($b1, 22)->tinggiBaris($b2, 26)->tinggiBaris($b3, 16);
        $xlsx->bekukan($b3);
    }

    /**
     * @param  Collection<int, Usulan>  $daftar
     * @return array<string, float>
     */
    private function tulisIsi(PenulisXlsx $xlsx, Collection $daftar): array
    {
        $total = ['tiket' => 0.0, 'transport' => 0.0, 'harian' => 0.0, 'penginapan' => 0.0, 'jumlah' => 0.0];
        $nomor = 0;

        foreach ($daftar as $usulan) {
            $biaya = $this->biaya($usulan);
            $nomor++;

            $total['tiket'] += $biaya['tiket'];
            $total['transport'] += $biaya['transport'];
            $total['harian'] += $biaya['harian_jumlah'];
            $total['penginapan'] += $biaya['penginapan_jumlah'];
            $total['jumlah'] += $biaya['jumlah'];

            $xlsx->baris([
                ['isi' => $nomor, 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $usulan->user?->nama ?? '—', 'gaya' => 'teks'],
                ['isi' => self::KOTA_ASAL, 'gaya' => 'teks-tengah'],
                ['isi' => $usulan->lokasi, 'gaya' => 'teks-tengah'],
                ['isi' => $usulan->durasi.' hari', 'gaya' => 'teks-tengah'],
                ['isi' => $usulan->kegiatan?->nama ?? '—', 'gaya' => 'teks'],
                ['isi' => $biaya['tiket'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['transport'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['harian_hari'], 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $biaya['harian_satuan'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['harian_jumlah'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['penginapan_hari'], 'gaya' => 'teks-tengah', 'angka' => true],
                ['isi' => $biaya['penginapan_satuan'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['penginapan_jumlah'], 'gaya' => 'angka', 'angka' => true],
                ['isi' => $biaya['jumlah'], 'gaya' => 'angka', 'angka' => true],
            ]);

            $xlsx->baris([
                '', '', '', '', ['isi' => $this->rentangTanggal($usulan), 'gaya' => 'teks-tengah'],
                ['isi' => $this->maksud($usulan, $biaya['lainnya']), 'gaya' => 'teks'],
                '', '', '', '', '', '', '', '', '',
            ], 'teks');

            $xlsx->baris([
                '', '', '', '', '',
                ['isi' => $this->dasarPenugasan($usulan), 'gaya' => 'teks'],
                '', '', '', '', '', '', '', '', '',
            ], 'teks');

            // Tiga baris milik satu pegawai disatukan agar terbaca sebagai satu blok.
            $awal = $xlsx->jumlahBaris() - 2;
            $akhir = $awal + 2;

            foreach (['A', 'B', 'C', 'D', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'] as $kolom) {
                $xlsx->gabung("{$kolom}{$awal}:{$kolom}{$akhir}");
            }
        }

        return $total;
    }

    /**
     * @param  array<string, float>  $total
     */
    private function tulisTotal(PenulisXlsx $xlsx, array $total): void
    {
        $baris = $xlsx->jumlahBaris() + 1;

        $xlsx->baris([
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => 'T O T A L', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => $total['tiket'], 'gaya' => 'total', 'angka' => true],
            ['isi' => $total['transport'], 'gaya' => 'total', 'angka' => true],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => $total['harian'], 'gaya' => 'total', 'angka' => true],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => '', 'gaya' => 'total-teks'],
            ['isi' => $total['penginapan'], 'gaya' => 'total', 'angka' => true],
            ['isi' => $total['jumlah'], 'gaya' => 'total', 'angka' => true],
        ]);

        $xlsx->gabung("B{$baris}:F{$baris}");
        $xlsx->tinggiBaris($baris, 20);
    }

    private function tulisTandaTangan(PenulisXlsx $xlsx): void
    {
        $ppk = User::where('role', User::ROLE_PPK)->first();
        $hariIni = Carbon::now()->translatedFormat('d F Y');

        $xlsx->barisKosong(2);

        $kolom = fn (string $kiri, string $kanan) => [
            '', '', '', '',
            ['isi' => $kiri, 'gaya' => 'polos'],
            '', '', '', '', '',
            ['isi' => $kanan, 'gaya' => 'polos'],
        ];

        $xlsx->baris($kolom('Disahkan Oleh :', self::KOTA_ASAL.', '.$hariIni));
        $xlsx->baris($kolom('Kepala Seksi Pencairan Dana I KPPN Manado,', 'Pejabat Pembuat Komitmen'));
        $xlsx->barisKosong(3);
        $xlsx->baris($kolom('…………………………………..', $ppk?->nama ?? '…………………………………..'));
        $xlsx->baris($kolom('NIP. …………………………', 'NIP. '.($ppk?->nip ?? '…………………………')));
    }

    /**
     * Pecah rincian biaya menjadi kolom-kolom daftar nominatif.
     *
     * @return array<string, float>
     */
    private function biaya(Usulan $usulan): array
    {
        $rincian = $usulan->keuangan?->rincianBiaya ?? collect();

        $perKategori = fn (KategoriBiaya $kategori) => $rincian
            ->filter(fn (RincianBiaya $item) => $item->kategori === $kategori);

        $harian = $perKategori(KategoriBiaya::UangHarian);
        $penginapan = $perKategori(KategoriBiaya::Penginapan);

        $nilai = [
            'tiket' => (float) $perKategori(KategoriBiaya::Transport)->sum('jumlah'),
            'transport' => (float) $perKategori(KategoriBiaya::TransportLokal)->sum('jumlah'),
            'harian_hari' => (float) $harian->sum('volume'),
            'harian_satuan' => (float) ($harian->first()?->harga_satuan ?? 0),
            'harian_jumlah' => (float) $harian->sum('jumlah'),
            'penginapan_hari' => (float) $penginapan->sum('volume'),
            'penginapan_satuan' => (float) ($penginapan->first()?->harga_satuan ?? 0),
            'penginapan_jumlah' => (float) $penginapan->sum('jumlah'),
            'lainnya' => (float) $perKategori(KategoriBiaya::Lainnya)->sum('jumlah'),
        ];

        // Jumlah pembayaran mengikuti nilai yang benar-benar dianggarkan.
        $nilai['jumlah'] = $rincian->isNotEmpty()
            ? (float) $rincian->sum('jumlah')
            : (float) ($usulan->keuangan?->total ?? 0);

        return $nilai;
    }

    private function rentangTanggal(Usulan $usulan): string
    {
        $mulai = Carbon::parse($usulan->tanggal_mulai);
        $selesai = Carbon::parse($usulan->tanggal_selesai);

        return $mulai->isSameMonth($selesai)
            ? $mulai->format('d').'-'.$selesai->format('d/m/Y')
            : $mulai->format('d/m').'-'.$selesai->format('d/m/Y');
    }

    private function maksud(Usulan $usulan, float $lainnya): string
    {
        $maksud = trim((string) $usulan->uraian) ?: 'Ke '.$usulan->lokasi.' pada '.$usulan->instansi;

        if ($lainnya > 0) {
            $maksud .= ' (termasuk biaya lainnya Rp '.number_format($lainnya, 0, ',', '.').')';
        }

        return $maksud;
    }

    private function dasarPenugasan(Usulan $usulan): string
    {
        $nomor = trim((string) $usulan->no_tugas) ?: '—';
        $tanggal = $usulan->created_at?->translatedFormat('d F Y');

        return $tanggal ? "No. {$nomor}, Tgl. {$tanggal}" : "No. {$nomor}";
    }
}
