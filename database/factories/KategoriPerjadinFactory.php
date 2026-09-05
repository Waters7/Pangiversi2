<?php

namespace Database\Factories;

use App\Models\KategoriPerjadin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KategoriPerjadin>
 */
class KategoriPerjadinFactory extends Factory
{
    /**
     * Contoh kategori yang benar-benar dipakai, supaya data uji tetap masuk akal.
     *
     * @var list<array{string, string, string}>
     */
    private const CONTOH = [
        ['Perjadin Luar Kota', 'Luar Kota FullBoard', 'LK-FB'],
        ['Perjadin Luar Kota', 'Luar Kota NonFullBoard', 'LK-NFB'],
        ['Perjadin Dalam Kota', 'Dalam Kota FullDay', 'DK-FD'],
        ['Perjadin Dalam Kota', 'Transport Lokal', 'TL'],
        ['Narasumber', 'Narasumber (Daring)', 'NS-DAR'],
        ['Diklat', 'Diklat Dalam Kota', 'DL-DK'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$grup, $nama, $kode] = fake()->randomElement(self::CONTOH);

        return [
            'grup' => $grup,
            'nama' => $nama,
            // Kode wajib unik, jadi diberi akhiran saat dipakai berulang.
            'kode' => $kode.'-'.fake()->unique()->numerify('###'),
            'urutan' => fake()->numberBetween(1, 50),
            'is_aktif' => true,
        ];
    }

    public function grup(string $grup, string $nama): static
    {
        return $this->state(fn () => ['grup' => $grup, 'nama' => $nama]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_aktif' => false]);
    }
}
