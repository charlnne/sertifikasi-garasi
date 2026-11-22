<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Services\RentalCostCalculator;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalCostCalculatorTest extends TestCase
{
    private RentalCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new RentalCostCalculator;
    }

    public function test_monthly_price_returns_25_times_daily_price(): void
    {
        $result = $this->calculator->monthlyPrice(30000.0);

        $this->assertSame(750000.0, $result);
    }

    public static function dailyRentCostProvider(): array
    {
        return [
            'boundary: 1 day' => [1, 30000.0, 30000.0],
            'positive: 3 days x 10000' => [3, 10000.0, 30000.0],
            'positive: 3 days' => [3, 30000.0, 90000.0],
            'boundary: 29 days' => [29, 5000.0, 145000.0],
        ];
    }

    #[DataProvider('dailyRentCostProvider')]
    public function test_calculate_rent_cost_daily_returns_days_times_price(int $days, float $dailyPrice, float $expected): void
    {
        $result = $this->calculator->calculateRentCost(RentalType::Daily, $days, $dailyPrice);

        $this->assertSame($expected, $result);
    }

    public function test_calculate_rent_cost_daily_zero_days_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateRentCost(RentalType::Daily, 0, 30000.0);
    }

    public static function monthlyRentCostProvider(): array
    {
        return [
            '30-day month' => [30, 30000.0],
            '31-day month' => [31, 30000.0],
            'February 28 days' => [28, 50000.0],
        ];
    }

    #[DataProvider('monthlyRentCostProvider')]
    public function test_calculate_rent_cost_monthly_returns_25_times_daily_price(int $daysInMonth, float $dailyPrice): void
    {
        $result = $this->calculator->calculateRentCost(RentalType::Monthly, $daysInMonth, $dailyPrice);

        $this->assertSame(25 * $dailyPrice, $result);
    }

    public static function chargedKwhProvider(): array
    {
        return [
            'boundary: battery 0%' => [60.0, 0, 60.0],
            'positive: battery 25%' => [60.0, 25, 45.0],
            'positive: decimal capacity 75 kWh battery 50%' => [75.0, 50, 37.5],
            'boundary: battery 100%' => [60.0, 100, 0.0],
        ];
    }

    #[DataProvider('chargedKwhProvider')]
    public function test_calculate_charged_kwh_valid_percent_returns_remaining_capacity(float $capacity, int $percent, float $expected): void
    {
        $result = $this->calculator->calculateChargedKwh($capacity, $percent);

        $this->assertSame($expected, $result);
    }

    public static function invalidBatteryPercentProvider(): array
    {
        return [
            'boundary: -1%' => [-1],
            'boundary: 101%' => [101],
        ];
    }

    #[DataProvider('invalidBatteryPercentProvider')]
    public function test_calculate_charged_kwh_percent_out_of_range_throws_exception(int $percent): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Battery percentage must be between 0 and 100.');

        $this->calculator->calculateChargedKwh(60.0, $percent);
    }

    public function test_calculate_charged_kwh_zero_capacity_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateChargedKwh(0.0, 25);
    }

    public static function chargingCostProvider(): array
    {
        return [
            'boundary: battery 0% charges full 60 kWh' => [0, 60.0, 600000.0],
            'positive: battery 25% charges 45 kWh' => [25, 45.0, 450000.0],
            'boundary: battery 100% is not charged' => [100, 0.0, 0.0],
        ];
    }

    #[DataProvider('chargingCostProvider')]
    public function test_calculate_charging_cost_capacity60_price10000_returns_kwh_times_price(int $percent, float $expectedKwh, float $expectedCost): void
    {
        $chargedKwh = $this->calculator->calculateChargedKwh(60.0, $percent);

        $result = $this->calculator->calculateChargingCost($chargedKwh, 10000.0);

        $this->assertSame($expectedKwh, $chargedKwh);
        $this->assertSame($expectedCost, $result);
    }

    public static function totalProvider(): array
    {
        return [
            'positive: rent + charging' => [1250000.0, 450000.0, 1700000.0],
            'positive: without charging' => [90000.0, 0.0, 90000.0],
            'positive: decimal values' => [100000.5, 375000.25, 475000.75],
        ];
    }

    #[DataProvider('totalProvider')]
    public function test_calculate_total_rent_and_charging_returns_exact_sum_without_extra_percentage(float $rentCost, float $chargingCost, float $expected): void
    {
        $result = $this->calculator->calculateTotal($rentCost, $chargingCost);

        $this->assertSame($expected, $result);
        $this->assertSame($rentCost + $chargingCost, $result);
    }

    public static function extensionCostProvider(): array
    {
        return [
            'boundary: +1 day' => [1, 30000.0, 30000.0],
            'positive: +2 days x 10000' => [2, 10000.0, 20000.0],
            'positive: +2 days' => [2, 30000.0, 60000.0],
            'positive: +5 days bicycle garage' => [5, 5000.0, 25000.0],
        ];
    }

    #[DataProvider('extensionCostProvider')]
    public function test_calculate_extension_cost_additional_days_returns_days_times_price(int $additionalDays, float $dailyPrice, float $expected): void
    {
        $result = $this->calculator->calculateExtensionCost($additionalDays, $dailyPrice);

        $this->assertSame($expected, $result);
        $this->assertGreaterThan(0, $result);
    }

    public static function calculateExtensionProvider(): array
    {
        return [
            'daily 5 days, December reference' => [RentalType::Daily, 5, '2026-11-30', 5, '2026-12-05', 50000.0],
            'boundary: daily max 30 days' => [RentalType::Daily, 30, '2026-11-30', 30, '2026-12-30', 300000.0],
            'monthly, December reference' => [RentalType::Monthly, null, '2026-11-30', 31, '2026-12-31', 250000.0],
            'monthly, February 2027 reference' => [RentalType::Monthly, null, '2027-01-31', 28, '2027-02-28', 250000.0],
            'boundary: daily max, February 2027 reference' => [RentalType::Daily, 27, '2027-01-31', 27, '2027-02-27', 270000.0],
        ];
    }

    #[DataProvider('calculateExtensionProvider')]
    public function test_calculate_extension_returns_days_end_date_and_cost(
        RentalType $type,
        ?int $days,
        string $endDate,
        int $expectedDays,
        string $expectedEndDate,
        float $expectedCost,
    ): void {
        $result = $this->calculator->calculateExtension($type, $days, 10000.0, CarbonImmutable::parse($endDate));

        $this->assertSame($expectedDays, $result['days']);
        $this->assertSame($expectedEndDate, $result['end_date']->toDateString());
        $this->assertSame($expectedCost, $result['cost']);
        $this->assertGreaterThan(0, $result['cost']);
    }

    public static function invalidDailyExtensionProvider(): array
    {
        return [
            'boundary: 0 days' => [0],
            'boundary: 31 days (max + 1)' => [31],
        ];
    }

    #[DataProvider('invalidDailyExtensionProvider')]
    public function test_calculate_extension_daily_out_of_limit_throws_exception(int $days): void
    {
        $this->expectException(DomainException::class);

        $this->calculator->calculateExtension(RentalType::Daily, $days, 10000.0, CarbonImmutable::parse('2026-11-30'));
    }

    public function test_calculate_extension_monthly_zero_daily_price_throws_instead_of_zero_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateExtension(RentalType::Monthly, null, 0.0, CarbonImmutable::parse('2026-11-30'));
    }

    public static function invalidExtensionProvider(): array
    {
        return [
            'boundary: 0 additional days' => [0, 30000.0],
            'negative: negative additional days' => [-2, 30000.0],
            'negative: daily price 0' => [1, 0.0],
        ];
    }

    #[DataProvider('invalidExtensionProvider')]
    public function test_calculate_extension_cost_invalid_input_throws_instead_of_negative_result(int $additionalDays, float $dailyPrice): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculateExtensionCost($additionalDays, $dailyPrice);
    }
}
