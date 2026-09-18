<?php

namespace App\Services;

use App\Models\LogApi;
use App\Models\Pengaturan;
use App\Models\PengirimanIntegrasi;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pengiriman data dashboard eksekutif ke aplikasi tujuan, terjadwal maupun
 * manual, memakai token bersama.
 *
 * Kebalikan dari API bertoken: di sini PANGI yang mendorong datanya, jadi
 * aplikasi tujuan tidak perlu menjadwalkan penarikan sendiri. Alamat,
 * token, dan jadwalnya diatur super administrator pada menu Integrasi
 * Data; setiap pengiriman — berhasil atau gagal — dicatat.
 */
class PengirimIntegrasi
{
    public const JADWAL = [
        'tiap_jam' => 'Tiap jam',
        'harian' => 'Harian',
        'mingguan' => 'Mingguan',
    ];

    public const HARI = [
        '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis',
        '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
    ];

    /** Batas tunggu balasan aplikasi tujuan, detik. */
    private const TENGGAT_DETIK = 20;

    public function __construct(
        private PaketDataEksekutif $paket,
        private RingkasanEksekutif $ringkasan,
    ) {}

    /**
     * Pengaturan yang sedang berlaku, siap ditampilkan.
     *
     * @return array{aktif: bool, url: string, token_tersamar: ?string, token_dari: 'sendiri'|'api'|null, jadwal: string, jam: string, hari: string}
     */
    public function pengaturan(): array
    {
        $tokenSendiri = Pengaturan::rahasia(Pengaturan::INTEGRASI_TOKEN_TUJUAN);
        $token = $this->token();

        return [
            'aktif' => Pengaturan::aktif(Pengaturan::INTEGRASI_AKTIF),
            'url' => Pengaturan::ambil(Pengaturan::INTEGRASI_URL),
            'token_tersamar' => $token === '' ? null : LogApi::samarkan($token),
            'token_dari' => $token === '' ? null : ($tokenSendiri !== '' ? 'sendiri' : 'api'),
            'jadwal' => Pengaturan::ambil(Pengaturan::INTEGRASI_JADWAL),
            'jam' => Pengaturan::ambil(Pengaturan::INTEGRASI_JAM),
            'hari' => Pengaturan::ambil(Pengaturan::INTEGRASI_HARI),
        ];
    }

    /**
     * Token yang dibawa saat mengirim: token tujuan bila diisi, kalau tidak
     * token API PANGI sendiri — satu token bersama untuk dua arah.
     */
    public function token(): string
    {
        $sendiri = Pengaturan::rahasia(Pengaturan::INTEGRASI_TOKEN_TUJUAN);

        return $sendiri !== '' ? $sendiri : Pengaturan::tokenApi()['token'];
    }

    /** Pengiriman diaktifkan dan alamat tujuannya terisi. */
    public function siap(): bool
    {
        return Pengaturan::aktif(Pengaturan::INTEGRASI_AKTIF)
            && filter_var(Pengaturan::ambil(Pengaturan::INTEGRASI_URL), FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Waktu kirim terjadwal berikutnya menurut pengaturan, dihitung dari
     * pengiriman terjadwal terakhir (berhasil maupun gagal — kegagalan tidak
     * diulang tiap menit, melainkan pada jadwal berikutnya).
     */
    public function jadwalBerikutnya(?CarbonInterface $sekarang = null): ?CarbonInterface
    {
        if (! $this->siap()) {
            return null;
        }

        $sekarang = $sekarang ?? now();
        $terakhir = $this->terakhirTerjadwal()?->created_at;
        [$jam, $menit] = $this->jamMenit();

        return match (Pengaturan::ambil(Pengaturan::INTEGRASI_JADWAL)) {
            'tiap_jam' => $terakhir ? $terakhir->copy()->addHour()->startOfMinute() : $sekarang->copy()->startOfMinute(),
            'mingguan' => $this->berikutnyaMingguan($sekarang, $terakhir, $jam, $menit),
            default => $this->berikutnyaHarian($sekarang, $terakhir, $jam, $menit),
        };
    }

    /** Sudah waktunya penjadwal mengirim. */
    public function jatuhTempo(?CarbonInterface $sekarang = null): bool
    {
        $berikutnya = $this->jadwalBerikutnya($sekarang);

        return $berikutnya !== null && ! ($sekarang ?? now())->lt($berikutnya);
    }

    /**
     * Kirim paket tahun berjalan ke alamat tujuan dan catat hasilnya.
     */
    public function kirim(string $pemicu = PengirimanIntegrasi::PEMICU_JADWAL, ?User $oleh = null): PengirimanIntegrasi
    {
        $tujuan = Pengaturan::ambil(Pengaturan::INTEGRASI_URL);
        $tahun = $this->ringkasan->tahunBawaan();
        $mulai = microtime(true);
        $catatan = [
            'pemicu' => $pemicu,
            'id_user' => $oleh?->id,
            'tujuan' => $tujuan,
            'tahun' => $tahun,
            'created_at' => now(),
        ];

        if (filter_var($tujuan, FILTER_VALIDATE_URL) === false) {
            return PengirimanIntegrasi::create($catatan + [
                'status' => PengirimanIntegrasi::GAGAL,
                'pesan' => 'Alamat tujuan belum diisi atau tidak sah.',
            ]);
        }

        try {
            $paket = $this->paket->kiriman($tahun);
            $ukuran = strlen((string) json_encode($paket));

            $balasan = Http::withToken($this->token())
                ->acceptJson()
                ->timeout(self::TENGGAT_DETIK)
                ->withHeaders(['X-Api-Token' => $this->token()])
                ->post($tujuan, $paket);

            return PengirimanIntegrasi::create($catatan + [
                'status' => $balasan->successful() ? PengirimanIntegrasi::BERHASIL : PengirimanIntegrasi::GAGAL,
                'kode_http' => $balasan->status(),
                'pesan' => $balasan->successful()
                    ? null
                    : 'Aplikasi tujuan menjawab '.$balasan->status().': '.mb_substr($balasan->body(), 0, 500),
                'ukuran_byte' => $ukuran,
                'durasi_ms' => (int) round((microtime(true) - $mulai) * 1000),
            ]);
        } catch (ConnectionException $galat) {
            $pesan = 'Tidak dapat terhubung ke aplikasi tujuan: '.$galat->getMessage();
        } catch (Throwable $galat) {
            $pesan = 'Pengiriman gagal: '.$galat->getMessage();
        }

        return PengirimanIntegrasi::create($catatan + [
            'status' => PengirimanIntegrasi::GAGAL,
            'pesan' => mb_substr($pesan, 0, 1000),
            'durasi_ms' => (int) round((microtime(true) - $mulai) * 1000),
        ]);
    }

    public function terakhirTerjadwal(): ?PengirimanIntegrasi
    {
        return PengirimanIntegrasi::where('pemicu', PengirimanIntegrasi::PEMICU_JADWAL)->latest('id')->first();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function jamMenit(): array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', Pengaturan::ambil(Pengaturan::INTEGRASI_JAM), $m)) {
            return [min(23, (int) $m[1]), min(59, (int) $m[2])];
        }

        return [6, 0];
    }

    private function berikutnyaHarian(CarbonInterface $sekarang, ?CarbonInterface $terakhir, int $jam, int $menit): CarbonInterface
    {
        $hariIni = Carbon::instance($sekarang)->setTime($jam, $menit);

        // Belum pernah, atau kiriman terakhir sebelum jadwal hari ini → hari ini.
        if ($terakhir === null || $terakhir->lt($hariIni)) {
            return $hariIni;
        }

        return $hariIni->addDay();
    }

    private function berikutnyaMingguan(CarbonInterface $sekarang, ?CarbonInterface $terakhir, int $jam, int $menit): CarbonInterface
    {
        $hari = (int) Pengaturan::ambil(Pengaturan::INTEGRASI_HARI); // 1 = Senin
        $mingguIni = Carbon::instance($sekarang)->startOfWeek(CarbonInterface::MONDAY)->addDays($hari - 1)->setTime($jam, $menit);

        if ($terakhir === null || $terakhir->lt($mingguIni)) {
            return $mingguIni;
        }

        return $mingguIni->addWeek();
    }
}
