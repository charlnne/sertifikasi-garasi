<?php

namespace App\Enums;

enum VehicleType: string
{
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case ElectricCar = 'electric_car';

    public function label(): string
    {
        return match ($this) {
            self::Bicycle => 'Bicycle',
            self::Motorcycle => 'Motorcycle',
            self::Car => 'Car',
            self::ElectricCar => 'Electric Car',
        };
    }

    public function requiresPlateNumber(): bool
    {
        return $this !== self::Bicycle;
    }

    public function hasBattery(): bool
    {
        return $this === self::ElectricCar;
    }
}
