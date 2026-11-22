@extends('layouts.app')

@section('title', 'Dashboard')

@use('App\Enums\GarageType')
@inject('garageRules', 'App\Services\GarageRuleService')

@section('content')
    @if ($user->isAdmin())
        <div class="mb-6 rounded-2xl bg-gradient-to-r from-maroon-900 to-maroon-700 p-6 text-white shadow-md">
            <p class="text-sm text-maroon-100">Admin panel</p>
            <p class="mt-1 text-2xl font-semibold">Hello, <span class="text-yellow-300">{{ $user->name }}</span>.</p>
        </div>

        @php
            $cards = [
                ['label' => 'Applicants awaiting verification', 'value' => $summary['pending_renters'], 'route' => route('admin.renters.index')],
                ['label' => 'Payments awaiting verification', 'value' => $summary['pending_payments'], 'route' => route('admin.payments.index')],
                ['label' => 'Active rentals today', 'value' => $summary['active_rentals_today'], 'route' => route('admin.extensions.index')],
            ];
        @endphp

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            @foreach ($cards as $card)
                <a href="{{ $card['route'] }}" class="group rounded-xl border-t-4 border-maroon-700 bg-white p-5 shadow-sm ring-1 ring-maroon-100 transition hover:-translate-y-0.5 hover:border-yellow-400 hover:shadow-md">
                    <p class="text-sm text-stone-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-4xl font-bold text-maroon-800">{{ $card['value'] }}</p>
                    <p class="mt-3 text-xs font-medium text-maroon-600 group-hover:text-maroon-800">View details &rarr;</p>
                </a>
            @endforeach
        </div>

        <h2 class="mb-3 text-lg font-semibold text-maroon-900">Garages per Type</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (GarageType::cases() as $type)
                <a href="{{ route('admin.garages.index', ['type' => $type->value]) }}" class="flex items-center justify-between rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100 transition hover:-translate-y-0.5 hover:ring-2 hover:ring-yellow-400">
                    <div>
                        <p class="text-sm text-stone-500">{{ $type->label() }} Garage</p>
                        <p class="mt-1 text-3xl font-bold text-maroon-800">{{ $summary['garages_per_type'][$type->value] }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-100 text-lg font-bold text-maroon-800">
                        {{ $garageRules->prefix($type) }}
                    </span>
                </a>
            @endforeach
        </div>
    @elseif ($user->isPending())
        <div class="rounded-2xl border-l-4 border-yellow-400 bg-white p-6 shadow-sm ring-1 ring-maroon-100">
            <p class="text-lg font-semibold text-maroon-900">Hello, {{ $user->name }}.</p>
            <p class="mt-2 text-sm text-stone-600">Your account is awaiting admin verification. Garage rental features become available once your registration is approved.</p>
        </div>
    @else
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-r from-maroon-900 to-maroon-700 p-6 text-white shadow-md">
            <div>
                <p class="text-2xl font-semibold">Hello, <span class="text-yellow-300">{{ $user->name }}</span>.</p>
                <p class="mt-1 text-sm text-maroon-100">Your account is approved, you can rent a garage.</p>
            </div>
            <x-button variant="accent" :href="route('rentals.create')">+ New Rental</x-button>
        </div>

        <h2 class="mb-3 text-lg font-semibold text-maroon-900">Active Rentals</h2>

        @if ($rentals->isEmpty())
            <div class="rounded-xl bg-white p-6 text-center text-sm text-stone-500 shadow-sm ring-1 ring-maroon-100">
                No active rentals yet. Press the New Rental button to start renting a garage.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                        <tr>
                            <th class="px-4 py-3">Garage</th>
                            <th class="px-4 py-3">Vehicle</th>
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rentals as $rental)
                            <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                                <td class="px-4 py-3 font-mono font-semibold text-maroon-800">{{ $rental->garage->code }}</td>
                                <td class="px-4 py-3">{{ $rental->vehicle->getDisplayName() }}</td>
                                <td class="px-4 py-3">
                                    {{ $rental->start_date->translatedFormat('d M Y') }} - {{ $rental->end_date->translatedFormat('d M Y') }}
                                    <span class="block text-xs text-stone-500">{{ $rental->rental_type->label() }}, {{ $rental->remaining_days }} days left</span>
                                </td>
                                <td class="px-4 py-3"><x-status-badge :status="$rental->payment_status" /></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($rental->can_charge)
                                        <a href="{{ route('chargings.create', $rental) }}" class="mr-3 inline-block rounded-lg bg-yellow-400 px-2.5 py-1 text-xs font-semibold text-maroon-900 shadow-sm hover:bg-yellow-300 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 active:scale-[0.97]">Charging</a>
                                    @elseif ($rental->charging_blocked)
                                        <span class="mr-3 inline-block cursor-not-allowed rounded-lg bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800 opacity-70"
                                              title="{{ \App\Services\ChargingService::PENDING_CHARGING_MESSAGE }}">Charging pending</span>
                                    @endif
                                    <a href="{{ route('rentals.show', $rental) }}" class="font-medium text-maroon-700 hover:underline">Details</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
