@extends('layouts.app')

@section('title', 'Add Garage')

@inject('garageRules', 'App\Services\GarageRuleService')
@use('App\Enums\GarageType')

@php
    $types = collect(GarageType::cases())->mapWithKeys(fn (GarageType $type) => [$type->value => [
        'label' => $type->label(),
        'prefix' => $garageRules->prefix($type),
        'hasCharger' => $garageRules->hasCharger($type),
        'lastNumber' => $summary[$type->value]['last_number'],
        'remaining' => $summary[$type->value]['remaining'],
    ]]);
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('admin.garages.store') }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100" id="garage-form">
            @csrf

            <p class="mb-2 text-sm font-medium">Garage Type</p>
            <div class="mb-4 grid gap-2 sm:grid-cols-3">
                @foreach (GarageType::cases() as $type)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm hover:-translate-y-0.5 hover:border-maroon-300 hover:shadow-md has-[:checked]:border-maroon-600 has-[:checked]:bg-maroon-50 has-[:checked]:ring-2 has-[:checked]:ring-yellow-400">
                        <input type="radio" name="type" value="{{ $type->value }}" @checked(old('type') === $type->value)>
                        {{ $type->label() }}
                        <span class="ml-auto text-xs text-stone-500">{{ $types[$type->value]['prefix'] }}</span>
                    </label>
                @endforeach
            </div>
            @error('type')
                <p class="-mt-2 mb-4 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div id="garage-fields" class="{{ old('type') ? '' : 'hidden' }}">
                <x-input name="count" label="Garage Count" type="number" min="1" step="1" />
                <p id="quota-info" class="-mt-3 mb-4 text-xs text-stone-500"></p>

                <x-input name="daily_price" label="Daily Price (Rp)" type="number" min="1" step="1" />

                <div id="kwh-field" class="hidden">
                    <x-input name="kwh_price" label="Price per kWh (Rp)" type="number" min="1" step="1" :value="$defaultKwhPrice" />
                </div>

                <div class="mb-4 rounded border border-maroon-100 bg-maroon-50/60 p-3 text-sm">
                    <p>Codes to be created: <strong id="preview-codes">-</strong></p>
                    <p class="mt-1">Monthly price (25 x daily): <strong id="preview-monthly">-</strong></p>
                </div>

                <div class="flex gap-2">
                    <x-button>Save</x-button>
                    <x-button variant="secondary" :href="route('admin.garages.index')">Cancel</x-button>
                </div>
            </div>

            <p id="choose-type-hint" class="text-sm text-stone-500 {{ old('type') ? 'hidden' : '' }}">Select a garage type first.</p>
        </form>

        <aside class="space-y-3">
            @foreach (GarageType::cases() as $type)
                @php($row = $summary[$type->value])
                <div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-maroon-100">
                    <p class="mb-2 font-semibold">{{ $type->label() }} Garage</p>
                    <dl class="grid grid-cols-2 gap-y-1">
                        <dt class="text-stone-500">Total</dt>
                        <dd class="text-right">{{ $row['total'] }}</dd>
                        <dt class="text-stone-500">Available today</dt>
                        <dd class="text-right font-medium text-emerald-700">{{ $row['available'] }}</dd>
                        <dt class="text-stone-500">In use</dt>
                        <dd class="text-right font-medium text-maroon-700">{{ $row['used'] }}</dd>
                        <dt class="text-stone-500">Booked</dt>
                        <dd class="text-right text-yellow-700">{{ $row['booked'] }}</dd>
                        <dt class="text-stone-500">Remaining quota</dt>
                        <dd class="text-right font-medium">{{ $row['remaining'] }}</dd>
                    </dl>
                </div>
            @endforeach
        </aside>
    </div>

    <script>
        const garageTypes = @js($types);
        const monthlyMultiplier = @js(\App\Services\RentalCostCalculator::MONTHLY_PRICE_MULTIPLIER);
        const form = document.getElementById('garage-form');
        const rupiah = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');
        const formatCode = (prefix, number) => prefix + String(number).padStart(3, '0');
        const garagesWord = (count) => count === 1 ? 'garage' : 'garages';

        function updatePreview() {
            const selected = form.querySelector('input[name="type"]:checked');

            document.getElementById('garage-fields').classList.toggle('hidden', !selected);
            document.getElementById('choose-type-hint').classList.toggle('hidden', !!selected);

            if (!selected) {
                return;
            }

            const type = garageTypes[selected.value];
            const count = parseInt(form.count.value, 10);
            const dailyPrice = parseFloat(form.daily_price.value);

            document.getElementById('kwh-field').classList.toggle('hidden', !type.hasCharger);
            form.kwh_price.disabled = !type.hasCharger;
            form.count.max = type.remaining;
            document.getElementById('quota-info').textContent =
                'Remaining ' + type.label.toLowerCase() + ' quota: ' + type.remaining + ' ' + garagesWord(type.remaining) + '.';

            let codes = '-';
            if (count > 0 && count <= type.remaining) {
                const first = formatCode(type.prefix, type.lastNumber + 1);
                const last = formatCode(type.prefix, type.lastNumber + count);
                codes = count === 1 ? first : first + ' – ' + last;
            } else if (count > type.remaining) {
                codes = 'Only ' + type.remaining + ' more ' + type.label.toLowerCase() + ' ' + garagesWord(type.remaining) + ' can be added (maximum 999).';
            }

            document.getElementById('preview-codes').textContent = codes;
            document.getElementById('preview-monthly').textContent =
                dailyPrice > 0 ? rupiah(dailyPrice * monthlyMultiplier) : '-';
        }

        form.addEventListener('input', updatePreview);
        updatePreview();
    </script>
@endsection
