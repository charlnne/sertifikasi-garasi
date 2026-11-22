<?php

namespace App\Enums;

enum GarageStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Booked => 'Booked',
            self::Used => 'In Use',
        };
    }
}
