<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargingRequest;
use App\Models\Rental;
use App\Services\ChargingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingController extends Controller
{
    public function __construct(private readonly ChargingService $chargingService) {}

    public function create(Request $request, Rental $rental): View|RedirectResponse
    {
        $this->ensureOwner($request, $rental);

        if ($reason = $this->chargingService->ineligibilityReason($rental)) {
            return redirect()->route('rentals.show', $rental)->with('error', $reason);
        }

        return view('chargings.create', compact('rental'));
    }

    public function store(StoreChargingRequest $request, Rental $rental): RedirectResponse
    {
        $this->ensureOwner($request, $rental);

        $charging = $this->chargingService->create(
            $rental,
            (int) $request->validated('current_battery_percent'),
            $request->file('payment_proof'),
        );

        return redirect()->route('rentals.show', $rental)
            ->with('success', "Charging of {$charging->charged_kwh} kWh submitted. Awaiting admin payment verification.");
    }

    /**
     * Renters may only charge for rentals of their own vehicles.
     */
    private function ensureOwner(Request $request, Rental $rental): void
    {
        abort_unless($rental->vehicle->user_id === $request->user()->id, 403);
    }
}
