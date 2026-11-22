<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Services\RentalPeriodCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalPeriodCalculatorTest extends TestCase
{
    private RentalPeriodCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new RentalPeriodCalculator;
        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function maxDailyDaysProvider(): array
    {
        return [
            'January (31 days)' => ['2027-01-10', 30],
            'regular February 2027 (28 days)' => ['2027-02-10', 27],
            'leap February 2028 (29 days)' => ['2028-02-10', 28],
            'April (30 days)' => ['2027-04-10', 29],
            'November (30 days)' => ['2026-11-21', 29],
        ];
    }

    #[DataProvider('maxDailyDaysProvider')]
    public function test_max_daily_days_by_month_returns_days_in_month_minus_one(string $startDate, int $expected): void
    {
        $date = CarbonImmutable::parse($startDate);

        $result = $this->calculator->maxDailyDays($date);

        $this->assertSame($expected, $result);
    }

    public static function validDailyDaysProvider(): array
    {
        return [
            'boundary: 1 day' => ['2026-11-21', 1],
            'boundary: November max 29 days' => ['2026-11-21', 29],
            'boundary: leap February max 28 days' => ['2028-02-01', 28],
        ];
    }

    #[DataProvider('validDailyDaysProvider')]
    public function test_total_days_daily_within_limit_returns_days(string $startDate, int $days): void
    {
        $result = $this->calculator->totalDays(RentalType::Daily, CarbonImmutable::parse($startDate), $days);

        $this->assertSame($days, $result);
    }

    public static function invalidDailyDaysProvider(): array
    {
        return [
            'boundary: 0 days' => ['2026-11-21', 0],
            'boundary: max + 1 November (30 days)' => ['2026-11-21', 30],
            'boundary: max + 1 February 2027 (28 days)' => ['2027-02-01', 28],
            'negative: empty number of days' => ['2026-11-21', null],
        ];
    }

    #[DataProvider('invalidDailyDaysProvider')]
    public function test_total_days_daily_out_of_limit_throws_exception(string $startDate, ?int $days): void
    {
        $this->expectException(DomainException::class);

        $this->calculator->totalDays(RentalType::Daily, CarbonImmutable::parse($startDate), $days);
    }

    public function test_total_days_monthly_returns_days_in_start_month(): void
    {
        $result = $this->calculator->totalDays(RentalType::Monthly, CarbonImmutable::parse('2028-02-10'));

        $this->assertSame(29, $result);
    }

    public static function endDateProvider(): array
    {
        return [
            'positive: daily 3 days' => [RentalType::Daily, '2026-11-21', 3, '2026-11-23'],
            'boundary: daily 1 day ends on the same day' => [RentalType::Daily, '2026-11-21', 1, '2026-11-21'],
            'positive: monthly November' => [RentalType::Monthly, '2026-11-21', null, '2026-12-20'],
            'positive: monthly leap February' => [RentalType::Monthly, '2028-02-10', null, '2028-03-09'],
            'positive: monthly across years' => [RentalType::Monthly, '2026-12-15', null, '2027-01-14'],
        ];
    }

    #[DataProvider('endDateProvider')]
    public function test_calculate_end_date_example_cases_returns_expected_date(RentalType $type, string $startDate, ?int $days, string $expected): void
    {
        $result = $this->calculator->calculateEndDate($type, CarbonImmutable::parse($startDate), $days);

        $this->assertSame($expected, $result->toDateString());
    }

    public static function validStartDateProvider(): array
    {
        return [
            'boundary: today' => ['2026-11-21'],
            'positive: far in the future' => ['2028-06-01'],
        ];
    }

    #[DataProvider('validStartDateProvider')]
    public function test_validate_start_date_today_or_later_does_not_throw(string $startDate): void
    {
        $this->calculator->validateStartDate(CarbonImmutable::parse($startDate));

        $this->addToAssertionCount(1);
    }

    public function test_validate_start_date_yesterday_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Start date cannot be before today.');

        $this->calculator->validateStartDate(CarbonImmutable::parse('2026-11-20'));
    }

    public static function baseDaysProvider(): array
    {
        return [
            'positive: daily 3 days without extension' => ['2026-11-21', '2026-11-23', 0, 3],
            'positive: daily 3 days extended by 2 days' => ['2026-11-21', '2026-11-25', 2, 3],
            'positive: monthly extended by 5 days' => ['2026-11-21', '2026-12-25', 5, 30],
        ];
    }

    #[DataProvider('baseDaysProvider')]
    public function test_base_days_with_extension_returns_initial_days(string $startDate, string $endDate, int $extensionDays, int $expected): void
    {
        $result = $this->calculator->baseDays(CarbonImmutable::parse($startDate), CarbonImmutable::parse($endDate), $extensionDays);

        $this->assertSame($expected, $result);
    }

    public static function maxDailyExtensionDaysProvider(): array
    {
        return [
            'ends 30 November, December reference' => ['2026-11-30', 30],
            'ends 15 January 2027, January reference' => ['2027-01-15', 30],
            'ends 31 January 2027, February reference' => ['2027-01-31', 27],
            'ends 10 February 2028, leap February reference' => ['2028-02-10', 28],
        ];
    }

    #[DataProvider('maxDailyExtensionDaysProvider')]
    public function test_max_daily_extension_days_uses_month_after_end_date(string $endDate, int $expected): void
    {
        $result = $this->calculator->maxDailyExtensionDays(CarbonImmutable::parse($endDate));

        $this->assertSame($expected, $result);
    }

    public function test_extension_start_date_is_day_after_end_date(): void
    {
        $result = $this->calculator->extensionStartDate(CarbonImmutable::parse('2026-11-30'));

        $this->assertSame('2026-12-01', $result->toDateString());
    }

    public static function extensionDaysProvider(): array
    {
        return [
            'daily 5 days' => [RentalType::Daily, '2026-11-30', 5, 5],
            'boundary: daily max 30 days, December reference' => [RentalType::Daily, '2026-11-30', 30, 30],
            'monthly, December reference 31 days' => [RentalType::Monthly, '2026-11-30', null, 31],
            'monthly, February 2027 reference 28 days' => [RentalType::Monthly, '2027-01-31', null, 28],
        ];
    }

    #[DataProvider('extensionDaysProvider')]
    public function test_extension_days_returns_additional_days(RentalType $type, string $endDate, ?int $days, int $expected): void
    {
        $result = $this->calculator->extensionDays($type, CarbonImmutable::parse($endDate), $days);

        $this->assertSame($expected, $result);
    }

    public static function invalidExtensionDaysProvider(): array
    {
        return [
            'boundary: 0 days' => ['2026-11-30', 0, 30],
            'boundary: max + 1, December reference (31 days)' => ['2026-11-30', 31, 30],
            'boundary: max + 1, February 2027 reference (28 days)' => ['2027-01-31', 28, 27],
            'negative: empty' => ['2026-11-30', null, 30],
        ];
    }

    #[DataProvider('invalidExtensionDaysProvider')]
    public function test_extension_days_daily_out_of_limit_throws_exception(string $endDate, ?int $days, int $maxDays): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Daily extension days must be between 1 and {$maxDays} days.");

        $this->calculator->extensionDays(RentalType::Daily, CarbonImmutable::parse($endDate), $days);
    }

    public function test_extended_end_date_adds_additional_days(): void
    {
        $result = $this->calculator->extendedEndDate(CarbonImmutable::parse('2026-11-30'), 31);

        $this->assertSame('2026-12-31', $result->toDateString());
    }

    public static function remainingDaysProvider(): array
    {
        return [
            'boundary: ends today' => ['2026-11-21', 1],
            'positive: ends in 3 days' => ['2026-11-23', 3],
            'boundary: already passed' => ['2026-11-20', 0],
        ];
    }

    #[DataProvider('remainingDaysProvider')]
    public function test_remaining_days_from_today_returns_inclusive_count(string $endDate, int $expected): void
    {
        $result = $this->calculator->remainingDays(CarbonImmutable::parse($endDate));

        $this->assertSame($expected, $result);
    }

    public function test_count_days_inclusive_same_day_returns_one(): void
    {
        $date = CarbonImmutable::parse('2026-11-21');

        $result = $this->calculator->countDaysInclusive($date, $date);

        $this->assertSame(1, $result);
    }

    public function test_count_days_inclusive_across_month_returns_total_days(): void
    {
        $result = $this->calculator->countDaysInclusive(
            CarbonImmutable::parse('2026-11-21'),
            CarbonImmutable::parse('2026-12-20'),
        );

        $this->assertSame(30, $result);
    }
}
