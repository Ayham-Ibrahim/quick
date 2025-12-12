<?php

namespace App\Providers;

use App\Models\Driver;
use App\Observers\DriverObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Driver::observe(DriverObserver::class);
    }
}
