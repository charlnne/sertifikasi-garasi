<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use DomainException;
use Illuminate\Support\Str;

/**
 * Determines new garage numbers: continues from the last number of the same type, up to 999 per type.
 * Garage codes are not stored but built from the type prefix + a 3-digit number (S001, C010).
 *
 * @author Charlene
 *
 * @version 1.0
 */
class GarageCodeGenerator
{
    public const MAX_PER_TYPE = 999;

    public function __construct(private readonly GarageRuleService $garageRules = new GarageRuleService) {}

    public function lastNumber(GarageType $type): int
    {
        return (int) Garage::ofType($type)->max('number');
    }

    public function remainingQuota(GarageType $type): int
    {
        return self::MAX_PER_TYPE - $this->lastNumber($type);
    }

    /**
     * Numbers for a batch of new garages based on the last number in the database.
     *
     * @return array<int, int>
     *
     * @throws DomainException If the count is invalid or exceeds the remaining quota.
     */
    public function nextNumbers(GarageType $type, int $count): array
    {
        return $this->buildNumbers($type, $this->lastNumber($type), $count);
    }

    /**
     * Consecutive numbers after the last number.
     *
     * @param  GarageType  $type  Garage type (for the quota message).
     * @param  int  $lastNumber  Last garage number for this type (0 if none yet).
     * @param  int  $count  Number of garages to create.
     * @return array<int, int> Example: [11, 12, 13].
     *
     * @throws DomainException If the count is outside 1-999 or exceeds the remaining quota.
     */
    public function buildNumbers(GarageType $type, int $lastNumber, int $count): array
    {
        if ($count < 1 || $count > self::MAX_PER_TYPE) {
            throw new DomainException('Garage count must be between 1 and '.self::MAX_PER_TYPE.'.');
        }

        $remaining = self::MAX_PER_TYPE - $lastNumber;
        $label = strtolower($type->label());

        if ($remaining <= 0) {
            throw new DomainException(ucfirst($label).' garage quota is full (maximum '.self::MAX_PER_TYPE.').');
        }

        if ($count > $remaining) {
            throw new DomainException("Only {$remaining} more {$label} ".Str::plural('garage', $remaining).' can be added (maximum '.self::MAX_PER_TYPE.').');
        }

        return range($lastNumber + 1, $lastNumber + $count);
    }

    /**
     * Garage codes for a list of numbers, used for the preview and success message.
     *
     * @param  array<int, int>  $numbers
     * @return array<int, string> Example: ['C011', 'C012'].
     */
    public function codesFor(GarageType $type, array $numbers): array
    {
        return array_map(fn (int $number) => $this->garageRules->formatCode($type, $number), $numbers);
    }
}
