<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Summary figures for the admin dashboard and the active rental list for the renter dashboard.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class DashboardService
{
    public function __construct(
        private readonly RenterVerificationService $renterVerificationService,
        private readonly PaymentVerificationService $paymentVerificationService,
        private readonly RentalExtensionService $extensionService,
        private readonly ChargingService $chargingService,
    ) {}

    /**
     * Figures for the admin dashboard cards.
     *
     * Active rentals today only counts verified rentals that cover today, matching
     * the list on the extension page.
     *
     * @return array{pending_renters: int, pending_payments: int, active_rentals_today: int, garages_per_type: array<string, int>}
     */
    public function adminSummary(): array
    {
        $garageCounts = Garage::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return [
            'pending_renters' => $this->renterVerificationService->countPending(),
            'pending_payments' => $this->paymentVerificationService->countPending(),
            'active_rentals_today' => $this->extensionService->activeRentalsToday()->count(),
            'garages_per_type' => collect(GarageType::cases())
                ->mapWithKeys(fn (GarageType $type) => [$type->value => (int) ($garageCounts[$type->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * The renter's rentals that are still active (not finished and not cancelled), including upcoming ones.
     * Each rental gets can_charge and charging_blocked flags for the additional charging button.
     *
     * @return Collection<int, Rental>
     */
    public function activeRentalsFor(User $renter): Collection
    {
        return Rental::ownedBy($renter)
            ->notCancelled()
            ->whereDate('end_date', '>=', today()->toDateString())
            ->with(['garage', 'vehicle'])
            ->orderBy('start_date')
            ->get()
            ->each(function (Rental $rental) {
                $reason = $this->chargingService->ineligibilityReason($rental);

                $rental->setAttribute('can_charge', $reason === null);
                $rental->setAttribute('charging_blocked', $reason === ChargingService::PENDING_CHARGING_MESSAGE);
            });
    }
}
