<?php

namespace App\Services;

use App\Models\CatatanPenyimpanan;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Mengukur ukuran aplikasi di peladen: berkas unggahan, basis data, log,
 * pustaka, dan kode — untuk menu Administrasi Sistem → Pemantauan Server.
 *
 * Folder aplikasi dijelajahi sekali saja dan setiap berkas dimasukkan ke
 * satu kelompok, sehingga jumlah kelompoknya sama dengan total. Tautan
 * public/storage dilewati supaya berkas unggahan tidak terhitung dua kali.
 */
class PengukurPenyimpanan
{
    /** @var array<string, string> */
    public const KELOMPOK = [
        'unggahan' => 'Berkas unggahan pengguna',
        'basis_data' => 'Basis data',
        'log' => 'Log aplikasi',
        'sementara' => 'Cache, sesi & tampilan terkompilasi',
        'berkas_sistem' => 'Berkas sistem lain',
        'pustaka' => 'Pustaka PHP (vendor)',
        'aplikasi' => 'Kode & aset aplikasi',
        'git' => 'Riwayat git',
    ];

    /**
     * Sebutan folder unggahan yang dipahami pengguna.
     *
     * @var array<string, string>
     */
    public const LABEL_FOLDER = [
        'dokumen/surat-tugas' => 'Surat tugas',
        'dokumen/spd' => 'SPD bertanda tangan',
        'dokumen/sppd' => 'SPPD bertanda tangan',
        'dokumen/rundown' => 'Rundown kegiatan',
        'dokumen/dokumen-pendukung' => 'Dokumen pendukung',
        'dokumen/boarding-pass' => 'Boarding pass',
        'dokumen/invoice-tiket' => 'Invoice tiket',
        'dokumen/nota-transport' => 'Nota transportasi',
        'dokumen/bill_hotel' => 'Bill hotel',
        'dokumen/bill-hotel' => 'Bill hotel (lama)',
        'dokumen/kwintasi' => 'Kuitansi',
        'dokumen/faktur' => 'Faktur (lama)',
        'dokumen/laporan-hasil' => 'Laporan hasil',
        'dokumen/penyelenggaraan_bukti' => 'Bukti biaya penyelenggaraan',
        'keuangan/uang-muka' => 'Bukti transfer uang muka',
        'keuangan/pelunasan' => 'Bukti transfer pelunasan',
        'keuangan/transport-lokal' => 'Bukti bayar transport lokal',
        'foto-profil' => 'Foto profil',
        'bantuan' => 'Lampiran bantuan',
    ];

    /**
     * Folder yang tidak dijelajahi: node_modules tidak dipasang di peladen
     * dan menghitungnya lama; public/storage hanya tautan ke unggahan.
     */
    private const DILEWATI = ['node_modules', 'public/storage', 'public/hot'];

    private const JUMLAH_TERBESAR = 10;

    private const JUMLAH_TABEL = 10;

    /**
     * Ukur sekarang lalu simpan sebagai catatan hari ini.
     */
    public function catat(): CatatanPenyimpanan
    {
        $hasil = $this->ukur();
        $tanggal = now('Asia/Makassar')->toDateString();

        $catatan = CatatanPenyimpanan::whereDate('tanggal', $tanggal)->first() ?? new CatatanPenyimpanan(['tanggal' => $tanggal]);

        $catatan->fill([
            'total_byte' => $hasil['total'],
            'unggahan_byte' => $hasil['kelompok']['unggahan']['byte'],
            'basis_data_byte' => $hasil['kelompok']['basis_data']['byte'],
            'rincian' => $hasil,
        ])->save();

        return $catatan;
    }

    /**
     * @return array{
     *     total: int,
     *     kelompok: array<string, array{byte: int, berkas: int}>,
     *     unggahan: list<array{folder: string, label: string, byte: int, berkas: int}>,
     *     tabel: list<array{nama: string, byte: int}>,
     *     terbesar: list<array{jalur: string, byte: int}>,
     *     basis_data: array{driver: string, terukur: bool},
     *     disk: array{total: ?int, bebas: ?int},
     *     lama_detik: float
     * }
     */
    public function ukur(): array
    {
        $mulai = microtime(true);
        $akar = $this->normalkan((string) config('pantau.akar'));
        $sqlite = $this->berkasSqlite();

        $kelompok = array_fill_keys(array_keys(self::KELOMPOK), ['byte' => 0, 'berkas' => 0]);
        $unggahan = [];
        $terbesar = [];

        foreach ($this->berkasDi($akar) as $berkas) {
            if ($berkas->isLink()) {
                continue;
            }

            $jalur = $this->normalkan($berkas->getPathname());
            $relatif = ltrim(substr($jalur, strlen($akar)), '/');

            try {
                $ukuran = (int) $berkas->getSize();
            } catch (Throwable) {
                continue;
            }

            $kunci = $this->kelompokUntuk($relatif, $sqlite !== null && str_starts_with($jalur, $sqlite));
            $kelompok[$kunci]['byte'] += $ukuran;
            $kelompok[$kunci]['berkas']++;

            if ($kunci === 'unggahan') {
                $folder = $this->folderUnggahan($relatif);
                $unggahan[$folder] ??= ['byte' => 0, 'berkas' => 0];
                $unggahan[$folder]['byte'] += $ukuran;
                $unggahan[$folder]['berkas']++;
            }

            // Berkas terbesar hanya dari storage — yang dapat ditindaklanjuti
            // administrator, bukan pustaka atau kode.
            if (str_starts_with($relatif, 'storage/')) {
                $terbesar = $this->simpanTerbesar($terbesar, $relatif, $ukuran);
            }
        }

        [$tabel, $ukuranTabel, $terukur] = $this->ukurBasisData();

        // SQLite di dalam folder aplikasi sudah terhitung dari berkasnya.
        // Yang di luar folder diukur dari berkasnya juga; MySQL dari ukuran
        // tabelnya.
        if ($kelompok['basis_data']['byte'] === 0) {
            $ukuranBerkas = $sqlite !== null ? $this->ukuranBerkasSqlite($sqlite) : 0;

            if ($ukuranBerkas > 0 || $ukuranTabel > 0) {
                $kelompok['basis_data'] = $ukuranBerkas > 0
                    ? ['byte' => $ukuranBerkas, 'berkas' => 1]
                    : ['byte' => $ukuranTabel, 'berkas' => count($tabel)];
            }
        }

        uasort($unggahan, fn (array $a, array $b) => $b['byte'] <=> $a['byte']);

        return [
            'total' => array_sum(array_column($kelompok, 'byte')),
            'kelompok' => $kelompok,
            'unggahan' => array_values(array_map(
                fn (string $folder, array $isi) => [
                    'folder' => $folder,
                    'label' => self::LABEL_FOLDER[$folder] ?? $folder,
                    'byte' => $isi['byte'],
                    'berkas' => $isi['berkas'],
                ],
                array_keys($unggahan),
                $unggahan,
            )),
            'tabel' => $tabel,
            'terbesar' => $terbesar,
            'basis_data' => [
                'driver' => DB::connection()->getDriverName(),
                'terukur' => $terukur || $kelompok['basis_data']['byte'] > 0,
            ],
            'disk' => $this->ruangDisk($akar),
            'lama_detik' => round(microtime(true) - $mulai, 2),
        ];
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function berkasDi(string $akar): iterable
    {
        if (! is_dir($akar)) {
            return [];
        }

        $direktori = new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS);

        $tersaring = new RecursiveCallbackFilterIterator(
            $direktori,
            function (SplFileInfo $berkas, string $kunci, RecursiveDirectoryIterator $iterator) use ($akar): bool {
                if (! $iterator->hasChildren()) {
                    return true;
                }

                $relatif = ltrim(substr($this->normalkan($berkas->getPathname()), strlen($akar)), '/');

                return ! in_array($relatif, self::DILEWATI, true);
            },
        );

        return new RecursiveIteratorIterator(
            $tersaring,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );
    }

    private function kelompokUntuk(string $relatif, bool $berkasSqlite): string
    {
        return match (true) {
            $berkasSqlite => 'basis_data',
            str_starts_with($relatif, 'storage/app/public/') => 'unggahan',
            str_starts_with($relatif, 'storage/app/') => 'berkas_sistem',
            str_starts_with($relatif, 'storage/logs/') => 'log',
            str_starts_with($relatif, 'storage/framework/') => 'sementara',
            str_starts_with($relatif, 'vendor/') => 'pustaka',
            str_starts_with($relatif, '.git/') => 'git',
            default => 'aplikasi',
        };
    }

    /**
     * Folder unggahan dikelompokkan sampai jenis berkasnya, misalnya
     * "dokumen/boarding-pass"; folder lain cukup nama puncaknya.
     */
    private function folderUnggahan(string $relatif): string
    {
        $bagian = explode('/', substr($relatif, strlen('storage/app/public/')));

        if (count($bagian) === 1) {
            return '(akar unggahan)';
        }

        return in_array($bagian[0], ['dokumen', 'keuangan'], true) && count($bagian) > 2
            ? $bagian[0].'/'.$bagian[1]
            : $bagian[0];
    }

    /**
     * @param  list<array{jalur: string, byte: int}>  $terbesar
     * @return list<array{jalur: string, byte: int}>
     */
    private function simpanTerbesar(array $terbesar, string $jalur, int $ukuran): array
    {
        if (count($terbesar) >= self::JUMLAH_TERBESAR && $ukuran <= end($terbesar)['byte']) {
            return $terbesar;
        }

        $terbesar[] = ['jalur' => $jalur, 'byte' => $ukuran];
        usort($terbesar, fn (array $a, array $b) => $b['byte'] <=> $a['byte']);

        return array_slice($terbesar, 0, self::JUMLAH_TERBESAR);
    }

    /**
     * Ukuran tiap tabel, dari information_schema pada MySQL atau dbstat pada
     * SQLite. Hosting tertentu menutup aksesnya — halaman tetap tampil.
     *
     * @return array{0: list<array{nama: string, byte: int}>, 1: int, 2: bool}
     */
    private function ukurBasisData(): array
    {
        try {
            $tabel = collect(Schema::getTables())
                ->map(fn (array $satu) => ['nama' => (string) $satu['name'], 'byte' => (int) ($satu['size'] ?? 0)])
                ->sortByDesc('byte')
                ->values();
        } catch (Throwable) {
            return [[], 0, false];
        }

        return [$tabel->take(self::JUMLAH_TABEL)->all(), (int) $tabel->sum('byte'), $tabel->sum('byte') > 0];
    }

    /**
     * Berkas SQLite yang dipakai, bila basis datanya SQLite berkas.
     */
    private function berkasSqlite(): ?string
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return null;
        }

        $jalur = realpath((string) DB::connection()->getConfig('database'));

        return $jalur === false ? null : $this->normalkan($jalur);
    }

    /**
     * Berkas SQLite beserta jurnal WAL-nya, yang ikut memakan ruang.
     */
    private function ukuranBerkasSqlite(string $jalur): int
    {
        return (int) collect([$jalur, $jalur.'-wal', $jalur.'-shm'])
            ->filter(fn (string $berkas) => is_file($berkas))
            ->sum(fn (string $berkas) => filesize($berkas));
    }

    /**
     * Ruang cakram peladen. Pada hosting bersama angka ini milik seluruh
     * peladen, bukan kuota akun — fungsinya pun kadang dimatikan.
     *
     * @return array{total: ?int, bebas: ?int}
     */
    private function ruangDisk(string $akar): array
    {
        $total = function_exists('disk_total_space') ? @disk_total_space($akar) : false;
        $bebas = function_exists('disk_free_space') ? @disk_free_space($akar) : false;

        return [
            'total' => $total === false ? null : (int) $total,
            'bebas' => $bebas === false ? null : (int) $bebas,
        ];
    }

    private function normalkan(string $jalur): string
    {
        return rtrim(str_replace('\\', '/', $jalur), '/');
    }
}
