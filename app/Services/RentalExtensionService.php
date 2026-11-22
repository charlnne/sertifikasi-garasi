<?php

namespace App\Services;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Traits\HandlesFileUpload;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rental extension by admins. The additional period starts from (end_date + 1 day) and can be daily
 * or monthly. If it conflicts with the next booking, the admin gets a recommended maximum number of days.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class RentalExtensionService
{
    use HandlesFileUpload;

    public const PROOF_DIRECTORY = 'extensions';

    public function __construct(
        private readonly RentalPeriodCalculator $periodCalculator,
        private readonly RentalCostCalculator $costCalculator,
        private readonly GarageAvailabilityService $availabilityService,
    ) {}

    /**
     * Rentals running today with a verified payment.
     *
     * @return Collection<int, Rental>
     */
    public function activeRentalsToday(): Collection
    {
        return Rental::verified()
            ->covering(today())
            ->with(['garage', 'vehicle.user'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Calculate the additional days, new end date, and extension cost without saving.
     *
     * @return array{days: int, end_date: CarbonImmutable, cost: float}
     *
     * @throws DomainException If the daily extension days are out of range.
     */
    public function quote(Rental $rental, RentalType $type, ?int $days = null): array
    {
        return $this->costCalculator->calculateExtension($type, $days, (float) $rental->garage->daily_price, $rental->end_date);
    }

    /**
     * The earliest other rental in the same garage that starts after this rental ends.
     */
    public function nextBooking(Rental $rental): ?Rental
    {
        return Rental::where('garage_id', $rental->garage_id)
            ->notCancelled()
            ->whereKeyNot($rental->id)
            ->whereDate('start_date', '>', $rental->end_date->toDateString())
            ->orderBy('start_date')
            ->first();
    }

    /**
     * Maximum extension days: the daily limit of the reference month, capped by the next booking.
     * A result of 0 means the next booking starts exactly one day after end_date.
     *
     * @return int Maximum number of days that can still be extended.
     */
    public function maxExtendableDays(Rental $rental): int
    {
        $maxDays = $this->periodCalculator->maxDailyExtensionDays($rental->end_date);
        $nextBooking = $this->nextBooking($rental);

        if ($nextBooking === null) {
            return $maxDays;
        }

        $daysUntilNextBooking = (int) $this->periodCalculator->extensionStartDate($rental->end_date)
            ->diffInDays(CarbonImmutable::instance($nextBooking->start_date)->startOfDay());

        return min($maxDays, $daysUntilNextBooking);
    }

    /**
     * Extend a rental. end_date is replaced, extension_days and extension_cost are accumulated,
     * and the extension payment proof is replaced with the latest one.
     *
     * @param  Rental  $rental  Rental active today with a verified payment.
     * @param  RentalType  $type  Extension type.
     * @param  int|null  $days  Number of days (daily extension only).
     * @param  UploadedFile  $paymentProof  Extension payment proof.
     * @return Rental The extended rental.
     *
     * @throws DomainException If it conflicts with the next booking, the rental cannot be extended,
     *                         or the number of days is invalid.
     */
    public function extend(Rental $rental, RentalType $type, ?int $days, UploadedFile $paymentProof): Rental
    {
        $this->ensureExtendable($rental);
        $this->quote($rental, $type, $days);

        $proofPath = $this->uploadFile($paymentProof, self::PROOF_DIRECTORY);

        try {
            [$rental, $oldProofPath] = DB::transaction(function () use ($rental, $type, $days, $proofPath) {
                // Lock the garage so an extension and a new rental cannot take the same dates at the same time.
                Garage::whereKey($rental->garage_id)->lockForUpdate()->firstOrFail();
                $rental = Rental::whereKey($rental->id)->lockForUpdate()->with(['garage', 'vehicle'])->firstOrFail();

                $this->ensureExtendable($rental);
                $quote = $this->quote($rental, $type, $days);
                $startDate = $this->periodCalculator->extensionStartDate($rental->end_date);

                if (! $this->availabilityService->isGarageAvailable($rental->garage, $startDate, $quote['end_date'], $rental->id)) {
                    throw $this->conflictException($rental, $type);
                }

                if (! $this->availabilityService->isVehicleFree($rental->vehicle, $startDate, $quote['end_date'], $rental->id)) {
                    throw new DomainException("Vehicle {$rental->vehicle->getDisplayName()} already has another rental during the extension period.");
                }

                $oldProofPath = $rental->extension_payment_proof;

                $rental->update([
                    'end_date' => $quote['end_date'],
                    'extension_days' => $rental->extension_days + $quote['days'],
                    'extension_cost' => round((float) $rental->extension_cost + $quote['cost'], 2),
                    'extension_payment_proof' => $proofPath,
                ]);

                return [$rental, $oldProofPath];
            });
        } catch (Throwable $exception) {
            $this->deleteFile($proofPath);

            throw $exception;
        }

        $this->deleteFile($oldProofPath);

        return $rental;
    }

    /**
     * @throws DomainException If the payment is not verified or the rental is not running today.
     */
    public function ensureExtendable(Rental $rental): void
    {
        if (! $rental->isVerified()) {
            throw new DomainException('Only rentals with a verified payment can be extended.');
        }

        $today = today();

        if ($rental->start_date->gt($today) || $rental->end_date->lt($today)) {
            throw new DomainException('Only rentals running today can be extended.');
        }
    }

    /**
     * Conflict message with a recommendation: daily gets the maximum days, monthly is pointed to daily.
     * The maximum days can be fetched again through maxExtendableDays() for the recommendation button.
     */
    private function conflictException(Rental $rental, RentalType $type): DomainException
    {
        $maxDays = $this->maxExtendableDays($rental);
        $bookingDate = $this->nextBooking($rental)->start_date->translatedFormat('d F Y');
        $code = $rental->garage->code;

        if ($maxDays === 0) {
            return new DomainException("Garage {$code} is booked from {$bookingDate}, right after this rental ends. The rental cannot be extended.");
        }

        return new DomainException($type === RentalType::Monthly
            ? "The monthly extension conflicts with a booking for garage {$code} from {$bookingDate}. Use a daily extension of at most {$maxDays} days."
            : "The extension conflicts with a booking for garage {$code} from {$bookingDate}. Maximum extension is {$maxDays} days.");
    }
}
