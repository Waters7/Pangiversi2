<?php

namespace App\Services;

use App\Enums\DokumenCetak;
use App\Enums\KategoriBiaya;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\KategoriPembiayaan;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\SpdPelaksana;
use App\Models\SpdPengikut;
use App\Models\SuratPerjalananDinas;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data contoh untuk pratinjau pengaturan dokumen output.
 *
 * Seluruh modelnya dirangkai di memori — tidak ada satu baris pun yang
 * ditulis ke basis data — dan namanya sengaja ditandai "CONTOH" supaya
 * tidak tertukar dengan dokumen sungguhan bila halamannya tercetak.
 */
class ContohDokumen
{
    private Carbon $berangkat;

    private Carbon $kembali;

    public function __construct()
    {
        $this->berangkat = Carbon::create(now()->year, 3, 10);
        $this->kembali = $this->berangkat->copy()->addDays(2);
    }

    /**
     * Bekal tampilan untuk satu dokumen, siap dikirim ke view-nya.
     *
     * @return array<string, mixed>
     */
    public function bekal(DokumenCetak $dokumen): array
    {
        return match ($dokumen) {
            DokumenCetak::Perjadin => $this->spd(),
            DokumenCetak::RincianBiaya => $this->rincian(),
            DokumenCetak::DaftarRiil => $this->riil(),
            DokumenCetak::DaftarNominatif => $this->nominatif(),
        };
    }

    // ── Orang dan unit ──

    private function unit(): UnitKerja
    {
        return new UnitKerja(['kode' => 'CONTOH', 'nama' => 'Jurusan Contoh']);
    }

    private function pelaksana(): User
    {
        $orang = new User([
            'nama' => 'CONTOH — Nama Pelaksana, S.KM',
            'nip' => '199001012015031001',
            'jabatan' => 'Dosen Ahli Muda',
            'golongan' => 'III/c',
        ]);
        $orang->setRelation('unit', $this->unit());

        return $orang;
    }

    private function pejabat(string $nama, string $jabatan): User
    {
        return new User(['nama' => $nama, 'nip' => '198505052010121002', 'jabatan' => $jabatan]);
    }

    // ── 1. Surat Perjalanan Dinas ──

    /** @return array<string, mixed> */
    private function spd(): array
    {
        $spd = new SuratPerjalananDinas([
            'maksud' => 'CONTOH — mengikuti rapat koordinasi penyusunan anggaran.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => $this->berangkat,
            'tanggal_kembali' => $this->kembali,
            'lama_hari' => 3,
            'instansi_pembebanan' => 'Politeknik Kesehatan Kemenkes Manado',
            'akun_pembebanan' => '524111',
            'keterangan_lain' => 'Contoh keterangan lain pada Surat Perjalanan Dinas.',
            'no_tugas' => 'KP.01.02/F.XXX/0000/'.now()->year,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => $this->berangkat->copy()->subDays(5),
        ]);

        $pelaksana = collect([
            new SpdPelaksana([
                'nomor_surat' => '000',
                'nama' => 'CONTOH — Nama Pelaksana, S.KM',
                'nip' => '199001012015031001',
                'pangkat_golongan' => 'Penata / III c',
                'jabatan_instansi' => 'Dosen Ahli Muda',
                'tingkat_biaya' => 'Tingkat C',
            ]),
        ]);

        $pengikut = collect([
            new SpdPengikut([
                'nama' => 'CONTOH — Nama Pengikut',
                'tanggal_lahir' => Carbon::create(1995, 7, 17),
                'keterangan' => 'Tenaga pendukung kegiatan',
            ]),
        ]);

        return [
            'spd' => $spd,
            'daftarPelaksana' => $pelaksana,
            'pengikut' => $pengikut,
            'ppk' => $this->pejabat('CONTOH — Pejabat Pembuat Komitmen', 'Pejabat Pembuat Komitmen'),
            'direktur' => $this->pejabat('CONTOH — Direktur', 'Direktur'),
        ];
    }

    // ── 2. Rincian biaya ──

    /** @return array<string, mixed> */
    private function rincian(): array
    {
        $usulan = $this->usulan();
        $peserta = $this->peserta($usulan);
        $riil = $this->daftarRiil($usulan, $peserta);

        $biaya = collect([
            $this->biaya(KategoriBiaya::Transport, 'Tiket Pesawat Pergi', 1, 'tiket', 2_100_000),
            $this->biaya(KategoriBiaya::Transport, 'Tiket Pesawat Pulang', 1, 'tiket', 2_250_000),
            $this->biaya(KategoriBiaya::UangHarian, 'Uang Harian', 3, 'hari', 530_000),
            $this->biaya(KategoriBiaya::Penginapan, 'Biaya Hotel', 2, 'malam', 730_000, 'Hotel Contoh'),
        ]);

        $keuangan = new Keuangan([
            'uang_muka' => 5_000_000,
            'tanggal_pelunasan' => $this->kembali->copy()->addDays(10)->toDateString(),
        ]);
        $keuangan->kode_konfirmasi_bayar = 'CONTOH-BYR-0001';
        $keuangan->dikonfirmasi_bayar_at = $this->kembali->copy()->addDays(10);

        $totalBiaya = $biaya->sum('jumlah');

        return [
            'usulan' => $usulan,
            'peserta' => $peserta,
            'rincianPerKategori' => $biaya->groupBy(fn (RincianBiaya $b) => $b->kategori),
            'total' => $totalBiaya,
            'tanggalPelaksana' => $this->kembali->copy()->addDays(4),
            'dibayarkan' => 5_000_000,
            'terbilang' => app(Terbilang::class)->konversi($totalBiaya),
            'bendahara' => $this->pejabat('CONTOH — Bendahara Pengeluaran', 'Bendahara Pengeluaran'),
            'ppk' => $this->pejabat('CONTOH — Pejabat Pembuat Komitmen', 'Pejabat Pembuat Komitmen'),
            'daftarRiil' => $riil,
            'keuangan' => $keuangan,
            'qrPelaksana' => $this->qr(),
            'qrPpk' => $this->qr(),
            'qrBendahara' => $this->qr(),
        ];
    }

    // ── 3. Daftar pengeluaran riil ──

    /** @return array<string, mixed> */
    private function riil(): array
    {
        $usulan = $this->usulan();
        $peserta = $this->peserta($usulan);

        return [
            'usulan' => $usulan,
            'peserta' => $peserta,
            'daftar' => $this->daftarRiil($usulan, $peserta),
            'qr' => $this->qr(),
            'qrPelaksana' => $this->qr(),
        ];
    }

    // ── 4. Daftar nominatif ──

    /** @return array<string, mixed> */
    private function nominatif(): array
    {
        $nominatif = new DaftarNominatif([
            'no_tugas' => 'KP.01.02/F.XXX/0000/'.now()->year,
            'tanggal_tugas' => $this->berangkat,
        ]);
        $nominatif->kode_verifikasi = 'CONTOH-NOM-0001';
        $nominatif->ditandatangani_at = $this->kembali->copy()->addDays(7);
        $nominatif->setRelation('kategoriPembiayaan', new KategoriPembiayaan(['kode' => '00', 'nama' => 'Kategori Contoh']));
        $nominatif->setRelation('akunPembiayaan', new AkunPembiayaan(['kode' => '524111', 'nama' => 'Belanja Perjalanan Dinas Biasa']));

        $baris = [];
        foreach (['CONTOH — Nama Pelaksana, S.KM', 'CONTOH — Nama Pelaksana Kedua'] as $i => $nama) {
            $baris[] = [
                'nomor' => $i + 1,
                'nama' => $nama,
                'asal' => 'Manado',
                'tujuan' => 'Jakarta',
                'lamanya' => 3,
                'berangkat' => $this->berangkat,
                'kembali' => $this->kembali,
                'maksud' => 'Rapat koordinasi penyusunan anggaran',
                'no_tugas' => $nominatif->no_tugas,
                'no_sppd' => '00'.($i + 1),
                'tanggal_sppd' => $this->berangkat,
                'tiket' => 4_350_000,
                'transport' => 300_000,
                'harian_hari' => 3,
                'harian_biaya' => 530_000,
                'harian_jumlah' => 1_590_000,
                'inap_hari' => 2,
                'inap_biaya' => 730_000,
                'inap_jumlah' => 1_460_000,
                'jumlah' => 7_700_000,
            ];
        }

        $jumlahkan = fn (string $kolom) => array_sum(array_column($baris, $kolom));

        return [
            'nominatif' => $nominatif,
            'baris' => $baris,
            'total' => [
                'tiket' => $jumlahkan('tiket'),
                'transport' => $jumlahkan('transport'),
                'harian_jumlah' => $jumlahkan('harian_jumlah'),
                'inap_jumlah' => $jumlahkan('inap_jumlah'),
                'jumlah' => $jumlahkan('jumlah'),
            ],
            'ppk' => $this->pejabat('CONTOH — Pejabat Pembuat Komitmen', 'Pejabat Pembuat Komitmen'),
            'qr' => $this->qr(),
        ];
    }

    // ── Bahan bersama ──

    private function usulan(): Usulan
    {
        $usulan = new Usulan([
            'no_usulan' => 'PJ-CONTOH-'.now()->format('Y-m').'-000',
            'no_tugas' => 'KP.01.02/F.XXX/0000/'.now()->year,
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan RI',
            'tanggal_mulai' => $this->berangkat->toDateString(),
            'tanggal_selesai' => $this->kembali->toDateString(),
        ]);
        $usulan->setRelation('user', $this->pelaksana());

        return $usulan;
    }

    private function peserta(Usulan $usulan): PesertaUsulan
    {
        $peserta = new PesertaUsulan([
            'nama' => 'CONTOH — Nama Pelaksana, S.KM',
            'nip' => '199001012015031001',
            'jabatan' => 'Dosen Ahli Muda',
            'peran' => 'ketua',
        ]);
        $peserta->setRelation('user', $this->pelaksana());
        $peserta->setRelation('usulan', $usulan);

        return $peserta;
    }

    private function daftarRiil(Usulan $usulan, PesertaUsulan $peserta): DaftarRiil
    {
        $riil = new DaftarRiil([
            'total_riil' => 300_000,
            'keterangan' => 'Transport lokal dari dan ke bandara.',
        ]);
        $riil->kode_verifikasi = 'CONTOH-RIIL-0001';
        $riil->kode_konfirmasi = 'CONTOH-KNF-0001';
        $riil->ditandatangani_at = $this->kembali->copy()->addDays(6);
        $riil->disetujui_pegawai_at = $this->kembali->copy()->addDays(4);
        $riil->setRelation('usulan', $usulan);
        $riil->setRelation('peserta', $peserta);
        $riil->setRelation('ppk', $this->pejabat('CONTOH — Pejabat Pembuat Komitmen', 'Pejabat Pembuat Komitmen'));
        $riil->setRelation('rincian', collect([
            new RincianDaftarRiil(['urutan' => 1, 'uraian' => 'Taksi Manado — Bandara Sam Ratulangi', 'nominal' => 150_000]),
            new RincianDaftarRiil(['urutan' => 2, 'uraian' => 'Taksi Bandara Soekarno-Hatta — lokasi kegiatan', 'nominal' => 150_000]),
        ]));

        return $riil;
    }

    private function biaya(KategoriBiaya $kategori, string $komponen, int $volume, string $satuan, int $harga, ?string $keterangan = null): RincianBiaya
    {
        return new RincianBiaya([
            'kategori' => $kategori->value,
            'komponen' => $komponen,
            'volume' => $volume,
            'satuan' => $satuan,
            'harga_satuan' => $harga,
            'jumlah' => $volume * $harga,
            'keterangan' => $keterangan,
        ]);
    }

    /** Kode QR contoh — menunjuk ke halaman verifikasi aplikasi. */
    private function qr(): string
    {
        return app(QrCodeService::class)->dataUri(route('verifikasi.tampil', 'CONTOH'), 180);
    }

    /**
     * Pelaksana contoh dipakai juga untuk nama berkas unduhan.
     */
    public function namaBerkas(DokumenCetak $dokumen): string
    {
        return 'Contoh-'.str_replace('-', '', ucwords($dokumen->value, '-')).'.pdf';
    }

    /** @return Collection<int, string> */
    public function catatan(): Collection
    {
        return collect([
            'Seluruh isinya data contoh — tidak ada perjalanan dinas sungguhan yang ditampilkan.',
            'Elemen yang dimatikan pada formulir langsung hilang dari pratinjau ini.',
        ]);
    }
}
