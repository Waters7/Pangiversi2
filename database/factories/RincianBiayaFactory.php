<?php

namespace Database\Factories;

use App\Enums\KategoriBiaya;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RincianBiaya>
 */
class RincianBiayaFactory extends Factory
{
    /**
     * Komponen yang lazim dipakai tiap kategori, lengkap dengan satuan dan
     * kisaran harganya. Kategori wajib terisi karena dokumen resmi — rincian
     * PMK 113 dan daftar nominatif — memisahkan biaya berdasarkan kolom ini.
     *
     * @var array<string, array{komponen: list<string>, satuan: string, harga: list<int>, volume: array{int, int}}>
     */
    private const POLA = [
        KategoriBiaya::Transport->value => [
            'komponen' => ['Tiket pesawat PP', 'Tiket kapal PP', 'Tiket bus PP'],
            'satuan' => 'Tiket',
            'harga' => [1_850_000, 2_970_000, 3_992_700, 4_749_300],
            'volume' => [1, 1],
        ],
        KategoriBiaya::UangHarian->value => [
            'komponen' => ['Uang harian'],
            'satuan' => 'OH',
            'harga' => [370_000, 430_000, 530_000],
            'volume' => [2, 5],
        ],
        KategoriBiaya::TransportLokal->value => [
            'komponen' => ['Transport lokal', 'Taksi bandara PP', 'Sewa kendaraan'],
            'satuan' => 'Kali',
            'harga' => [168_000, 250_000, 422_500, 720_000],
            'volume' => [1, 2],
        ],
        KategoriBiaya::Penginapan->value => [
            'komponen' => ['Uang penginapan', 'Bill hotel'],
            'satuan' => 'OH',
            'harga' => [318_600, 422_000, 510_000, 730_000],
            'volume' => [1, 4],
        ],
        KategoriBiaya::Lainnya->value => [
            'komponen' => ['Biaya pendaftaran seminar', 'Uang representasi', 'Airport tax'],
            'satuan' => 'Paket',
            'harga' => [150_000, 300_000, 750_000],
            'volume' => [1, 1],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->untukKategori(fake()->randomElement(array_keys(self::POLA)))
            + ['id_keuangan' => Keuangan::factory()];
    }

    public function kategori(KategoriBiaya $kategori): static
    {
        return $this->state(fn () => $this->untukKategori($kategori->value));
    }

    /**
     * @return array<string, mixed>
     */
    private function untukKategori(string $kategori): array
    {
        $pola = self::POLA[$kategori];

        $volume = fake()->numberBetween(...$pola['volume']);
        $harga = fake()->randomElement($pola['harga']);

        return [
            'kategori' => $kategori,
            'komponen' => fake()->randomElement($pola['komponen']),
            'volume' => $volume,
            'satuan' => $pola['satuan'],
            'harga_satuan' => $harga,
            'jumlah' => $volume * $harga,
        ];
    }
}
