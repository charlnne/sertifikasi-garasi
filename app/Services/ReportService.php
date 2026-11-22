<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\ElectricCharging;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Revenue report for admins. Only data with a verified payment is counted.
 *
 * Reference date for each revenue type:
 * - rent (and rental count): the rental start_date,
 * - extension: updated_at of rentals with extension_days > 0 (the table does not store a date per extension),
 * - charging (initial and additional): the charging created_at.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class ReportService
{
    public const VIEW_MONTHLY = 'monthly';

    public const VIEW_DAILY = 'daily';

    public const VIEW_GARAGE_TYPE = 'garage_type';

    public const VIEW_RENTER = 'renter';

    public const VIEWS = [
        self::VIEW_MONTHLY => 'By Month',
        self::VIEW_DAILY => 'By Day',
        self::VIEW_GARAGE_TYPE => 'By Garage Type',
        self::VIEW_RENTER => 'By Renter',
    ];

    /**
     * Build the report for the given view and period.
     *
     * @param  string  $view  monthly (per month in a year), daily (per day in a month), garage_type, or renter.
     * @param  int  $year  Report year.
     * @param  int|null  $month  Report month; required for daily, optional for garage_type and renter, ignored for monthly.
     * @return array{rows: array<int, array{label: string, rental_count: int, rent_revenue: float, extension_revenue: float, charging_revenue: float, total: float}>, totals: array{rental_count: int, rent_revenue: float, extension_revenue: float, charging_revenue: float, total: float}}
     */
    public function build(string $view, int $year, ?int $month = null): array
    {
        $month = $view === self::VIEW_MONTHLY ? null : $month;
        $rows = $this->emptyRows($view, $year, $month);

        foreach ($this->verifiedRentals('start_date', $year, $month) as $rental) {
            $key = $this->groupKey($view, $rental, $rental->start_date);
            $rows[$key] ??= $this->emptyRow($this->renterLabel($rental));
            $rows[$key]['rental_count']++;
            $rows[$key]['rent_revenue'] += $rental->rent_cost;
        }

        foreach ($this->verifiedRentals('updated_at', $year, $month)->where('extension_days', '>', 0) as $rental) {
            $key = $this->groupKey($view, $rental, $rental->updated_at);
            $rows[$key] ??= $this->emptyRow($this->renterLabel($rental));
            $rows[$key]['extension_revenue'] += (float) $rental->extension_cost;
        }

        foreach ($this->verifiedChargings($year, $month) as $charging) {
            $key = $this->groupKey($view, $charging->rental, $charging->created_at);
            $rows[$key] ??= $this->emptyRow($this->renterLabel($charging->rental));
            $rows[$key]['charging_revenue'] += $charging->cost;
        }

        $rows = array_map(fn (array $row) => $this->withTotal($row), $rows);

        if ($view === self::VIEW_RENTER) {
            uasort($rows, fn (array $a, array $b) => strcmp($a['label'], $b['label']));
        }

        return [
            'rows' => array_values($rows),
            'totals' => $this->sumRows($rows),
        ];
    }

    /**
     * Empty rows that are always shown: 12 months, every day of the month, or every garage type.
     *
     * @return array<string, array<string, mixed>>
     */
    private function emptyRows(string $view, int $year, ?int $month): array
    {
        return match ($view) {
            self::VIEW_MONTHLY => collect(range(1, 12))
                ->mapWithKeys(fn (int $number) => [$number => $this->emptyRow(CarbonImmutable::create($year, $number)->translatedFormat('F Y'))])
                ->all(),
            self::VIEW_DAILY => collect(range(1, CarbonImmutable::create($year, $month ?? 1)->daysInMonth))
                ->mapWithKeys(fn (int $day) => [$day => $this->emptyRow(CarbonImmutable::create($year, $month ?? 1, $day)->translatedFormat('d F Y'))])
                ->all(),
            self::VIEW_GARAGE_TYPE => collect(GarageType::cases())
                ->mapWithKeys(fn (GarageType $type) => [$type->value => $this->emptyRow($type->label().' Garage')])
                ->all(),
            default => [],
        };
    }

    private function groupKey(string $view, Rental $rental, CarbonInterface $date): string|int
    {
        return match ($view) {
            self::VIEW_MONTHLY => $date->month,
            self::VIEW_DAILY => $date->day,
            self::VIEW_GARAGE_TYPE => $rental->garage->type->value,
            default => $rental->vehicle->user_id,
        };
    }

    /**
     * @return Collection<int, Rental>
     */
    private function verifiedRentals(string $dateColumn, int $year, ?int $month): Collection
    {
        return Rental::verified()
            ->tap(fn (Builder $query) => $this->wherePeriod($query, $dateColumn, $year, $month))
            ->with(['garage', 'vehicle.user'])
            ->get();
    }

    /**
     * @return Collection<int, ElectricCharging>
     */
    private function verifiedChargings(int $year, ?int $month): Collection
    {
        return ElectricCharging::verified()
            ->tap(fn (Builder $query) => $this->wherePeriod($query, 'created_at', $year, $month))
            ->with(['rental.garage', 'rental.vehicle.user'])
            ->get();
    }

    private function wherePeriod(Builder $query, string $column, int $year, ?int $month): void
    {
        $query->whereYear($column, $year)->when($month, fn (Builder $query) => $query->whereMonth($column, $month));
    }

    private function renterLabel(Rental $rental): string
    {
        return $rental->vehicle->user->name;
    }

    /**
     * @return array{label: string, rental_count: int, rent_revenue: float, extension_revenue: float, charging_revenue: float}
     */
    private function emptyRow(string $label): array
    {
        return [
            'label' => $label,
            'rental_count' => 0,
            'rent_revenue' => 0.0,
            'extension_revenue' => 0.0,
            'charging_revenue' => 0.0,
        ];
    }

    /**
     * Total = rent + extension + charging revenue, with nothing added.
     */
    private function withTotal(array $row): array
    {
        $row['total'] = round($row['rent_revenue'] + $row['extension_revenue'] + $row['charging_revenue'], 2);

        return $row;
    }

    private function sumRows(array $rows): array
    {
        $totals = ['rental_count' => 0, 'rent_revenue' => 0.0, 'extension_revenue' => 0.0, 'charging_revenue' => 0.0, 'total' => 0.0];

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $column) {
                $totals[$column] += $row[$column];
            }
        }

        return $totals;
    }
}
