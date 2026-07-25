<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Observers\PaymentObserver;
use App\Observers\PropertyUnitObserver;
use App\Observers\ReservationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
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

        // The header's Areas menu is derived from live listings, so it can never
        // link somewhere with nothing in it. A composer rather than a variable
        // every controller has to remember to pass — the header renders on every
        // public page, and one that forgot would drop the menu silently.
        View::composer('layouts.app', function ($view) {
            $view->with('navAreas', Cache::remember('nav_areas', now()->addMinutes(10), function () {
                return Property::query()
                    ->where('verification_status', 'Approved')
                    ->whereHas('units', fn($q) => $q->where('availability_status', 'Available')
                        ->where('verification_status', 'Approved'))
                    ->pluck('address')
                    ->map(function ($address) {
                        // Addresses are free text shaped "Barangay, City, Cebu";
                        // the city is the second-to-last comma segment. Anything
                        // not in that shape contributes no area rather than a
                        // wrong one — the listing is still browsable either way.
                        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
                        return count($parts) >= 2 ? $parts[count($parts) - 2] : null;
                    })
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->take(6);
            }));
        });
    }
}
