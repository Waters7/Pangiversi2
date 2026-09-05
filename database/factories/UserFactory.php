<?php

namespace Database\Factories;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'nip' => fake()->unique()->numerify('NIP-##########'),
            // Dipakai tautan wa.me saat tim keuangan menagih kelengkapan berkas.
            'no_hp' => fake()->numerify('08##########'),
            'role' => User::ROLE_DOSEN_TENDIK,
            'jabatan' => fake()->randomElement([
                'Dosen', 'Dosen Tetap', 'Instruktur', 'Analis Kepegawaian',
                'Pranata Laboratorium Pendidikan', 'Pengadministrasi Umum',
                'Bendahara Pengeluaran', 'Arsiparis',
            ]),
            'id_unit' => UnitKerja::inRandomOrder()->value('id'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set the user role to administrator.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    /**
     * Set the user role to PPK.
     */
    public function ppk(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_PPK,
        ]);
    }

    /**
     * Assign the user to a unit kerja.
     */
    public function diUnit(UnitKerja $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'id_unit' => $unit->id,
        ]);
    }

    /**
     * Assign the user's direct supervisor.
     */
    public function berAtasan(User $atasan): static
    {
        return $this->state(fn (array $attributes) => [
            'id_atasan' => $atasan->id,
        ]);
    }
}
