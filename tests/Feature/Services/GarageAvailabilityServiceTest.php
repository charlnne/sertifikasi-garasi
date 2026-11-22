<?php

namespace Tests\Feature\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\GarageAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarageAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageAvailabilityService $service;

    private Garage $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GarageAvailabilityService;
        $this->garage = Garage::factory()->car()->create(['number' => 1]);
        // Existing rental: 10 to 15 December 2026.
        Rental::factory()->for($this->garage)->period('2026-12-10', 6)->verified()->create();
    }

    public static function rangeProvider(): array
    {
        return [
            'negative: full overlap (same range)' => ['2026-12-10', '2026-12-15', false],
            'negative: full overlap (enclosing)' => ['2026-12-05', '2026-12-20', false],
            'negative: inside the range' => ['2026-12-12', '2026-12-13', false],
            'negative: partial overlap at start' => ['2026-12-08', '2026-12-10', false],
            'negative: partial overlap at end' => ['2026-12-15', '2026-12-18', false],
            'positive: adjacent before' => ['2026-12-05', '2026-12-09', true],
            'positive: adjacent after' => ['2026-12-16', '2026-12-20', true],
        ];
    }

    #[DataProvider('rangeProvider')]
    public function test_is_garage_available_against_existing_rental(string $start, string $end, bool $expected): void
    {
        $result = $this->service->isGarageAvailable($this->garage, CarbonImmutable::parse($start), CarbonImmutable::parse($end));

        $this->assertSame($expected, $result);
    }

    public function test_is_garage_available_rejected_rental_does_not_block(): void
    {
        $garage = Garage::factory()->car()->create(['number' => 2]);
        Rental::factory()->for($garage)->period('2026-12-10', 6)->rejected()->create();

        $result = $this->service->isGarageAvailable($garage, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-15'));

        $this->assertTrue($result);
    }

    public function test_is_garage_available_pending_rental_blocks(): void
    {
        $garage = Garage::factory()->car()->create(['number' => 2]);
        Rental::factory()->for($garage)->period('2026-12-10', 6)->create();

        $result = $this->service->isGarageAvailable($garage, CarbonImmutable::parse('2026-12-12'), CarbonImmutable::parse('2026-12-12'));

        $this->assertFalse($result);
    }

    public function test_is_garage_available_ignores_given_rental(): void
    {
        $rental = $this->garage->rentals()->first();

        $result = $this->service->isGarageAvailable($this->garage, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-20'), $rental->id);

        $this->assertTrue($result);
    }

    public function test_is_vehicle_free_vehicle_cannot_double_book_other_garage(): void
    {
        $vehicle = $this->garage->rentals()->first()->vehicle;

        $this->assertFalse($this->service->isVehicleFree($vehicle, CarbonImmutable::parse('2026-12-14'), CarbonImmutable::parse('2026-12-16')));
        $this->assertTrue($this->service->isVehicleFree($vehicle, CarbonImmutable::parse('2026-12-16'), CarbonImmutable::parse('2026-12-17')));
    }

    public function test_is_vehicle_free_other_vehicle_is_free(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->assertTrue($this->service->isVehicleFree($vehicle, CarbonImmutable::parse('2026-12-10'), CarbonImmutable::parse('2026-12-15')));
    }

    public function test_available_garages_excludes_taken_and_other_types(): void
    {
        Garage::factory()->car()->create(['number' => 2]);
        Garage::factory()->car()->create(['number' => 3]);
        Garage::factory()->bicycle()->create(['number' => 1]);

        $result = $this->service->availableGarages(GarageType::Car, CarbonImmutable::parse('2026-12-14'), CarbonImmutable::parse('2026-12-16'));

        $this->assertSame(['C002', 'C003'], $result->pluck('code')->all());
    }
}
