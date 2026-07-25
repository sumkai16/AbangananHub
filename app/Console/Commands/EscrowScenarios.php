<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\BuildsEscrowFixtures;
use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Builds a browsable set of escrow states for manual UI checking.
 *
 * escrow:verify proves the logic; this proves the screens. The countdown, the
 * notices and the admin queue all render off states that take a week to reach
 * naturally, so without backdated fixtures the only way to see the red
 * "last day" panel is to wait six days for it.
 *
 * Additive and tagged: it never touches reservations that already exist, and
 * --clean removes exactly what it made.
 */
class EscrowScenarios extends Command
{
    use BuildsEscrowFixtures;

    protected $signature = 'escrow:scenarios {--clean : remove all escrow fixtures and exit}';

    protected $description = 'Create browsable move-in escrow scenarios for manual UI testing';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        if ($this->option('clean')) {
            $removed = $this->purgeFixtures();
            $this->info("Removed {$removed} fixture user(s) and everything cascading from them.");

            return self::SUCCESS;
        }

        $this->purgeFixtures();

        $landlord = $this->fixtureUser('landlord', 'Fixture', 'Landlord', 'Landlord');
        $tenant   = $this->fixtureUser('tenant', 'Fixture', 'Tenant', 'Tenant');

        $rows = [];

        $rows[] = ['Clock 1 — awaiting turnover', $this->make('paid, keys not yet handed over', $landlord, $tenant, [
            'target_move_in_date' => today()->addDays(5),
            'paid_at'             => now(),
            'move_in_deadline_at' => today()->addDays(12),
        ]), 'Tenant: blue "Payment secured", no countdown. Landlord: "Mark keys turned over" button.'];

        $rows[] = ['Clock 2 — 5 days left', $this->make('turnover done, comfortable', $landlord, $tenant, [
            'keys_turned_over_at' => now()->subDays(2),
            'move_in_deadline_at' => now()->addDays(5),
        ]), 'Tenant: amber "5 days left to confirm your move-in".'];

        $rows[] = ['Clock 2 — 1 day left', $this->make('turnover done, urgent', $landlord, $tenant, [
            'keys_turned_over_at' => now()->subDays(6),
            'move_in_deadline_at' => now()->addDay(),
        ]), 'Tenant: red "1 day left".'];

        $rows[] = ['Clock 2 — last day', $this->make('deadline is today', $landlord, $tenant, [
            'keys_turned_over_at' => now()->subDays(7),
            'move_in_deadline_at' => today()->addHours(23),
        ]), 'Tenant: red "Today is your last day". Must NOT be released by tonight\'s run.'];

        $rows[] = ['Clock 2 — overdue, unreleased', $this->make('deadline passed, job not yet run', $landlord, $tenant, [
            'keys_turned_over_at' => now()->subDays(9),
            'move_in_deadline_at' => today()->subDay()->addHours(9),
        ]), 'Tenant: red "Your move-in confirmation window has passed". Confirm button still works.'];

        $rows[] = ['Disputed by tenant', $this->make('tenant reported missing keys', $landlord, $tenant, [
            'keys_turned_over_at'    => now()->subDays(3),
            'move_in_disputed_at'    => now()->subDay(),
            'move_in_dispute_reason' => 'The landlord has not given me the keys and is not replying to messages.',
        ]), 'Tenant: amber "under review", no countdown, no confirm button. Admin: in "Needs review".'];

        $rows[] = ['Escalated by the system', $this->make('landlord never marked turnover', $landlord, $tenant, [
            'paid_at'                => now()->subDays(30),
            'target_move_in_date'    => today()->subDays(20),
            'move_in_disputed_at'    => now()->subDay(),
            'move_in_dispute_reason' => 'Landlord did not turn over the keys by the deadline.',
        ]), 'Admin: in "Needs review" with the system sentence, distinct from the tenant\'s wording.'];

        $rows[] = ['Completed — tenant confirmed', $this->make('already occupied', $landlord, $tenant, [
            'status'                      => 'Occupied',
            'keys_turned_over_at'         => now()->subDays(10),
            'tenant_confirmed_move_in_at' => now()->subDays(9),
            'payment_status'              => 'Released',
            'unit_status'                 => 'Occupied',
        ]), 'Tenant: no countdown, no actions. Landlord: normal occupied row.'];

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Escrow scenarios created</>');
        $this->newLine();

        $this->table(
            ['State', 'Reservation', 'What to look for'],
            array_map(fn ($r) => [$r[0], '#' . $r[1]->reservation_id, wordwrap($r[2], 58, "\n")], $rows)
        );

        $this->newLine();
        $this->line('  <options=bold>Log in as</>');
        $this->line('    landlord  ' . $landlord->email . '  /  ' . self::FIXTURE_PASSWORD);
        $this->line('    tenant    ' . $tenant->email . '  /  ' . self::FIXTURE_PASSWORD);
        $this->line('    admin     use your own admin account');
        $this->newLine();
        $this->line('  Tenant agreement pages: /tenant/reservations/{id}/agreement');
        $this->line('  Admin review queue:     /admin/reservations?filter=disputed');
        $this->newLine();
        $this->line('  <fg=yellow>Tonight\'s scheduled run will act on these.</> Re-run this command to reset them,');
        $this->line('  or remove them with <options=bold>php artisan escrow:scenarios --clean</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function make(string $label, $landlord, $tenant, array $o): Reservation
    {
        return $this->makeScenario(array_merge([
            'label'    => $label,
            'landlord' => $landlord,
            'tenant'   => $tenant,
        ], $o));
    }
}
