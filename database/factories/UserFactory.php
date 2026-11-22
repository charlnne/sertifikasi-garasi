<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('################'),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Renter,
            'status' => UserStatus::Approved,
            'ktp_path' => 'ktp/example.jpg',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
            'ktp_path' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Pending]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Rejected]);
    }
}
