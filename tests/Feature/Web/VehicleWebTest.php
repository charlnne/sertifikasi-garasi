<?php

namespace Tests\Feature\Web;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleWebTest extends TestCase
{
    use RefreshDatabase;

    private User $renter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renter = User::factory()->create();
    }

    public function test_store_car_with_plate_saves_vehicle_for_renter(): void
    {
        $response = $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'car',
            'plate_number' => ' b  1234   klm ',
            'brand_model' => 'Toyota Avanza',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $vehicle = Vehicle::firstOrFail();
        $this->assertSame($this->renter->id, $vehicle->user_id);
        $this->assertSame('B 1234 KLM', $vehicle->plate_number);
        $this->assertNull($vehicle->battery_capacity);
    }

    public function test_store_bicycle_without_plate_is_accepted(): void
    {
        $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'bicycle',
            'brand_model' => 'Polygon Heist',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Vehicle::firstOrFail()->plate_number);
    }

    public function test_store_bicycle_with_plate_ignores_plate(): void
    {
        $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'bicycle',
            'plate_number' => 'B 1 A',
            'brand_model' => 'Polygon Heist',
        ]);

        $this->assertNull(Vehicle::firstOrFail()->plate_number);
    }

    public static function vehicleTypeRequiringPlateProvider(): array
    {
        return [
            'motorcycle' => ['motorcycle'],
            'car' => ['car'],
            'electric car' => ['electric_car'],
        ];
    }

    #[DataProvider('vehicleTypeRequiringPlateProvider')]
    public function test_store_without_plate_for_non_bicycle_returns_validation_error(string $type): void
    {
        $response = $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => $type,
            'brand_model' => 'Some Vehicle',
            'battery_capacity' => 60,
        ]);

        $response->assertSessionHasErrors(['plate_number' => 'Plate number is required except for bicycles.']);
    }

    public function test_store_duplicate_plate_returns_validation_error(): void
    {
        Vehicle::factory()->create(['plate_number' => 'B 1234 KLM']);

        $response = $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'car',
            'plate_number' => 'b 1234 klm',
            'brand_model' => 'Honda Jazz',
        ]);

        $response->assertSessionHasErrors(['plate_number' => 'Plate number is already registered.']);
    }

    public function test_store_electric_car_with_capacity_saves_capacity(): void
    {
        $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'electric_car',
            'plate_number' => 'B 2001 EVA',
            'brand_model' => 'Hyundai Ioniq 5',
            'battery_capacity' => 72.6,
        ])->assertSessionHasNoErrors();

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame(VehicleType::ElectricCar, $vehicle->type);
        $this->assertSame('72.60', $vehicle->battery_capacity);
    }

    public static function invalidCapacityProvider(): array
    {
        return [
            'negative: empty' => [null, 'Battery capacity is required for an electric car.'],
            'boundary: 0 kWh' => [0, 'Battery capacity must be greater than 0 kWh.'],
            'negative: minus' => [-10, 'Battery capacity must be greater than 0 kWh.'],
        ];
    }

    #[DataProvider('invalidCapacityProvider')]
    public function test_store_electric_car_invalid_capacity_returns_validation_error(?int $capacity, string $expectedMessage): void
    {
        $response = $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'electric_car',
            'plate_number' => 'B 2001 EVA',
            'brand_model' => 'Hyundai Ioniq 5',
            'battery_capacity' => $capacity,
        ]);

        $response->assertSessionHasErrors(['battery_capacity' => $expectedMessage]);
    }

    public function test_store_car_ignores_battery_capacity(): void
    {
        $this->actingAs($this->renter)->post(route('vehicles.store'), [
            'type' => 'car',
            'plate_number' => 'B 1234 KLM',
            'brand_model' => 'Toyota Avanza',
            'battery_capacity' => 60,
        ]);

        $this->assertNull(Vehicle::firstOrFail()->battery_capacity);
    }

    public function test_index_shows_only_own_vehicles(): void
    {
        Vehicle::factory()->for($this->renter)->create(['brand_model' => 'My Car']);
        Vehicle::factory()->create(['brand_model' => 'Other Renter Car']);

        $response = $this->actingAs($this->renter)->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('My Car');
        $response->assertDontSee('Other Renter Car');
    }

    public function test_index_admin_returns403(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('vehicles.index'))->assertForbidden();
    }

    public function test_index_pending_renter_returns403(): void
    {
        $renter = User::factory()->pending()->create();

        $this->actingAs($renter)->get(route('vehicles.index'))->assertForbidden();
    }
}
