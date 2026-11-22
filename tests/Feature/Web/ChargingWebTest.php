<?php

namespace Tests\Feature\Web;

use App\Enums\PaymentStatus;
use App\Models\ElectricCharging;
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

class ChargingWebTest extends TestCase
{
    use RefreshDatabase;

    private User $renter;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('public');

        $this->renter = User::factory()->create();
        $vehicle = Vehicle::factory()->electricCar(60)->for($this->renter)->create();
        $garage = Garage::factory()->electric()->create(['number' => 1, 'kwh_price' => 10000]);
        $this->rental = Rental::factory()->for($vehicle)->for($garage)->period('2026-11-20', 5)->verified()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'current_battery_percent' => 40,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    public function test_store_eligible_rental_creates_pending_additional_charging(): void
    {
        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload());

        $response->assertRedirect(route('rentals.show', $this->rental));
        $charging = ElectricCharging::firstOrFail();
        $this->assertFalse($charging->isInitial());
        $this->assertSame(PaymentStatus::Pending, $charging->payment_status);
        $this->assertSame(36.0, $charging->charged_kwh);
        $this->assertSame(360000.0, $charging->cost);
        Storage::disk('public')->assertExists($charging->payment_proof);
    }

    public static function batteryPercentProvider(): array
    {
        return [
            'boundary: 0%' => [0, true],
            'boundary: 99%' => [99, true],
            'boundary: 100% already full' => [100, false],
            'boundary: -1%' => [-1, false],
        ];
    }

    #[DataProvider('batteryPercentProvider')]
    public function test_store_battery_percent_limits(int $percent, bool $valid): void
    {
        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload([
            'current_battery_percent' => $percent,
        ]));

        $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('current_battery_percent');
        $this->assertSame($valid ? 1 : 0, ElectricCharging::count());
    }

    public function test_store_full_battery_shows_full_message(): void
    {
        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload([
            'current_battery_percent' => 100,
        ]));

        $response->assertSessionHasErrors(['current_battery_percent' => 'A 100% battery is already full, battery percentage may not exceed 99.']);
    }

    public function test_store_electric_car_in_car_garage_is_rejected(): void
    {
        $rental = Rental::factory()->for($this->rental->vehicle)->for(Garage::factory()->car()->create(['number' => 1]))
            ->period('2026-12-01', 3)->verified()->create();
        Carbon::setTestNow('2026-12-02 10:00:00');

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $rental), $this->payload());

        $response->assertSessionHas('error', 'Charging is only available in electric garages.');
        $this->assertSame(0, ElectricCharging::count());
    }

    public function test_store_non_electric_vehicle_is_rejected(): void
    {
        $car = Vehicle::factory()->car()->for($this->renter)->create();
        $rental = Rental::factory()->for($car)->for(Garage::factory()->car()->create(['number' => 2]))
            ->period('2026-11-21', 3)->verified()->create();

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $rental), $this->payload());

        $response->assertSessionHas('error', 'Charging is only available for electric cars.');
    }

    public function test_store_other_renters_rental_returns403(): void
    {
        $otherRenter = User::factory()->create();

        $response = $this->actingAs($otherRenter)->post(route('chargings.store', $this->rental), $this->payload());

        $response->assertForbidden();
        $this->assertSame(0, ElectricCharging::count());
    }

    public static function inactiveRentalProvider(): array
    {
        return [
            'not started yet' => ['2026-11-25', 3, 'verified', 'Charging is only available while the rental is running today.'],
            'already finished' => ['2026-11-10', 3, 'verified', 'Charging is only available while the rental is running today.'],
            'payment pending' => ['2026-11-21', 3, 'pending', 'Charging is only available after the rental payment is verified.'],
            'payment rejected' => ['2026-11-21', 3, 'rejected', 'Charging is only available after the rental payment is verified.'],
        ];
    }

    #[DataProvider('inactiveRentalProvider')]
    public function test_store_rental_not_active_today_is_rejected(string $startDate, int $days, string $paymentStatus, string $expectedMessage): void
    {
        $garage = Garage::factory()->electric()->create(['number' => 9]);
        $rental = Rental::factory()->for($this->rental->vehicle)->for($garage)->period($startDate, $days)
            ->create(['payment_status' => $paymentStatus]);

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $rental), $this->payload());

        $response->assertSessionHas('error', $expectedMessage);
    }

    public function test_create_eligible_rental_shows_form(): void
    {
        $response = $this->actingAs($this->renter)->get(route('chargings.create', $this->rental));

        $response->assertOk();
        $response->assertSee('60 kWh');
    }

    public function test_create_ineligible_rental_redirects_with_reason(): void
    {
        $this->rental->update(['payment_status' => 'pending']);

        $response = $this->actingAs($this->renter)->get(route('chargings.create', $this->rental));

        $response->assertRedirect(route('rentals.show', $this->rental));
        $response->assertSessionHas('error', 'Charging is only available after the rental payment is verified.');
    }

    public function test_rental_detail_shows_charging_button_only_when_eligible(): void
    {
        $this->actingAs($this->renter)->get(route('rentals.show', $this->rental))
            ->assertSee(route('chargings.create', $this->rental));

        $this->rental->update(['payment_status' => 'pending']);

        $this->actingAs($this->renter)->get(route('rentals.show', $this->rental))
            ->assertDontSee(route('chargings.create', $this->rental));
    }

    public function test_admin_cannot_create_charging(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('chargings.store', $this->rental), $this->payload())->assertForbidden();
    }

    public function test_store_with_pending_additional_charging_is_rejected(): void
    {
        ElectricCharging::factory()->for($this->rental)->additional()->create();

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload());

        $response->assertSessionHas('error', 'There is still a pending charging for this rental. Please wait for admin verification.');
        $this->assertSame(1, ElectricCharging::count());
    }

    public function test_create_with_pending_additional_charging_redirects_with_reason(): void
    {
        ElectricCharging::factory()->for($this->rental)->additional()->create();

        $response = $this->actingAs($this->renter)->get(route('chargings.create', $this->rental));

        $response->assertRedirect(route('rentals.show', $this->rental));
        $response->assertSessionHas('error', 'There is still a pending charging for this rental. Please wait for admin verification.');
    }

    public static function decidedPreviousChargingProvider(): array
    {
        return [
            'previous charging verified' => ['verified'],
            'previous charging rejected' => ['rejected'],
        ];
    }

    #[DataProvider('decidedPreviousChargingProvider')]
    public function test_store_after_previous_charging_decided_is_accepted(string $previousStatus): void
    {
        ElectricCharging::factory()->for($this->rental)->additional()->create(['payment_status' => $previousStatus]);

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload());

        $response->assertSessionMissing('error');
        $this->assertSame(2, ElectricCharging::count());
    }

    public function test_store_pending_initial_charging_does_not_block_additional_charging(): void
    {
        ElectricCharging::factory()->for($this->rental)->create(['payment_status' => 'pending']);

        $response = $this->actingAs($this->renter)->post(route('chargings.store', $this->rental), $this->payload());

        $response->assertSessionMissing('error');
        $this->assertSame(1, ElectricCharging::additional()->count());
    }

    public function test_rental_detail_with_pending_charging_shows_disabled_button_and_reason(): void
    {
        ElectricCharging::factory()->for($this->rental)->additional()->create();

        $response = $this->actingAs($this->renter)->get(route('rentals.show', $this->rental));

        $response->assertOk();
        $response->assertDontSee(route('chargings.create', $this->rental));
        $response->assertSee('There is still a pending charging for this rental. Please wait for admin verification.');
    }
}
