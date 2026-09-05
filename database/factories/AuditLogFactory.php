<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_usulan' => null,
            'id_user' => User::factory(),
            'aksi' => AuditLog::AKSI_DIPERBARUI,
            'deskripsi' => fake()->sentence(),
            'status_lama' => null,
            'status_baru' => null,
            'catatan' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
