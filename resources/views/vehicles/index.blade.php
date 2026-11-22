@extends('layouts.app')

@section('title', 'My Vehicles')

@section('content')
    <div class="mb-4 flex justify-end">
        <x-button variant="accent" :href="route('vehicles.create')">+ Add Vehicle</x-button>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                <tr>
                    <th class="px-4 py-3">Brand / Model</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Plate Number</th>
                    <th class="px-4 py-3 text-right">Battery Capacity</th>
                    <th class="px-4 py-3 text-right">Rentals</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicles as $vehicle)
                    <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                        <td class="px-4 py-2 font-medium">{{ $vehicle->brand_model }}</td>
                        <td class="px-4 py-2">{{ $vehicle->type->label() }}</td>
                        <td class="px-4 py-2 font-mono">{{ $vehicle->plate_number ?? '-' }}</td>
                        <td class="px-4 py-2 text-right">{{ $vehicle->battery_capacity !== null ? rtrim(rtrim($vehicle->battery_capacity, '0'), '.').' kWh' : '-' }}</td>
                        <td class="px-4 py-2 text-right">{{ $vehicle->rentals_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">No vehicles yet. Add a vehicle before renting a garage.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
