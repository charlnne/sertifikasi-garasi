@extends('layouts.app')

@section('title', 'Revenue Report')

@use('App\Services\ReportService')

@php
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($number) => [$number => \Illuminate\Support\Carbon::create(2000, $number)->translatedFormat('F')]);
    $needsMonth = $view !== ReportService::VIEW_MONTHLY;
    $groupLabel = match ($view) {
        ReportService::VIEW_MONTHLY => 'Month',
        ReportService::VIEW_DAILY => 'Date',
        ReportService::VIEW_GARAGE_TYPE => 'Garage Type',
        default => 'Renter',
    };
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (ReportService::VIEWS as $key => $label)
            <a href="{{ route('admin.reports.index', ['view' => $key, 'year' => $year, 'month' => $month]) }}"
               class="rounded-lg px-3 py-1.5 transition hover:-translate-y-0.5 {{ $view === $key ? 'bg-maroon-700 text-white shadow-sm' : 'bg-white text-maroon-800 ring-1 ring-maroon-100 hover:bg-maroon-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="hidden" name="view" value="{{ $view }}">
        <div>
            <label for="year" class="mb-1 block text-xs text-stone-600">Year</label>
            <input id="year" type="number" name="year" value="{{ $year }}" min="2000" max="2100"
                   class="w-28 rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm">
        </div>
        @if ($needsMonth)
            <div>
                <label for="month" class="mb-1 block text-xs text-stone-600">Month</label>
                <select id="month" name="month" class="rounded-lg border border-stone-300 bg-white shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200 px-3 py-2 text-sm">
                    @if ($view !== ReportService::VIEW_DAILY)
                        <option value="">All months</option>
                    @endif
                    @foreach ($monthNames as $number => $name)
                        <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <x-button variant="secondary">Show</x-button>
    </form>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <p class="mb-2 text-xs text-stone-500">
        Verified payments only. Rent revenue is based on the start date, extensions on the latest
        extension date, and charging on the charging date.
    </p>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-maroon-100">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-maroon-100 bg-maroon-50 text-xs uppercase tracking-wide text-maroon-900">
                <tr>
                    <th class="px-4 py-3">{{ $groupLabel }}</th>
                    <th class="px-4 py-3 text-right">Rentals</th>
                    <th class="px-4 py-3 text-right">Rent Revenue</th>
                    <th class="px-4 py-3 text-right">Extension Revenue</th>
                    <th class="px-4 py-3 text-right">Charging Revenue</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr class="border-b last:border-0 {{ $row['total'] == 0 && $row['rental_count'] === 0 ? 'text-stone-400' : '' }}">
                        <td class="px-4 py-2">{{ $row['label'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $row['rental_count'] }}</td>
                        <td class="px-4 py-2 text-right">@rupiah($row['rent_revenue'])</td>
                        <td class="px-4 py-2 text-right">@rupiah($row['extension_revenue'])</td>
                        <td class="px-4 py-2 text-right">@rupiah($row['charging_revenue'])</td>
                        <td class="px-4 py-2 text-right font-medium">@rupiah($row['total'])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-stone-500">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-stone-300 bg-maroon-50 font-semibold">
                <tr>
                    <td class="px-4 py-3">Total</td>
                    <td class="px-4 py-3 text-right">{{ $report['totals']['rental_count'] }}</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['totals']['rent_revenue'])</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['totals']['extension_revenue'])</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['totals']['charging_revenue'])</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['totals']['total'])</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
