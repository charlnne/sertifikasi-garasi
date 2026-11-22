@extends('layouts.app')

@section('title', 'Rental Extensions')

@section('content')
    <p class="mb-4 text-sm text-stone-600">
        Rentals running today with a verified payment. Renters request extensions directly from the admin.
    </p>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                <tr>
                    <th class="px-4 py-3">Renter</th>
                    <th class="px-4 py-3">Vehicle</th>
                    <th class="px-4 py-3">Garage</th>
                    <th class="px-4 py-3">Rental Type</th>
                    <th class="px-4 py-3">Start Date</th>
                    <th class="px-4 py-3">End Date</th>
                    <th class="px-4 py-3 text-right">Days Left</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rentals as $rental)
                    <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                        <td class="px-4 py-2 font-medium">{{ $rental->vehicle->user->name }}</td>
                        <td class="px-4 py-2">{{ $rental->vehicle->getDisplayName() }}</td>
                        <td class="px-4 py-2 font-mono">{{ $rental->garage->code }}</td>
                        <td class="px-4 py-2">
                            {{ $rental->rental_type->label() }}
                            @if ($rental->extension_days > 0)
                                <span class="block text-xs text-stone-500">+{{ $rental->extension_days }} extension days</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $rental->start_date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $rental->end_date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-2 text-right">{{ $rental->remaining_days }} days</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.extensions.create', $rental) }}" class="font-semibold text-maroon-700 hover:underline">Extend</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-stone-500">No rentals are running today.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
