<?php

namespace App\Services;

use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Management of renters' vehicles. Vehicles can only be added and viewed.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class VehicleService
{
    /**
     * @return Collection<int, Vehicle>
     */
    public function listFor(User $renter): Collection
    {
        return $renter->vehicles()->withCount('rentals')->latest()->get();
    }

    /**
     * Store a new vehicle. The plate is ignored for bicycles and battery capacity only applies to electric cars.
     *
     * @param  array{type: string, plate_number?: ?string, brand_model: string, battery_capacity?: ?string}  $data
     */
    public function create(User $renter, array $data): Vehicle
    {
        $type = VehicleType::from($data['type']);

        return $renter->vehicles()->create([
            'type' => $type,
            'plate_number' => $type->requiresPlateNumber() ? $data['plate_number'] : null,
            'brand_model' => $data['brand_model'],
            'battery_capacity' => $type->hasBattery() ? $data['battery_capacity'] : null,
        ]);
    }
}
