<?php

namespace Tests\Feature\Web;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarageWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'car',
            'count' => 5,
            'daily_price' => 30000,
        ], $overrides);
    }

    public function test_store_bulk_car_garages_creates_sequential_numbers(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload());

        $response->assertRedirect(route('admin.garages.index', ['type' => 'car']));
        $response->assertSessionHas('success', '5 Car garages created (C001 - C005).');
        $this->assertSame([1, 2, 3, 4, 5], Garage::ofType(GarageType::Car)->orderBy('number')->pluck('number')->all());
        $this->assertNull(Garage::first()->kwh_price);
    }

    public function test_store_continues_last_number_per_type(): void
    {
        Garage::factory()->car()->create(['number' => 10]);
        Garage::factory()->bicycle()->create(['number' => 3]);

        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload(['count' => 2]));

        $codes = Garage::ofType(GarageType::Car)->orderBy('number')->get()->pluck('code')->all();
        $this->assertSame(['C010', 'C011', 'C012'], $codes);
    }

    public function test_store_electric_garage_saves_kwh_price(): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), [
            'type' => 'electric',
            'count' => 1,
            'daily_price' => 50000,
            'kwh_price' => 12000,
        ]);

        $garage = Garage::firstOrFail();
        $this->assertSame('E001', $garage->code);
        $this->assertSame('12000.00', $garage->kwh_price);
    }

    public function test_store_electric_without_kwh_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), [
            'type' => 'electric',
            'count' => 1,
            'daily_price' => 50000,
        ]);

        $response->assertSessionHasErrors(['kwh_price' => 'Price per kWh is required for an electric garage.']);
    }

    public function test_store_non_electric_garage_ignores_kwh_price(): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload([
            'count' => 1,
            'kwh_price' => 10000,
        ]));

        $this->assertNull(Garage::firstOrFail()->kwh_price);
    }

    public static function validCountProvider(): array
    {
        return [
            'boundary: 1 garage' => [1, 'S001'],
            'boundary: 999 garages' => [999, 'S999'],
        ];
    }

    #[DataProvider('validCountProvider')]
    public function test_store_valid_count_creates_garages(int $count, string $lastCode): void
    {
        $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload([
            'type' => 'bicycle',
            'count' => $count,
            'daily_price' => 5000,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($count, Garage::count());
        $this->assertSame($lastCode, Garage::orderByDesc('number')->first()->code);
    }

    public static function invalidCountProvider(): array
    {
        return [
            'boundary: 0 garages' => [0, 'Garage count must be at least 1.'],
            'boundary: 1000 garages' => [1000, 'Garage count may not exceed 999.'],
        ];
    }

    #[DataProvider('invalidCountProvider')]
    public function test_store_invalid_count_returns_validation_error(int $count, string $expectedMessage): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload(['count' => $count]));

        $response->assertSessionHasErrors(['count' => $expectedMessage]);
        $this->assertSame(0, Garage::count());
    }

    public function test_store_exceeding_remaining_quota_returns_remaining_quota_message(): void
    {
        Garage::factory()->bicycle()->create(['number' => 998]);

        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload([
            'type' => 'bicycle',
            'count' => 2,
        ]));

        $response->assertSessionHasErrors(['count' => 'Only 1 more bicycle garage can be added (maximum 999).']);
        $this->assertSame(1, Garage::count());
    }

    public function test_store_exactly_remaining_quota_creates_e999(): void
    {
        Garage::factory()->electric()->create(['number' => 998]);

        $this->actingAs($this->admin)->post(route('admin.garages.store'), [
            'type' => 'electric',
            'count' => 1,
            'daily_price' => 50000,
            'kwh_price' => 10000,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Garage::get()->contains(fn (Garage $garage) => $garage->code === 'E999'));
    }

    public function test_store_zero_daily_price_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.garages.store'), $this->storePayload(['daily_price' => 0]));

        $response->assertSessionHasErrors('daily_price');
    }

    public static function forbiddenRouteProvider(): array
    {
        return [
            'edit' => ['admin.garages.edit'],
            'update' => ['admin.garages.update'],
            'destroy' => ['admin.garages.destroy'],
            'show' => ['admin.garages.show'],
        ];
    }

    #[DataProvider('forbiddenRouteProvider')]
    public function test_garage_routes_edit_and_delete_do_not_exist(string $routeName): void
    {
        $this->assertFalse(Route::has($routeName));
    }

    public function test_update_and_delete_requests_return404(): void
    {
        $garage = Garage::factory()->create();

        $this->actingAs($this->admin)->put("/admin/garages/{$garage->id}", ['daily_price' => 1])->assertNotFound();
        $this->actingAs($this->admin)->delete("/admin/garages/{$garage->id}")->assertNotFound();
        $this->assertSame('30000.00', $garage->fresh()->daily_price);
    }

    public function test_index_renter_returns403(): void
    {
        $renter = User::factory()->create();

        $this->actingAs($renter)->get(route('admin.garages.index'))->assertForbidden();
        $this->actingAs($renter)->get(route('admin.garages.create'))->assertForbidden();
        $this->actingAs($renter)->post(route('admin.garages.store'), $this->storePayload())->assertForbidden();
    }

    public function test_index_shows_code_prices_and_status_today(): void
    {
        $garage = Garage::factory()->electric()->create(['number' => 7, 'daily_price' => 50000, 'kwh_price' => 10000]);
        Rental::factory()->for($garage)->period('2026-11-20', 3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['E007', 'Electric', 'Rp 50.000', 'Rp 1.250.000', 'Rp 10.000', 'In Use']);
    }

    public function test_index_filter_by_type_and_status(): void
    {
        $usedCar = Garage::factory()->car()->create(['number' => 1]);
        Garage::factory()->car()->create(['number' => 2]);
        Garage::factory()->bicycle()->create(['number' => 3]);
        Rental::factory()->for($usedCar)->period('2026-11-21', 1)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index', ['type' => 'car', 'status' => 'available']));

        $codes = $response->viewData('garages')->pluck('code')->all();
        $this->assertSame(['C002'], $codes);
    }

    public static function searchProvider(): array
    {
        return [
            'full code' => ['C011', ['C011']],
            'lowercase' => ['c011', ['C011']],
            'code prefix' => ['C01', ['C010', 'C011']],
            'type prefix only' => ['S', ['S011']],
            'digits only' => ['011', ['S011', 'C011']],
            'no match' => ['X01', []],
        ];
    }

    #[DataProvider('searchProvider')]
    public function test_index_search_by_code_returns_matching_garages(string $search, array $expected): void
    {
        Garage::factory()->car()->create(['number' => 10]);
        Garage::factory()->car()->create(['number' => 11]);
        Garage::factory()->car()->create(['number' => 100]);
        Garage::factory()->bicycle()->create(['number' => 11]);

        $response = $this->actingAs($this->admin)->get(route('admin.garages.index', ['search' => $search]));

        $this->assertSame($expected, $response->viewData('garages')->pluck('code')->all());
    }

    public function test_create_shows_summary_per_type(): void
    {
        $used = Garage::factory()->car()->create(['number' => 1]);
        $booked = Garage::factory()->car()->create(['number' => 2]);
        Garage::factory()->car()->create(['number' => 3]);
        Rental::factory()->for($used)->period('2026-11-21', 2)->create();
        Rental::factory()->for($booked)->period('2026-11-25', 2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.garages.create'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['car'] === [
            'total' => 3,
            'available' => 1,
            'used' => 1,
            'booked' => 1,
            'last_number' => 3,
            'remaining' => 996,
        ] && $summary['bicycle']['remaining'] === 999);
        $response->assertSee('value="10000"', false);
    }
}
