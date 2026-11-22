<?php

namespace Database\Factories;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Services\GarageRuleService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => GarageType::Car,
            'number' => fake()->unique()->numberBetween(1, 999),
            'daily_price' => 30000,
            'kwh_price' => null,
        ];
    }

    public function bicycle(): static
    {
        return $this->state(fn () => [
            'type' => GarageType::Bicycle,
            'daily_price' => 5000,
            'kwh_price' => null,
        ]);
    }

    public function car(): static
    {
        return $this->state(fn () => [
            'type' => GarageType::Car,
            'daily_price' => 30000,
            'kwh_price' => null,
        ]);
    }

    public function electric(): static
    {
        return $this->state(fn () => [
            'type' => GarageType::Electric,
            'daily_price' => 50000,
            'kwh_price' => GarageRuleService::DEFAULT_KWH_PRICE,
        ]);
    }
}
