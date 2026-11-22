@extends('layouts.app')

@section('title', 'Additional Charging')

@section('content')
    <form method="POST" action="{{ route('chargings.store', $rental) }}" enctype="multipart/form-data" id="charging-form"
          class="max-w-xl rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
        @csrf

        <dl class="mb-4 grid grid-cols-[150px_1fr] gap-y-1 text-sm">
            <dt class="text-stone-500">Garage</dt>
            <dd class="font-mono">{{ $rental->garage->code }}</dd>
            <dt class="text-stone-500">Vehicle</dt>
            <dd>{{ $rental->vehicle->getDisplayName() }}</dd>
            <dt class="text-stone-500">Battery capacity</dt>
            <dd>{{ (float) $rental->vehicle->battery_capacity }} kWh</dd>
            <dt class="text-stone-500">Electricity price</dt>
            <dd>@rupiah($rental->garage->kwh_price) / kWh</dd>
        </dl>

        <x-input name="current_battery_percent" label="Current Battery Percentage (%)" type="number" min="0" max="99" step="1" required />
        <p class="-mt-3 mb-4 text-xs text-stone-500">The battery is charged until full. A 100% battery needs no charging.</p>

        <div class="mb-4 rounded border border-maroon-100 bg-maroon-50/60 p-4 text-sm">
            <p>Energy charged: <strong id="preview-kwh">-</strong></p>
            <p class="mt-1">Charging cost: <strong id="preview-cost">-</strong></p>
        </div>

        <x-input name="payment_proof" label="Payment Proof (jpg/png, max 2 MB)" type="file" accept="image/jpeg,image/png" required />

        <div class="flex gap-2">
            <x-button>Submit Charging</x-button>
            <x-button variant="secondary" :href="route('rentals.show', $rental)">Cancel</x-button>
        </div>
    </form>

    <script>
        const chargingForm = document.getElementById('charging-form');
        const batteryCapacity = @js((float) $rental->vehicle->battery_capacity);
        const kwhPrice = @js((float) $rental->garage->kwh_price);

        function updateChargingPreview() {
            const value = chargingForm.current_battery_percent.value;
            const percent = parseInt(value, 10);

            if (value === '' || percent < 0 || percent > 99) {
                document.getElementById('preview-kwh').textContent = '-';
                document.getElementById('preview-cost').textContent = '-';
                return;
            }

            const kwh = Math.round(batteryCapacity * (100 - percent)) / 100;
            document.getElementById('preview-kwh').textContent = kwh.toLocaleString('id-ID') + ' kWh';
            document.getElementById('preview-cost').textContent = 'Rp ' + Math.round(kwh * kwhPrice).toLocaleString('id-ID');
        }

        chargingForm.current_battery_percent.addEventListener('input', updateChargingPreview);
        updateChargingPreview();
    </script>
@endsection
