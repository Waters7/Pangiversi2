<?php

namespace App\Services;

use App\Models\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * Memeriksa kesehatan aplikasi tiap menit — situs dapat diakses, basis data
 * tersambung, penyimpanan dapat ditulisi — lalu mengirim peringatan
 * WhatsApp ke nomor yang diatur bila aplikasi tidak dapat diakses, dan
 * kabar susulan ketika pulih.
 *
 * Status, riwayat, dan cermin pengaturannya ditulis sebagai berkas: saat
 * basis data terputus, peringatan tetap harus dapat dikirim. Karena itu
 * pemeriksaan dijalankan cron tersendiri (pangi:pantau-server), bukan lewat
 * schedule:run yang ikut bergantung pada basis data.
 */
class PemantauServer
{
    public const STATUS_NORMAL = 'normal';

    public const STATUS_GANGGUAN = 'gangguan';

    public const JENIS_GANGGUAN = 'gangguan';

    public const JENIS_PENGINGAT = 'pengingat';

    public const JENIS_PULIH = 'pulih';

    public const JENIS_UJI = 'uji';

    /** Pemeriksaan yang lebih lama dari ini menandakan cron berhenti. */
    public const BATAS_SENYAP_MENIT = 5;

    private const BATAS_RIWAYAT = 30;

    private const BERKAS_STATUS = 'status-server.json';

    private const BERKAS_CERMIN = 'pengaturan-peringatan.json';

    public function __construct(private PengirimWhatsapp $wa) {}

    // ── Pengaturan ──

    /**
     * Pengaturan peringatan yang berlaku: dari basis data bila tersambung,
     * dari cerminnya bila tidak.
     *
     * @return array{aktif: bool, nomor: list<string>, gateway: string, token: string, url_wablas: string, ulang_menit: int}
     */
    public function pengaturan(): array
    {
        try {
            $pengaturan = $this->pengaturanBasisData();
        } catch (Throwable) {
            return $this->pengaturanCermin();
        }

        if (! is_file($this->berkas(self::BERKAS_CERMIN))) {
            $this->tulisCermin($pengaturan);
        }

        return $pengaturan;
    }

    /**
     * @param  array{aktif: bool, nomor: list<string>, gateway: string, url_wablas: string, ulang_menit: int}  $data
     * @param  string|null  $tokenBaru  Diisi untuk mengganti token; null berarti biarkan.
     */
    public function simpanPengaturan(array $data, ?string $tokenBaru, bool $hapusToken = false): void
    {
        Pengaturan::simpan([
            Pengaturan::PERINGATAN_AKTIF => $data['aktif'] ? '1' : '0',
            Pengaturan::PERINGATAN_NOMOR => implode(',', $data['nomor']),
            Pengaturan::WA_GATEWAY => $data['gateway'],
            Pengaturan::WA_URL_WABLAS => $data['url_wablas'],
            Pengaturan::PERINGATAN_ULANG_MENIT => (string) $data['ulang_menit'],
        ]);

        if ($hapusToken) {
            Pengaturan::simpanRahasia(Pengaturan::WA_TOKEN, '');
        } elseif (filled($tokenBaru)) {
            Pengaturan::simpanRahasia(Pengaturan::WA_TOKEN, $tokenBaru);
        }

        $this->tulisCermin($this->pengaturanBasisData());
    }

    /**
     * @return array{aktif: bool, nomor: list<string>, gateway: string, token: string, url_wablas: string, ulang_menit: int}
     */
    private function pengaturanBasisData(): array
    {
        return [
            'aktif' => Pengaturan::aktif(Pengaturan::PERINGATAN_AKTIF),
            'nomor' => PengirimWhatsapp::daftarNomor(Pengaturan::ambil(Pengaturan::PERINGATAN_NOMOR)),
            'gateway' => Pengaturan::ambil(Pengaturan::WA_GATEWAY),
            'token' => Pengaturan::rahasia(Pengaturan::WA_TOKEN),
            'url_wablas' => Pengaturan::ambil(Pengaturan::WA_URL_WABLAS),
            'ulang_menit' => max(15, Pengaturan::angka(Pengaturan::PERINGATAN_ULANG_MENIT)),
        ];
    }

    /**
     * @return array{aktif: bool, nomor: list<string>, gateway: string, token: string, url_wablas: string, ulang_menit: int}
     */
    private function pengaturanCermin(): array
    {
        $cermin = $this->bacaJson(self::BERKAS_CERMIN);

        try {
            $token = filled($cermin['token'] ?? null) ? Crypt::decryptString($cermin['token']) : '';
        } catch (Throwable) {
            $token = '';
        }

        return [
            'aktif' => (bool) ($cermin['aktif'] ?? false),
            'nomor' => array_values((array) ($cermin['nomor'] ?? [])),
            'gateway' => (string) ($cermin['gateway'] ?? 'fonnte'),
            'token' => $token,
            'url_wablas' => (string) ($cermin['url_wablas'] ?? ''),
            'ulang_menit' => max(15, (int) ($cermin['ulang_menit'] ?? 60)),
        ];
    }

    /**
     * Token di cermin tetap terenkripsi dengan APP_KEY, sama seperti di
     * basis data — berkas ini tidak boleh membuka rahasianya.
     *
     * @param  array{aktif: bool, nomor: list<string>, gateway: string, token: string, url_wablas: string, ulang_menit: int}  $pengaturan
     */
    private function tulisCermin(array $pengaturan): void
    {
        $this->tulisJson(self::BERKAS_CERMIN, [
            'token' => $pengaturan['token'] === '' ? '' : Crypt::encryptString($pengaturan['token']),
        ] + $pengaturan);
    }

    // ── Pemeriksaan ──

    /**
     * @return list<array{kode: string, label: string, lolos: bool, pesan: string}>
     */
    public function periksa(): array
    {
        return [
            $this->periksaSitus(),
            $this->periksaBasisData(),
            $this->periksaPenyimpanan(),
        ];
    }

    /**
     * @return array{kode: string, label: string, lolos: bool, pesan: string}
     */
    private function periksaSitus(): array
    {
        $alamat = rtrim((string) config('app.url'), '/').'/up';
        $tenggat = (int) config('pantau.tenggat_detik', 10);

        try {
            $balasan = Http::timeout($tenggat)->connectTimeout($tenggat)->get($alamat);
        } catch (Throwable $e) {
            return $this->hasil('situs', 'Situs', false, 'Tidak dapat dihubungi — '.Str::limit($e->getMessage(), 140));
        }

        return match (true) {
            $balasan->successful() => $this->hasil('situs', 'Situs', true, 'Dapat diakses (HTTP '.$balasan->status().')'),
            $balasan->status() === 503 => $this->hasil('situs', 'Situs', false, 'Mode pemeliharaan atau layanan tidak tersedia (HTTP 503)'),
            default => $this->hasil('situs', 'Situs', false, 'Membalas galat HTTP '.$balasan->status()),
        };
    }

    /**
     * @return array{kode: string, label: string, lolos: bool, pesan: string}
     */
    private function periksaBasisData(): array
    {
        $this->batasiTungguBasisData();

        try {
            DB::connection()->select('select 1');
        } catch (Throwable $e) {
            return $this->hasil('basis_data', 'Basis data', false, 'Tidak tersambung — '.Str::limit($e->getMessage(), 140));
        }

        return $this->hasil('basis_data', 'Basis data', true, 'Tersambung');
    }

    /**
     * @return array{kode: string, label: string, lolos: bool, pesan: string}
     */
    private function periksaPenyimpanan(): array
    {
        $uji = $this->berkas('.uji-tulis');

        try {
            $this->pastikanFolder();
            $tertulis = file_put_contents($uji, (string) time()) !== false;
            @unlink($uji);
        } catch (Throwable) {
            $tertulis = false;
        }

        return $tertulis
            ? $this->hasil('penyimpanan', 'Penyimpanan', true, 'Dapat ditulisi')
            : $this->hasil('penyimpanan', 'Penyimpanan', false, 'Folder storage tidak dapat ditulisi (penuh atau izinnya berubah)');
    }

    /**
     * Basis data yang tidak menjawab jangan sampai menahan pemeriksaan
     * sampai melewati menit berikutnya.
     */
    private function batasiTungguBasisData(): void
    {
        $nama = (string) config('database.default');

        if (! in_array(config("database.connections.{$nama}.driver"), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        config(["database.connections.{$nama}.options" => (array) config("database.connections.{$nama}.options")
            + [PDO::ATTR_TIMEOUT => (int) config('pantau.tenggat_detik', 10)]]);
        DB::purge($nama);
    }

    /**
     * @return array{kode: string, label: string, lolos: bool, pesan: string}
     */
    private function hasil(string $kode, string $label, bool $lolos, string $pesan): array
    {
        return compact('kode', 'label', 'lolos', 'pesan');
    }

    // ── Siklus tiap menit ──

    /**
     * Satu putaran pemantauan: periksa, perbarui status, dan kirim
     * peringatan bila perlu.
     *
     * Gangguan baru diumumkan setelah beberapa kali gagal berturut-turut;
     * selama belum pulih, pengingat dikirim tiap jeda yang diatur; begitu
     * pulih, kabarnya dikirim sekali.
     *
     * @return array<string, mixed> Status terbaru.
     */
    public function jalankan(): array
    {
        $status = $this->status();
        $pemeriksaan = $this->periksa();
        $gagal = array_values(array_filter($pemeriksaan, fn (array $satu) => ! $satu['lolos']));
        $sekarang = now();

        $status['terakhir_dicek'] = $sekarang->toIso8601String();
        $status['pemeriksaan'] = $pemeriksaan;

        if ($gagal === []) {
            if ($status['status'] === self::STATUS_GANGGUAN) {
                $status = $this->beritahu($status, self::JENIS_PULIH, $this->pesanPulih($status, $sekarang));
            }

            return $this->tulisStatus(array_merge($status, [
                'status' => self::STATUS_NORMAL,
                'gagal_beruntun' => 0,
                'gagal_sejak' => null,
                'diberitahu_at' => null,
            ]));
        }

        $status['gagal_beruntun'] = (int) $status['gagal_beruntun'] + 1;
        $status['gagal_sejak'] ??= $sekarang->toIso8601String();

        if ($status['status'] === self::STATUS_NORMAL && $status['gagal_beruntun'] >= (int) config('pantau.ambang_gagal', 3)) {
            $status['status'] = self::STATUS_GANGGUAN;
            $status = $this->beritahu($status, self::JENIS_GANGGUAN, $this->pesanGangguan($status, $pemeriksaan, false));
            $status['diberitahu_at'] = $sekarang->toIso8601String();
        } elseif ($status['status'] === self::STATUS_GANGGUAN && $this->waktunyaMengingatkan($status, $sekarang)) {
            $status = $this->beritahu($status, self::JENIS_PENGINGAT, $this->pesanGangguan($status, $pemeriksaan, true));
            $status['diberitahu_at'] = $sekarang->toIso8601String();
        }

        return $this->tulisStatus($status);
    }

    /**
     * Kirim pesan uji ke nomor yang diatur, tanpa menunggu gangguan.
     *
     * @return array{terkirim: bool, pesan: string}
     */
    public function kirimUji(): array
    {
        $pengaturan = $this->pengaturan();
        $isi = "*Uji peringatan PANGI*\n"
            ."Pesan ini menandakan peringatan WhatsApp pemantau server sudah tersambung.\n\n"
            .'Alamat: '.config('app.url')."\n"
            .'Waktu: '.$this->waktu(now());

        $hasil = $this->wa->kirim($pengaturan, $pengaturan['nomor'], $isi);
        $this->tulisStatus($this->catatRiwayat($this->status(), self::JENIS_UJI, $hasil));

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function waktunyaMengingatkan(array $status, CarbonInterface $sekarang): bool
    {
        if (blank($status['diberitahu_at'] ?? null)) {
            return true;
        }

        $menit = $this->pengaturan()['ulang_menit'];

        return Carbon::parse($status['diberitahu_at'])->addMinutes($menit)->lte($sekarang);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function beritahu(array $status, string $jenis, string $isi): array
    {
        $pengaturan = $this->pengaturan();

        $hasil = $pengaturan['aktif']
            ? $this->wa->kirim($pengaturan, $pengaturan['nomor'], $isi)
            : ['terkirim' => false, 'pesan' => 'Peringatan WhatsApp nonaktif — tidak dikirim.'];

        return $this->catatRiwayat($status, $jenis, $hasil);
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  array{terkirim: bool, pesan: string}  $hasil
     * @return array<string, mixed>
     */
    private function catatRiwayat(array $status, string $jenis, array $hasil): array
    {
        array_unshift($status['riwayat'], [
            'waktu' => now()->toIso8601String(),
            'jenis' => $jenis,
            'terkirim' => $hasil['terkirim'],
            'keterangan' => $hasil['pesan'],
        ]);

        $status['riwayat'] = array_slice($status['riwayat'], 0, self::BATAS_RIWAYAT);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  list<array{kode: string, label: string, lolos: bool, pesan: string}>  $pemeriksaan
     */
    private function pesanGangguan(array $status, array $pemeriksaan, bool $pengingat): string
    {
        $sejak = Carbon::parse($status['gagal_sejak']);

        $judul = $pengingat
            ? '*PANGI masih belum dapat diakses* — sudah '.$this->lama($sejak, now())
            : '*PERINGATAN — PANGI tidak dapat diakses*';

        return $judul."\n"
            .'Sejak: '.$this->waktu($sejak)."\n"
            .'Alamat: '.config('app.url')."\n\n"
            ."Hasil pemeriksaan:\n"
            .collect($pemeriksaan)->map(fn (array $satu) => '- '.$satu['label'].': '.$satu['pesan'])->implode("\n")
            ."\n\nPesan otomatis dari pemantau server PANGI.";
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function pesanPulih(array $status, CarbonInterface $sekarang): string
    {
        $sejak = Carbon::parse($status['gagal_sejak'] ?? $sekarang);

        return "*PANGI kembali normal*\n"
            .'Pulih: '.$this->waktu($sekarang).', setelah gangguan sekitar '.$this->lama($sejak, $sekarang).".\n"
            .'Alamat: '.config('app.url')."\n\n"
            .'Pesan otomatis dari pemantau server PANGI.';
    }

    private function waktu(CarbonInterface $waktu): string
    {
        return $waktu->copy()->timezone('Asia/Makassar')->translatedFormat('d M Y, H.i').' WITA';
    }

    private function lama(CarbonInterface $dari, CarbonInterface $sampai): string
    {
        $menit = max(1, (int) round($dari->diffInMinutes($sampai)));

        return $menit < 60
            ? $menit.' menit'
            : intdiv($menit, 60).' jam '.($menit % 60).' menit';
    }

    // ── Status di berkas ──

    /**
     * @return array{status: string, gagal_beruntun: int, gagal_sejak: ?string, diberitahu_at: ?string, terakhir_dicek: ?string, pemeriksaan: list<array<string, mixed>>, riwayat: list<array<string, mixed>>}
     */
    public function status(): array
    {
        return array_merge([
            'status' => self::STATUS_NORMAL,
            'gagal_beruntun' => 0,
            'gagal_sejak' => null,
            'diberitahu_at' => null,
            'terakhir_dicek' => null,
            'pemeriksaan' => [],
            'riwayat' => [],
        ], $this->bacaJson(self::BERKAS_STATUS));
    }

    /**
     * Cron pemeriksaan berjalan: pemeriksaan terakhir masih baru.
     */
    public function cronBerjalan(): bool
    {
        $terakhir = $this->status()['terakhir_dicek'];

        return filled($terakhir)
            && Carbon::parse($terakhir)->gt(now()->subMinutes(self::BATAS_SENYAP_MENIT));
    }

    /**
     * Berkas kunci supaya dua putaran tidak menulis status bersamaan.
     *
     * @return resource|null Null bila putaran lain masih berjalan.
     */
    public function kunci()
    {
        $this->pastikanFolder();
        $kunci = fopen($this->berkas('.kunci'), 'c');

        if ($kunci === false || ! flock($kunci, LOCK_EX | LOCK_NB)) {
            return null;
        }

        return $kunci;
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function tulisStatus(array $status): array
    {
        $this->tulisJson(self::BERKAS_STATUS, $status);

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function bacaJson(string $nama): array
    {
        $jalur = $this->berkas($nama);

        if (! is_file($jalur)) {
            return [];
        }

        $isi = json_decode((string) file_get_contents($jalur), true);

        return is_array($isi) ? $isi : [];
    }

    /**
     * Ditulis ke berkas sementara lalu dipindahkan, supaya pembaca tidak
     * pernah mendapati berkas setengah jadi.
     *
     * @param  array<string, mixed>  $isi
     */
    private function tulisJson(string $nama, array $isi): void
    {
        $this->pastikanFolder();

        $jalur = $this->berkas($nama);
        $sementara = $jalur.'.'.Str::random(6).'.tmp';

        file_put_contents($sementara, json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($sementara, $jalur);
    }

    private function pastikanFolder(): void
    {
        $folder = (string) config('pantau.folder');

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
    }

    private function berkas(string $nama): string
    {
        return rtrim((string) config('pantau.folder'), '/\\').DIRECTORY_SEPARATOR.$nama;
    }
}
