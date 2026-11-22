<?php

namespace Tests\Feature\Models;

use App\Enums\GarageStatus;
use App\Enums\RentalStatus;
use App\Models\ElectricCharging;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DerivedValueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_garage_code_and_monthly_price_are_derived(): void
    {
        $garage = Garage::factory()->car()->create(['number' => 10, 'daily_price' => 30000]);

        $this->assertSame('C010', $garage->code);
        $this->assertSame(750000.0, $garage->monthly_price);
    }

    public static function garageStatusProvider(): array
    {
        return [
            'used: rental covers today' => ['2026-11-20', 3, 'verified', GarageStatus::Used],
            'used: pending rental still counts' => ['2026-11-21', 1, 'pending', GarageStatus::Used],
            'booked: rental starts tomorrow' => ['2026-11-22', 2, 'verified', GarageStatus::Booked],
            'available: rental ended yesterday' => ['2026-11-18', 3, 'verified', GarageStatus::Available],
            'available: rejected rental does not count' => ['2026-11-21', 3, 'rejected', GarageStatus::Available],
        ];
    }

    #[DataProvider('garageStatusProvider')]
    public function test_garage_status_by_rental_dates_returns_expected(string $startDate, int $days, string $paymentStatus, GarageStatus $expected): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->for($garage)->period($startDate, $days)->create(['payment_status' => $paymentStatus]);

        $this->assertSame($expected, $garage->statusOn());
        $this->assertSame($expected, Garage::withStatusOn()->find($garage->id)->status);
        $this->assertTrue(Garage::whereStatus($expected)->whereKey($garage->id)->exists());
    }

    public function test_garage_status_used_takes_priority_over_booked(): void
    {
        $garage = Garage::factory()->car()->create();
        Rental::factory()->for($garage)->period('2026-11-21', 2)->create();
        Rental::factory()->for($garage)->period('2026-11-25', 2)->create();

        $this->assertSame(GarageStatus::Used, $garage->statusOn());
    }

    public static function rentalStatusProvider(): array
    {
        return [
            'active: ends today' => ['2026-11-19', 3, 'verified', RentalStatus::Active],
            'active: not started yet' => ['2026-11-25', 3, 'pending', RentalStatus::Active],
            'finished: ended yesterday' => ['2026-11-18', 3, 'verified', RentalStatus::Finished],
            'cancelled: payment rejected' => ['2026-11-21', 3, 'rejected', RentalStatus::Cancelled],
        ];
    }

    #[DataProvider('rentalStatusProvider')]
    public function test_rental_status_by_payment_and_end_date_returns_expected(string $startDate, int $days, string $paymentStatus, RentalStatus $expected): void
    {
        $rental = Rental::factory()->period($startDate, $days)->create(['payment_status' => $paymentStatus]);

        $this->assertSame($expected, $rental->status);
    }

    public function test_rental_daily_costs_without_extension(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 10000]);
        $rental = Rental::factory()->for($garage)->period('2026-11-21', 3)->create();

        $this->assertSame(3, $rental->base_days);
        $this->assertSame(30000.0, $rental->rent_cost);
        $this->assertSame('0.00', $rental->extension_cost);
        $this->assertSame(30000.0, $rental->initial_total);
    }

    public function test_rental_daily_extended_keeps_rent_cost_and_adds_extension_cost(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 10000]);
        $rental = Rental::factory()->for($garage)->period('2026-11-21', 3)->extended(2, 20000)->create();

        $this->assertSame('2026-11-25', $rental->end_date->toDateString());
        $this->assertSame(3, $rental->base_days);
        $this->assertSame(5, $rental->total_days);
        $this->assertSame(30000.0, $rental->rent_cost);
        $this->assertSame('20000.00', $rental->extension_cost);
    }

    public function test_rental_monthly_rent_cost_is_25_times_daily_price(): void
    {
        $garage = Garage::factory()->car()->create(['daily_price' => 30000]);
        $rental = Rental::factory()->for($garage)->monthly('2026-11-21')->create();

        $this->assertSame('2026-12-20', $rental->end_date->toDateString());
        $this->assertSame(750000.0, $rental->rent_cost);
    }

    public function test_initial_total_is_rent_plus_initial_charging_without_extra_percentage(): void
    {
        $vehicle = Vehicle::factory()->electricCar(60)->create();
        $garage = Garage::factory()->electric()->create(['daily_price' => 50000, 'kwh_price' => 10000]);
        $rental = Rental::factory()->for($vehicle)->for($garage)->period('2026-11-21', 2)->create();
        ElectricCharging::factory()->for($rental)->create(['current_battery_percent' => 25]);
        ElectricCharging::factory()->for($rental)->additional()->create(['current_battery_percent' => 50]);

        $rental->refresh();

        $this->assertSame(45.0, $rental->initialCharging->charged_kwh);
        $this->assertSame(450000.0, $rental->initial_charging_cost);
        $this->assertSame(100000.0 + 450000.0, $rental->initial_total);
        $this->assertCount(2, $rental->chargings);
    }

    public function test_user_rentals_go_through_vehicles(): void
    {
        $renter = User::factory()->create();
        $vehicle = Vehicle::factory()->for($renter)->create();
        Rental::factory()->for($vehicle)->count(2)->create();
        Rental::factory()->create();

        $this->assertCount(2, $renter->rentals);
        $this->assertSame(2, Rental::ownedBy($renter)->count());
    }
}
