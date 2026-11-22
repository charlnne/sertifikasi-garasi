<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Finished => 'Finished',
            self::Cancelled => 'Cancelled',
        };
    }
}
