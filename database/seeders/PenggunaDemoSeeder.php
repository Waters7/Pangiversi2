<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Services\ImporPengguna;
use App\Services\SumberDataPegawai;
use App\Services\SumberPegawaiBerkas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Tujuh akun untuk demo aplikasi — satu tiap peran yang berperan dalam
 * alur perjalanan dinas — dengan kata sandi demo yang sama supaya
 * presenter dapat berpindah akun tanpa mencari-cari sandi.
 *
 * Datanya (nama, NIP, jabatan, golongan) diambil dari berkas pegawai yang
 * sama dengan PegawaiPoltekkesSeeder; yang diubah hanya peran pengembang
 * menjadi super administrator, kata sandi, rekening contoh bagi yang
 * belum punya, dan garis atasan ke Direktur.
 *
 * Sengaja menolak berjalan di lingkungan produksi: seeder ini mengganti
 * kata sandi akun pejabat sungguhan dengan sandi yang tercetak di layar.
 */
class PenggunaDemoSeeder extends Seeder
{
    public const SANDI_DEMO = 'Demo2026!';

    /**
     * NIP tiap akun demo beserta peran yang dipakai saat demo.
     *
     * @var array<string, PeranPengguna>
     */
    public const AKUN = [
        '199310182025061003' => PeranPengguna::SuperAdministrator, // Octavianus Elricth Waters Modami — pengembang
        '197104041994031002' => PeranPengguna::Pimpinan,           // Hanung Prasetya — Direktur
        '197906082002122001' => PeranPengguna::Pimpinan,           // Junita Elvira Ratela — Wakil Direktur II
        '197812202008012014' => PeranPengguna::DosenTendik,        // Debora Kalundang — Kasubbag Administrasi Umum, pelaksana
        '198908292022032002' => PeranPengguna::TimKeuangan,        // Christie Angelia Budiman — Tim Keuangan
        '198609262008122003' => PeranPengguna::Ppk,                // Stefanny Zulistya Wenno — PPK
        '198503072010122001' => PeranPengguna::TimKeuangan,        // Merlin Lapananda — Tim Keuangan
    ];

    private const NIP_DIREKTUR = '197104041994031002';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('PenggunaDemoSeeder tidak dijalankan di produksi: sandi akun sungguhan tidak boleh diganti sandi demo.');

            return;
        }

        $berkas = database_path('data/pegawai-poltekkes.csv');

        if (! is_readable($berkas)) {
            $this->command?->warn("Berkas pegawai tidak ditemukan: {$berkas}");

            return;
        }

        $baris = (new SumberPegawaiBerkas($berkas))->ambil()
            ->filter(fn (array $b) => isset(self::AKUN[$b['nip']]))
            ->map(fn (array $b) => [...$b, 'role' => self::AKUN[$b['nip']]->value, 'password' => self::SANDI_DEMO])
            ->values();

        $hilang = collect(self::AKUN)->keys()->diff($baris->pluck('nip'));

        if ($hilang->isNotEmpty()) {
            $this->command?->warn('NIP tidak ditemukan di berkas pegawai: '.$hilang->implode(', '));
        }

        // Mode timpa: peran dan jabatan mengikuti daftar di atas walau akunnya
        // sudah ada dengan peran lain.
        app(ImporPengguna::class)->jalankan(new class($baris) implements SumberDataPegawai
        {
            /** @param  Collection<int, array<string, string|null>>  $baris */
            public function __construct(private Collection $baris) {}

            public function ambil(): Collection
            {
                return $this->baris;
            }

            public function nama(): string
            {
                return 'Akun demo';
            }
        });

        $this->lengkapi();

        $this->command?->info('Akun demo siap ('.$baris->count().' akun). Kata sandi semuanya: '.self::SANDI_DEMO);
    }

    /**
     * Sandi demo, rekening contoh bagi yang belum punya (pembayaran menolak
     * pelaksana tanpa rekening), dan garis atasan ke Direktur.
     */
    private function lengkapi(): void
    {
        $direktur = User::firstWhere('nip', self::NIP_DIREKTUR);

        User::whereIn('nip', array_keys(self::AKUN))->get()->each(function (User $akun) use ($direktur): void {
            $akun->forceFill([
                'password' => self::SANDI_DEMO, // di-hash oleh cast 'hashed'
                'id_atasan' => $akun->nip === self::NIP_DIREKTUR ? null : $direktur?->id,
                'nama_bank' => $akun->nama_bank ?: 'Bank Mandiri',
                'nomor_rekening' => $akun->nomor_rekening ?: '1500-00-'.substr($akun->nip, -6),
                'nama_rekening' => $akun->nama_rekening ?: $akun->nama,
            ])->save();
        });
    }
}
