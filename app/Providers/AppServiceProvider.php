<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\Setting;
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

        $this->applyRentalSettingOverrides();

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
                    ->browsable()
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

    /**
     * Merge admin-set overrides over config/rentals.php.
     *
     * Keeping the merge here means the 13 existing `config('rentals.*')` call sites
     * — in Reservation, RentLedger, two console commands and two controllers — need
     * no change, and unset keys keep the documented file default. It runs for
     * console commands too, so ProcessMoveInDeadlines and ProcessRentReminders pick
     * up admin changes as well, which is the intent.
     *
     * The table is missing before the migration that creates it runs, so a failed
     * read must leave the file defaults in place rather than break `artisan`.
     */
    private function applyRentalSettingOverrides(): void
    {
        try {
            foreach (Setting::overrides() as $key => $value) {
                config(["rentals.$key" => $value]);
            }
        } catch (\Throwable) {
            // Defaults stand.
        }
    }
}
