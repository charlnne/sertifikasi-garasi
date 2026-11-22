<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGarageRequest;
use App\Services\GarageRuleService;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GarageController extends Controller
{
    public function __construct(private readonly GarageService $garageService) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'status', 'search']);
        $garages = $this->garageService->paginate($filters);

        return view('admin.garages.index', compact('garages', 'filters'));
    }

    public function create(): View
    {
        $summary = $this->garageService->summaryByType();
        $defaultKwhPrice = GarageRuleService::DEFAULT_KWH_PRICE;

        return view('admin.garages.create', compact('summary', 'defaultKwhPrice'));
    }

    public function store(StoreGarageRequest $request): RedirectResponse
    {
        $type = GarageType::from($request->validated('type'));

        $garages = $this->garageService->createBulk(
            $type,
            (int) $request->validated('count'),
            (float) $request->validated('daily_price'),
            $request->validated('kwh_price') !== null ? (float) $request->validated('kwh_price') : null,
        );

        $range = $garages->count() === 1
            ? $garages->first()->code
            : $garages->first()->code.' - '.$garages->last()->code;

        return redirect()->route('admin.garages.index', ['type' => $type->value])
            ->with('success', "{$garages->count()} {$type->label()} ".Str::plural('garage', $garages->count())." created ({$range}).");
    }
}
