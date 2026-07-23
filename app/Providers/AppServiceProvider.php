<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Observers\PaymentObserver;
use App\Observers\PropertyUnitObserver;
use App\Observers\ReservationObserver;
use Illuminate\Database\Eloquent\Model;
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
        // Surface N+1 queries immediately in local/dev; stays silent in production.
        Model::preventLazyLoading(! app()->isProduction());

        Payment::observe(PaymentObserver::class);
        PropertyUnit::observe(PropertyUnitObserver::class);
        Reservation::observe(ReservationObserver::class);
    }
}
