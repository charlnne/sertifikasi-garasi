<?php

namespace Tests\Feature\Web;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtensionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Garage $garage;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
        $this->garage = Garage::factory()->car()->create(['number' => 1, 'daily_price' => 10000]);
        // Rental from 21 to 30 November 2026, extension reference month December.
        $this->rental = Rental::factory()->for($this->garage)->period('2026-11-21', 10)->verified()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'extension_type' => 'daily',
            'days' => 5,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    public function test_index_lists_only_verified_rentals_active_today(): void
    {
        Rental::factory()->period('2026-11-21', 3)->create();
        Rental::factory()->period('2026-11-25', 3)->verified()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.extensions.index'));

        $response->assertOk();
        $this->assertSame([$this->rental->id], $response->viewData('rentals')->pluck('id')->all());
        $response->assertSee('10 days');
        $response->assertSee(route('admin.extensions.create', $this->rental));
    }

    public function test_create_shows_reference_month_and_limits(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.extensions.create', $this->rental));

        $response->assertOk();
        $response->assertViewHas('maxDailyDays', 30);
        $response->assertViewHas('monthlyDays', 31);
        $response->assertSee('01 December 2026');
    }

    public function test_create_not_extendable_rental_redirects_with_reason(): void
    {
        $pending = Rental::factory()->period('2026-11-21', 3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.extensions.create', $pending));

        $response->assertRedirect(route('admin.extensions.index'));
        $response->assertSessionHas('error', 'Only rentals with a verified payment can be extended.');
    }

    public function test_store_daily_extension_updates_rental(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.extensions.store', $this->rental), $this->payload());

        $response->assertRedirect(route('admin.extensions.index'));
        $response->assertSessionHas('success', 'Rental for garage C001 extended from 30 November 2026 to 05 December 2026.');
        $rental = $this->rental->fresh();
        $this->assertSame(5, $rental->extension_days);
        $this->assertSame('50000.00', $rental->extension_cost);
        Storage::disk('public')->assertExists($rental->extension_payment_proof);
    }

    public function test_store_monthly_extension_ignores_days(): void
    {
        $this->actingAs($this->admin)->post(route('admin.extensions.store', $this->rental), $this->payload([
            'extension_type' => 'monthly',
            'days' => null,
        ]))->assertSessionHas('success');

        $rental = $this->rental->fresh();
        $this->assertSame('2026-12-31', $rental->end_date->toDateString());
        $this->assertSame('250000.00', $rental->extension_cost);
    }

    public function test_store_daily_over_limit_shows_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.extensions.create', $this->rental))
            ->post(route('admin.extensions.store', $this->rental), $this->payload(['days' => 31]));

        $response->assertRedirect(route('admin.extensions.create', $this->rental));
        $response->assertSessionHas('error', 'Daily extension days must be between 1 and 30 days.');
        $this->assertSame(0, $this->rental->fresh()->extension_days);
    }

    public function test_store_conflict_shows_error_and_recommendation_button(): void
    {
        Rental::factory()->for($this->garage)->period('2026-12-04', 3)->create();
        $createUrl = route('admin.extensions.create', $this->rental);

        $response = $this->actingAs($this->admin)->from($createUrl)
            ->post(route('admin.extensions.store', $this->rental), $this->payload());

        $response->assertRedirect($createUrl);
        $response->assertSessionHas('error', 'The extension conflicts with a booking for garage C001 from 04 December 2026. Maximum extension is 3 days.');

        $page = $this->actingAs($this->admin)->get($createUrl);
        $page->assertViewHas('maxExtendableDays', 3);
        $page->assertSee('Use this number');
    }

    public function test_create_booking_right_after_end_date_hides_recommendation_button(): void
    {
        Rental::factory()->for($this->garage)->period('2026-12-01', 3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.extensions.create', $this->rental));

        $response->assertSee('the rental cannot be extended');
        $response->assertDontSee('Use this number');
    }

    public function test_store_daily_without_days_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.extensions.store', $this->rental), $this->payload(['days' => null]));

        $response->assertSessionHasErrors(['days' => 'Number of days is required for a daily extension.']);
    }

    public function test_store_without_payment_proof_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.extensions.store', $this->rental), $this->payload(['payment_proof' => null]));

        $response->assertSessionHasErrors(['payment_proof' => 'Payment proof is required.']);
    }

    public function test_renter_cannot_access_extension_pages(): void
    {
        $renter = $this->rental->vehicle->user;

        $this->actingAs($renter)->get(route('admin.extensions.index'))->assertForbidden();
        $this->actingAs($renter)->get(route('admin.extensions.create', $this->rental))->assertForbidden();
        $this->actingAs($renter)->post(route('admin.extensions.store', $this->rental), $this->payload())->assertForbidden();
        $this->assertSame(0, $this->rental->fresh()->extension_days);
    }
}
