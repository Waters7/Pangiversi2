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

    public const ROLE_PPK = 'ppk';

    public const ROLE_ADMINISTRATOR = 'administrator';

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
        return $this->role === self::ROLE_PPK;
    }

    public function isPegawai(): bool
    {
        return $this->role === self::ROLE_PEGAWAI;
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_PEGAWAI => 'Pegawai',
            self::ROLE_PPK => 'PPK',
            self::ROLE_ADMINISTRATOR => 'Administrator',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::roleOptions()[$this->role] ?? 'Unknown';
    }
}
