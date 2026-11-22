<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('dashboard', ['user' => $user, 'summary' => $dashboardService->adminSummary()]);
        }

        $rentals = $user->isApproved() ? $dashboardService->activeRentalsFor($user) : collect();

        return view('dashboard', compact('user', 'rentals'));
    }
}
