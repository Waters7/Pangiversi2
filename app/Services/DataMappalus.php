<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\PengambilanMappalus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Data daftar nominatif yang disediakan bagi Mappalus, satu baris per
 * pelaksana: tim kerja, kode akun, total nominal, dan keterangan berisi nama
 * pelaksana dan nomor surat tugas.
 *
 * Yang disediakan hanya pelaksana pada nominatif bertanda tangan PPK yang tim
 * kerja dan kode akunnya sudah diisi. Mappalus mengambilnya lalu menyatakan
 * sudah menyimpannya; baris yang dikonfirmasi tidak disajikan lagi kecuali
 * isinya berubah, sehingga tidak ada data ganda maupun yang terlewat.
 */
class DataMappalus
{
    public const BELUM_LENGKAP = 'belum_lengkap';

    public const BELUM_DIAMBIL = 'belum_diambil';

    public const SUDAH_DIAMBIL = 'sudah_diambil';

    public const DIKONFIRMASI = 'dikonfirmasi';

    public const BERUBAH = 'berubah';

    public const DITARIK = 'ditarik';

    /**
     * Status yang tampil pada daftar integrasi.
     *
     * @var array<string, string>
     */
    public const LABEL = [
        self::BELUM_DIAMBIL => 'Belum Diambil',
        self::SUDAH_DIAMBIL => 'Sudah Diambil',
        self::DIKONFIRMASI => 'Dikonfirmasi',
        self::BERUBAH => 'Berubah',
        self::DITARIK => 'Ditarik',
    ];

    /** Status yang masih disajikan kepada Mappalus. */
    private const TERSEDIA = [self::BELUM_DIAMBIL, self::SUDAH_DIAMBIL, self::BERUBAH];

    public function __construct(private PenyusunNominatif $penyusun) {}

    /**
     * Seluruh pelaksana pada nominatif bertanda tangan, ditambah catatan
     * pelaksana yang pernah diambil tetapi kini tidak tersedia lagi.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function baris(): Collection
    {
        $catatan = PengambilanMappalus::all()->keyBy('referensi');

        $daftar = DaftarNominatif::with('akunPembiayaan', 'timKerja')
            ->whereNotNull('ditandatangani_at')
            ->get();

        $pelaksana = $this->penyusun->barisBanyak($daftar->pluck('no_tugas'));

        $baris = $daftar->flatMap(fn (DaftarNominatif $nominatif) => ($pelaksana->get($nominatif->no_tugas) ?? collect())
            ->map(fn (array $isi) => $this->susun($nominatif, $isi, $catatan->get($isi['usulan']->no_usulan))));

        $tersedia = $baris->reject(fn (array $item) => $item['status'] === self::BELUM_LENGKAP)->pluck('data.referensi')->flip();

        $ditarik = $catatan
            ->reject(fn (PengambilanMappalus $item) => $tersedia->has($item->referensi))
            ->map(fn (PengambilanMappalus $item) => $this->barisDitarik($item));

        return $baris->concat($ditarik->values())->sortBy('data.referensi')->values();
    }

    /**
     * Baris yang masih harus diambil Mappalus.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return Collection<int, array<string, mixed>>
     */
    public function tersedia(Collection $baris): Collection
    {
        return $baris->whereIn('status', self::TERSEDIA)->values();
    }

    /**
     * Catat baris yang baru saja disajikan kepada Mappalus.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    public function catatDiambil(Collection $baris): void
    {
        foreach ($baris as $item) {
            $catatan = PengambilanMappalus::firstOrNew(['referensi' => $item['data']['referensi']]);

            $catatan->fill([
                'id_usulan' => $item['id_usulan'],
                'id_nominatif' => $item['id_nominatif'],
                'tim_kerja' => $item['data']['tim_kerja'],
                'kode_akun' => $item['data']['kode_akun'],
                'total_nominal' => $item['data']['total_nominal'],
                'keterangan' => $item['data']['keterangan'],
                'sidik' => $item['sidik'],
                'jumlah_diambil' => $catatan->jumlah_diambil + 1,
                'pertama_diambil_at' => $catatan->pertama_diambil_at ?? now(),
                'diambil_at' => now(),
            ])->save();
        }
    }

    /**
     * Data yang sudah terkirim pada rentang waktu itu, menurut catatan
     * kirimannya: yang disajikan persis isi yang dulu dikirim, urut dari yang
     * terlama. Waktu kirim terakhirnya yang dipakai, sebab itulah saat versi
     * yang tercatat ini sampai ke Mappalus.
     *
     * @return Collection<int, PengambilanMappalus>
     */
    public function terkirim(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return PengambilanMappalus::whereBetween('diambil_at', [$dari, $sampai])
            ->orderBy('diambil_at')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * Catat permintaan ulang tanpa menyentuh waktu kirim dan konfirmasinya,
     * supaya rentang waktu yang sama tetap menyajikan data yang sama.
     *
     * @param  Collection<int, PengambilanMappalus>  $catatan
     */
    public function catatKirimUlang(Collection $catatan): void
    {
        PengambilanMappalus::whereKey($catatan->pluck('id'))
            ->increment('jumlah_kirim_ulang', 1, ['kirim_ulang_at' => now()]);
    }

    /**
     * Tandai data yang sudah disimpan Mappalus. Versinya harus sama dengan
     * yang terakhir diambil: bila datanya berubah di antara keduanya,
     * konfirmasi versi lama tidak boleh menutupi perubahannya.
     *
     * @param  list<array{referensi: string, versi: string}>  $kiriman
     * @return array{dikonfirmasi: list<string>, ditolak: list<string>}
     */
    public function konfirmasi(array $kiriman): array
    {
        $catatan = PengambilanMappalus::whereIn('referensi', array_column($kiriman, 'referensi'))->get()->keyBy('referensi');
        $hasil = ['dikonfirmasi' => [], 'ditolak' => []];

        foreach ($kiriman as $isi) {
            $item = $catatan->get($isi['referensi']);

            if ($item === null || $item->sidik !== $isi['versi']) {
                $hasil['ditolak'][] = $isi['referensi'];

                continue;
            }

            if ($item->sidik_dikonfirmasi !== $item->sidik) {
                $item->update(['sidik_dikonfirmasi' => $item->sidik, 'dikonfirmasi_at' => now()]);
            }

            $hasil['dikonfirmasi'][] = $isi['referensi'];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $isi  Satu baris dari PenyusunNominatif.
     * @return array<string, mixed>
     */
    private function susun(DaftarNominatif $nominatif, array $isi, ?PengambilanMappalus $catatan): array
    {
        $data = [
            'referensi' => $isi['usulan']->no_usulan,
            'tim_kerja' => $nominatif->timKerja?->nama,
            'kode_akun' => $nominatif->akunPembiayaan?->kode,
            'total_nominal' => round((float) $isi['jumlah'], 2),
            'keterangan' => mb_substr($isi['nama'].' - '.$isi['no_tugas'], 0, 500),
        ];

        $sidik = hash('sha256', (string) json_encode($data));

        return [
            'data' => $data,
            'sidik' => $sidik,
            'id_usulan' => $isi['usulan']->id,
            'id_nominatif' => $nominatif->id,
            'catatan' => $catatan,
            'status' => match (true) {
                blank($data['tim_kerja']) || blank($data['kode_akun']) || $data['total_nominal'] <= 0 => self::BELUM_LENGKAP,
                $catatan === null => self::BELUM_DIAMBIL,
                $catatan->sidik_dikonfirmasi === $sidik => self::DIKONFIRMASI,
                $catatan->sidik === $sidik => self::SUDAH_DIAMBIL,
                default => self::BERUBAH,
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function barisDitarik(PengambilanMappalus $catatan): array
    {
        return [
            'data' => [
                'referensi' => $catatan->referensi,
                'tim_kerja' => $catatan->tim_kerja,
                'kode_akun' => $catatan->kode_akun,
                'total_nominal' => (float) $catatan->total_nominal,
                'keterangan' => $catatan->keterangan,
            ],
            'sidik' => $catatan->sidik,
            'id_usulan' => $catatan->id_usulan,
            'id_nominatif' => $catatan->id_nominatif,
            'catatan' => $catatan,
            'status' => self::DITARIK,
        ];
    }
}
