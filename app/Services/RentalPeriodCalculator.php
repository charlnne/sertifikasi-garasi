<?php

namespace App\Services;

use App\Enums\RentalType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Rental period calculations. The end date (end_date) is the last day the garage is used (inclusive).
 *
 * Days in a month come from Carbon's daysInMonth, so February with 28/29 days
 * is handled automatically.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class RentalPeriodCalculator
{
    /**
     * Maximum daily rental days = days in the start date's month - 1.
     *
     * @param  CarbonInterface  $startDate  Rental start date.
     * @return int Example: January 30, February 2027 27, February 2028 28, April 29.
     */
    public function maxDailyDays(CarbonInterface $startDate): int
    {
        return $startDate->daysInMonth - 1;
    }

    /**
     * Number of rental days. Daily follows the input (1 to maxDailyDays), monthly = days in the start date's month.
     *
     * @param  RentalType  $type  Rental type.
     * @param  CarbonInterface  $startDate  Rental start date.
     * @param  int|null  $days  Selected number of days (daily rental only).
     * @return int Number of rental days.
     *
     * @throws DomainException If the daily rental days are out of range.
     */
    public function totalDays(RentalType $type, CarbonInterface $startDate, ?int $days = null): int
    {
        if ($type === RentalType::Monthly) {
            return $startDate->daysInMonth;
        }

        $maxDays = $this->maxDailyDays($startDate);

        if ($days === null || $days < 1 || $days > $maxDays) {
            throw new DomainException("Daily rental days must be between 1 and {$maxDays} days.");
        }

        return $days;
    }

    /**
     * Calculate the end date: start date + (number of days - 1).
     *
     * @param  RentalType  $type  Rental type.
     * @param  CarbonInterface  $startDate  Rental start date.
     * @param  int|null  $days  Number of days (daily rental only).
     * @return CarbonImmutable End date (inclusive).
     *
     * @throws DomainException If the daily rental days are out of range.
     */
    public function calculateEndDate(RentalType $type, CarbonInterface $startDate, ?int $days = null): CarbonImmutable
    {
        $start = CarbonImmutable::instance($startDate)->startOfDay();

        return $start->addDays($this->totalDays($type, $start, $days) - 1);
    }

    /**
     * The start date must be today or later, with no upper limit.
     *
     * @throws DomainException If the start date is before today.
     */
    public function validateStartDate(CarbonInterface $startDate): void
    {
        if (CarbonImmutable::instance($startDate)->startOfDay()->lt($this->today())) {
            throw new DomainException('Start date cannot be before today.');
        }
    }

    /**
     * Number of days from the start date to the end date, both included.
     */
    public function countDaysInclusive(CarbonInterface $startDate, CarbonInterface $endDate): int
    {
        return (int) CarbonImmutable::instance($startDate)->startOfDay()
            ->diffInDays(CarbonImmutable::instance($endDate)->startOfDay()) + 1;
    }

    /**
     * Initial rental days before extensions: end_date - start_date + 1 - extension_days.
     *
     * @param  CarbonInterface  $startDate  Rental start date.
     * @param  CarbonInterface  $endDate  Current end date (including extensions).
     * @param  int  $extensionDays  Accumulated extension days.
     * @return int Initial rental days.
     */
    public function baseDays(CarbonInterface $startDate, CarbonInterface $endDate, int $extensionDays): int
    {
        return $this->countDaysInclusive($startDate, $endDate) - $extensionDays;
    }

    /**
     * The extension period starts one day after the current end date.
     * The month of this date is the extension reference month.
     */
    public function extensionStartDate(CarbonInterface $currentEndDate): CarbonImmutable
    {
        return CarbonImmutable::instance($currentEndDate)->startOfDay()->addDay();
    }

    /**
     * Maximum daily extension days = days in the reference month - 1.
     *
     * @param  CarbonInterface  $currentEndDate  End date before the extension.
     * @return int Example: ends 30 November (reference December) = 30, ends 31 January 2027 (reference February) = 27.
     */
    public function maxDailyExtensionDays(CarbonInterface $currentEndDate): int
    {
        return $this->maxDailyDays($this->extensionStartDate($currentEndDate));
    }

    /**
     * Number of extension days. Daily follows the input (1 to the maximum), monthly = days in the reference month.
     *
     * @param  RentalType  $type  Extension type.
     * @param  CarbonInterface  $currentEndDate  End date before the extension.
     * @param  int|null  $days  Number of days (daily extension only).
     * @return int Number of additional days.
     *
     * @throws DomainException If the daily extension days are out of range.
     */
    public function extensionDays(RentalType $type, CarbonInterface $currentEndDate, ?int $days = null): int
    {
        $referenceDate = $this->extensionStartDate($currentEndDate);

        if ($type === RentalType::Monthly) {
            return $referenceDate->daysInMonth;
        }

        $maxDays = $this->maxDailyDays($referenceDate);

        if ($days === null || $days < 1 || $days > $maxDays) {
            throw new DomainException("Daily extension days must be between 1 and {$maxDays} days.");
        }

        return $days;
    }

    /**
     * New end date = current end date + additional days.
     */
    public function extendedEndDate(CarbonInterface $currentEndDate, int $additionalDays): CarbonImmutable
    {
        return CarbonImmutable::instance($currentEndDate)->startOfDay()->addDays($additionalDays);
    }

    /**
     * Remaining rental days from today to the end date (inclusive), at least 0.
     */
    public function remainingDays(CarbonInterface $endDate): int
    {
        $end = CarbonImmutable::instance($endDate)->startOfDay();

        return $end->lt($this->today()) ? 0 : $this->countDaysInclusive($this->today(), $end);
    }

    private function today(): CarbonImmutable
    {
        return now()->toImmutable()->startOfDay();
    }
}
