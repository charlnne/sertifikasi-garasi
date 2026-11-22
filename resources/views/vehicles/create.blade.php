@extends('layouts.app')

@section('title', 'Add Vehicle')

@use('App\Enums\VehicleType')

@section('content')
    <form method="POST" action="{{ route('vehicles.store') }}" class="max-w-xl rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100" id="vehicle-form">
        @csrf

        <div class="mb-4">
            <label for="type" class="mb-1 block text-sm font-medium">Vehicle Type</label>
            <select id="type" name="type" class="w-full rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm" required>
                <option value="">Select a type</option>
                @foreach (VehicleType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('type')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-input name="brand_model" label="Brand / Model" placeholder="e.g. Toyota Avanza" required />

        <div id="plate-field">
            <x-input name="plate_number" label="Plate Number" placeholder="e.g. B 1234 KLM" maxlength="15" />
        </div>

        <div id="battery-field" class="hidden">
            <x-input name="battery_capacity" label="Battery Capacity (kWh)" type="number" min="0.01" step="0.01" />
        </div>

        <div class="flex gap-2">
            <x-button>Save</x-button>
            <x-button variant="secondary" :href="route('vehicles.index')">Cancel</x-button>
        </div>
    </form>

    <script>
        const vehicleForm = document.getElementById('vehicle-form');

        function toggleVehicleFields() {
            const type = vehicleForm.type.value;

            document.getElementById('plate-field').classList.toggle('hidden', type === 'bicycle');
            document.getElementById('battery-field').classList.toggle('hidden', type !== 'electric_car');
        }

        vehicleForm.type.addEventListener('change', toggleVehicleFields);
        toggleVehicleFields();
    </script>
@endsection
