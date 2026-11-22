<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRentalGarageRequest;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Rental;
use App\Services\ChargingService;
use App\Services\GarageRuleService;
use App\Services\RentalService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rentalService) {}

    public function index(Request $request): View
    {
        $rentals = $this->rentalService->paginateFor($request->user());

        return view('rentals.index', compact('rentals'));
    }

    public function create(SearchRentalGarageRequest $request): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('brand_model')->get();
        $search = null;
        $searchError = null;

        if ($request->isSearching()) {
            $vehicle = $vehicles->firstWhere('id', (int) $request->validated('vehicle_id'));

            try {
                $search = $this->rentalService->searchGarages(
                    $vehicle,
                    $request->rentalType(),
                    $request->startDate(),
                    $request->days(),
                    $request->garageType(),
                ) + [
                    'vehicle' => $vehicle,
                    'charges_vehicle' => app(GarageRuleService::class)->chargesVehicle($request->garageType(), $vehicle->type),
                ];
            } catch (DomainException $exception) {
                $searchError = $exception->getMessage();
            }
        }

        return view('rentals.create', compact('vehicles', 'search', 'searchError'));
    }

    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $rental = $this->rentalService->create(
            $request->user(),
            $request->safe()->except('payment_proof'),
            $request->file('payment_proof'),
        );

        return redirect()->route('rentals.show', $rental)
            ->with('success', "Rental for garage {$rental->garage->code} submitted. Awaiting admin payment verification.");
    }

    public function show(Request $request, Rental $rental, ChargingService $chargingService): View
    {
        // Renters may only view rentals for their own vehicles.
        abort_unless($rental->vehicle->user_id === $request->user()->id, 403);

        $rental->load(['garage', 'vehicle', 'chargings']);
        $canCharge = $chargingService->isEligible($rental);
        $chargingBlocked = $chargingService->isBlockedByPendingCharging($rental);
        $pendingChargingMessage = ChargingService::PENDING_CHARGING_MESSAGE;

        return view('rentals.show', compact('rental', 'canCharge', 'chargingBlocked', 'pendingChargingMessage'));
    }
}
