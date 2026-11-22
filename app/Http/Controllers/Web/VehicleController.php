<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicleService) {}

    public function index(Request $request): View
    {
        $vehicles = $this->vehicleService->listFor($request->user());

        return view('vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        return view('vehicles.create');
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = $this->vehicleService->create($request->user(), $request->validated());

        return redirect()->route('vehicles.index')
            ->with('success', "Vehicle {$vehicle->getDisplayName()} added.");
    }
}
