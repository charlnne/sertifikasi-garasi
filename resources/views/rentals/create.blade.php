@extends('layouts.app')

@section('title', 'New Rental')

@inject('garageRules', 'App\Services\GarageRuleService')
@use('App\Enums\GarageType')
@use('App\Enums\RentalType')
@use('App\Services\RentalCostCalculator')

@php
    $vehicleData = $vehicles->mapWithKeys(fn ($vehicle) => [$vehicle->id => [
        'garageTypes' => array_map(fn ($type) => $type->value, $garageRules->garageTypesFor($vehicle->type)),
    ]]);
    $garageTypeLabels = collect(GarageType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()]);
    $value = fn (string $field, $default = null) => old($field, request($field, $default));
@endphp

@section('content')
    @if ($vehicles->isEmpty())
        <div class="rounded-lg bg-white p-6 text-sm shadow-sm">
            You don't have any vehicles yet.
            <a href="{{ route('vehicles.create') }}" class="font-medium font-medium text-maroon-700 underline">Add a vehicle</a>
            before renting a garage.
        </div>
    @else
        <form method="GET" action="{{ route('rentals.create') }}" id="search-form" class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
            <h2 class="mb-4 font-semibold">1. Choose a vehicle and dates</h2>

            <div class="grid gap-x-4 md:grid-cols-2">
                <div class="mb-4">
                    <label for="vehicle_id" class="mb-1 block text-sm font-medium">Vehicle</label>
                    <select id="vehicle_id" name="vehicle_id" class="w-full rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm" required>
                        <option value="">Select a vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) $value('vehicle_id') === (string) $vehicle->id)>
                                {{ $vehicle->getDisplayName() }} - {{ $vehicle->type->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <a href="{{ route('vehicles.create') }}" class="mt-1 inline-block text-xs text-stone-500 underline">Add a new vehicle</a>
                </div>

                <div class="mb-4">
                    <label for="garage_type" class="mb-1 block text-sm font-medium">Garage Type</label>
                    <select id="garage_type" name="garage_type" class="w-full rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm" required
                            data-selected="{{ $value('garage_type') }}">
                        <option value="">Select a vehicle first</option>
                    </select>
                    @error('garage_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <span class="mb-1 block text-sm font-medium">Rental Type</span>
                    <div class="flex gap-4 text-sm">
                        @foreach (RentalType::cases() as $type)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rental_type" value="{{ $type->value }}"
                                       @checked($value('rental_type', RentalType::Daily->value) === $type->value)>
                                {{ $type->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('rental_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-input name="start_date" label="Start Date" type="date" :min="today()->toDateString()"
                         :value="$value('start_date', today()->toDateString())" required />

                <div id="days-field">
                    <x-input name="days" label="Number of Days" type="number" min="1" step="1" :value="$value('days', 1)" />
                    <p id="days-info" class="-mt-3 mb-4 text-xs text-stone-500"></p>
                </div>

                <div class="mb-4">
                    <label for="end_date_preview" class="mb-1 block text-sm font-medium">End Date</label>
                    <input id="end_date_preview" type="text" readonly
                           class="w-full rounded border border-maroon-100 bg-maroon-50/60 px-3 py-2 text-sm text-stone-600">
                    <p class="mt-1 text-xs text-stone-500">Last day the garage is used (included).</p>
                </div>
            </div>

            <x-button>Show Available Garages</x-button>
        </form>

        @if ($searchError)
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $searchError }}</div>
        @endif

        @if ($search)
            @php($garageType = GarageType::from(request('garage_type')))
            <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data" id="rental-form"
                  class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100">
                @csrf
                @foreach (['vehicle_id', 'rental_type', 'start_date', 'days'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ request($field) }}">
                @endforeach

                <h2 class="mb-1 font-semibold">2. Choose an available {{ strtolower($garageType->label()) }} garage</h2>
                <p class="mb-4 text-sm text-stone-500">
                    {{ $search['vehicle']->getDisplayName() }},
                    {{ \Illuminate\Support\Carbon::parse(request('start_date'))->translatedFormat('d F Y') }}
                    to {{ $search['end_date']->translatedFormat('d F Y') }}
                    ({{ RentalType::from(request('rental_type'))->label() }})
                </p>

                @if ($search['garages']->isEmpty())
                    <p class="rounded border border-maroon-100 bg-maroon-50/60 p-4 text-sm text-stone-600">
                        No {{ strtolower($garageType->label()) }} garages are free for this date range.
                        Try changing the dates or the garage type.
                    </p>
                @else
                    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($search['garages'] as $garage)
                            <label class="cursor-pointer rounded-lg border border-stone-300 bg-white p-3 text-sm shadow-sm hover:-translate-y-0.5 hover:border-maroon-300 hover:shadow-md has-[:checked]:border-maroon-600 has-[:checked]:bg-maroon-50 has-[:checked]:ring-2 has-[:checked]:ring-yellow-400">
                                <input type="radio" name="garage_id" value="{{ $garage->id }}" class="mr-1"
                                       data-daily-price="{{ $garage->daily_price }}" data-kwh-price="{{ $garage->kwh_price }}"
                                       @checked((string) old('garage_id') === (string) $garage->id)>
                                <span class="font-mono font-semibold">{{ $garage->code }}</span>
                                <span class="mt-1 block text-xs text-stone-600">Daily @rupiah($garage->daily_price)</span>
                                <span class="block text-xs text-stone-600">Monthly @rupiah($garage->monthly_price)</span>
                                @if ($garage->kwh_price !== null)
                                    <span class="block text-xs text-stone-600">Electricity @rupiah($garage->kwh_price)/kWh</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('garage_id')
                        <p class="-mt-2 mb-4 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="grid gap-x-4 md:grid-cols-2">
                        @if ($search['charges_vehicle'])
                            <div>
                                <x-input name="current_battery_percent" label="Current Battery Percentage (%)" type="number"
                                         min="0" max="100" step="1" required />
                                <p class="-mt-3 mb-4 text-xs text-stone-500">
                                    Battery capacity {{ (float) $search['vehicle']->battery_capacity }} kWh, charged until full.
                                </p>
                            </div>
                        @endif

                        <x-input name="payment_proof" label="Payment Proof (jpg/png, max 2 MB)" type="file"
                                 accept="image/jpeg,image/png" required />
                    </div>

                    <div class="mb-4 rounded border border-maroon-100 bg-maroon-50/60 p-4 text-sm">
                        <p class="mb-2 font-medium">Cost Summary</p>
                        <dl class="grid max-w-sm grid-cols-2 gap-y-1">
                            <dt>Rent cost</dt>
                            <dd class="text-right" id="cost-rent">-</dd>
                            <dt>Charging cost</dt>
                            <dd class="text-right" id="cost-charging">-</dd>
                            <dt class="border-t border-stone-300 pt-1 font-semibold">Total</dt>
                            <dd class="border-t border-stone-300 pt-1 text-right font-semibold" id="cost-total">-</dd>
                        </dl>
                        <p class="mt-2 text-xs text-stone-500">The total is recalculated by the system when saved.</p>
                    </div>

                    <x-button>Submit Rental</x-button>
                @endif
            </form>

            <script>
                const rentalForm = document.getElementById('rental-form');
                const rentalPreview = {
                    type: @js(request('rental_type')),
                    days: @js((int) request('days')),
                    monthlyMultiplier: @js(RentalCostCalculator::MONTHLY_PRICE_MULTIPLIER),
                    batteryCapacity: @js($search['charges_vehicle'] ? (float) $search['vehicle']->battery_capacity : null),
                };

                function updateCostSummary() {
                    const garage = rentalForm.querySelector('input[name="garage_id"]:checked');
                    const rupiah = (value) => 'Rp ' + Math.round(value).toLocaleString('id-ID');

                    if (!garage) {
                        return;
                    }

                    const dailyPrice = parseFloat(garage.dataset.dailyPrice);
                    const rent = rentalPreview.type === 'monthly'
                        ? rentalPreview.monthlyMultiplier * dailyPrice
                        : rentalPreview.days * dailyPrice;

                    let charging = 0;
                    const percentInput = rentalForm.current_battery_percent;
                    if (rentalPreview.batteryCapacity && percentInput && percentInput.value !== '') {
                        const percent = Math.min(100, Math.max(0, parseInt(percentInput.value, 10)));
                        const kwh = rentalPreview.batteryCapacity * (100 - percent) / 100;
                        charging = kwh * parseFloat(garage.dataset.kwhPrice);
                    }

                    document.getElementById('cost-rent').textContent = rupiah(rent);
                    document.getElementById('cost-charging').textContent = rupiah(charging);
                    document.getElementById('cost-total').textContent = rupiah(rent + charging);
                }

                if (rentalForm) {
                    rentalForm.addEventListener('input', updateCostSummary);
                    rentalForm.addEventListener('change', updateCostSummary);
                    updateCostSummary();
                }
            </script>
        @endif

        <script>
            const searchForm = document.getElementById('search-form');
            const vehicleData = @js($vehicleData);
            const garageTypeLabels = @js($garageTypeLabels);
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            function fillGarageTypes() {
                const select = searchForm.garage_type;
                const vehicle = vehicleData[searchForm.vehicle_id.value];
                const selected = select.value || select.dataset.selected;

                select.innerHTML = '';
                if (!vehicle) {
                    select.add(new Option('Select a vehicle first', ''));
                    return;
                }

                vehicle.garageTypes.forEach((type) => {
                    select.add(new Option(garageTypeLabels[type], type, false, type === selected));
                });
            }

            function updateEndDate() {
                const type = searchForm.querySelector('input[name="rental_type"]:checked')?.value;
                const parts = searchForm.start_date.value.split('-').map(Number);
                const output = document.getElementById('end_date_preview');

                document.getElementById('days-field').classList.toggle('hidden', type === 'monthly');
                searchForm.days.disabled = type === 'monthly';

                if (parts.length !== 3 || !parts[0]) {
                    output.value = '';
                    return;
                }

                const [year, month, day] = parts;
                const daysInMonth = new Date(year, month, 0).getDate();
                const maxDays = daysInMonth - 1;
                document.getElementById('days-info').textContent = 'Maximum ' + maxDays + ' days for a start date in this month.';
                searchForm.days.max = maxDays;

                const totalDays = type === 'monthly' ? daysInMonth : parseInt(searchForm.days.value, 10);
                if (!totalDays || totalDays < 1 || (type !== 'monthly' && totalDays > maxDays)) {
                    output.value = '';
                    return;
                }

                const end = new Date(year, month - 1, day + totalDays - 1);
                output.value = end.getDate() + ' ' + monthNames[end.getMonth()] + ' ' + end.getFullYear()
                    + ' (' + totalDays + (totalDays === 1 ? ' day)' : ' days)');
            }

            searchForm.vehicle_id.addEventListener('change', fillGarageTypes);
            searchForm.addEventListener('input', updateEndDate);
            searchForm.addEventListener('change', updateEndDate);
            fillGarageTypes();
            updateEndDate();
        </script>
    @endif
@endsection
