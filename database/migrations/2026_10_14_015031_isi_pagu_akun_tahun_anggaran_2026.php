<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pagu perjalanan dinas TA 2026 per kode akun, menurut Rincian Kertas Kerja
 * Satker TA 2026 Poltekkes Kemenkes Manado: Rp 2.375.475.000, terdiri atas
 * RM Rp 581.180.000 dan BLU Rp 1.794.295.000.
 *
 * Akun 525115 (Belanja Perjalanan, BLU) belum ada pada master akun, jadi
 * ditambahkan. Pagu hanya diisi bila tahun anggaran 2026 sudah ada — tahun
 * anggaran dibuat administrator lewat Master Data, bukan oleh migrasi.
 */
return new class extends Migration
{
    private const TAHUN = 2026;

    /** @var list<array{kode: string, nama: string, sumber: string, pagu: int}> */
    private const PAGU = [
        ['kode' => '524111', 'nama' => 'Belanja Perjalanan Dinas Biasa', 'sumber' => 'RM', 'pagu' => 437_132_000],
        ['kode' => '524119', 'nama' => 'Belanja Perjalanan Dinas Paket Meeting Luar Kota', 'sumber' => 'RM', 'pagu' => 144_048_000],
        ['kode' => '525115', 'nama' => 'Belanja Perjalanan', 'sumber' => 'BLU', 'pagu' => 1_794_295_000],
    ];

    public function up(): void
    {
        $akun = collect(self::PAGU)->mapWithKeys(fn (array $baris) => [$baris['kode'] => $this->akun($baris['kode'], $baris['nama'])]);

        $tahun = DB::table('tahun_anggaran')->where('tahun', self::TAHUN)->first();

        if (! $tahun) {
            return;
        }

        $sumber = DB::table('kategori_pembiayaan')->pluck('id', 'kode');

        foreach (self::PAGU as $baris) {
            DB::table('pagu_akun')->updateOrInsert(
                ['id_tahun_anggaran' => $tahun->id, 'id_akun_pembiayaan' => $akun[$baris['kode']]],
                [
                    'id_kategori_pembiayaan' => $sumber[$baris['sumber']] ?? null,
                    'pagu' => $baris['pagu'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        // Pagu tahun anggaran adalah jumlah pagu seluruh akunnya.
        DB::table('tahun_anggaran')->where('id', $tahun->id)->update([
            'pagu' => DB::table('pagu_akun')->where('id_tahun_anggaran', $tahun->id)->sum('pagu'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $tahun = DB::table('tahun_anggaran')->where('tahun', self::TAHUN)->value('id');

        if ($tahun) {
            DB::table('pagu_akun')->where('id_tahun_anggaran', $tahun)->delete();
        }
    }

    /** Id akun berkode itu, ditambahkan ke master akun bila belum ada. */
    private function akun(string $kode, string $nama): int
    {
        $ada = DB::table('akun_pembiayaan')->where('kode', $kode)->value('id');

        if ($ada) {
            return (int) $ada;
        }

        return (int) DB::table('akun_pembiayaan')->insertGetId([
            'kode' => $kode,
            'nama' => $nama,
            'urutan' => (int) DB::table('akun_pembiayaan')->max('urutan') + 1,
            'is_aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
