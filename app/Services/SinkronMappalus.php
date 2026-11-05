<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\LogApi;
use App\Models\Pengaturan;
use App\Models\PengirimanMappalus;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pengiriman daftar nominatif bertanda tangan PPK ke Mappalus, satu kiriman
 * per pelaksana: tim kerja, kode akun, total nominal, dan keterangan berisi
 * nama pelaksana dan nomor surat tugas.
 *
 * Data yang sudah diterima tidak pernah terkirim ulang dengan sendirinya.
 * Bila isinya berubah sesudah terkirim, statusnya Berubah dan pengirimannya
 * diputuskan super administrator, sebab Mappalus sudah memegang versi lama.
 */
class SinkronMappalus
{
    public const BELUM_LENGKAP = 'belum_lengkap';

    public const SIAP = 'siap';

    public const BERHASIL = 'berhasil';

    public const GAGAL = 'gagal';

    public const BERUBAH = 'berubah';

    public const DITARIK = 'ditarik';

    /** @var array<string, string> */
    public const LABEL = [
        self::SIAP => 'Siap Kirim',
        self::BERHASIL => 'Berhasil',
        self::GAGAL => 'Gagal',
        self::BERUBAH => 'Berubah',
        self::BELUM_LENGKAP => 'Belum Lengkap',
        self::DITARIK => 'Ditarik',
    ];

    /** Sesudah sekian percobaan, pengiriman otomatis berhenti dan menunggu sinkron manual. */
    public const MAKS_PERCOBAAN_OTOMATIS = 5;

    /** Sekali sinkron dibatasi supaya tidak melampaui batas waktu peladen; sisanya menyusul. */
    public const BATAS_PER_SINKRON = 50;

    /** Batas tunggu balasan Mappalus, detik. */
    private const TENGGAT_DETIK = 20;

    private const KUNCI_SINKRON = 'sinkron-mappalus';

    /** Sinkron yang macet tidak boleh mengunci yang berikutnya selamanya. */
    private const LAMA_KUNCI_DETIK = 1800;

    public function __construct(private PenyusunNominatif $penyusun) {}

    /**
     * @return array{aktif: bool, url: string, token_tersamar: ?string}
     */
    public function pengaturan(): array
    {
        $token = $this->token();

        return [
            'aktif' => Pengaturan::aktif(Pengaturan::MAPPALUS_AKTIF),
            'url' => $this->alamat(),
            'token_tersamar' => $token === '' ? null : LogApi::samarkan($token),
        ];
    }

    public function token(): string
    {
        return Pengaturan::rahasia(Pengaturan::MAPPALUS_TOKEN);
    }

    public function alamat(): string
    {
        return Pengaturan::ambil(Pengaturan::MAPPALUS_URL);
    }

    /** Alamat dan token terisi, jadi pengiriman dapat dicoba. */
    public function siap(): bool
    {
        return $this->token() !== '' && filter_var($this->alamat(), FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Seluruh pelaksana pada daftar nominatif bertanda tangan, beserta catatan
     * yang tandatangannya sudah dicabut sesudah datanya diterima Mappalus.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function baris(): Collection
    {
        $catatan = PengirimanMappalus::all()->keyBy('referensi');

        $daftar = DaftarNominatif::with('akunPembiayaan', 'timKerja')
            ->whereNotNull('ditandatangani_at')
            ->latest('ditandatangani_at')
            ->get();

        $pelaksana = $this->penyusun->barisBanyak($daftar->pluck('no_tugas'));

        $baris = $daftar->flatMap(fn (DaftarNominatif $nominatif) => ($pelaksana->get($nominatif->no_tugas) ?? collect())
            ->map(fn (array $isi) => $this->susun($nominatif, $isi, $catatan->get($isi['usulan']->no_usulan))));

        $tercantum = $baris->pluck('data.referensi')->flip();

        $ditarik = $catatan
            ->filter(fn (PengirimanMappalus $item) => $item->pernahDiterima() && ! $tercantum->has($item->referensi))
            ->map(fn (PengirimanMappalus $item) => $this->barisDitarik($item));

        return $baris->concat($ditarik->values())->values();
    }

    /**
     * @param  array<string, mixed>  $isi  Satu baris dari PenyusunNominatif.
     * @return array<string, mixed>
     */
    private function susun(DaftarNominatif $nominatif, array $isi, ?PengirimanMappalus $catatan): array
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
            'nama' => $isi['nama'],
            'no_tugas' => $isi['no_tugas'],
            'catatan' => $catatan,
            'status' => $this->status($data, $sidik, $catatan),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function status(array $data, string $sidik, ?PengirimanMappalus $catatan): string
    {
        return match (true) {
            blank($data['tim_kerja']) || blank($data['kode_akun']) || $data['total_nominal'] <= 0 => self::BELUM_LENGKAP,
            $catatan === null => self::SIAP,
            $catatan->sidik_diterima === $sidik => self::BERHASIL,
            $catatan->pernahDiterima() => self::BERUBAH,
            default => self::GAGAL,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function barisDitarik(PengirimanMappalus $catatan): array
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
            'nama' => null,
            'no_tugas' => null,
            'catatan' => $catatan,
            'status' => self::DITARIK,
        ];
    }

    /**
     * Kirim yang belum pernah diterima Mappalus: Siap Kirim dan Gagal.
     *
     * Berhenti pada kegagalan sambungan pertama, sebab baris berikutnya hanya
     * akan menghabiskan batas tunggu yang sama. Pengiriman otomatis melewati
     * baris yang percobaannya sudah habis.
     *
     * @return array{berhasil: int, gagal: int, sisa: int}|null null bila sinkron lain sedang berjalan.
     */
    public function sinkron(?User $oleh = null, bool $otomatis = false): ?array
    {
        $hasil = Cache::lock(self::KUNCI_SINKRON, self::LAMA_KUNCI_DETIK)->get(function () use ($oleh, $otomatis): array {
            $antrean = $this->baris()
                ->filter(fn (array $baris) => in_array($baris['status'], [self::SIAP, self::GAGAL], true))
                ->when($otomatis, fn (Collection $baris) => $baris->reject(
                    fn (array $item) => ($item['catatan']?->percobaan ?? 0) >= self::MAKS_PERCOBAAN_OTOMATIS
                ))
                ->values();

            $hitung = ['berhasil' => 0, 'gagal' => 0, 'sisa' => $antrean->count()];

            foreach ($antrean->take(self::BATAS_PER_SINKRON) as $baris) {
                $catatan = $this->kirim($baris, $oleh);
                $hitung['sisa']--;
                $hitung[$catatan->sidik_diterima === $baris['sidik'] ? 'berhasil' : 'gagal']++;

                if ($catatan->kode_http === null) {
                    break;
                }
            }

            return $hitung;
        });

        return $hasil === false ? null : $hasil;
    }

    /**
     * Kirim satu pelaksana yang gagal atau berubah, atas keputusan super
     * administrator. Mengembalikan null bila barisnya tidak dapat dikirim.
     */
    public function kirimUlang(string $referensi, User $oleh): ?PengirimanMappalus
    {
        $baris = $this->baris()->first(fn (array $item) => $item['data']['referensi'] === $referensi
            && in_array($item['status'], [self::GAGAL, self::BERUBAH], true));

        return $baris ? $this->kirim($baris, $oleh) : null;
    }

    /**
     * Hasil tiap kiriman langsung dicatat, supaya sinkron yang terhenti di
     * tengah jalan tidak mengulang yang sudah diterima.
     *
     * @param  array<string, mixed>  $baris
     */
    private function kirim(array $baris, ?User $oleh): PengirimanMappalus
    {
        $catatan = PengirimanMappalus::firstOrNew(['referensi' => $baris['data']['referensi']]);

        $catatan->fill([
            'id_usulan' => $baris['id_usulan'],
            'id_nominatif' => $baris['id_nominatif'],
            'tim_kerja' => $baris['data']['tim_kerja'],
            'kode_akun' => $baris['data']['kode_akun'],
            'total_nominal' => $baris['data']['total_nominal'],
            'keterangan' => $baris['data']['keterangan'],
            'sidik' => $baris['sidik'],
            'percobaan' => $catatan->percobaan + 1,
            'kode_http' => null,
            'dikirim_at' => now(),
            'id_user' => $oleh?->id,
        ]);

        try {
            $balasan = Http::withToken($this->token())
                ->acceptJson()
                ->timeout(self::TENGGAT_DETIK)
                ->withHeaders(['X-Api-Token' => $this->token(), 'Idempotency-Key' => $baris['sidik']])
                ->post($this->alamat(), $baris['data']);

            $this->catatBalasan($catatan, $balasan, $baris['sidik']);
        } catch (ConnectionException $galat) {
            $catatan->pesan = mb_substr('Tidak dapat terhubung ke Mappalus: '.$galat->getMessage(), 0, 1000);
        } catch (Throwable $galat) {
            $catatan->pesan = mb_substr('Pengiriman gagal: '.$galat->getMessage(), 0, 1000);
        }

        $catatan->save();

        return $catatan;
    }

    /**
     * Mappalus berupa aplikasi yang menjawab 200 berisi halaman web pada
     * alamat apa pun, jadi kode 2xx saja belum membuktikan datanya diterima:
     * balasannya juga harus JSON dari API penerimanya.
     */
    private function catatBalasan(PengirimanMappalus $catatan, Response $balasan, string $sidik): void
    {
        $catatan->kode_http = $balasan->status();

        if (! $balasan->successful()) {
            $catatan->pesan = mb_substr('Mappalus menjawab '.$balasan->status().': '.$balasan->body(), 0, 1000);

            return;
        }

        if (! str_contains(strtolower((string) $balasan->header('Content-Type')), 'json')) {
            $catatan->pesan = "Mappalus menjawab {$balasan->status()} tetapi bukan JSON, jadi penerimaannya tidak terbukti. Periksa alamat API bersama pengembang Mappalus.";

            return;
        }

        $catatan->fill(['pesan' => null, 'sidik_diterima' => $sidik, 'diterima_at' => now()]);
    }
}
