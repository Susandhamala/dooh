<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'rentall_uuid'])]
#[Hidden(['id', 'password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected string $guard_name = 'api';

    /** Public identifier; the integer id never leaves the API. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Tokens carry the UUID, and the guard looks users up by it. */
    public function getAuthIdentifierName(): string
    {
        return 'uuid';
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getAuthIdentifier();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'password' => 'hashed',
        ];
    }
}
