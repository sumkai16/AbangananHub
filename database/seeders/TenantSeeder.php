<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserRole;
use App\Support\MoveInPaymentBreakdown;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Five fully filled-in tenants, each with an active tenancy on a different
 * listed property. Each one has a different payment story so the landlord
 * tenancy page shows every state: on time, late, cash, advance, partial, overdue.
 *
 * Money is written the way the app writes it: the move-in payment is split by
 * MoveInPaymentBreakdown into a Deposit row plus 'Monthly' rows (advance rent
 * is just Monthly rows dated into future months), and each later month is one
 * 'Monthly' row carrying its billing_period.
 *
 * Must run after PropertySeeder. Idempotent: a tenant that already has an
 * Occupied reservation is left alone (run migrate:fresh --seed to rebuild).
 */
class TenantSeeder extends Seeder
{
    /**
     * pay_offsets: days after the due date each month's rent was paid, cycled
     *              (negative = paid early).
     * cash_every:  every Nth month was handed over in cash and typed in by the landlord.
     * methods:     online method used for non-cash payments, cycled.
     * advance:     extra months paid at move-in.
     * current:     what happened this month: 'paid' | 'unpaid' | 'partial'.
     */
    private const TENANTS = [
        [
            'first_name' => 'Kristine', 'last_name' => 'Alcantara', 'email' => 'kristine@abangananhub.com',
            'contact' => '09171230001', 'months_in' => 4, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0, -1, 0, 0], 'cash_every' => 0, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'paid',
            'bio' => 'Call-center agent on the night shift. Quiet, tidy, and always pays on the 1st.',
        ],
        [
            'first_name' => 'Jomar', 'last_name' => 'Pacana', 'email' => 'jomar@abangananhub.com',
            'contact' => '09171230002', 'months_in' => 7, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0, 2, 0, 5, 1, 0, 3], 'cash_every' => 3, 'methods' => ['GCash', 'GCash', 'Maya'],
            'advance' => 0, 'current' => 'paid',
            'bio' => 'Nursing student at UV. Looking for a peaceful place close to school and the jeepney line.',
        ],
        [
            'first_name' => 'Angeline', 'last_name' => 'Rosales', 'email' => 'angeline@abangananhub.com',
            'contact' => '09171230003', 'months_in' => 2, 'occupants' => 2, 'due_day' => 5,
            'pay_offsets' => [-2, 0], 'cash_every' => 0, 'methods' => ['GCash', 'QRPh'],
            'advance' => 1, 'current' => 'paid',
            'bio' => 'Freelance graphic designer working from home with my partner. Non-smokers, no pets.',
        ],
        [
            'first_name' => 'Dennis', 'last_name' => 'Cabahug', 'email' => 'dennis@abangananhub.com',
            'contact' => '09171230004', 'months_in' => 10, 'occupants' => 1, 'due_day' => 15,
            'pay_offsets' => [0, -2, 1, 0, 0, 2, -1, 0, 1, 0], 'cash_every' => 0, 'methods' => ['Bank Transfer', 'Bank Transfer', 'GCash'],
            'advance' => 0, 'current' => 'partial',
            'bio' => 'Civil engineer assigned to a Cebu project. Long-term renter, easy to reach by chat.',
        ],
        [
            'first_name' => 'Rhea', 'last_name' => 'Montecillo', 'email' => 'rhea@abangananhub.com',
            'contact' => '09171230005', 'months_in' => 1, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0], 'cash_every' => 0, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'unpaid',
            'bio' => 'Junior accountant, just moved to the city for work. Early sleeper, keeps the place clean.',
        ],
    ];

    public function run(): void
    {
        $units = $this->pickUnits(count(self::TENANTS));

        foreach (self::TENANTS as $i => $t) {
            $tenant = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'first_name'         => $t['first_name'],
                    'last_name'          => $t['last_name'],
                    'password'           => Hash::make('password'),
                    'contact_number'     => $t['contact'],
                    'gcash_number'       => $t['contact'],
                    'gcash_account_name' => $t['first_name'].' '.$t['last_name'],
                    'profile_picture'    => 'https://i.pravatar.cc/300?img='.(11 + $i * 7),
                    'bio'                => $t['bio'],
                    'profile_visibility' => 'public',
                    'account_status'     => 'active',
                ]
            );
            UserRole::firstOrCreate(['user_id' => $tenant->user_id, 'role' => 'Tenant']);

            $unit = $units[$i] ?? null;
            if (! $unit || Reservation::where('tenant_id', $tenant->user_id)->where('rental_status', 'Occupied')->exists()) {
                continue;
            }

            $this->rent($tenant, $unit, $t);
        }
    }

    /** One available, approved unit from every third property, so the tenants spread across the listings. */
    private function pickUnits(int $count)
    {
        return PropertyUnit::with('property')->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->orderBy('property_id')->orderBy('unit_id')
            ->get()
            ->unique('property_id')
            ->values()
            ->filter(fn ($u, $k) => $k % 3 === 0)
            ->values()
            ->take($count);
    }

    private function rent(User $tenant, PropertyUnit $unit, array $t): void
    {
        $moveIn     = now()->subMonths($t['months_in'])->startOfMonth();
        $rent       = (float) $unit->rental_fee;
        $deposit    = (float) ($unit->security_deposit ?? $rent);
        $landlordId = $unit->property->landlord_id;

        $reservation = Reservation::create([
            'tenant_id'                   => $tenant->user_id,
            'property_id'                 => $unit->property_id,
            'unit_id'                     => $unit->unit_id,
            'rental_status'               => 'Occupied',
            'reservation_date'            => $moveIn->copy()->subDays(10),
            'target_move_in_date'         => $moveIn,
            'target_move_out_date'        => $moveIn->copy()->addMonths(12),
            'agreed_monthly_rent'         => $rent,
            'rent_due_day'                => $t['due_day'],
            'occupants_count'             => min($t['occupants'], max(1, (int) $unit->occupancy_limit)),
            'agreement_terms_notes'       => 'Rent due on day '.$t['due_day'].' of each month. One month deposit, one month advance.',
            'agreed_at'                   => $moveIn->copy()->subDays(8),
            'agreed_ip'                   => '127.0.0.1',
            'landlord_tc_accepted_at'     => $moveIn->copy()->subDays(8),
            'tenant_tc_accepted_at'       => $moveIn->copy()->subDays(8),
            'keys_turned_over_at'         => $moveIn,
            'tenant_confirmed_move_in_at' => $moveIn,
            'handover_at'                 => $moveIn,
            'handover_confirmed_at'       => $moveIn,
        ]);

        $unit->update(['availability_status' => 'Occupied']);

        $covered = $this->moveInPayment($reservation, $t, $moveIn, $rent, $deposit);

        // Every month after move-in up to and including this one, minus whatever
        // the move-in payment already covered in advance.
        for ($m = 1; $m <= $t['months_in']; $m++) {
            $period = $moveIn->copy()->addMonths($m);

            if (in_array($period->format('Y-m'), $covered, true)) {
                continue;
            }

            $isCurrent = $m === $t['months_in'];
            $amount    = $rent;

            if ($isCurrent && $t['current'] === 'unpaid') {
                continue;
            }
            if ($isCurrent && $t['current'] === 'partial') {
                $amount = round($rent / 2, 2);
            }

            $this->monthlyPayment($reservation, $t, $period, $m, $amount, $landlordId);
        }
    }

    /**
     * Deposit + first month (+ any advance) collected online before move-in and
     * released to the landlord once the tenant confirmed the keys.
     *
     * @return list<string> 'Y-m' of every month the payment settled
     */
    private function moveInPayment(Reservation $reservation, array $t, Carbon $moveIn, float $rent, float $deposit): array
    {
        $breakdown = MoveInPaymentBreakdown::make(
            $deposit + $rent * (1 + $t['advance']),
            $rent,
            $deposit,
            $moveIn
        );

        $paidAt = $moveIn->copy()->subDays(7)->setTime(14, 20);
        $ref    = $this->gcashRef($t['email'], 'move-in');
        $months = [];

        foreach ($breakdown->rows() as $row) {
            Payment::create($row + [
                'reservation_id' => $reservation->reservation_id,
                'payment_method' => 'GCash',
                'status'         => 'Released',
                'paid_at'        => $paidAt,
                'released_at'    => $moveIn->copy()->addDay(),
                'release_reason' => 'tenant_confirmed',
                'reference_no'   => $ref,
            ]);

            if ($row['billing_period']) {
                $months[] = $row['billing_period']->format('Y-m');
            }
        }

        return $months;
    }

    private function monthlyPayment(Reservation $reservation, array $t, Carbon $period, int $m, float $amount, int $landlordId): void
    {
        $offset = $t['pay_offsets'][($m - 1) % count($t['pay_offsets'])];
        $paidAt = $period->copy()->day($t['due_day'])->addDays($offset)->setTime(9 + $m % 8, ($m * 17) % 60);
        if ($paidAt->isFuture()) {
            $paidAt = now()->subHour();
        }

        $cash   = $t['cash_every'] > 0 && $m % $t['cash_every'] === 0;
        $method = $cash ? 'Cash' : $t['methods'][($m - 1) % count($t['methods'])];

        Payment::create([
            'reservation_id' => $reservation->reservation_id,
            'payment_type'   => 'Monthly',
            'billing_period' => $period->toDateString(),
            'amount'         => $amount,
            'payment_method' => $method,
            'status'         => 'Paid',
            'paid_at'        => $paidAt,
            // Cash is typed in by the landlord (and so can be voided); online
            // payments arrive by themselves with a wallet/bank reference.
            'recorded_by'    => $cash ? $landlordId : null,
            'reference_no'   => $cash ? null : $this->gcashRef($t['email'], $period->format('Y-m')),
            'payment_notes'  => $cash ? 'Handed over in person.' : null,
        ]);
    }

    /** Stable 13-digit wallet-style reference, e.g. "5023189774210". */
    private function gcashRef(string $seed, string $salt): string
    {
        return substr(sprintf('%u%u', crc32($seed.$salt), crc32($salt.$seed)), 0, 13);
    }
}
