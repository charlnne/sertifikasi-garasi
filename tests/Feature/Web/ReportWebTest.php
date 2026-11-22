<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Expected figures are calculated by hand from DatabaseSeeder with today as 21 November 2026:
 * - September: C001 daily 3 x 30,000 + C001 monthly 25 x 30,000.
 * - October: E001 monthly 25 x 50,000 + initial charging 75 kWh x 75% x 10,000. Rejected S001 is not counted.
 * - November: S003 monthly 125,000, C002 90,000 + extension 60,000, E002 350,000 + charging 420,000
 *   and 300,000 (the 70% charging is still pending), two C003 rentals at 90,000 each. S002 is still pending.
 */
class ReportWebTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 10:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->service = app(ReportService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function row(string $label, int $count, float $rent, float $extension, float $charging): array
    {
        return [
            'label' => $label,
            'rental_count' => $count,
            'rent_revenue' => $rent,
            'extension_revenue' => $extension,
            'charging_revenue' => $charging,
            'total' => $rent + $extension + $charging,
        ];
    }

    public function test_monthly_report_matches_seeder_and_ignores_pending_or_rejected(): void
    {
        $report = $this->service->build(ReportService::VIEW_MONTHLY, 2026);

        $this->assertCount(12, $report['rows']);
        $this->assertSame($this->row('September 2026', 2, 840000.0, 0.0, 0.0), $report['rows'][8]);
        $this->assertSame($this->row('October 2026', 1, 1250000.0, 0.0, 562500.0), $report['rows'][9]);
        $this->assertSame($this->row('November 2026', 5, 745000.0, 60000.0, 720000.0), $report['rows'][10]);
        $this->assertSame($this->row('January 2026', 0, 0.0, 0.0, 0.0), $report['rows'][0]);
        $this->assertSame([
            'rental_count' => 8,
            'rent_revenue' => 2835000.0,
            'extension_revenue' => 60000.0,
            'charging_revenue' => 1282500.0,
            'total' => 4177500.0,
        ], $report['totals']);
    }

    public function test_daily_report_for_november(): void
    {
        $report = $this->service->build(ReportService::VIEW_DAILY, 2026, 11);

        $this->assertCount(30, $report['rows']);
        $this->assertSame($this->row('11 November 2026', 1, 125000.0, 0.0, 0.0), $report['rows'][10]);
        $this->assertSame($this->row('19 November 2026', 1, 350000.0, 0.0, 420000.0), $report['rows'][18]);
        $this->assertSame($this->row('20 November 2026', 1, 90000.0, 0.0, 300000.0), $report['rows'][19]);
        $this->assertSame($this->row('21 November 2026', 1, 90000.0, 60000.0, 0.0), $report['rows'][20]);
        $this->assertSame($this->row('26 November 2026', 1, 90000.0, 0.0, 0.0), $report['rows'][25]);
        $this->assertSame(1525000.0, $report['totals']['total']);
    }

    public function test_garage_type_report_for_year(): void
    {
        $report = $this->service->build(ReportService::VIEW_GARAGE_TYPE, 2026);

        $this->assertSame([
            $this->row('Bicycle Garage', 1, 125000.0, 0.0, 0.0),
            $this->row('Car Garage', 5, 1110000.0, 60000.0, 0.0),
            $this->row('Electric Garage', 2, 1600000.0, 0.0, 1282500.0),
        ], $report['rows']);
    }

    public function test_renter_report_for_year_sorted_by_name(): void
    {
        $report = $this->service->build(ReportService::VIEW_RENTER, 2026);

        $this->assertSame([
            $this->row('Budi Santoso', 3, 530000.0, 60000.0, 720000.0),
            $this->row('Siti Rahmawati', 5, 2305000.0, 0.0, 562500.0),
        ], $report['rows']);
    }

    public function test_renter_report_filtered_by_month(): void
    {
        $report = $this->service->build(ReportService::VIEW_RENTER, 2026, 10);

        $this->assertSame([$this->row('Siti Rahmawati', 1, 1250000.0, 0.0, 562500.0)], $report['rows']);
    }

    public function test_report_year_without_data_is_zero(): void
    {
        $report = $this->service->build(ReportService::VIEW_MONTHLY, 2025);

        $this->assertSame(0.0, $report['totals']['total']);
        $this->assertSame(0, $report['totals']['rental_count']);
    }

    public function test_report_page_shows_totals_and_filters(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['view' => 'monthly', 'year' => 2026]));

        $response->assertOk();
        $response->assertSeeInOrder(['November 2026', '5', 'Rp 745.000', 'Rp 60.000', 'Rp 720.000', 'Rp 1.525.000']);
        $response->assertSeeInOrder(['Total', '8', 'Rp 2.835.000', 'Rp 60.000', 'Rp 1.282.500', 'Rp 4.177.500']);
    }

    public function test_report_page_daily_defaults_to_current_month(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['view' => 'daily']));

        $response->assertViewHas('month', 11);
        $response->assertViewHas('year', 2026);
    }

    public function test_report_page_invalid_filter_returns_validation_error(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['view' => 'weekly', 'month' => 13]));

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHasErrors(['view' => 'The selected report type is invalid.', 'month' => 'Month must be between 1 and 12.']);
    }

    public function test_renter_cannot_access_reports(): void
    {
        $renter = User::where('email', 'budi@example.com')->firstOrFail();

        $this->actingAs($renter)->get(route('admin.reports.index'))->assertForbidden();
    }
}
