<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable overrides for `config/rentals.php`.
 *
 * Only keys an admin has actually changed get a row. `AppServiceProvider::boot()`
 * merges them over the config at boot, so all existing `config('rentals.*')` call
 * sites keep working untouched and an unset key falls through to the file default.
 * `config/rentals.php` therefore remains the documentation for what each key means;
 * DEFINITIONS below carries only what the admin form and its validation need.
 */
class Setting extends Model
{
    protected $primaryKey = 'setting_id';

    protected $fillable = ['key', 'value'];

    /** One cache entry for the whole override set — busted on every write. */
    private const CACHE_KEY = 'settings.rentals.overrides';

    public const GROUP_ESCROW = 'Escrow clocks';
    public const GROUP_RENT   = 'Rent ledger';

    /**
     * The whitelist. Nothing outside this map can be stored or edited — the form
     * renders from it and UpdateSettingsRequest validates against it, so an admin
     * cannot set a grace period to -5 or to "banana".
     *
     * `type` is 'integer' or 'integer_list' (a comma-separated list, stored as text
     * and cast back on read).
     */
    public const DEFINITIONS = [
        'move_in_confirmation_days' => [
            'group' => self::GROUP_ESCROW,
            'label' => 'Move-in confirmation window',
            'help'  => 'Days the tenant has to confirm move-in after keys are turned over. Expiry releases the held deposit to the landlord.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:90'],
        ],
        'turnover_grace_days' => [
            'group' => self::GROUP_ESCROW,
            'label' => 'Turnover grace period',
            'help'  => 'Days past the agreed move-in date before an un-turned-over reservation is escalated to admin review.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:90'],
        ],
        'turnover_grace_days_no_date' => [
            'group' => self::GROUP_ESCROW,
            'label' => 'Turnover grace period (no move-in date)',
            'help'  => 'Fallback used when the reservation has no target move-in date. Counted from the payment date instead.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:120'],
        ],
        'handover_max_extension_days' => [
            'group' => self::GROUP_ESCROW,
            'label' => 'Maximum handover extension',
            'help'  => 'Ceiling on how far confirmed handover slots may push the turnover deadline, measured from the original deadline — so repeated reschedules converge here instead of walking forever.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:180'],
        ],
        'reminder_days_remaining' => [
            'group' => self::GROUP_ESCROW,
            'label' => 'Confirmation reminder thresholds',
            'help'  => 'Days-remaining marks that trigger a move-in confirmation reminder. Comma-separated, e.g. 4, 1, 0 on a 7-day window fires on day 3, day 6, and the morning of expiry.',
            'unit'  => 'days remaining',
            'type'  => 'integer_list',
            'rule'  => ['array', 'min:1', 'max:6'],
            'item_rule' => ['integer', 'min:0', 'max:90'],
        ],
        'rent_due_day_default' => [
            'group' => self::GROUP_RENT,
            'label' => 'Default rent due day',
            'help'  => 'Day of the month rent falls due, used only when the reservation carries neither its own due day nor a move-in date to take one from. Capped at 28 so the day exists in February.',
            'unit'  => 'day of month',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:28'],
        ],
        'rent_overdue_grace_days' => [
            'group' => self::GROUP_RENT,
            'label' => 'Rent overdue grace period',
            'help'  => 'Days past the due date before a billing period reads as Overdue rather than Due. Zero means the day after is already late.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:0', 'max:30'],
        ],
        'rent_reminder_lead_days' => [
            'group' => self::GROUP_RENT,
            'label' => 'Rent reminder lead time',
            'help'  => 'How far before the due date the "due soon" nudge fires.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:30'],
        ],
        'rent_overdue_reminder_interval_days' => [
            'group' => self::GROUP_RENT,
            'label' => 'Overdue reminder interval',
            'help'  => 'How often the overdue reminder repeats while a period stays unpaid.',
            'unit'  => 'days',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:60'],
        ],
        'rent_reminder_max_overdue_weeks' => [
            'group' => self::GROUP_RENT,
            'label' => 'Stop reminding after',
            'help'  => 'Reminders stop past this many overdue weeks, so a tenancy the landlord forgot to end does not nag forever.',
            'unit'  => 'weeks',
            'type'  => 'integer',
            'rule'  => ['integer', 'min:1', 'max:104'],
        ],
    ];

    /**
     * Stored overrides, keyed and cast per DEFINITIONS. Cached forever so the boot
     * merge costs a cache read rather than a query on every request and command.
     */
    public static function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::query()
                ->whereIn('key', array_keys(self::DEFINITIONS))
                ->pluck('value', 'key')
                ->map(fn($value, $key) => self::cast($key, $value))
                ->all();
        });
    }

    /** The value an admin currently sees for a key: their override, else the file default. */
    public static function effective(string $key): mixed
    {
        return self::overrides()[$key] ?? self::default($key);
    }

    /** The `config/rentals.php` value, read past any override applied at boot. */
    public static function default(string $key): mixed
    {
        // config() has already been overwritten in boot(), so re-read the file.
        static $file = null;
        $file ??= require config_path('rentals.php');

        return $file[$key] ?? null;
    }

    /**
     * Store or clear one key. A null/empty value deletes the row so the key falls
     * back to the file default rather than being pinned to a copy of it.
     */
    public static function put(string $key, mixed $value): void
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            return;
        }

        if ($value === null || $value === '' || $value === []) {
            self::where('key', $key)->delete();
        } else {
            self::updateOrCreate(['key' => $key], ['value' => self::serialize($key, $value)]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** Render a value for display in the form and in audit metadata. */
    public static function display(string $key, mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    private static function cast(string $key, ?string $value): mixed
    {
        if (self::DEFINITIONS[$key]['type'] === 'integer_list') {
            return array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $value)), fn($v) => $v !== '')));
        }

        return (int) $value;
    }

    private static function serialize(string $key, mixed $value): string
    {
        return self::DEFINITIONS[$key]['type'] === 'integer_list'
            ? implode(',', array_map('intval', (array) $value))
            : (string) (int) $value;
    }
}
