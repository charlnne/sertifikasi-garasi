<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Garage management by admins: bulk creation, filtered listing, and a summary per type.
 * Garages cannot be edited or deleted so rental cost history stays consistent.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class GarageService
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly GarageCodeGenerator $codeGenerator,
        private readonly GarageRuleService $garageRules,
    ) {}

    /**
     * Create several garages at once, numbered after the last number of that type.
     *
     * @param  GarageType  $type  Garage type.
     * @param  int  $count  Number of garages, 1 up to the remaining quota.
     * @param  float  $dailyPrice  Daily rental price.
     * @param  float|null  $kwhPrice  Price per kWh, only used for electric garages.
     * @return Collection<int, Garage> Newly created garages.
     *
     * @throws DomainException If the count is invalid or exceeds the remaining quota.
     */
    public function createBulk(GarageType $type, int $count, float $dailyPrice, ?float $kwhPrice = null): Collection
    {
        $kwhPrice = $this->garageRules->hasCharger($type) ? ($kwhPrice ?? GarageRuleService::DEFAULT_KWH_PRICE) : null;

        return DB::transaction(function () use ($type, $count, $dailyPrice, $kwhPrice) {
            // Lock this type's garage rows so two admins never get the same number.
            Garage::ofType($type)->lockForUpdate()->get(['id']);
            $numbers = $this->codeGenerator->nextNumbers($type, $count);
            $now = now();

            Garage::insert(array_map(fn (int $number) => [
                'type' => $type->value,
                'number' => $number,
                'daily_price' => $dailyPrice,
                'kwh_price' => $kwhPrice,
                'created_at' => $now,
                'updated_at' => $now,
            ], $numbers));

            return Garage::ofType($type)->whereIn('number', $numbers)->orderBy('number')->get();
        });
    }

    /**
     * @param  array{type?: ?string, status?: ?string, search?: ?string}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $type = GarageType::tryFrom((string) ($filters['type'] ?? ''));
        $status = GarageStatus::tryFrom((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        return Garage::query()
            ->withStatusOn()
            ->when($type, fn (Builder $query) => $query->ofType($type))
            ->when($status, fn (Builder $query) => $query->whereStatus($status))
            ->when($search !== '', fn (Builder $query) => $this->applyCodeSearch($query, $search))
            ->orderByRaw("case type when 'bicycle' then 1 when 'car' then 2 else 3 end")
            ->orderBy('number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Garage count summary per type for today.
     *
     * @return array<string, array{total: int, available: int, used: int, booked: int, last_number: int, remaining: int}>
     */
    public function summaryByType(): array
    {
        $totals = $this->countPerType(Garage::query());
        $lastNumbers = Garage::query()->selectRaw('type, max(number) as last_number')->groupBy('type')->pluck('last_number', 'type');
        $perStatus = [];

        foreach (GarageStatus::cases() as $status) {
            $perStatus[$status->value] = $this->countPerType(Garage::query()->whereStatus($status));
        }

        $summary = [];

        foreach (GarageType::cases() as $type) {
            $total = $totals[$type->value] ?? 0;

            $summary[$type->value] = [
                'total' => $total,
                'available' => $perStatus[GarageStatus::Available->value][$type->value] ?? 0,
                'used' => $perStatus[GarageStatus::Used->value][$type->value] ?? 0,
                'booked' => $perStatus[GarageStatus::Booked->value][$type->value] ?? 0,
                'last_number' => (int) ($lastNumbers[$type->value] ?? 0),
                'remaining' => GarageCodeGenerator::MAX_PER_TYPE - $total,
            ];
        }

        return $summary;
    }

    /**
     * Garage code search. Codes are not stored, so "C01" means the car type with a
     * 3-digit number starting with "01" (numbers 10 to 19).
     */
    private function applyCodeSearch(Builder $query, string $search): void
    {
        if (! preg_match('/^([A-Za-z])?(\d{0,3})$/', $search, $matches)) {
            $query->whereRaw('1 = 0');

            return;
        }

        [, $prefix, $digits] = $matches + [1 => '', 2 => ''];

        if ($prefix !== '') {
            $type = $this->garageRules->typeForPrefix($prefix);

            if ($type === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->ofType($type);
        }

        if ($digits !== '') {
            $scale = 10 ** (3 - strlen($digits));
            $query->whereBetween('number', [(int) $digits * $scale, ((int) $digits + 1) * $scale - 1]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function countPerType(Builder $query): array
    {
        return $query->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
