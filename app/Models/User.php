<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'nik',
        'name',
        'phone',
        'email',
        'password',
        'role',
        'status',
        'ktp_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'renter',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function rentals(): HasManyThrough
    {
        return $this->hasManyThrough(Rental::class, Vehicle::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isRenter(): bool
    {
        return $this->role === UserRole::Renter;
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === UserStatus::Rejected;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }
}
