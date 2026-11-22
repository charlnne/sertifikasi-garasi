@extends('layouts.app')

@section('title', 'Garages')

@use('App\Enums\GarageStatus')
@use('App\Enums\GarageType')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.garages.index') }}" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code, e.g. C011"
                   class="w-48 rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm">
            <select name="type" class="rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm">
                <option value="">All types</option>
                @foreach (GarageType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (GarageStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <x-button variant="secondary">Filter</x-button>
            @if (array_filter($filters))
                <a href="{{ route('admin.garages.index') }}" class="self-center text-sm text-stone-600 hover:underline">Reset</a>
            @endif
        </form>

        <x-button variant="accent" :href="route('admin.garages.create')">+ Add Garage</x-button>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Daily Price</th>
                    <th class="px-4 py-3 text-right">Monthly Price</th>
                    <th class="px-4 py-3 text-right">Price per kWh</th>
                    <th class="px-4 py-3">Status Today</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($garages as $garage)
                    <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                        <td class="px-4 py-2 font-mono font-semibold text-maroon-800">{{ $garage->code }}</td>
                        <td class="px-4 py-2">{{ $garage->type->label() }}</td>
                        <td class="px-4 py-2 text-right">@rupiah($garage->daily_price)</td>
                        <td class="px-4 py-2 text-right">@rupiah($garage->monthly_price)</td>
                        <td class="px-4 py-2 text-right">@if ($garage->kwh_price !== null) @rupiah($garage->kwh_price) @else - @endif</td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$garage->status" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-stone-500">No matching garages.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $garages->links() }}</div>
@endsection
