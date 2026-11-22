<?php

namespace Tests\Feature\Web;

use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalWebTest extends TestCase
{
    use RefreshDatabase;

    private User $renter;

    private Vehicle $car;

    private Garage $carGarage;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('public');

        $this->renter = User::factory()->create();
        $this->car = Vehicle::factory()->car()->for($this->renter)->create();
        $this->carGarage = Garage::factory()->car()->create(['number' => 1, 'daily_price' => 10000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $this->car->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-21',
            'days' => 3,
            'garage_id' => $this->carGarage->id,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    public function test_store_daily_rental_saves_pending_rental_with_end_date(): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload());

        $rental = Rental::firstOrFail();
        $response->assertRedirect(route('rentals.show', $rental));
        $this->assertSame('2026-11-23', $rental->end_date->toDateString());
        $this->assertSame(RentalType::Daily, $rental->rental_type);
        $this->assertSame(PaymentStatus::Pending, $rental->payment_status);
        $this->assertSame(0, $rental->extension_days);
        $this->assertSame(30000.0, $rental->initial_total);
        $this->assertCount(0, $rental->chargings);
        Storage::disk('public')->assertExists($rental->payment_proof);
    }

    public function test_store_monthly_rental_ignores_days_and_uses_days_in_month(): void
    {
        $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'rental_type' => 'monthly',
            'days' => null,
        ]))->assertSessionHasNoErrors();

        $rental = Rental::firstOrFail();
        $this->assertSame('2026-12-20', $rental->end_date->toDateString());
        $this->assertSame(250000.0, $rental->rent_cost);
    }

    public function test_store_electric_car_in_electric_garage_creates_initial_charging(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->renter)->create();
        $electricGarage = Garage::factory()->electric()->create(['number' => 1, 'daily_price' => 50000, 'kwh_price' => 10000]);

        $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'vehicle_id' => $electricCar->id,
            'garage_id' => $electricGarage->id,
            'days' => 2,
            'current_battery_percent' => 25,
        ]))->assertSessionHasNoErrors();

        $rental = Rental::firstOrFail();
        $charging = $rental->initialCharging;
        $this->assertNotNull($charging);
        $this->assertNull($charging->payment_proof);
        $this->assertSame(PaymentStatus::Pending, $charging->payment_status);
        $this->assertSame(450000.0, $charging->cost);
        $this->assertSame(100000.0 + 450000.0, $rental->initial_total);
    }

    public function test_store_electric_car_in_car_garage_has_no_charging(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->renter)->create();

        $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'vehicle_id' => $electricCar->id,
            'current_battery_percent' => 25,
        ]))->assertSessionHasNoErrors();

        $this->assertCount(0, Rental::firstOrFail()->chargings);
    }

    public function test_store_electric_garage_without_battery_percent_is_rejected(): void
    {
        $electricCar = Vehicle::factory()->electricCar(60)->for($this->renter)->create();
        $electricGarage = Garage::factory()->electric()->create(['number' => 1]);

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'vehicle_id' => $electricCar->id,
            'garage_id' => $electricGarage->id,
        ]));

        $response->assertSessionHas('error', 'Current battery percentage is required for an electric car in an electric garage.');
        $this->assertSame(0, Rental::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public static function invalidBatteryPercentProvider(): array
    {
        return [
            'boundary: -1%' => [-1],
            'boundary: 101%' => [101],
        ];
    }

    #[DataProvider('invalidBatteryPercentProvider')]
    public function test_store_battery_percent_out_of_range_returns_validation_error(int $percent): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['current_battery_percent' => $percent]));

        $response->assertSessionHasErrors(['current_battery_percent' => 'Battery percentage must be between 0 and 100.']);
    }

    public static function incompatibleProvider(): array
    {
        return [
            'car in bicycle garage' => ['car', 'bicycle'],
            'car in electric garage' => ['car', 'electric'],
            'motorcycle in car garage' => ['motorcycle', 'car'],
            'bicycle in electric garage' => ['bicycle', 'electric'],
        ];
    }

    #[DataProvider('incompatibleProvider')]
    public function test_store_incompatible_garage_is_rejected(string $vehicleType, string $garageType): void
    {
        $vehicle = Vehicle::factory()->{$vehicleType}()->for($this->renter)->create();
        $garage = Garage::factory()->{$garageType}()->create(['number' => 5]);

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'current_battery_percent' => 50,
        ]));

        $response->assertSessionHas('error');
        $this->assertSame(0, Rental::count());
    }

    public static function overlapProvider(): array
    {
        return [
            'negative: full overlap' => ['2026-11-21', 3, false],
            'negative: partial overlap' => ['2026-11-23', 3, false],
            'positive: adjacent' => ['2026-11-24', 3, true],
        ];
    }

    #[DataProvider('overlapProvider')]
    public function test_store_against_existing_rental_in_same_garage(string $startDate, int $days, bool $expectedCreated): void
    {
        Rental::factory()->for($this->carGarage)->period('2026-11-21', 3)->verified()->create();

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload([
            'start_date' => $startDate,
            'days' => $days,
        ]));

        $expectedCreated
            ? $response->assertSessionHasNoErrors()->assertSessionMissing('error')
            : $response->assertSessionHas('error', 'Garage C001 is already taken for that date range.');
        $this->assertSame($expectedCreated ? 2 : 1, Rental::count());
    }

    public function test_store_rejected_rental_does_not_block_garage(): void
    {
        Rental::factory()->for($this->carGarage)->period('2026-11-21', 3)->rejected()->create();

        $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload())->assertSessionMissing('error');

        $this->assertSame(2, Rental::count());
    }

    public function test_store_same_vehicle_double_booking_is_rejected(): void
    {
        $otherGarage = Garage::factory()->car()->create(['number' => 2]);
        Rental::factory()->for($this->car)->for($otherGarage)->period('2026-11-22', 3)->create();

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload());

        $response->assertSessionHas('error');
        $this->assertSame(1, Rental::count());
    }

    public static function startDateProvider(): array
    {
        return [
            'boundary: yesterday' => ['2026-11-20', false],
            'boundary: today' => ['2026-11-21', true],
            'positive: far in the future' => ['2027-08-01', true],
        ];
    }

    #[DataProvider('startDateProvider')]
    public function test_store_start_date_rules(string $startDate, bool $valid): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['start_date' => $startDate]));

        $valid
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors(['start_date' => 'Start date cannot be before today.']);
    }

    public static function validDaysProvider(): array
    {
        return [
            'boundary: 1 day' => [1, '2026-11-21'],
            'boundary: November max 29 days' => [29, '2026-12-19'],
        ];
    }

    #[DataProvider('validDaysProvider')]
    public function test_store_daily_days_within_limit_is_saved(int $days, string $expectedEndDate): void
    {
        $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['days' => $days]))->assertSessionMissing('error');

        $this->assertSame($expectedEndDate, Rental::firstOrFail()->end_date->toDateString());
    }

    public function test_store_daily_zero_days_returns_validation_error(): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['days' => 0]));

        $response->assertSessionHasErrors('days');
        $this->assertSame(0, Rental::count());
    }

    public function test_store_daily_max_plus_one_days_is_rejected(): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['days' => 30]));

        $response->assertSessionHas('error', 'Daily rental days must be between 1 and 29 days.');
        $this->assertSame(0, Rental::count());
    }

    public function test_store_other_renters_vehicle_returns_validation_error(): void
    {
        $otherVehicle = Vehicle::factory()->car()->create();

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['vehicle_id' => $otherVehicle->id]));

        $response->assertSessionHasErrors('vehicle_id');
    }

    public function test_store_without_payment_proof_returns_validation_error(): void
    {
        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['payment_proof' => null]));

        $response->assertSessionHasErrors(['payment_proof' => 'Payment proof is required.']);
    }

    public function test_store_payment_proof_larger_than2_mb_returns_validation_error(): void
    {
        $file = UploadedFile::fake()->image('proof.jpg')->size(2049);

        $response = $this->actingAs($this->renter)->post(route('rentals.store'), $this->payload(['payment_proof' => $file]));

        $response->assertSessionHasErrors('payment_proof');
    }

    public function test_create_search_lists_only_available_compatible_garages(): void
    {
        $free = Garage::factory()->car()->create(['number' => 2]);
        Garage::factory()->bicycle()->create(['number' => 1]);
        Rental::factory()->for($this->carGarage)->period('2026-11-22', 2)->create();

        $response = $this->actingAs($this->renter)->get(route('rentals.create', [
            'vehicle_id' => $this->car->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-21',
            'days' => 3,
            'garage_type' => 'car',
        ]));

        $response->assertOk();
        $search = $response->viewData('search');
        $this->assertSame('2026-11-23', $search['end_date']->toDateString());
        $this->assertSame([$free->id], $search['garages']->pluck('id')->all());
    }

    public function test_create_search_incompatible_garage_type_shows_error(): void
    {
        $response = $this->actingAs($this->renter)->get(route('rentals.create', [
            'vehicle_id' => $this->car->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-21',
            'days' => 3,
            'garage_type' => 'bicycle',
        ]));

        $response->assertOk();
        $response->assertViewHas('searchError', 'Car cannot park in a Bicycle garage.');
    }

    public function test_create_search_monthly_shows_end_date_in_next_month(): void
    {
        $response = $this->actingAs($this->renter)->get(route('rentals.create', [
            'vehicle_id' => $this->car->id,
            'rental_type' => 'monthly',
            'start_date' => '2026-12-15',
            'garage_type' => 'car',
        ]));

        $this->assertSame('2027-01-14', $response->viewData('search')['end_date']->toDateString());
    }

    public function test_create_admin_returns403(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('rentals.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('rentals.store'), $this->payload())->assertForbidden();
    }

    public function test_create_pending_renter_returns403(): void
    {
        $pending = User::factory()->pending()->create();

        $this->actingAs($pending)->get(route('rentals.create'))->assertForbidden();
    }

    public function test_index_shows_only_own_rentals(): void
    {
        $own = Rental::factory()->for($this->car)->create();
        Rental::factory()->create();

        $response = $this->actingAs($this->renter)->get(route('rentals.index'));

        $response->assertOk();
        $this->assertSame([$own->id], $response->viewData('rentals')->pluck('id')->all());
    }

    public function test_show_own_rental_displays_cost_breakdown(): void
    {
        $rental = Rental::factory()->for($this->car)->for($this->carGarage)->period('2026-11-21', 3)->extended(2, 20000)->verified()->create();

        $response = $this->actingAs($this->renter)->get(route('rentals.show', $rental));

        $response->assertOk();
        $response->assertSeeInOrder(['Rent cost', 'Rp 30.000', 'Initial payment total', 'Rp 30.000', 'Extension', 'Rp 20.000']);
    }

    public function test_show_other_renters_rental_returns403(): void
    {
        $rental = Rental::factory()->create();

        $this->actingAs($this->renter)->get(route('rentals.show', $rental))->assertForbidden();
    }
}
