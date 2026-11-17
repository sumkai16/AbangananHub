<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DepositCharge;
use App\Models\HoldMatchAlert;
use App\Models\LandlordVerification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\AdminQueues;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\DefenseAdminSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LandlordSeeder;
use Database\Seeders\PropertySeeder;
use Database\Seeders\ReportSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\TenantSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin side of the defense world: after the base seeders and DefenseAdminSeeder, every admin queue has something
 * to show, and running the seeder again adds nothing. Rolled back with the test, files go to a fake disk.
 */
class DefenseAdminSeedTest extends TestCase
{
    use DatabaseTransactions;

    private function seedWorld(): void
    {
        config(['broadcasting.default' => 'null']);
        Storage::fake('local');

        $this->seed([
            UserSeeder::class, LandlordSeeder::class, AmenitySeeder::class, PropertySeeder::class, TenantSeeder::class,
            ReviewSeeder::class, ReportSeeder::class, DemoSeeder::class, DefenseAdminSeeder::class,
        ]);
    }

    /** @return array<string, int> */
    private function queues(): array
    {
        return collect(AdminQueues::rows())->pluck('count', 'key')->all() + ['payouts' => AdminQueues::payoutsToAct()];
    }

    public function test_every_admin_queue_has_something_waiting(): void
    {
        $this->seedWorld();
        $q = $this->queues();

        $this->assertGreaterThanOrEqual(3, $q['verifications'], 'landlord applications');
        $this->assertGreaterThanOrEqual(3, $q['listings'], 'listings waiting for approval (Guadalupe plus two)');
        $this->assertSame(3, $q['documents'], 'uploaded documents to verify');
        $this->assertSame(2, $q['units'], 'units needing their own approval');
        $this->assertGreaterThanOrEqual(9, $q['reports'], 'pending reports');
        $this->assertSame(1, $q['disputes'], 'move-in dispute on held money');
        $this->assertSame(1, $q['escalations'], 'refund not returned');
        // Owed (Ramon) + two accounts to approve + one failed payout.
        $this->assertSame(4, $q['payouts'], 'payouts to act on');
        $this->assertSame(2, TenantHold::where('status', TenantHold::PENDING)->count() + HoldMatchAlert::open()->count());
    }

    public function test_the_applications_are_viewable_and_the_rejected_one_is_kept(): void
    {
        $this->seedWorld();

        $pending = LandlordVerification::where('verification_status', 'Pending')->where('user_id', '!=', User::where('email', 'landlord.pending@abangananhub.com')->value('user_id'))->get();
        $this->assertCount(2, $pending);
        foreach ($pending as $v) {
            Storage::disk('local')->assertExists($v->government_id);
            Storage::disk('local')->assertExists($v->selfie);
        }
        $this->assertSame(['match', 'review'], $pending->sortBy('verification_id')->pluck('face_match_status')->all());
        $this->assertSame(1, LandlordVerification::where('verification_status', 'Rejected')->count());
    }

    public function test_the_pending_listings_are_one_complete_and_one_thin(): void
    {
        $this->seedWorld();

        $complete = Property::where('title', 'Tunghaan Annex Rooms, Minglanilla')->withCount(['media', 'units'])->firstOrFail();
        $thin = Property::where('title', 'Aloguinsan Seaside Annex')->withCount(['media', 'units'])->firstOrFail();

        $this->assertSame('Pending', $complete->verification_status);
        $this->assertGreaterThan(0, $complete->media_count);
        $this->assertSame(3, $complete->units_count);
        $this->assertSame(0, $thin->media_count);
        $this->assertSame(1, $thin->units_count);
    }

    public function test_documents_cover_pending_verified_rejected_and_requested(): void
    {
        $this->seedWorld();

        $byStatus = PropertyDocument::query()->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $this->assertSame(4, (int) $byStatus['Pending']);   // three uploaded, one only requested
        $this->assertSame(1, (int) $byStatus['Verified']);
        $this->assertSame(1, (int) $byStatus['Rejected']);
        $this->assertSame(3, PropertyDocument::where('status', 'Pending')->whereNotNull('file_path')->count());
        PropertyDocument::whereNotNull('file_path')->get()->each(fn ($d) => Storage::disk('local')->assertExists($d->file_path));
    }

    public function test_reports_have_pending_and_resolved_with_actions(): void
    {
        $this->seedWorld();

        $this->assertGreaterThanOrEqual(9, Report::where('report_status', 'Pending')->count());
        $this->assertGreaterThanOrEqual(8, Report::where('report_status', 'Resolved')->count());
        $this->assertTrue(Report::where('action_taken', 'warn_user')->exists());
        $this->assertTrue(Report::where('action_taken', 'delist_property')->exists());
    }

    public function test_the_refund_dispute_is_held_online_money_and_the_escalation_is_kept_direct_money(): void
    {
        $this->seedWorld();

        $dispute = AdminQueues::moveInDisputes()->with('payments')->sole();
        $this->assertSame('Rental Agreement Signed', $dispute->rental_status);
        $this->assertSame(2, $dispute->payments->where('status', 'Held')->count());
        // Rent plus a deposit of the same size, both held under one PayMongo payment.
        $this->assertSame(2 * (float) $dispute->agreed_monthly_rent, (float) $dispute->payments->where('status', 'Held')->sum('amount'));
        $this->assertSame(1, $dispute->payments->pluck('paymongo_payment_id')->unique()->count(), 'one PayMongo payment, so Refund is offered');

        $kept = AdminQueues::escalations()->select('reservations.*')->sole();
        $this->assertSame(3500.0, $kept->keptDirectAmount());
        $this->assertSame('Pending', $kept->refundReport->report_status);
        $this->assertStringContainsString('Refund not returned', $kept->refundReport->report_reason);
        $this->assertSame($kept->property->landlord_id, $kept->refundReport->reported_user_id);
    }

    /**
     * A real move-in dispute has two held rows (rent and deposit under one PayMongo payment). Lazy-loading protection only
     * trips when a query returns more than one row, so RefundRequestsTest (one held row) never sees what this does:
     * RefundRequestController::index loads `payments` without `payments.reservation`, and the shared payments
     * `_actions` partial reads `$payment->reservation`. Until the controller eager-loads it, this test fails.
     */
    public function test_the_refund_requests_page_renders_a_two_row_dispute_and_the_escalation(): void
    {
        $this->seedWorld();

        $this->actingAs(User::where('email', 'admin@abangananhub.com')->first())
            ->get(route('admin.refund-requests.index'))->assertOk()
            ->assertSee('Marites Dacillo')->assertSee('Jaypee Marapao')->assertSee('Review report');
    }

    public function test_payouts_show_owed_to_approve_failed_and_sent(): void
    {
        $this->seedWorld();

        $this->assertSame(1, Payment::pendingPayout()->join('reservations', 'reservations.reservation_id', '=', 'payments.reservation_id')
            ->join('properties', 'properties.property_id', '=', 'reservations.property_id')->distinct()->count('properties.landlord_id'));
        $this->assertSame(2, UserPayoutAccount::whereNull('payout_approved_at')->whereNotNull('gcash_number')->count());
        $this->assertSame(1, Payout::where('status', 'Failed')->whereNull('resolved_at')->count());
        $this->assertSame(1, Payout::where('status', 'Sent')->count());

        $this->actingAs(User::where('email', 'admin@abangananhub.com')->first())->get(route('admin.payouts.index'))->assertOk();
    }

    public function test_holds_have_pending_active_released_and_a_duplicate_alert(): void
    {
        $this->seedWorld();

        $this->assertSame(1, TenantHold::where('status', TenantHold::PENDING)->count());
        $this->assertSame(1, TenantHold::where('status', TenantHold::ACTIVE)->count());
        $this->assertSame(1, TenantHold::where('status', TenantHold::RELEASED)->count());
        $alert = HoldMatchAlert::open()->sole();
        $this->assertSame(TenantHold::ACTIVE, $alert->hold->status);
        $this->assertContains('phone', $alert->signals);

        $this->actingAs(User::where('email', 'admin@abangananhub.com')->first())->get(route('admin.tenant-holds.index'))->assertOk();
    }

    public function test_deposit_charges_are_two_active_and_one_voided(): void
    {
        $this->seedWorld();

        $this->assertSame(2, DepositCharge::whereNull('voided_at')->count());
        $this->assertSame(1, DepositCharge::whereNotNull('voided_at')->count());
        $this->assertSame('Completed', DepositCharge::first()->reservation->rental_status);
    }

    public function test_the_payments_page_has_every_status(): void
    {
        $this->seedWorld();

        $present = Payment::query()->select('status')->distinct()->pluck('status')->all();
        foreach (['Held', 'Released', 'Paid', 'Pending', 'Voided', 'Refunded'] as $status) {
            $this->assertContains($status, $present, "no {$status} payment");
        }
    }

    public function test_the_activity_log_spans_days_and_actions_and_its_chain_is_intact(): void
    {
        $this->seedWorld();

        $rows = AuditLog::orderBy('log_id')->get();
        $this->assertGreaterThanOrEqual(12, $rows->count());
        $this->assertGreaterThanOrEqual(8, $rows->pluck('action')->unique()->count());
        $this->assertGreaterThanOrEqual(5, $rows->map(fn ($r) => $r->created_at->toDateString())->unique()->count());

        $previous = null;
        foreach ($rows as $row) {
            if ($previous !== null) {
                $this->assertSame($previous->row_hash, $row->prev_hash, 'the hash chain is broken at log '.$row->log_id);
            }
            $previous = $row;
        }
    }

    public function test_ratings_have_enough_reviews_and_a_suspended_user_exists(): void
    {
        $this->seedWorld();

        $this->assertGreaterThanOrEqual(40, DB::table('reviews')->count());
        $this->assertSame(1, User::where('account_status', 'Suspended')->count());
        $bell = DB::table('notifications')->where('user_id', User::where('email', 'admin@abangananhub.com')->value('user_id'))->where('is_read', false);
        $this->assertGreaterThanOrEqual(5, (clone $bell)->count());
        foreach (['GCash account to approve', 'Refund not returned', 'Move-in dispute', 'New landlord application'] as $title) {
            $this->assertTrue((clone $bell)->where('title', $title)->exists(), "no unread '{$title}' notification");
        }
    }

    public function test_running_the_seeder_twice_adds_nothing(): void
    {
        $this->seedWorld();

        $tables = ['users', 'properties', 'property_units', 'property_media', 'unit_media', 'reservations', 'payments', 'payouts', 'reports', 'landlord_verifications',
            'property_documents', 'tenant_holds', 'hold_match_alerts', 'deposit_charges', 'audit_logs', 'notifications', 'conversations', 'messages'];
        $count = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();

        $before = $count();
        $this->seed(DefenseAdminSeeder::class);

        $this->assertSame($before, $count());
    }

    public function test_the_scenarios_name_real_accounts_and_pages(): void
    {
        $this->seedWorld();

        $keys = array_column(DefenseAdminSeeder::SCENARIOS, 'key');
        $this->assertSame($keys, array_unique($keys), 'scenario keys are unique');
        $this->assertGreaterThanOrEqual(19, count($keys));

        foreach (DefenseAdminSeeder::SCENARIOS as $scenario) {
            $this->assertNotEmpty($scenario['shows']);
            $this->assertStringStartsWith('/', $scenario['where']);
            $this->assertTrue(User::where('email', $scenario['who'])->exists(), $scenario['key'].': no such account '.$scenario['who']);
        }
    }

    public function test_the_admin_pages_render_with_the_seeded_data(): void
    {
        $this->seedWorld();
        $admin = User::where('email', 'admin@abangananhub.com')->first();

        foreach ([
            'admin.dashboard' => [], 'admin.verifications.index' => ['status' => 'Pending'], 'admin.listings.approval' => ['status' => 'Pending'],
            'admin.documents.index' => ['status' => 'Pending'], 'admin.catalogue.units.index' => ['needs' => 'approval'], 'admin.reports.index' => ['status' => 'Pending'],
            'admin.payments.index' => ['status' => 'All'], 'admin.deposit-charges.index' => [], 'admin.audit-logs.index' => [], 'admin.users.index' => ['status' => 'Suspended'],
            'admin.reservations.index' => [], 'admin.conversations.index' => [],
        ] as $route => $query) {
            $this->actingAs($admin)->get(route($route, $query))->assertOk();
        }
    }
}
