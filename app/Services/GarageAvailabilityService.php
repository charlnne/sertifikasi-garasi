<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checks garage and vehicle availability for a date range.
 *
 * Ranges overlap when existing.start_date <= end AND existing.end_date >= start (inclusive dates).
 * Rentals with a rejected payment are ignored, so they never block.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class GarageAvailabilityService
{
    /**
     * Garages of a type that are free for the whole date range.
     *
     * @return Collection<int, Garage>
     */
    public function availableGarages(GarageType $type, CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return Garage::ofType($type)
            ->whereDoesntHave('rentals', fn (Builder $rentals) => $rentals->notCancelled()->overlapping($startDate, $endDate))
            ->orderBy('number')
            ->get();
    }

    /**
     * @param  int|null  $ignoreRentalId  Rental excluded from the check, used for extensions.
     */
    public function isGarageAvailable(Garage $garage, CarbonInterface $startDate, CarbonInterface $endDate, ?int $ignoreRentalId = null): bool
    {
        return ! $this->conflictingRentals(Rental::where('garage_id', $garage->id), $startDate, $endDate, $ignoreRentalId)->exists();
    }

    /**
     * A vehicle may not have two active rentals with overlapping ranges.
     *
     * @param  int|null  $ignoreRentalId  Rental excluded from the check, used for extensions.
     */
    public function isVehicleFree(Vehicle $vehicle, CarbonInterface $startDate, CarbonInterface $endDate, ?int $ignoreRentalId = null): bool
    {
        return ! $this->conflictingRentals(Rental::where('vehicle_id', $vehicle->id), $startDate, $endDate, $ignoreRentalId)->exists();
    }

    private function conflictingRentals(Builder $query, CarbonInterface $startDate, CarbonInterface $endDate, ?int $ignoreRentalId): Builder
    {
        return $query->notCancelled()
            ->overlapping($startDate, $endDate)
            ->when($ignoreRentalId, fn (Builder $rentals) => $rentals->whereKeyNot($ignoreRentalId));
    }
}
