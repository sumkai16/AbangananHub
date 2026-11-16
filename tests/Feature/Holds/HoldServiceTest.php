<?php

namespace Tests\Feature\Holds;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The hold life cycle: flag, confirm or reject, release, and lifting itself once the rent is paid. */
class HoldServiceTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private HoldService $holds;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Cache::flush();
        $this->holds = app(HoldService::class);
    }

    private function admin()
    {
        return $this->makeUserWithRole('Admin');
    }

    private function payAugustToOctober($reservation): void
    {
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makePayment($reservation, ['billing_period' => $month, 'amount' => 5000]);
        }
    }

    public function test_a_landlord_can_flag_their_own_tenant_who_owes_rent(): void
    {
        $this->admin();
        $t = $this->makeTenancy();

        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid, not answering.');

        $this->assertSame(TenantHold::PENDING, $hold->status);
        $this->assertSame($t['tenant']->user_id, $hold->tenant_id);
        $this->assertSame($t['landlord']->user_id, $hold->landlord_id);
        $this->assertSame('15000.00', $hold->amount_owed);
        $this->assertNotNull($hold->flagged_at);
        $this->assertSame('active', $t['tenant']->fresh()->account_status, 'the hold never touches account_status');
        $this->assertTrue(Notification::where('user_id', $t['tenant']->user_id)->where('type', 'account')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'hold.flagged', 'auditable_id' => $hold->hold_id]);
    }

    public function test_a_pending_hold_does_not_restrict_the_tenant_until_confirmed(): void
    {
        $t = $this->makeTenancy();
        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        $this->assertFalse($t['tenant']->isOnHold());

        $this->holds->confirm($hold, $this->admin());

        $this->assertTrue($t['tenant']->isOnHold());
    }

    public function test_only_the_owning_landlord_can_flag(): void
    {
        $t = $this->makeTenancy();
        $stranger = $this->makeLandlord();

        $this->expectException(ValidationException::class);
        $this->holds->flag($stranger, $t['reservation'], 'Three months unpaid.');
    }

    public function test_a_tenant_with_no_overdue_rent_cannot_be_flagged(): void
    {
        $t = $this->makeTenancy();
        $this->payAugustToOctober($t['reservation']);

        $this->expectException(ValidationException::class);
        $this->holds->flag($t['landlord'], $t['reservation']->fresh(), 'Three months unpaid.');
    }

    public function test_the_reason_must_be_long_enough(): void
    {
        $t = $this->makeTenancy();

        $this->expectException(ValidationException::class);
        $this->holds->flag($t['landlord'], $t['reservation'], 'too short');
    }

    public function test_one_open_hold_per_tenancy(): void
    {
        $t = $this->makeTenancy();
        $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        $this->expectException(ValidationException::class);
        $this->holds->flag($t['landlord'], $t['reservation'], 'Flagging the same tenancy again.');
    }

    public function test_confirm_only_works_on_a_pending_hold(): void
    {
        $t = $this->makeTenancy();
        $admin = $this->admin();
        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        $confirmed = $this->holds->confirm($hold, $admin);
        $this->assertSame(TenantHold::ACTIVE, $confirmed->status);
        $this->assertSame($admin->user_id, $confirmed->confirmed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hold.confirmed', 'auditable_id' => $hold->hold_id]);

        $this->expectException(ValidationException::class);
        $this->holds->confirm($hold, $admin);
    }

    public function test_reject_needs_a_note_and_ends_the_hold(): void
    {
        $t = $this->makeTenancy();
        $admin = $this->admin();
        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        try {
            $this->holds->reject($hold, $admin, '   ');
            $this->fail('A blank note should be refused.');
        } catch (ValidationException) {
        }

        $rejected = $this->holds->reject($hold, $admin, 'The bill is disputed in chat.');
        $this->assertSame(TenantHold::REJECTED, $rejected->status);
        $this->assertSame('The bill is disputed in chat.', $rejected->rejected_note);
        $this->assertFalse($t['tenant']->isOnHold());
        $this->assertDatabaseHas('audit_logs', ['action' => 'hold.rejected', 'auditable_id' => $hold->hold_id]);
    }

    public function test_release_ends_the_hold_and_every_copy_of_it(): void
    {
        $t = $this->makeTenancy();
        $admin = $this->admin();
        $hold = $this->holds->confirm($this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.'), $admin);

        $other = $this->makeTenant();
        $copy = TenantHold::create([
            'tenant_id' => $other->user_id, 'source_hold_id' => $hold->hold_id, 'reason' => 'Duplicate account',
            'status' => TenantHold::ACTIVE, 'flagged_at' => now(),
        ]);

        $this->holds->release($hold, $admin, 'admin');

        $this->assertSame(TenantHold::RELEASED, $hold->fresh()->status);
        $this->assertSame('admin', $hold->fresh()->release_reason);
        $this->assertSame(TenantHold::RELEASED, $copy->fresh()->status);
        $this->assertSame('source_released', $copy->fresh()->release_reason);
        $this->assertFalse($t['tenant']->isOnHold());
        $this->assertFalse($other->isOnHold());
        $this->assertSame(2, AuditLog::where('action', 'hold.released')->count());

        $this->expectException(ValidationException::class);
        $this->holds->release($hold, $admin, 'admin');
    }

    public function test_release_refuses_an_unknown_reason(): void
    {
        $t = $this->makeTenancy();
        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        $this->expectException(\InvalidArgumentException::class);
        $this->holds->release($hold, null, 'because');
    }

    public function test_the_tenant_can_leave_a_note(): void
    {
        $this->admin();
        $t = $this->makeTenancy();
        $hold = $this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.');

        $this->holds->dispute($hold, 'I paid cash on the 3rd.');

        $this->assertSame('I paid cash on the 3rd.', $hold->fresh()->tenant_note);
    }

    public function test_release_if_paid_waits_while_rent_is_overdue_then_lifts_once_paid(): void
    {
        $t = $this->makeTenancy();
        $hold = $this->holds->confirm($this->holds->flag($t['landlord'], $t['reservation'], 'Three months unpaid.'), $this->admin());

        $this->assertFalse($this->holds->releaseIfPaid($hold));
        $this->assertTrue($hold->fresh()->isOpen());

        $this->payAugustToOctober($t['reservation']);

        $this->assertTrue($this->holds->releaseIfPaid($hold->fresh()));
        $this->assertSame('paid', $hold->fresh()->release_reason);
        $this->assertNull($hold->fresh()->released_by);
    }

    public function test_a_cancelled_tenancy_drops_the_debt_but_a_completed_one_does_not(): void
    {
        $a = $this->makeTenancy();
        $holdA = $this->holds->flag($a['landlord'], $a['reservation'], 'Three months unpaid.');
        $a['reservation']->update(['rental_status' => 'Cancelled']);

        $this->assertTrue($this->holds->releaseIfPaid($holdA->fresh()));
        $this->assertSame('debt_dropped', $holdA->fresh()->release_reason);

        $b = $this->makeTenancy();
        $holdB = $this->holds->flag($b['landlord'], $b['reservation'], 'Three months unpaid.');
        $b['reservation']->update(['rental_status' => 'Completed']);

        $this->assertFalse($this->holds->releaseIfPaid($holdB->fresh()), 'unpaid rent on an ended tenancy is still owed');
    }

    public function test_a_hold_without_a_reservation_is_never_released_on_its_own(): void
    {
        $copy = TenantHold::create([
            'tenant_id' => $this->makeTenant()->user_id, 'reason' => 'Duplicate account',
            'status' => TenantHold::ACTIVE, 'flagged_at' => now(),
        ]);

        $this->assertFalse($this->holds->releaseIfPaid($copy));
    }

    public function test_the_command_releases_paid_holds_only(): void
    {
        $paid = $this->makeTenancy();
        $owing = $this->makeTenancy();
        $admin = $this->admin();
        $holdPaid = $this->holds->confirm($this->holds->flag($paid['landlord'], $paid['reservation'], 'Three months unpaid.'), $admin);
        $holdOwing = $this->holds->confirm($this->holds->flag($owing['landlord'], $owing['reservation'], 'Three months unpaid.'), $admin);
        $this->payAugustToOctober($paid['reservation']);

        $this->artisan('holds:release-paid')->expectsOutput('Released 1 hold(s).')->assertSuccessful();

        $this->assertSame(TenantHold::RELEASED, $holdPaid->fresh()->status);
        $this->assertSame(TenantHold::ACTIVE, $holdOwing->fresh()->status);
    }
}
