<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination::tailwind');
        Carbon::setLocale(config('app.locale'));

        // Format Rupiah amounts in views, e.g. @rupiah(30000) becomes "Rp 30.000".
        Blade::directive('rupiah', fn (string $amount) => "<?php echo 'Rp '.number_format((float) ({$amount}), 0, ',', '.'); ?>");
    }
}
