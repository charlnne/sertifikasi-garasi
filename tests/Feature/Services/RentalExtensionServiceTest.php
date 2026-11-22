<?php

namespace Tests\Feature\Services;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Services\RentalExtensionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalExtensionService $service;

    private Garage $garage;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('public');

        $this->service = app(RentalExtensionService::class);
        $this->garage = Garage::factory()->car()->create(['number' => 1, 'daily_price' => 10000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Verified daily rental from 21 to 30 November 2026.
     */
    private function rentalEndingNovember30(): Rental
    {
        return Rental::factory()->for($this->garage)->period('2026-11-21', 10)->verified()->create();
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->image('proof.jpg');
    }

    public function test_extend_daily_five_days_updates_end_date_days_and_cost(): void
    {
        $rental = $this->rentalEndingNovember30();

        $result = $this->service->extend($rental, RentalType::Daily, 5, $this->proof());

        $this->assertSame('2026-12-05', $result->end_date->toDateString());
        $this->assertSame(5, $result->extension_days);
        $this->assertSame('50000.00', $result->extension_cost);
        Storage::disk('public')->assertExists($result->extension_payment_proof);
    }

    public function test_extend_daily_max_thirty_days_is_accepted(): void
    {
        $rental = $this->rentalEndingNovember30();

        $result = $this->service->extend($rental, RentalType::Daily, 30, $this->proof());

        $this->assertSame('2026-12-30', $result->end_date->toDateString());
        $this->assertSame('300000.00', $result->extension_cost);
    }

    public static function invalidDaysProvider(): array
    {
        return [
            'boundary: 0 days' => [0],
            'boundary: 31 days' => [31],
        ];
    }

    #[DataProvider('invalidDaysProvider')]
    public function test_extend_daily_out_of_limit_is_rejected_without_changes(int $days): void
    {
        $rental = $this->rentalEndingNovember30();

        try {
            $this->service->extend($rental, RentalType::Daily, $days, $this->proof());
            $this->fail('The extension should have been rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Daily extension days must be between 1 and 30 days.', $exception->getMessage());
        }

        $this->assertSame('2026-11-30', $rental->fresh()->end_date->toDateString());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_extend_monthly_uses_reference_month_days_and_25_times_price(): void
    {
        $rental = $this->rentalEndingNovember30();

        $result = $this->service->extend($rental, RentalType::Monthly, null, $this->proof());

        $this->assertSame(31, $result->extension_days);
        $this->assertSame('2026-12-31', $result->end_date->toDateString());
        $this->assertSame('250000.00', $result->extension_cost);
    }

    public function test_extend_reference_month_february_2027(): void
    {
        Carbon::setTestNow('2027-01-20 10:00:00');
        $daily = Rental::factory()->for($this->garage)->period('2027-01-20', 12)->verified()->create();
        $otherGarage = Garage::factory()->car()->create(['number' => 2, 'daily_price' => 10000]);
        $monthly = Rental::factory()->for($otherGarage)->period('2027-01-20', 12)->verified()->create();

        $this->assertSame(27, $this->service->maxExtendableDays($daily));
        $dailyResult = $this->service->extend($daily, RentalType::Daily, 27, $this->proof());
        $monthlyResult = $this->service->extend($monthly, RentalType::Monthly, null, $this->proof());

        $this->assertSame('2027-02-27', $dailyResult->end_date->toDateString());
        $this->assertSame('270000.00', $dailyResult->extension_cost);
        $this->assertSame(28, $monthlyResult->extension_days);
        $this->assertSame('2027-02-28', $monthlyResult->end_date->toDateString());
        $this->assertSame('250000.00', $monthlyResult->extension_cost);
    }

    public function test_extend_daily_28_days_in_february_reference_is_rejected(): void
    {
        Carbon::setTestNow('2027-01-20 10:00:00');
        $rental = Rental::factory()->for($this->garage)->period('2027-01-20', 12)->verified()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Daily extension days must be between 1 and 27 days.');

        $this->service->extend($rental, RentalType::Daily, 28, $this->proof());
    }

    public function test_extend_twice_accumulates_days_and_cost_and_replaces_proof(): void
    {
        $rental = $this->rentalEndingNovember30();

        $first = $this->service->extend($rental, RentalType::Daily, 5, $this->proof());
        $firstProof = $first->extension_payment_proof;
        // The second extension starts 6 December, reference month December (31 days).
        $second = $this->service->extend($first, RentalType::Monthly, null, $this->proof());

        $this->assertSame('2027-01-05', $second->end_date->toDateString());
        $this->assertSame(36, $second->extension_days);
        $this->assertSame('300000.00', $second->extension_cost);
        $this->assertSame(10, $second->base_days);
        $this->assertSame(100000.0, $second->rent_cost);
        Storage::disk('public')->assertMissing($firstProof);
        Storage::disk('public')->assertExists($second->extension_payment_proof);
    }

    public function test_extend_monthly_rental_can_be_extended(): void
    {
        $rental = Rental::factory()->for($this->garage)->monthly('2026-11-10')->verified()->create();

        $result = $this->service->extend($rental, RentalType::Daily, 3, $this->proof());

        $this->assertSame('2026-12-12', $result->end_date->toDateString());
        $this->assertSame('30000.00', $result->extension_cost);
        $this->assertSame(250000.0, $result->rent_cost);
    }

    public static function notExtendableProvider(): array
    {
        return [
            'payment pending' => ['2026-11-21', 10, 'pending', 'Only rentals with a verified payment can be extended.'],
            'payment rejected' => ['2026-11-21', 10, 'rejected', 'Only rentals with a verified payment can be extended.'],
            'not started yet' => ['2026-11-25', 3, 'verified', 'Only rentals running today can be extended.'],
            'already finished' => ['2026-11-10', 3, 'verified', 'Only rentals running today can be extended.'],
        ];
    }

    #[DataProvider('notExtendableProvider')]
    public function test_extend_not_extendable_rental_is_rejected(string $startDate, int $days, string $paymentStatus, string $expectedMessage): void
    {
        $rental = Rental::factory()->for($this->garage)->period($startDate, $days)->create(['payment_status' => $paymentStatus]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->service->extend($rental, RentalType::Daily, 1, $this->proof());
    }

    public function test_extend_daily_conflict_recommends_max_days(): void
    {
        $rental = $this->rentalEndingNovember30();
        Rental::factory()->for($this->garage)->period('2026-12-04', 3)->create();

        try {
            $this->service->extend($rental, RentalType::Daily, 5, $this->proof());
            $this->fail('The extension should have conflicted.');
        } catch (DomainException $exception) {
            $this->assertSame(3, $this->service->maxExtendableDays($rental));
            $this->assertSame('The extension conflicts with a booking for garage C001 from 04 December 2026. Maximum extension is 3 days.', $exception->getMessage());
        }

        $this->assertSame('2026-11-30', $rental->fresh()->end_date->toDateString());
        $this->assertEmpty(Storage::disk('public')->allFiles());

        $result = $this->service->extend($rental->fresh(), RentalType::Daily, 3, $this->proof());
        $this->assertSame('2026-12-03', $result->end_date->toDateString());
    }

    public function test_extend_monthly_conflict_recommends_daily_extension(): void
    {
        $rental = $this->rentalEndingNovember30();
        Rental::factory()->for($this->garage)->period('2026-12-20', 3)->verified()->create();

        try {
            $this->service->extend($rental, RentalType::Monthly, null, $this->proof());
            $this->fail('The extension should have conflicted.');
        } catch (DomainException $exception) {
            $this->assertSame(19, $this->service->maxExtendableDays($rental));
            $this->assertSame('The monthly extension conflicts with a booking for garage C001 from 20 December 2026. Use a daily extension of at most 19 days.', $exception->getMessage());
        }
    }

    public function test_extend_booking_right_after_end_date_cannot_be_extended(): void
    {
        $rental = $this->rentalEndingNovember30();
        Rental::factory()->for($this->garage)->period('2026-12-01', 3)->create();

        $this->assertSame(0, $this->service->maxExtendableDays($rental));

        try {
            $this->service->extend($rental, RentalType::Daily, 1, $this->proof());
            $this->fail('The extension should have been rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(0, $this->service->maxExtendableDays($rental));
            $this->assertSame('Garage C001 is booked from 01 December 2026, right after this rental ends. The rental cannot be extended.', $exception->getMessage());
        }
    }

    public function test_max_extendable_days_without_next_booking_is_daily_limit(): void
    {
        $rental = $this->rentalEndingNovember30();

        $this->assertSame(30, $this->service->maxExtendableDays($rental));
    }

    public function test_extend_rejected_next_booking_does_not_block(): void
    {
        $rental = $this->rentalEndingNovember30();
        Rental::factory()->for($this->garage)->period('2026-12-01', 3)->rejected()->create();

        $result = $this->service->extend($rental, RentalType::Daily, 5, $this->proof());

        $this->assertSame('2026-12-05', $result->end_date->toDateString());
    }

    public function test_extend_vehicle_booked_elsewhere_is_rejected(): void
    {
        $rental = $this->rentalEndingNovember30();
        $otherGarage = Garage::factory()->car()->create(['number' => 2]);
        Rental::factory()->for($rental->vehicle)->for($otherGarage)->period('2026-12-03', 2)->create();

        $this->expectException(DomainException::class);

        $this->service->extend($rental, RentalType::Daily, 5, $this->proof());
    }

    public function test_active_rentals_today_only_verified_rentals_covering_today(): void
    {
        $active = $this->rentalEndingNovember30();
        Rental::factory()->period('2026-11-21', 2)->create();
        Rental::factory()->period('2026-11-25', 2)->verified()->create();
        Rental::factory()->period('2026-11-10', 2)->verified()->create();

        $result = $this->service->activeRentalsToday();

        $this->assertSame([$active->id], $result->pluck('id')->all());
    }
}
