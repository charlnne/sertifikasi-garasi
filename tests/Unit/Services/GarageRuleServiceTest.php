<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Services\GarageRuleService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarageRuleServiceTest extends TestCase
{
    private GarageRuleService $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new GarageRuleService;
    }

    public static function vehicleGarageCombinationProvider(): array
    {
        return [
            'positive: bicycle in bicycle garage' => [VehicleType::Bicycle, GarageType::Bicycle, true],
            'negative: bicycle in car garage' => [VehicleType::Bicycle, GarageType::Car, false],
            'negative: bicycle in electric garage' => [VehicleType::Bicycle, GarageType::Electric, false],
            'positive: motorcycle in bicycle garage' => [VehicleType::Motorcycle, GarageType::Bicycle, true],
            'negative: motorcycle in car garage' => [VehicleType::Motorcycle, GarageType::Car, false],
            'negative: motorcycle in electric garage' => [VehicleType::Motorcycle, GarageType::Electric, false],
            'negative: car in bicycle garage' => [VehicleType::Car, GarageType::Bicycle, false],
            'positive: car in car garage' => [VehicleType::Car, GarageType::Car, true],
            'negative: car in electric garage' => [VehicleType::Car, GarageType::Electric, false],
            'negative: electric car in bicycle garage' => [VehicleType::ElectricCar, GarageType::Bicycle, false],
            'positive: electric car in car garage' => [VehicleType::ElectricCar, GarageType::Car, true],
            'positive: electric car in electric garage' => [VehicleType::ElectricCar, GarageType::Electric, true],
        ];
    }

    #[DataProvider('vehicleGarageCombinationProvider')]
    public function test_can_park_vehicle_garage_combination_returns_expected(VehicleType $vehicleType, GarageType $garageType, bool $expected): void
    {
        $result = $this->rules->canPark($garageType, $vehicleType);

        $this->assertSame($expected, $result);
    }

    public static function prefixProvider(): array
    {
        return [
            'bicycle' => [GarageType::Bicycle, 'S'],
            'car' => [GarageType::Car, 'C'],
            'electric' => [GarageType::Electric, 'E'],
        ];
    }

    #[DataProvider('prefixProvider')]
    public function test_prefix_each_garage_type_returns_letter_and_back(GarageType $type, string $expectedPrefix): void
    {
        $this->assertSame($expectedPrefix, $this->rules->prefix($type));
        $this->assertSame($type, $this->rules->typeForPrefix(strtolower($expectedPrefix)));
    }

    public function test_type_for_prefix_unknown_letter_returns_null(): void
    {
        $this->assertNull($this->rules->typeForPrefix('X'));
    }

    public static function formatCodeProvider(): array
    {
        return [
            'bicycle number 1' => [GarageType::Bicycle, 1, 'S001'],
            'car number 10' => [GarageType::Car, 10, 'C010'],
            'boundary: electric number 999' => [GarageType::Electric, 999, 'E999'],
        ];
    }

    #[DataProvider('formatCodeProvider')]
    public function test_format_code_number_returns_prefix_and_three_digits(GarageType $type, int $number, string $expected): void
    {
        $result = $this->rules->formatCode($type, $number);

        $this->assertSame($expected, $result);
    }

    public static function chargesVehicleProvider(): array
    {
        return [
            'positive: electric car in electric garage' => [VehicleType::ElectricCar, GarageType::Electric, true],
            'negative: electric car in car garage' => [VehicleType::ElectricCar, GarageType::Car, false],
            'negative: regular car in car garage' => [VehicleType::Car, GarageType::Car, false],
            'negative: motorcycle in bicycle garage' => [VehicleType::Motorcycle, GarageType::Bicycle, false],
        ];
    }

    #[DataProvider('chargesVehicleProvider')]
    public function test_charges_vehicle_combination_returns_expected(VehicleType $vehicleType, GarageType $garageType, bool $expected): void
    {
        $result = $this->rules->chargesVehicle($garageType, $vehicleType);

        $this->assertSame($expected, $result);
    }

    public function test_has_charger_only_electric_garage(): void
    {
        $this->assertFalse($this->rules->hasCharger(GarageType::Bicycle));
        $this->assertFalse($this->rules->hasCharger(GarageType::Car));
        $this->assertTrue($this->rules->hasCharger(GarageType::Electric));
    }

    public function test_garage_types_for_electric_car_returns_car_and_electric(): void
    {
        $result = $this->rules->garageTypesFor(VehicleType::ElectricCar);

        $this->assertSame([GarageType::Car, GarageType::Electric], $result);
    }
}
