<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserRole;
use App\Services\RentLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Browsable walk-in tenancy + rent-ledger states for manual UI checking.
 *
 * Overdue rent and multi-month histories are weeks wide and cannot be reached
 * by using the app, so the only way to see a red "3 months behind" row or a
 * partially-paid period is to backdate the data — Carbon::setTestNow() does not
 * reach a separate artisan process.
 *
 * Additive and tagged: every fixture hangs off a user on FIXTURE_DOMAIN, and
 * --clean deletes those users and lets the users.user_id FK cascade take the
 * properties, units, reservations and payments with them. Refuses to run in
 * production because it creates and deletes users.
 */
class WalkInScenarios extends Command
{
    protected $signature = 'walkin:scenarios {--clean : remove all walk-in fixtures and exit}';

    protected $description = 'Create browsable walk-in tenant & rent ledger scenarios for manual UI testing';

    private const FIXTURE_DOMAIN = 'walkin-fixture.test';
    private const FIXTURE_PASSWORD = 'walkin-test-1234';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('walk-in fixtures refuse to run in production.');

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            $removed = $this->purge();
            $this->info("Removed {$removed} fixture user(s) and everything cascading from them.");

            return self::SUCCESS;
        }

        $this->purge();

        $landlord = $this->fixtureLandlord();
        $rows = [];

        // 1 — no email, fully paid three months
        $rows[] = ['Walk-in, no email, all paid', $this->scenario($landlord, [
            'first' => 'Nita', 'last' => 'Reyes', 'email' => null, 'contact' => '09170000001',
            'rent' => 4500, 'due_day' => 5, 'move_in' => now()->subMonths(2)->startOfMonth()->addDays(4),
            'deposit' => 4500,
            'monthly' => [0, 1, 2], // months-ago that are paid in full
        ]), 'Ledger all green. Tenant badged Walk-in, email shows as —.'];

        // 2 — with email, months overdue (backdated)
        $rows[] = ['Walk-in, behind on rent', $this->scenario($landlord, [
            'first' => 'Juan', 'last' => 'Dela Cruz', 'email' => 'juan.walkin@'.self::FIXTURE_DOMAIN, 'contact' => '09170000002',
            'rent' => 6000, 'due_day' => 1, 'move_in' => now()->subMonths(2)->startOfMonth(),
            'deposit' => 6000,
            'monthly' => [2], // only the oldest month paid; later months overdue
        ]), 'Red Overdue rows. Payments index lists it first, standing "Behind".'];

        // 3 — partial payment on the current period
        $rows[] = ['Walk-in, partial this month', $this->scenario($landlord, [
            'first' => 'Bea', 'last' => 'Santos', 'email' => null, 'contact' => '09170000003',
            'rent' => 5000, 'due_day' => 10, 'move_in' => now()->startOfMonth(),
            'partial' => [0 => 2000], // months-ago => amount, less than rent
        ]), 'Current period reads Partial (amber), ₱3,000 balance.'];

        // 4 — platform tenant with recorded monthly payments (proves ledger is not walk-in-only)
        $rows[] = ['Platform tenant, recorded rent', $this->scenario($landlord, [
            'first' => 'Marco', 'last' => 'Lim', 'email' => 'marco.platform@'.self::FIXTURE_DOMAIN, 'contact' => '09170000004',
            'walk_in' => false,
            'rent' => 7000, 'due_day' => 15, 'move_in' => now()->subMonths(1)->startOfMonth()->addDays(14),
            'monthly' => [1], // first month recorded, current month still owed
        ]), 'NOT badged Walk-in. Ledger records rent for a platform tenancy just the same.'];

        // 5 — ended tenancy (Completed, unit back to Available)
        $rows[] = ['Walk-in, tenancy ended', $this->scenario($landlord, [
            'first' => 'Grace', 'last' => 'Uy', 'email' => null, 'contact' => '09170000005',
            'rent' => 4000, 'due_day' => 1, 'move_in' => now()->subMonths(4)->startOfMonth(),
            'move_out' => now()->subMonth()->startOfMonth(),
            'status' => 'Completed', 'unit_status' => 'Available',
            'monthly' => [4, 3, 2, 1],
        ]), 'Status Completed. Unit Available. Ledger read-only, no Record Payment.'];

        // 6 — a unit already holding a live platform reservation (double-booking guard target)
        $rows[] = ['Live reservation on unit', $this->scenario($landlord, [
            'first' => 'Ken', 'last' => 'Abad', 'email' => 'ken.pending@'.self::FIXTURE_DOMAIN, 'contact' => '09170000006',
            'walk_in' => false,
            'rent' => 5500, 'due_day' => 1, 'move_in' => now()->addWeek(),
            'status' => 'Rental Agreement Signed', 'unit_status' => 'Reserved',
        ]), 'Try adding a walk-in on THIS unit — it must be absent from the picker (409 if forced).'];

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Walk-in scenarios created</>');
        $this->newLine();

        $this->table(
            ['State', 'Reservation', 'What to look for'],
            array_map(fn ($r) => [$r[0], '#'.$r[1]->reservation_id, wordwrap($r[2], 54, "\n")], $rows)
        );

        $this->newLine();
        $this->line('  <options=bold>Log in as</>');
        $this->line('    landlord  '.$landlord->email.'  /  '.self::FIXTURE_PASSWORD);
        $this->line('    admin     use your own admin account');
        $this->newLine();
        $this->line('  Tenants:        /landlord/tenants');
        $this->line('  Rent & payments:/landlord/payments');
        $this->line('  A tenancy:      /landlord/tenancies/{id}');
        $this->line('  Admin payments: /admin/payments?status=Paid');
        $this->newLine();
        $this->line('  Remove with <options=bold>php artisan walkin:scenarios --clean</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function fixtureLandlord(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'landlord@'.self::FIXTURE_DOMAIN],
            [
                'first_name'     => 'Fixture',
                'last_name'      => 'Landlord',
                'password'       => Hash::make(self::FIXTURE_PASSWORD),
                'account_status' => 'active',
            ]
        );

        if (! UserRole::where('user_id', $user->user_id)->where('role', 'Landlord')->exists()) {
            (new UserRole())->forceFill([
                'user_id' => $user->user_id, 'role' => 'Landlord', 'assigned_at' => now(),
            ])->save();
        }

        return $user;
    }

    /**
     * One occupied (or completed) tenancy plus its recorded payments, backdated
     * to the exact ledger state named in $o. Each gets its own property + unit
     * so one scenario's unit status can't satisfy another's.
     */
    private function scenario(User $landlord, array $o): Reservation
    {
        $isWalkIn = $o['walk_in'] ?? true;

        // Walk-ins are fixture users on the fixture domain so --clean reaches
        // them; platform tenants are too, for the same teardown.
        $tenant = User::create([
            'first_name'             => $o['first'],
            'last_name'              => $o['last'],
            'email'                  => $o['email'] ?? ($isWalkIn ? null : Str::random(8).'@'.self::FIXTURE_DOMAIN),
            'password'               => Hash::make(self::FIXTURE_PASSWORD),
            'contact_number'         => $o['contact'] ?? null,
            'account_status'         => $isWalkIn ? 'inactive' : 'active',
            'is_walk_in'             => $isWalkIn,
            'created_by_landlord_id' => $isWalkIn ? $landlord->user_id : null,
        ]);
        $this->assignRole($tenant, 'Tenant');

        // A platform tenant needs a resolvable email for --clean; give the fixture
        // one on the fixture domain even though a real platform tenant always has
        // their own. (Handled above via Str::random fallback.)

        $property = Property::create([
            'landlord_id'         => $landlord->user_id,
            'title'               => '[walkin-fixture] '.$o['first'].' '.$o['last'],
            'description'         => 'Fixture property for walk-in testing.',
            'property_type'       => 'Apartment',
            'address'             => 'Fixture Address, Butuan City',
            'latitude'            => 8.9475,
            'longitude'           => 125.5406,
            'verification_status' => 'Approved',
        ]);

        $unit = PropertyUnit::create([
            'property_id'         => $property->property_id,
            'unit_label'          => 'Unit '.strtoupper(substr(md5($o['first'].$o['last']), 0, 4)),
            'description'         => 'Fixture unit.',
            'rental_fee'          => $o['rent'],
            'occupancy_limit'     => 2,
            'availability_status' => $o['unit_status'] ?? 'Occupied',
            'verification_status' => 'Approved',
        ]);

        $reservation = Reservation::create([
            'property_id'          => $property->property_id,
            'unit_id'              => $unit->unit_id,
            'tenant_id'            => $tenant->user_id,
            'conversation_id'      => null,
            'reservation_date'     => Carbon::parse($o['move_in']),
            'target_move_in_date'  => Carbon::parse($o['move_in']),
            'target_move_out_date' => isset($o['move_out']) ? Carbon::parse($o['move_out']) : null,
            'agreed_monthly_rent'  => $o['rent'],
            'rent_due_day'         => $o['due_day'] ?? null,
            'rental_status'        => $o['status'] ?? 'Occupied',
            'remarks'              => '[walkin-fixture]',
        ]);

        if (! empty($o['deposit'])) {
            $this->recordPayment($reservation, $landlord, 'Deposit', $o['deposit'], null, $o['move_in']);
        }

        foreach ($o['monthly'] ?? [] as $monthsAgo) {
            $period = now()->subMonths($monthsAgo)->startOfMonth();
            $this->recordPayment($reservation, $landlord, 'Monthly', $o['rent'], $period, $period);
        }

        foreach ($o['partial'] ?? [] as $monthsAgo => $amount) {
            $period = now()->subMonths($monthsAgo)->startOfMonth();
            $this->recordPayment($reservation, $landlord, 'Monthly', $amount, $period, $period);
        }

        return $reservation->fresh(['payments', 'unit']);
    }

    private function recordPayment(Reservation $reservation, User $landlord, string $type, float $amount, ?Carbon $period, $paidAt): void
    {
        Payment::create([
            'reservation_id' => $reservation->reservation_id,
            'payment_type'   => $type,
            'billing_period' => $period,
            'amount'         => $amount,
            'payment_method' => 'Cash',
            'status'         => 'Paid',
            'paid_at'        => Carbon::parse($paidAt),
            'recorded_by'    => $landlord->user_id,
        ]);
    }

    private function assignRole(User $user, string $role): void
    {
        if (! UserRole::where('user_id', $user->user_id)->where('role', $role)->exists()) {
            (new UserRole())->forceFill([
                'user_id' => $user->user_id, 'role' => $role, 'assigned_at' => now(),
            ])->save();
        }
    }

    /**
     * Fixture users share a domain on either their email or — for the emailless
     * walk-ins — their created_by_landlord_id pointing at the fixture landlord.
     * Delete the landlord last so its walk-ins are swept by the cascade first.
     */
    private function purge(): int
    {
        $landlord = User::where('email', 'landlord@'.self::FIXTURE_DOMAIN)->first();

        $count = 0;

        // Emailless walk-ins created by the fixture landlord.
        if ($landlord) {
            foreach (User::where('created_by_landlord_id', $landlord->user_id)->get() as $walkIn) {
                $walkIn->delete();
                $count++;
            }
        }

        foreach (User::where('email', 'like', '%@'.self::FIXTURE_DOMAIN)->get() as $user) {
            $user->delete();
            $count++;
        }

        return $count;
    }
}
