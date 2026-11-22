@extends('layouts.app')

@section('title', 'Rental History')

@section('content')
    <div class="mb-4 flex justify-end">
        <x-button variant="accent" :href="route('rentals.create')">+ New Rental</x-button>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                <tr>
                    <th class="px-4 py-3">Garage</th>
                    <th class="px-4 py-3">Vehicle</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3 text-right">Initial Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rentals as $rental)
                    <tr class="border-b border-maroon-50 last:border-0 hover:bg-maroon-50/40">
                        <td class="px-4 py-2 font-mono font-semibold text-maroon-800">{{ $rental->garage->code }}</td>
                        <td class="px-4 py-2">{{ $rental->vehicle->getDisplayName() }}</td>
                        <td class="px-4 py-2">
                            {{ $rental->rental_type->label() }}
                            @if ($rental->extension_days > 0)
                                <span class="block text-xs text-stone-500">+{{ $rental->extension_days }} extension days</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            {{ $rental->start_date->translatedFormat('d M Y') }} - {{ $rental->end_date->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-4 py-2 text-right">@rupiah($rental->initial_total)</td>
                        <td class="px-4 py-2"><x-status-badge :status="$rental->status" /></td>
                        <td class="px-4 py-2"><x-status-badge :status="$rental->payment_status" /></td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('rentals.show', $rental) }}" class="font-medium text-maroon-700 hover:underline">Details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-stone-500">No rental history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rentals->links() }}</div>
@endsection
