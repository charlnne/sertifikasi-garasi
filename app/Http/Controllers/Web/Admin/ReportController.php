<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ReportService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(ReportFilterRequest $request): View
    {
        $view = $request->reportView();
        $year = $request->year();
        $month = $request->month();

        $report = $this->reportService->build($view, $year, $month);

        return view('admin.reports.index', compact('report', 'view', 'year', 'month'));
    }
}
