<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Default: 3-day daily rental starting today in a car garage priced at 30,000/day.
 *
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory()->car(),
            'garage_id' => Garage::factory()->car(),
            'rental_type' => RentalType::Daily,
            'start_date' => today(),
            'end_date' => today()->addDays(2),
            'extension_days' => 0,
            'extension_cost' => 0,
            'payment_proof' => 'payments/example.jpg',
            'payment_status' => PaymentStatus::Pending,
            'extension_payment_proof' => null,
        ];
    }

    /**
     * Daily rental for a number of days starting on a given date.
     */
    public function period(string $startDate, int $days): static
    {
        return $this->state(function () use ($startDate, $days) {
            $start = Carbon::parse($startDate)->startOfDay();

            return [
                'rental_type' => RentalType::Daily,
                'start_date' => $start,
                'end_date' => $start->copy()->addDays($days - 1),
            ];
        });
    }

    /**
     * Monthly rental: ends at start_date + days in the start_date month - 1.
     */
    public function monthly(?string $startDate = null): static
    {
        return $this->state(function (array $attributes) use ($startDate) {
            $start = Carbon::parse($startDate ?? $attributes['start_date'])->startOfDay();

            return [
                'rental_type' => RentalType::Monthly,
                'start_date' => $start,
                'end_date' => $start->copy()->addDays($start->daysInMonth - 1),
            ];
        });
    }

    public function verified(): static
    {
        return $this->state(fn () => ['payment_status' => PaymentStatus::Verified]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['payment_status' => PaymentStatus::Rejected]);
    }

    /**
     * Add an extension on top of the defined period, along with its cost.
     */
    public function extended(int $days, float $cost): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => Carbon::parse($attributes['end_date'])->addDays($days),
            'extension_days' => $days,
            'extension_cost' => $cost,
            'extension_payment_proof' => 'extensions/example.jpg',
        ]);
    }
}
