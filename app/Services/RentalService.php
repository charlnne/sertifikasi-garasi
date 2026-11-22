<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Traits\HandlesFileUpload;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * New rentals created by renters, plus their rental history.
 *
 * All date and cost calculations are repeated on the server; the page preview is only a display aid.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class RentalService
{
    use HandlesFileUpload;

    public const PAYMENT_DIRECTORY = 'payments';

    public const PER_PAGE = 10;

    public function __construct(
        private readonly RentalPeriodCalculator $periodCalculator,
        private readonly GarageAvailabilityService $availabilityService,
        private readonly GarageRuleService $garageRules,
    ) {}

    /**
     * Calculate the end date and find available garages for the renter to choose from.
     *
     * @param  Vehicle  $vehicle  Renter's vehicle.
     * @param  RentalType  $type  Rental type.
     * @param  CarbonImmutable  $startDate  Start date, today or later.
     * @param  int|null  $days  Number of days (daily rental only).
     * @param  GarageType  $garageType  Selected garage type.
     * @return array{end_date: CarbonImmutable, garages: Collection<int, Garage>}
     *
     * @throws DomainException If the date, number of days, or garage type breaks the rules.
     */
    public function searchGarages(Vehicle $vehicle, RentalType $type, CarbonImmutable $startDate, ?int $days, GarageType $garageType): array
    {
        $this->periodCalculator->validateStartDate($startDate);
        $this->ensureCompatible($vehicle, $garageType);
        $endDate = $this->periodCalculator->calculateEndDate($type, $startDate, $days);

        return [
            'end_date' => $endDate,
            'garages' => $this->availabilityService->availableGarages($garageType, $startDate, $endDate),
        ];
    }

    /**
     * Store a new rental with a pending payment status. If an electric car parks in an electric garage,
     * an initial charging is created as well and paid together with the rental.
     *
     * @param  User  $renter  Approved renter.
     * @param  array{vehicle_id: int, rental_type: string, start_date: string, days?: ?int, garage_id: int, current_battery_percent?: ?int}  $data
     * @param  UploadedFile  $paymentProof  Payment proof for the initial total.
     * @return Rental The newly created rental.
     *
     * @throws DomainException If the date, compatibility, or availability rules are broken.
     */
    public function create(User $renter, array $data, UploadedFile $paymentProof): Rental
    {
        $vehicle = $renter->vehicles()->findOrFail($data['vehicle_id']);
        $type = RentalType::from($data['rental_type']);
        $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();

        $this->periodCalculator->validateStartDate($startDate);
        $endDate = $this->periodCalculator->calculateEndDate($type, $startDate, $type === RentalType::Daily ? (int) $data['days'] : null);

        $proofPath = $this->uploadFile($paymentProof, self::PAYMENT_DIRECTORY);

        try {
            return DB::transaction(function () use ($vehicle, $type, $startDate, $endDate, $data, $proofPath) {
                // Lock the garage so two renters cannot book the same range at the same time.
                $garage = Garage::whereKey($data['garage_id'])->lockForUpdate()->firstOrFail();

                $this->ensureCompatible($vehicle, $garage->type);

                if (! $this->availabilityService->isGarageAvailable($garage, $startDate, $endDate)) {
                    throw new DomainException("Garage {$garage->code} is already taken for that date range.");
                }

                if (! $this->availabilityService->isVehicleFree($vehicle, $startDate, $endDate)) {
                    throw new DomainException("Vehicle {$vehicle->getDisplayName()} already has another rental in that date range.");
                }

                $chargesVehicle = $this->garageRules->chargesVehicle($garage->type, $vehicle->type);
                $batteryPercent = $data['current_battery_percent'] ?? null;

                if ($chargesVehicle && $batteryPercent === null) {
                    throw new DomainException('Current battery percentage is required for an electric car in an electric garage.');
                }

                $rental = Rental::create([
                    'vehicle_id' => $vehicle->id,
                    'garage_id' => $garage->id,
                    'rental_type' => $type,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'payment_proof' => $proofPath,
                    'payment_status' => PaymentStatus::Pending,
                ]);

                if ($chargesVehicle) {
                    $rental->chargings()->create([
                        'current_battery_percent' => (int) $batteryPercent,
                        'payment_proof' => null,
                        'payment_status' => PaymentStatus::Pending,
                    ]);
                }

                return $rental;
            });
        } catch (Throwable $exception) {
            $this->deleteFile($proofPath);

            throw $exception;
        }
    }

    public function paginateFor(User $renter): LengthAwarePaginator
    {
        return Rental::ownedBy($renter)
            ->with(['garage', 'vehicle', 'initialCharging'])
            ->latest('start_date')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * @throws DomainException If the vehicle type may not park in that garage type.
     */
    private function ensureCompatible(Vehicle $vehicle, GarageType $garageType): void
    {
        if (! $this->garageRules->canPark($garageType, $vehicle->type)) {
            throw new DomainException("{$vehicle->type->label()} cannot park in a {$garageType->label()} garage.");
        }
    }
}
