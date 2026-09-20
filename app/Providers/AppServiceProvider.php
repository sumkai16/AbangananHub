<?php

namespace App\Providers;

use App\Models\Message;
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

        // Header badges, computed once per request. Each layout used to run the
        // notification count twice (the server-rendered dot plus the Alpine
        // dropdown's initial value) and the message count inline in a @php block.
        View::composer(['layouts.app', 'layouts.landlord', 'layouts.admin'], function ($view) {
            // Memoised on the request, not a static: a static would leak one
            // user's counts into the next in any long-running process.
            $attributes = request()->attributes;
            $counts = $attributes->get('nav_counts');

            if ($counts === null) {
                $user = auth()->user();
                $counts = $user ? [
                    'unreadNotificationCount' => $user->notifications()->where('is_read', false)->count(),
                    'unreadMessageCount' => Message::whereHas('conversation', fn ($q) => $q
                            ->where('tenant_id', $user->user_id)->orWhere('landlord_id', $user->user_id))
                        ->where('sender_id', '!=', $user->user_id)
                        ->where('is_read', false)
                        ->count(),
                ] : ['unreadNotificationCount' => 0, 'unreadMessageCount' => 0];
                $attributes->set('nav_counts', $counts);
            }

            $view->with($counts);
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
