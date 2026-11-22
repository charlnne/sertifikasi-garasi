@extends('layouts.app')

@section('title', 'Rental Details '.$rental->garage->code)

@use('App\Enums\PaymentStatus')

@section('content')
    @if ($rental->payment_status === PaymentStatus::Rejected)
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            The payment for this rental was rejected, so the rental is cancelled. Please contact the admin for more information.
        </div>
    @elseif ($rental->payment_status === PaymentStatus::Pending)
        <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
            The payment proof is being reviewed by the admin.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
            <h2 class="mb-3 font-semibold">Rental Information</h2>
            <dl class="grid grid-cols-[150px_1fr] gap-y-1 text-sm">
                <dt class="text-stone-500">Garage</dt>
                <dd class="font-mono font-medium">{{ $rental->garage->code }} ({{ $rental->garage->type->label() }})</dd>
                <dt class="text-stone-500">Vehicle</dt>
                <dd>{{ $rental->vehicle->getDisplayName() }} - {{ $rental->vehicle->type->label() }}</dd>
                <dt class="text-stone-500">Rental type</dt>
                <dd>{{ $rental->rental_type->label() }}</dd>
                <dt class="text-stone-500">Start date</dt>
                <dd>{{ $rental->start_date->translatedFormat('d F Y') }}</dd>
                <dt class="text-stone-500">End date</dt>
                <dd>{{ $rental->end_date->translatedFormat('d F Y') }} ({{ $rental->total_days }} days)</dd>
                <dt class="text-stone-500">Status</dt>
                <dd><x-status-badge :status="$rental->status" /></dd>
                <dt class="text-stone-500">Payment</dt>
                <dd><x-status-badge :status="$rental->payment_status" /></dd>
            </dl>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
            <h2 class="mb-3 font-semibold">Cost Breakdown</h2>
            <dl class="grid grid-cols-2 gap-y-1 text-sm">
                <dt>
                    Rent cost
                    <span class="block text-xs text-stone-500">
                        @if ($rental->rental_type === \App\Enums\RentalType::Monthly)
                            25 x @rupiah($rental->garage->daily_price)
                        @else
                            {{ $rental->base_days }} days x @rupiah($rental->garage->daily_price)
                        @endif
                    </span>
                </dt>
                <dd class="text-right">@rupiah($rental->rent_cost)</dd>
                <dt>Initial charging cost</dt>
                <dd class="text-right">@rupiah($rental->initial_charging_cost)</dd>
                <dt class="border-t border-maroon-100 pt-1 font-semibold">Initial payment total</dt>
                <dd class="border-t border-maroon-100 pt-1 text-right font-semibold">@rupiah($rental->initial_total)</dd>
                @if ($rental->extension_days > 0)
                    <dt class="pt-3">
                        Extension
                        <span class="block text-xs text-stone-500">Total {{ $rental->extension_days }} additional days</span>
                    </dt>
                    <dd class="pt-3 text-right">@rupiah($rental->extension_cost)</dd>
                @endif
            </dl>
            <a href="{{ asset('storage/'.$rental->payment_proof) }}" target="_blank" class="mt-4 inline-block text-sm text-stone-700 underline">
                View payment proof
            </a>
        </div>
    </div>

    @if ($rental->chargings->isNotEmpty())
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
            <h2 class="mb-3 font-semibold">Charging History</h2>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                    <tr>
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2 text-right">Battery</th>
                        <th class="px-3 py-2 text-right normal-case">kWh</th>
                        <th class="px-3 py-2 text-right">Cost</th>
                        <th class="px-3 py-2 pl-6">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rental->chargings->sortBy('created_at') as $charging)
                        <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                            <td class="px-3 py-2">{{ $charging->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $charging->isInitial() ? 'Initial charging' : 'Additional charging' }}</td>
                            <td class="px-3 py-2 text-right">{{ $charging->current_battery_percent }}%</td>
                            <td class="px-3 py-2 text-right">{{ $charging->charged_kwh }}</td>
                            <td class="px-3 py-2 text-right">@rupiah($charging->cost)</td>
                            <td class="px-3 py-2 pl-6"><x-status-badge :status="$charging->payment_status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 flex gap-2">
        <x-button variant="secondary" :href="route('rentals.index')">Back to history</x-button>
        @if ($canCharge)
            <x-button variant="accent" :href="route('chargings.create', $rental)">Additional Charging</x-button>
        @elseif ($chargingBlocked)
            <x-button type="button" variant="accent" disabled title="{{ $pendingChargingMessage }}">Additional Charging</x-button>
        @endif
    </div>

    @if ($chargingBlocked)
        <p class="mt-2 text-sm text-yellow-800">{{ $pendingChargingMessage }}</p>
    @endif
@endsection
