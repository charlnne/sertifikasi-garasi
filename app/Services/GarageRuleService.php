<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\VehicleType;

/**
 * Rules per garage type: code prefix, garage code format, which vehicles may park, and which garage has a charger.
 *
 * - Bicycle garage (S): bicycles and motorcycles.
 * - Car garage (C): cars and electric cars, without charging.
 * - Electric garage (E): electric cars only, with a charger.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class GarageRuleService
{
    public const DEFAULT_KWH_PRICE = 10000;

    public function prefix(GarageType $type): string
    {
        return match ($type) {
            GarageType::Bicycle => 'S',
            GarageType::Car => 'C',
            GarageType::Electric => 'E',
        };
    }

    /**
     * Garage code = prefix + 3-digit number, e.g. S001 or C010.
     */
    public function formatCode(GarageType $type, int $number): string
    {
        return $this->prefix($type).str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Find the garage type from the code prefix letter (S, C, or E).
     */
    public function typeForPrefix(string $prefix): ?GarageType
    {
        foreach (GarageType::cases() as $type) {
            if ($this->prefix($type) === strtoupper($prefix)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return array<int, VehicleType>
     */
    public function allowedVehicleTypes(GarageType $type): array
    {
        return match ($type) {
            GarageType::Bicycle => [VehicleType::Bicycle, VehicleType::Motorcycle],
            // Electric cars may park in a regular car garage, but they are not charged there.
            GarageType::Car => [VehicleType::Car, VehicleType::ElectricCar],
            GarageType::Electric => [VehicleType::ElectricCar],
        };
    }

    /**
     * Check whether a vehicle type may park in a garage type.
     */
    public function canPark(GarageType $garageType, VehicleType $vehicleType): bool
    {
        return in_array($vehicleType, $this->allowedVehicleTypes($garageType), true);
    }

    /**
     * Garage types that a vehicle type may use.
     *
     * @return array<int, GarageType>
     */
    public function garageTypesFor(VehicleType $vehicleType): array
    {
        return array_values(array_filter(
            GarageType::cases(),
            fn (GarageType $type) => $this->canPark($type, $vehicleType),
        ));
    }

    /**
     * Only electric garages have a charger.
     */
    public function hasCharger(GarageType $type): bool
    {
        return $type === GarageType::Electric;
    }

    /**
     * Charging only happens for electric cars parked in a garage with a charger.
     */
    public function chargesVehicle(GarageType $garageType, VehicleType $vehicleType): bool
    {
        return $this->hasCharger($garageType) && $vehicleType->hasBattery();
    }
}
