@extends('layouts.app')

@section('title', 'Payment Verification')

@use('App\Enums\RentalType')

@section('content')
    <h2 class="mb-3 font-semibold">New Rentals ({{ $rentals->count() }})</h2>

    @if ($rentals->isEmpty())
        <div class="mb-8 rounded-xl bg-white p-6 text-center text-sm text-stone-500 shadow-sm ring-1 ring-maroon-100">No rental payments are awaiting verification.</div>
    @endif

    <div class="mb-8 space-y-4">
        @foreach ($rentals as $rental)
            @php($renter = $rental->vehicle->user)
            <div class="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100 md:grid-cols-[200px_1fr_1fr]">
                <a href="{{ asset('storage/'.$rental->payment_proof) }}" target="_blank" class="block">
                    <img src="{{ asset('storage/'.$rental->payment_proof) }}" alt="Rental payment proof {{ $rental->garage->code }}"
                         class="h-36 w-full rounded border border-maroon-100 bg-maroon-50/60 object-contain">
                    <span class="mt-1 block text-center text-xs text-stone-500">Click to enlarge</span>
                </a>

                <dl class="grid grid-cols-[110px_1fr] content-start gap-y-1 text-sm">
                    <dt class="text-stone-500">Renter</dt>
                    <dd class="font-medium">{{ $renter->name }}</dd>
                    <dt class="text-stone-500">NIK</dt>
                    <dd class="font-mono">{{ $renter->nik }}</dd>
                    <dt class="text-stone-500">Phone</dt>
                    <dd>{{ $renter->phone }}</dd>
                    <dt class="text-stone-500">Vehicle</dt>
                    <dd>{{ $rental->vehicle->getDisplayName() }}</dd>
                    <dt class="text-stone-500">Garage</dt>
                    <dd class="font-mono">{{ $rental->garage->code }}</dd>
                    <dt class="text-stone-500">Period</dt>
                    <dd>
                        {{ $rental->rental_type->label() }},
                        {{ $rental->start_date->translatedFormat('d M Y') }} - {{ $rental->end_date->translatedFormat('d M Y') }}
                    </dd>
                </dl>

                <div class="text-sm">
                    <dl class="grid grid-cols-2 gap-y-1">
                        <dt>
                            Rent cost
                            <span class="block text-xs text-stone-500">
                                @if ($rental->rental_type === RentalType::Monthly)
                                    25 x @rupiah($rental->garage->daily_price)
                                @else
                                    {{ $rental->base_days }} days x @rupiah($rental->garage->daily_price)
                                @endif
                            </span>
                        </dt>
                        <dd class="text-right">@rupiah($rental->rent_cost)</dd>
                        <dt>
                            Initial charging
                            @if ($rental->initialCharging)
                                <span class="block text-xs text-stone-500">
                                    Battery {{ $rental->initialCharging->current_battery_percent }}%, {{ $rental->initialCharging->charged_kwh }} kWh
                                </span>
                            @endif
                        </dt>
                        <dd class="text-right">@rupiah($rental->initial_charging_cost)</dd>
                        <dt class="border-t border-maroon-100 pt-1 font-semibold">Total</dt>
                        <dd class="border-t border-maroon-100 pt-1 text-right font-semibold">@rupiah($rental->initial_total)</dd>
                    </dl>

                    <div class="mt-4 flex justify-end gap-2">
                        <form method="POST" action="{{ route('admin.payments.rentals.verify', $rental) }}"
                              onsubmit="return confirm(@js("Verify the rental payment for {$rental->garage->code} by {$renter->name}?"))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="success">Verify</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.rentals.reject', $rental) }}"
                              onsubmit="return confirm(@js("Reject the rental payment for {$rental->garage->code}? The rental will be cancelled and this decision cannot be changed."))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="danger">Reject</x-button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="mb-3 font-semibold">Additional Charging ({{ $chargings->count() }})</h2>

    @if ($chargings->isEmpty())
        <div class="rounded-xl bg-white p-6 text-center text-sm text-stone-500 shadow-sm ring-1 ring-maroon-100">No charging payments are awaiting verification.</div>
    @endif

    <div class="space-y-4">
        @foreach ($chargings as $charging)
            @php($renter = $charging->rental->vehicle->user)
            <div class="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100 md:grid-cols-[200px_1fr_1fr]">
                <a href="{{ asset('storage/'.$charging->payment_proof) }}" target="_blank" class="block">
                    <img src="{{ asset('storage/'.$charging->payment_proof) }}" alt="Charging payment proof"
                         class="h-36 w-full rounded border border-maroon-100 bg-maroon-50/60 object-contain">
                    <span class="mt-1 block text-center text-xs text-stone-500">Click to enlarge</span>
                </a>

                <dl class="grid grid-cols-[110px_1fr] content-start gap-y-1 text-sm">
                    <dt class="text-stone-500">Renter</dt>
                    <dd class="font-medium">{{ $renter->name }}</dd>
                    <dt class="text-stone-500">Phone</dt>
                    <dd>{{ $renter->phone }}</dd>
                    <dt class="text-stone-500">Vehicle</dt>
                    <dd>{{ $charging->rental->vehicle->getDisplayName() }}</dd>
                    <dt class="text-stone-500">Garage</dt>
                    <dd class="font-mono">{{ $charging->rental->garage->code }}</dd>
                    <dt class="text-stone-500">Submitted</dt>
                    <dd>{{ $charging->created_at->translatedFormat('d M Y H:i') }}</dd>
                </dl>

                <div class="text-sm">
                    <dl class="grid grid-cols-2 gap-y-1">
                        <dt>Current battery</dt>
                        <dd class="text-right">{{ $charging->current_battery_percent }}%</dd>
                        <dt>Charged</dt>
                        <dd class="text-right">{{ $charging->charged_kwh }} kWh x @rupiah($charging->rental->garage->kwh_price)</dd>
                        <dt class="border-t border-maroon-100 pt-1 font-semibold">Charging cost</dt>
                        <dd class="border-t border-maroon-100 pt-1 text-right font-semibold">@rupiah($charging->cost)</dd>
                    </dl>

                    <div class="mt-4 flex justify-end gap-2">
                        <form method="POST" action="{{ route('admin.payments.chargings.verify', $charging) }}"
                              onsubmit="return confirm(@js("Verify the charging payment by {$renter->name}?"))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="success">Verify</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.chargings.reject', $charging) }}"
                              onsubmit="return confirm(@js('Reject this charging payment? This decision cannot be changed.'))">
                            @csrf
                            @method('PATCH')
                            <x-button variant="danger">Reject</x-button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
