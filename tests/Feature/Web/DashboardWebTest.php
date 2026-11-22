<?php

namespace Tests\Feature\Web;

use App\Models\ElectricCharging;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_admin_summary_matches_seeder_and_ignores_pending_or_rejected(): void
    {
        $response = $this->actingAs($this->user('admin@example.com'))->get(route('dashboard'));

        $response->assertOk();
        // 1 pending applicant; pending payments = rental S002 + 1 additional charging;
        // verified rentals active today = C002, C003, E002, S003 (S002 is still pending and not counted).
        $response->assertViewHas('summary', [
            'pending_renters' => 1,
            'pending_payments' => 2,
            'active_rentals_today' => 4,
            'garages_per_type' => ['bicycle' => 10, 'car' => 10, 'electric' => 5],
        ]);
    }

    public function test_admin_cards_link_to_related_pages(): void
    {
        $response = $this->actingAs($this->user('admin@example.com'))->get(route('dashboard'));

        $response->assertSee(route('admin.renters.index'));
        $response->assertSee(route('admin.payments.index'));
        $response->assertSee(route('admin.extensions.index'));
        $response->assertSee(route('admin.garages.index', ['type' => 'electric']), false);
    }

    public function test_renter_sees_active_rentals_with_charging_button_only_when_eligible(): void
    {
        $budi = $this->user('budi@example.com');

        $response = $this->actingAs($budi)->get(route('dashboard'));

        $response->assertOk();
        $codes = $response->viewData('rentals')->map(fn (Rental $rental) => $rental->garage->code)->all();
        $this->assertSame(['E002', 'C002', 'S002'], $codes);

        // E002 is otherwise eligible, but the seeder leaves one of its additional chargings pending.
        $electric = $response->viewData('rentals')->first(fn (Rental $rental) => $rental->garage->code === 'E002');
        $car = $response->viewData('rentals')->first(fn (Rental $rental) => $rental->garage->code === 'C002');
        $this->assertTrue($electric->charging_blocked);
        $response->assertDontSee(route('chargings.create', $electric));
        $response->assertSee('Charging pending');
        $this->assertFalse($car->charging_blocked);
        $response->assertDontSee(route('chargings.create', $car));
        $response->assertSee(route('rentals.create'));
        $response->assertSee('Your account is approved');
    }

    public function test_renter_sees_charging_button_again_after_pending_charging_is_verified(): void
    {
        $budi = $this->user('budi@example.com');
        ElectricCharging::additional()->pending()->update(['payment_status' => 'verified']);

        $response = $this->actingAs($budi)->get(route('dashboard'));

        $electric = $response->viewData('rentals')->first(fn (Rental $rental) => $rental->garage->code === 'E002');
        $this->assertTrue($electric->can_charge);
        $response->assertSee(route('chargings.create', $electric));
    }

    public function test_renter_does_not_see_cancelled_or_other_renters_rentals(): void
    {
        $siti = $this->user('siti@example.com');

        $response = $this->actingAs($siti)->get(route('dashboard'));

        $codes = $response->viewData('rentals')->map(fn (Rental $rental) => $rental->garage->code)->all();
        // S001 was rejected (cancelled) and last month's rental is finished; the second C003 rental has not started but is still shown.
        $this->assertSame(['S003', 'C003', 'C003'], $codes);
    }

    public function test_pending_renter_only_sees_waiting_banner(): void
    {
        $response = $this->actingAs($this->user('pending@example.com'))->get(route('dashboard'));

        $response->assertSee('Your account is awaiting admin verification.');
        $response->assertDontSee('Active Rentals');
        $response->assertDontSee(route('rentals.create'));
    }
}
