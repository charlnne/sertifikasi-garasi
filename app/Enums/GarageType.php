<?php

namespace App\Enums;

enum GarageType: string
{
    case Bicycle = 'bicycle';
    case Car = 'car';
    case Electric = 'electric';

    public function label(): string
    {
        return match ($this) {
            self::Bicycle => 'Bicycle',
            self::Car => 'Car',
            self::Electric => 'Electric',
        };
    }
}
