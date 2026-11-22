<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Short label for the renter verification page tabs.
     */
    public function tabLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
