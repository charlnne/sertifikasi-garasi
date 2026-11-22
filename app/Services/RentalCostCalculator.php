<?php

namespace App\Services;

use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

/**
 * Garage rental cost calculations. All methods are pure (no database), so they are easy to test.
 *
 * The payment total only consists of rent cost + charging cost, with no tax or extra percentage.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class RentalCostCalculator
{
    public const MONTHLY_PRICE_MULTIPLIER = 25;

    public function __construct(private readonly RentalPeriodCalculator $periodCalculator = new RentalPeriodCalculator) {}

    /**
     * Calculate an extension starting from (end_date + 1 day).
     * Daily: days x daily price. Monthly: days in the reference month, cost 25 x daily price.
     *
     * @param  RentalType  $type  Extension type.
     * @param  int|null  $days  Number of days (daily extension only).
     * @param  float  $dailyPrice  Garage daily price.
     * @param  CarbonInterface  $currentEndDate  End date before the extension.
     * @return array{days: int, end_date: CarbonImmutable, cost: float}
     *
     * @throws DomainException If the daily extension days are out of range.
     * @throws InvalidArgumentException If the daily price is not greater than 0.
     */
    public function calculateExtension(RentalType $type, ?int $days, float $dailyPrice, CarbonInterface $currentEndDate): array
    {
        $additionalDays = $this->periodCalculator->extensionDays($type, $currentEndDate, $days);

        $cost = $type === RentalType::Monthly
            ? $this->calculateMonthlyExtensionCost($dailyPrice)
            : $this->calculateExtensionCost($additionalDays, $dailyPrice);

        return [
            'days' => $additionalDays,
            'end_date' => $this->periodCalculator->extendedEndDate($currentEndDate, $additionalDays),
            'cost' => $cost,
        ];
    }

    /**
     * Garage monthly price = 25 x daily price.
     */
    public function monthlyPrice(float $dailyPrice): float
    {
        return round(self::MONTHLY_PRICE_MULTIPLIER * $dailyPrice, 2);
    }

    /**
     * Calculate the initial rent cost. Daily = initial days x daily price, monthly = 25 x daily price.
     *
     * @param  RentalType  $type  Rental type.
     * @param  int  $baseDays  Initial rental days without extensions (used for daily rentals).
     * @param  float  $dailyPrice  Garage daily rental price.
     * @return float Rent cost.
     *
     * @throws InvalidArgumentException If the daily rental days are less than 1.
     */
    public function calculateRentCost(RentalType $type, int $baseDays, float $dailyPrice): float
    {
        if ($type === RentalType::Monthly) {
            return $this->monthlyPrice($dailyPrice);
        }

        if ($baseDays < 1) {
            throw new InvalidArgumentException('Rental days must be at least 1 day.');
        }

        return round($baseDays * $dailyPrice, 2);
    }

    /**
     * Calculate the charged kWh: battery capacity x (100 - current battery percent) / 100.
     *
     * @param  float  $batteryCapacity  Vehicle battery capacity in kWh.
     * @param  int  $batteryPercent  Current battery percentage (0-100).
     * @return float Charged kWh.
     *
     * @throws InvalidArgumentException If the percent is outside 0-100 or the capacity is not greater than 0.
     */
    public function calculateChargedKwh(float $batteryCapacity, int $batteryPercent): float
    {
        if ($batteryPercent < 0 || $batteryPercent > 100) {
            throw new InvalidArgumentException('Battery percentage must be between 0 and 100.');
        }

        if ($batteryCapacity <= 0) {
            throw new InvalidArgumentException('Battery capacity must be greater than 0 kWh.');
        }

        return round($batteryCapacity * (100 - $batteryPercent) / 100, 2);
    }

    public function calculateChargingCost(float $chargedKwh, float $kwhPrice): float
    {
        return round($chargedKwh * $kwhPrice, 2);
    }

    /**
     * Initial payment total = rent cost + initial charging cost, with nothing added.
     */
    public function calculateTotal(float $rentCost, float $chargingCost): float
    {
        return round($rentCost + $chargingCost, 2);
    }

    /**
     * Monthly extension cost = 25 x daily price, always greater than 0.
     *
     * @throws InvalidArgumentException If the daily price is not greater than 0.
     */
    public function calculateMonthlyExtensionCost(float $dailyPrice): float
    {
        if ($dailyPrice <= 0) {
            throw new InvalidArgumentException('Daily price must be greater than 0.');
        }

        return $this->monthlyPrice($dailyPrice);
    }

    /**
     * Calculate the daily extension cost: additional days x daily price.
     *
     * @param  int  $additionalDays  Additional days, at least 1.
     * @param  float  $dailyPrice  Garage daily rental price.
     * @return float Amount due, always greater than 0.
     *
     * @throws InvalidArgumentException If the additional days are less than 1 or the daily price is not greater than 0.
     */
    public function calculateExtensionCost(int $additionalDays, float $dailyPrice): float
    {
        if ($additionalDays < 1) {
            throw new InvalidArgumentException('Additional days must be at least 1 day.');
        }

        if ($dailyPrice <= 0) {
            throw new InvalidArgumentException('Daily price must be greater than 0.');
        }

        return round($additionalDays * $dailyPrice, 2);
    }
}
