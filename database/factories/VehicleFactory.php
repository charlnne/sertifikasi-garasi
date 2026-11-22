<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => VehicleType::Car,
            'plate_number' => $this->makePlateNumber(),
            'brand_model' => fake()->randomElement(['Toyota Avanza', 'Honda Jazz', 'Daihatsu Xenia', 'Suzuki Ertiga']),
            'battery_capacity' => null,
        ];
    }

    public function bicycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::Bicycle,
            'plate_number' => null,
            'brand_model' => fake()->randomElement(['Polygon Siskiu', 'United Detroit', 'Brompton C Line']),
        ]);
    }

    public function motorcycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::Motorcycle,
            'brand_model' => fake()->randomElement(['Honda Vario 125', 'Yamaha NMAX', 'Honda Beat']),
        ]);
    }

    public function car(): static
    {
        return $this->state(fn () => ['type' => VehicleType::Car]);
    }

    public function electricCar(float $batteryCapacity = 60): static
    {
        return $this->state(fn () => [
            'type' => VehicleType::ElectricCar,
            'brand_model' => fake()->randomElement(['Hyundai Ioniq 5', 'Wuling Air ev', 'BYD Atto 3']),
            'battery_capacity' => $batteryCapacity,
        ]);
    }

    private function makePlateNumber(): string
    {
        return strtoupper(fake()->unique()->bothify('B #### ???'));
    }
}
