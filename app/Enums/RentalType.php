<?php

namespace App\Enums;

enum RentalType: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Monthly => 'Monthly',
        };
    }
}
