<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nama', 'email', 'nip', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_PEGAWAI = 'pegawai';

    public const ROLE_DIREKTUR = 'direktur';

    public const ROLE_OUTSOURCING = 'outsourcing';

    public const ROLE_PPK = 'ppk';

    public const ROLE_KEUANGAN = 'keuangan';

    public const ROLE_ADMINISTRATOR = 'administrator';

    /**
     * Role groups: roles that share the same access level.
     *
     * @var array<string, list<string>>
     */
    public const GROUP_PEGAWAI = [self::ROLE_PEGAWAI, self::ROLE_DIREKTUR, self::ROLE_OUTSOURCING];

    public const GROUP_PPK = [self::ROLE_PPK, self::ROLE_KEUANGAN];

    public const GROUP_ADMIN = [self::ROLE_ADMINISTRATOR];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function usulan()
    {
        return $this->hasMany(Usulan::class, 'id_user');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isPPK(): bool
    {
        return in_array($this->role, self::GROUP_PPK);
    }

    public function isPegawai(): bool
    {
        return in_array($this->role, self::GROUP_PEGAWAI);
    }

    /**
     * Resolve a role to all roles in its group.
     *
     * @return list<string>
     */
    public static function expandRole(string $role): array
    {
        return match ($role) {
            self::ROLE_PEGAWAI, self::ROLE_DIREKTUR, self::ROLE_OUTSOURCING => self::GROUP_PEGAWAI,
            self::ROLE_PPK, self::ROLE_KEUANGAN => self::GROUP_PPK,
            self::ROLE_ADMINISTRATOR => self::GROUP_ADMIN,
            default => [$role],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_PEGAWAI => 'Pegawai',
            self::ROLE_DIREKTUR => 'Direktur',
            self::ROLE_OUTSOURCING => 'Outsourcing',
            self::ROLE_PPK => 'PPK',
            self::ROLE_KEUANGAN => 'Keuangan',
            self::ROLE_ADMINISTRATOR => 'Administrator',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::roleOptions()[$this->role] ?? 'Unknown';
    }
}
