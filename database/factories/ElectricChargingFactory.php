<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\ElectricCharging;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: initial charging (without its own payment proof) for an electric car in an electric garage.
 *
 * @extends Factory<ElectricCharging>
 */
class ElectricChargingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory()->state([
                'vehicle_id' => Vehicle::factory()->electricCar(),
                'garage_id' => Garage::factory()->electric(),
            ]),
            'current_battery_percent' => 25,
            'payment_proof' => null,
            'payment_status' => PaymentStatus::Pending,
        ];
    }

    public function additional(): static
    {
        return $this->state(fn () => ['payment_proof' => 'chargings/example.jpg']);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['payment_status' => PaymentStatus::Verified]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['payment_status' => PaymentStatus::Rejected]);
    }
}
