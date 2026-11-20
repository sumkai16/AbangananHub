<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Security\TrustedDevices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * The capstone-defense world: every screen the panel may be shown has real data behind it, in a known state.
 *
 * Runs last in DatabaseSeeder, so `php artisan migrate:fresh --seed` rebuilds everything from nothing (it WIPES the
 * database). On a database that already has the base seed it can be run alone and is safe to repeat:
 *     php artisan db:seed --class=DefenseSeeder
 *
 *  - DefenseAdminSeeder: what only an administrator clears (applications, approvals, reports, refund requests,
 *    payouts, holds, logs).
 *  - DefenseRentalsSeeder: the landlord's and tenant's side (sidebar numbers, an overdue rental, a payment to
 *    confirm, a QR request, a signed agreement waiting for payment, ...).
 *  - The administrator keeps two-factor sign-in ON, with a KNOWN secret: add ADMIN_TOTP_SECRET to an authenticator
 *    app once, or run `php artisan defense:code` to print the current 6-digit code. Recovery codes are fixed too.
 *
 * Everything here is demo data: fixed logins (password `password`), a fixed two-factor secret and fixed recovery
 * codes. It refuses to run in production. The scenario list for the demo script is each seeder's SCENARIOS constant
 * (see docs/defense-script.md).
 */
class DefenseSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@abangananhub.com';

    /** Base32 (A-Z, 2-7), 32 characters (authenticator apps need a power-of-two length). Add it as a time-based key, or use `defense:code`. */
    public const ADMIN_TOTP_SECRET = 'ABANGANANHUBDEFENSEKEYSEVEN23456';

    /** Single-use recovery codes for the administrator (the app's alphabet has no 0, O, 1 or I). */
    public const ADMIN_RECOVERY_CODES = [
        'DEFN-AB23', 'DEFN-CD34', 'DEFN-EF45', 'DEFN-GH56', 'DEFN-JK67', 'DEFN-LM72', 'DEFN-NP23', 'DEFN-QR34',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DefenseSeeder holds fixed passwords and a fixed two-factor secret: it never runs in production.');
        }

        $this->call([
            DefenseAdminSeeder::class,
            DefenseRentalsSeeder::class,
            DefenseSearchSeeder::class,
        ]);

        $this->secureAdmin();
    }

    /** Two-factor stays on for the demo, with a secret and recovery codes the presenter knows. */
    private function secureAdmin(): void
    {
        $admin = User::where('email', self::ADMIN_EMAIL)->firstOrFail();

        $admin->forceFill([
            'two_factor_secret'         => self::ADMIN_TOTP_SECRET,
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), self::ADMIN_RECOVERY_CODES),
            'two_factor_confirmed_at'   => now(),
            // Null so the first code the presenter reads off the phone is accepted (a step is accepted once).
            'two_factor_last_step'      => null,
        ])->save();

        // A re-run starts from "this browser is not remembered", so the demo shows the 2FA prompt.
        app(TrustedDevices::class)->revokeAll($admin);
    }
}
