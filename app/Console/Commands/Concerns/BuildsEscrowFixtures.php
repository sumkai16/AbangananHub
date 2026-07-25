<?php

namespace App\Console\Commands\Concerns;

use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture builder shared by escrow:scenarios and escrow:verify.
 *
 * The escrow feature is 7-to-14 days wide, so nothing about it can be observed
 * by using the app normally — every meaningful state has to be built by
 * backdating timestamps. This centralises that so the two commands can't drift
 * on what "a reservation waiting on turnover" actually means.
 *
 * Every fixture hangs off a user whose email is on FIXTURE_DOMAIN. Teardown
 * deletes those users and lets the FK cascade from users.user_id do the rest —
 * properties, units, reservations, payments, conversations and notifications
 * all go with them. That is why nothing here tracks created ids: the cascade
 * is the teardown, and it cannot miss a row the way a manual list can.
 */
trait BuildsEscrowFixtures
{
    protected const FIXTURE_DOMAIN = 'escrow-fixture.test';

    protected const FIXTURE_PASSWORD = 'escrow-test-1234';

    /**
     * Refuse to touch a production database under any circumstances. These
     * commands create and delete users; there is no safe version of that
     * against real tenants.
     */
    protected function guardEnvironment(): bool
    {
        if (app()->isProduction()) {
            $this->error('escrow fixtures refuse to run in production.');

            return false;
        }

        return true;
    }

    protected function fixtureUser(string $handle, string $first, string $last, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $handle . '@' . self::FIXTURE_DOMAIN],
            [
                'first_name'     => $first,
                'last_name'      => $last,
                'password'       => Hash::make(self::FIXTURE_PASSWORD),
                'account_status' => 'active',
            ]
        );

        $existing = UserRole::where('user_id', $user->user_id)->where('role', $role)->first();

        if (! $existing) {
            // forceFill: user_roles carries assigned_at, which is not
            // guaranteed to be mass-assignable on that model.
            (new UserRole())->forceFill([
                'user_id'     => $user->user_id,
                'role'        => $role,
                'assigned_at' => now(),
            ])->save();
        }

        return $user;
    }

    /**
     * Build one reservation parked in an exact escrow state.
     *
     * Each scenario gets its own property and unit. That is deliberate: several
     * checks assert on unit availability_status, and sharing a unit between
     * scenarios would let one scenario's release silently satisfy another's
     * assertion.
     *
     * Recognised options: label, landlord, tenant, status, target_move_in_date,
     * paid_at, payment_status (null for no payment at all), amount,
     * keys_turned_over_at, move_in_deadline_at, move_in_disputed_at,
     * move_in_dispute_reason, move_in_last_reminder_on,
     * tenant_confirmed_move_in_at, unit_status, with_conversation.
     */
    protected function makeScenario(array $o): Reservation
    {
        $label    = $o['label'];
        $landlord = $o['landlord'];
        $tenant   = $o['tenant'];

        $property = Property::create([
            'landlord_id'         => $landlord->user_id,
            'title'               => '[escrow-fixture] ' . $label,
            'description'         => 'Fixture property for escrow testing.',
            'property_type'       => 'Apartment',
            'address'             => 'Fixture Address, Butuan City',
            // properties.latitude/longitude carry no database default.
            'latitude'            => 8.9475,
            'longitude'           => 125.5406,
            'verification_status' => 'Approved',
        ]);

        $unit = PropertyUnit::create([
            'property_id'         => $property->property_id,
            'unit_label'          => 'Unit ' . strtoupper(substr(md5($label), 0, 4)),
            'description'         => 'Fixture unit.',
            'rental_fee'          => 5000,
            'occupancy_limit'     => 2,
            'availability_status' => $o['unit_status'] ?? 'Reserved',
            'verification_status' => 'Approved',
        ]);

        $conversationId = null;

        if ($o['with_conversation'] ?? true) {
            $conversationId = Conversation::create([
                'tenant_id'   => $tenant->user_id,
                'landlord_id' => $landlord->user_id,
                'property_id' => $property->property_id,
                'unit_id'     => $unit->unit_id,
            ])->conversation_id;
        }

        $reservation = Reservation::create([
            'property_id'         => $property->property_id,
            'unit_id'             => $unit->unit_id,
            'tenant_id'           => $tenant->user_id,
            'conversation_id'     => $conversationId,
            'reservation_date'    => today(),
            'target_move_in_date' => $o['target_move_in_date'] ?? null,
            'duration_of_stay'    => '6 Months',
            'occupants_count'     => 1,
            'rental_status'       => $o['status'] ?? 'Rental Agreement Signed',
            'remarks'             => '[escrow-fixture] ' . $label,
        ]);

        // Written after create() so the observer's created hook — which fires a
        // "new inquiry" notification — sees a plain reservation rather than one
        // that already has a deadline hanging off it.
        $escrow = array_filter([
            'keys_turned_over_at'         => $o['keys_turned_over_at'] ?? null,
            'move_in_deadline_at'         => $o['move_in_deadline_at'] ?? null,
            'move_in_disputed_at'         => $o['move_in_disputed_at'] ?? null,
            'move_in_dispute_reason'      => $o['move_in_dispute_reason'] ?? null,
            'move_in_last_reminder_on'    => $o['move_in_last_reminder_on'] ?? null,
            'tenant_confirmed_move_in_at' => $o['tenant_confirmed_move_in_at'] ?? null,
        ], fn ($v) => $v !== null);

        if ($escrow !== []) {
            // Query builder, not Eloquent: these columns are pure state setup
            // and must not trip the reservation observer into broadcasting a
            // status transition that never happened.
            DB::table('reservations')
                ->where('reservation_id', $reservation->reservation_id)
                ->update($escrow);
        }

        $paymentStatus = array_key_exists('payment_status', $o) ? $o['payment_status'] : 'Held';

        if ($paymentStatus !== null) {
            Payment::create([
                'reservation_id' => $reservation->reservation_id,
                'payment_type'   => 'Initial',
                'amount'         => $o['amount'] ?? 5000,
                'payment_method' => 'GCash',
                'status'         => $paymentStatus,
                'paid_at'        => $o['paid_at'] ?? now(),
            ]);
        }

        return $reservation->fresh();
    }

    /**
     * Delete every fixture user, letting the users.user_id cascade take the
     * properties, units, reservations, payments, conversations, messages and
     * notifications with them.
     */
    protected function purgeFixtures(): int
    {
        $users = User::where('email', 'like', '%@' . self::FIXTURE_DOMAIN)->get();

        foreach ($users as $user) {
            $user->delete();
        }

        return $users->count();
    }
}
