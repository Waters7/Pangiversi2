<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Mengosongkan seluruh data transaksi perjalanan dinas menjelang rilis.
 *
 * Data master — pengguna, unit kerja, kategori perjadin, komponen biaya,
 * lokasi tujuan, dan tahun anggaran — sengaja dipertahankan karena itulah
 * bekal awal sistem saat dipakai sungguhan.
 */
class BersihkanDataTransaksi extends Command
{
    protected $signature = 'pangi:bersihkan-transaksi
                            {--berkas : Hapus juga berkas unggahan pada storage}
                            {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Hapus seluruh usulan perjalanan dinas beserta turunannya, menyisakan data master dan akun pengguna';

    /**
     * Diurutkan dari anak ke induk agar kunci asing tidak menghalangi.
     *
     * @var list<string>
     */
    private const TABEL_TRANSAKSI = [
        'rincian_biayas',
        'dokumen_keuangan',
        'daftar_riil',
        'keuangan',
        'dokumen',
        'peserta_usulan',
        'persetujuan',
        'audit_logs',
        'notifikasi',
        'usulan',
    ];

    /**
     * Berkas unggahan yang ikut kehilangan induknya.
     *
     * @var list<string>
     */
    private const FOLDER_BERKAS = ['dokumen', 'keuangan'];

    public function handle(): int
    {
        $jumlah = $this->hitung();
        $total = array_sum($jumlah);

        if ($total === 0 && ! $this->option('berkas')) {
            $this->info('Tidak ada data transaksi yang tersisa.');

            return self::SUCCESS;
        }

        $this->table(['Tabel', 'Baris'], collect($jumlah)->map(
            fn (int $n, string $tabel): array => [$tabel, number_format($n, 0, ',', '.')]
        )->values()->all());

        if (! $this->option('force') && ! $this->confirm("Hapus {$total} baris data transaksi? Tindakan ini tidak dapat dibatalkan.")) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $this->hapusBaris();

        if ($this->option('berkas')) {
            $this->hapusBerkas();
        }

        $this->newLine();
        $this->info('Data transaksi dikosongkan. Akun pengguna dan data master tetap utuh.');
        $this->line('Pengguna tersisa: '.DB::table('users')->count());

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function hitung(): array
    {
        $jumlah = [];

        foreach (self::TABEL_TRANSAKSI as $tabel) {
            if (Schema::hasTable($tabel)) {
                $jumlah[$tabel] = DB::table($tabel)->count();
            }
        }

        return $jumlah;
    }

    private function hapusBaris(): void
    {
        // SQLite maupun MySQL sama-sama menolak truncate pada tabel bertaut,
        // jadi kuncinya dinonaktifkan sepanjang pembersihan.
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABEL_TRANSAKSI as $tabel) {
                if (! Schema::hasTable($tabel)) {
                    continue;
                }

                DB::table($tabel)->delete();
                $this->line("  dikosongkan: {$tabel}");
            }

            $this->aturUlangUrutan();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Kembalikan penomoran id ke satu supaya data pertama terlihat rapi.
     */
    private function aturUlangUrutan(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (self::TABEL_TRANSAKSI as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            match ($driver) {
                'sqlite' => DB::table('sqlite_sequence')->where('name', $tabel)->delete(),
                'mysql', 'mariadb' => DB::statement("ALTER TABLE `{$tabel}` AUTO_INCREMENT = 1"),
                'pgsql' => DB::statement("ALTER SEQUENCE IF EXISTS {$tabel}_id_seq RESTART WITH 1"),
                default => null,
            };
        }
    }

    private function hapusBerkas(): void
    {
        $disk = Storage::disk('public');

        foreach (self::FOLDER_BERKAS as $folder) {
            if (! $disk->exists($folder)) {
                continue;
            }

            $jumlah = count($disk->allFiles($folder));
            $disk->deleteDirectory($folder);

            $this->line("  berkas dihapus: {$folder} ({$jumlah} berkas)");
        }
    }
}
