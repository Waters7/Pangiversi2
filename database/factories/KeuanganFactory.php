<?php

namespace Database\Factories;

use App\Models\Keuangan;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keuangan>
 */
class KeuanganFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'total' => 0,
            'uang_muka' => 0,
            'sisa' => 0,
            'id_usulan' => Usulan::factory(),
            ...$this->tahapAcak(),
        ];
    }

    /**
     * Belum ada pembayaran sama sekali.
     */
    public function belumBayar(): static
    {
        return $this->state(fn () => [
            'status' => Keuangan::STATUS_BELUM,
            'tanggal_transfer' => null,
            'tanggal_pelunasan' => null,
            'kode_konfirmasi_bayar' => null,
            'dikonfirmasi_bayar_at' => null,
        ]);
    }

    /**
     * Uang muka 80% sudah ditransfer, pelunasan belum.
     */
    public function uangMuka(): static
    {
        return $this->state(fn () => [
            'status' => Keuangan::STATUS_SEBAGIAN,
            'tanggal_transfer' => fake()->dateTimeBetween('-30 days', 'now'),
            'tanggal_pelunasan' => null,
            'kode_konfirmasi_bayar' => null,
            'dikonfirmasi_bayar_at' => null,
        ]);
    }

    /**
     * Sudah lunas beserta kode konfirmasi bendahara.
     */
    public function lunas(): static
    {
        return $this->state(fn () => [
            'status' => Keuangan::STATUS_LUNAS,
            'tanggal_transfer' => fake()->dateTimeBetween('-30 days', '-7 days'),
            'tanggal_pelunasan' => fake()->dateTimeBetween('-7 days', 'now'),
            'kode_konfirmasi_bayar' => Keuangan::buatKodeKonfirmasiBayar(),
            'dikonfirmasi_bayar_at' => now(),
        ]);
    }

    /**
     * Tahap pembayaran acak yang tanggal dan kodenya tetap selaras dengan
     * statusnya — menimpa salah satunya saja akan membuat barisnya janggal,
     * jadi pakai state belumBayar/uangMuka/lunas bila butuh tahap tertentu.
     *
     * @return array<string, mixed>
     */
    private function tahapAcak(): array
    {
        $status = fake()->randomElement([
            Keuangan::STATUS_BELUM,
            Keuangan::STATUS_SEBAGIAN,
            Keuangan::STATUS_LUNAS,
        ]);

        $lunas = $status === Keuangan::STATUS_LUNAS;
        $adaTransfer = $status !== Keuangan::STATUS_BELUM;

        return [
            'status' => $status,
            'tanggal_transfer' => $adaTransfer ? fake()->dateTimeBetween('-30 days', '-7 days') : null,
            'tanggal_pelunasan' => $lunas ? fake()->dateTimeBetween('-7 days', 'now') : null,
            'kode_konfirmasi_bayar' => $lunas ? Keuangan::buatKodeKonfirmasiBayar() : null,
            'dikonfirmasi_bayar_at' => $lunas ? now() : null,
        ];
    }
}
