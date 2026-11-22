<?php

namespace Tests\Feature\Web;

use App\Enums\GarageStatus;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\ElectricCharging;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentVerificationWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->admin = User::factory()->admin()->create();

        $vehicle = Vehicle::factory()->electricCar(60)->create();
        $garage = Garage::factory()->electric()->create(['number' => 1, 'daily_price' => 50000, 'kwh_price' => 10000]);
        $this->rental = Rental::factory()->for($vehicle)->for($garage)->period('2026-11-21', 2)->create();
        ElectricCharging::factory()->for($this->rental)->create(['current_battery_percent' => 25]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_shows_pending_rental_with_cost_and_renter_data(): void
    {
        Rental::factory()->verified()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));

        $response->assertOk();
        $this->assertSame([$this->rental->id], $response->viewData('rentals')->pluck('id')->all());
        $response->assertSee($this->rental->vehicle->user->name);
        $response->assertSeeInOrder(['Rent cost', 'Rp 100.000', 'Initial charging', 'Rp 450.000', 'Total', 'Rp 550.000']);
        $response->assertSee($this->rental->payment_proof);
    }

    public function test_index_shows_only_pending_additional_chargings(): void
    {
        $pending = ElectricCharging::factory()->for($this->rental)->additional()->create(['current_battery_percent' => 50]);
        ElectricCharging::factory()->for($this->rental)->additional()->verified()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.payments.index'));

        $this->assertSame([$pending->id], $response->viewData('chargings')->pluck('id')->all());
        $response->assertSee('Rp 300.000');
    }

    public function test_verify_rental_sets_rental_and_initial_charging_verified(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('admin.payments.rentals.verify', $this->rental));

        $response->assertSessionHas('success');
        $this->assertSame(PaymentStatus::Verified, $this->rental->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Verified, $this->rental->initialCharging()->first()->payment_status);
    }

    public function test_reject_rental_cancels_rental_frees_garage_and_rejects_initial_charging(): void
    {
        $this->assertSame(GarageStatus::Used, $this->rental->garage->statusOn());

        $this->actingAs($this->admin)->patch(route('admin.payments.rentals.reject', $this->rental))->assertSessionHas('success');

        $rental = $this->rental->fresh();
        $this->assertSame(PaymentStatus::Rejected, $rental->payment_status);
        $this->assertSame(RentalStatus::Cancelled, $rental->status);
        $this->assertSame(PaymentStatus::Rejected, $rental->initialCharging()->first()->payment_status);
        $this->assertSame(GarageStatus::Available, $rental->garage->statusOn());
        $this->assertTrue(app(GarageAvailabilityService::class)->isGarageAvailable($rental->garage, $rental->start_date, $rental->end_date));
    }

    public static function decidedStatusProvider(): array
    {
        return [
            'already verified' => ['verified'],
            'already rejected' => ['rejected'],
        ];
    }

    #[DataProvider('decidedStatusProvider')]
    public function test_decided_rental_payment_cannot_be_changed(string $status): void
    {
        $this->rental->update(['payment_status' => $status]);

        $verify = $this->actingAs($this->admin)->patch(route('admin.payments.rentals.verify', $this->rental));
        $reject = $this->actingAs($this->admin)->patch(route('admin.payments.rentals.reject', $this->rental));

        $verify->assertSessionHas('error', 'This rental payment has already been verified.');
        $reject->assertSessionHas('error', 'This rental payment has already been verified.');
        $this->assertSame($status, $this->rental->fresh()->payment_status->value);
    }

    public function test_verify_additional_charging_sets_verified(): void
    {
        $charging = ElectricCharging::factory()->for($this->rental)->additional()->create();

        $this->actingAs($this->admin)->patch(route('admin.payments.chargings.verify', $charging))->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Verified, $charging->fresh()->payment_status);
    }

    public function test_reject_additional_charging_keeps_rental_unchanged(): void
    {
        $this->rental->update(['payment_status' => 'verified']);
        $charging = ElectricCharging::factory()->for($this->rental)->additional()->create();

        $this->actingAs($this->admin)->patch(route('admin.payments.chargings.reject', $charging))->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Rejected, $charging->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Verified, $this->rental->fresh()->payment_status);
    }

    public function test_decided_charging_payment_cannot_be_changed(): void
    {
        $charging = ElectricCharging::factory()->for($this->rental)->additional()->verified()->create();

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.chargings.reject', $charging));

        $response->assertSessionHas('error', 'This charging payment has already been verified.');
        $this->assertSame(PaymentStatus::Verified, $charging->fresh()->payment_status);
    }

    public function test_initial_charging_cannot_be_verified_separately(): void
    {
        $initial = $this->rental->initialCharging()->first();

        $response = $this->actingAs($this->admin)->patch(route('admin.payments.chargings.verify', $initial));

        $response->assertSessionHas('error', 'The initial charging is verified together with the rental payment.');
        $this->assertSame(PaymentStatus::Pending, $initial->fresh()->payment_status);
    }

    public function test_renter_cannot_access_payment_verification(): void
    {
        $renter = User::factory()->create();

        $this->actingAs($renter)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($renter)->patch(route('admin.payments.rentals.verify', $this->rental))->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $this->rental->fresh()->payment_status);
    }
}
