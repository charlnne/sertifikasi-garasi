@extends('layouts.app')

@section('title', 'Extend Rental '.$rental->garage->code)

@use('App\Enums\RentalType')
@use('App\Services\RentalCostCalculator')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('admin.extensions.store', $rental) }}" enctype="multipart/form-data" id="extension-form"
              class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-maroon-100"
              onsubmit="return confirm('Save this rental extension?')">
            @csrf

            <p class="mb-4 text-sm text-stone-600">
                The additional period starts on {{ $extensionStart->translatedFormat('d F Y') }}
                (reference month {{ $extensionStart->translatedFormat('F Y') }}).
            </p>

            <div class="mb-4">
                <span class="mb-1 block text-sm font-medium">Extension Type</span>
                <div class="flex gap-4 text-sm">
                    @foreach (RentalType::cases() as $type)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="extension_type" value="{{ $type->value }}"
                                   @checked(old('extension_type', RentalType::Daily->value) === $type->value)>
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('extension_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div id="days-field">
                <x-input name="days" label="Number of Days" type="number" min="1" :max="$maxDailyDays" step="1" :value="old('days', 1)" />
                <p class="-mt-3 mb-4 text-xs text-stone-500">Maximum {{ $maxDailyDays }} days for this reference month.</p>
            </div>

            <div class="mb-4 rounded border border-maroon-100 bg-maroon-50/60 p-4 text-sm">
                <dl class="grid max-w-sm grid-cols-2 gap-y-1">
                    <dt>Additional days</dt>
                    <dd class="text-right" id="preview-days">-</dd>
                    <dt>New end date</dt>
                    <dd class="text-right" id="preview-end-date">-</dd>
                    <dt class="border-t border-stone-300 pt-1 font-semibold">Extension cost</dt>
                    <dd class="border-t border-stone-300 pt-1 text-right font-semibold" id="preview-cost">-</dd>
                </dl>
                <p class="mt-2 text-xs text-stone-500">Dates and costs are recalculated by the system when saved.</p>
            </div>

            <x-input name="payment_proof" label="Extension Payment Proof (jpg/png, max 2 MB)" type="file"
                     accept="image/jpeg,image/png" required />

            <div class="flex gap-2">
                <x-button>Save Extension</x-button>
                <x-button variant="secondary" :href="route('admin.extensions.index')">Cancel</x-button>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-maroon-100">
                <p class="mb-2 font-semibold">Rental Details</p>
                <dl class="grid grid-cols-[110px_1fr] gap-y-1">
                    <dt class="text-stone-500">Renter</dt>
                    <dd>{{ $rental->vehicle->user->name }}</dd>
                    <dt class="text-stone-500">Vehicle</dt>
                    <dd>{{ $rental->vehicle->getDisplayName() }}</dd>
                    <dt class="text-stone-500">Garage</dt>
                    <dd class="font-mono">{{ $rental->garage->code }}</dd>
                    <dt class="text-stone-500">Daily price</dt>
                    <dd>@rupiah($rental->garage->daily_price)</dd>
                    <dt class="text-stone-500">Rental type</dt>
                    <dd>{{ $rental->rental_type->label() }}</dd>
                    <dt class="text-stone-500">Period</dt>
                    <dd>{{ $rental->start_date->translatedFormat('d M Y') }} - {{ $rental->end_date->translatedFormat('d M Y') }}</dd>
                    <dt class="text-stone-500">Extension</dt>
                    <dd>{{ $rental->extension_days }} days (@rupiah($rental->extension_cost))</dd>
                </dl>
            </div>

            @if ($nextBooking)
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-maroon-900">
                    <p class="font-semibold">Next booking in this garage</p>
                    <p class="mt-1">Starts {{ $nextBooking->start_date->translatedFormat('d F Y') }}.</p>
                    @if ($maxExtendableDays > 0)
                        <p class="mt-1">Maximum extension <strong>{{ $maxExtendableDays }} days</strong> (daily).</p>
                        <button type="button" id="use-max-days"
                                class="mt-3 rounded-lg bg-yellow-400 px-3 py-1.5 text-xs font-semibold text-maroon-900 shadow-sm hover:bg-yellow-300 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 active:scale-[0.97]">
                            Use this number
                        </button>
                    @else
                        <p class="mt-1 font-medium">The booking starts right after the rental ends, so the rental cannot be extended.</p>
                    @endif
                </div>
            @endif
        </aside>
    </div>

    <script>
        const extensionForm = document.getElementById('extension-form');
        const extension = {
            endDate: @js($rental->end_date->toDateString()),
            dailyPrice: @js((float) $rental->garage->daily_price),
            maxDailyDays: @js($maxDailyDays),
            monthlyDays: @js($monthlyDays),
            monthlyMultiplier: @js(RentalCostCalculator::MONTHLY_PRICE_MULTIPLIER),
            maxExtendableDays: @js($maxExtendableDays),
        };
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        function updateExtensionPreview() {
            const type = extensionForm.querySelector('input[name="extension_type"]:checked')?.value;
            const monthly = type === 'monthly';

            document.getElementById('days-field').classList.toggle('hidden', monthly);
            extensionForm.days.disabled = monthly;

            const days = monthly ? extension.monthlyDays : parseInt(extensionForm.days.value, 10);
            const valid = monthly || (days >= 1 && days <= extension.maxDailyDays);

            if (!valid) {
                ['preview-days', 'preview-end-date', 'preview-cost'].forEach((id) => document.getElementById(id).textContent = '-');
                return;
            }

            const [year, month, day] = extension.endDate.split('-').map(Number);
            const end = new Date(year, month - 1, day + days);
            const cost = monthly ? extension.monthlyMultiplier * extension.dailyPrice : days * extension.dailyPrice;

            document.getElementById('preview-days').textContent = days + (days === 1 ? ' day' : ' days');
            document.getElementById('preview-end-date').textContent = end.getDate() + ' ' + monthNames[end.getMonth()] + ' ' + end.getFullYear();
            document.getElementById('preview-cost').textContent = 'Rp ' + Math.round(cost).toLocaleString('id-ID');
        }

        document.getElementById('use-max-days')?.addEventListener('click', () => {
            extensionForm.querySelector('input[name="extension_type"][value="daily"]').checked = true;
            extensionForm.days.disabled = false;
            extensionForm.days.value = extension.maxExtendableDays;
            updateExtensionPreview();
        });

        extensionForm.addEventListener('input', updateExtensionPreview);
        extensionForm.addEventListener('change', updateExtensionPreview);
        updateExtensionPreview();
    </script>
@endsection
