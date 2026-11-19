<?php

namespace Tests\Feature\Holds;

use App\Models\Notification;
use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The tenant's /account/on-hold page: what is owed, how to pay, the dispute note, and lifting itself once paid. */
class OnHoldPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Cache::flush();
        $this->makeUserWithRole('Admin');
    }

    /** A tenancy with three unpaid months (Aug to Oct) and a hold on it in the given status. */
    private function heldTenancy(string $status = TenantHold::ACTIVE): array
    {
        $t = $this->makeTenancy();
        $hold = app(HoldService::class)->flag($t['landlord'], $t['reservation'], 'Three months unpaid, not answering.');
        if ($status === TenantHold::ACTIVE) {
            app(HoldService::class)->confirm($hold, $this->makeUserWithRole('Admin'));
        }

        return $t + ['hold' => $hold->fresh()];
    }

    public function test_a_tenant_with_no_hold_is_sent_home(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->get(route('account.on-hold'))
            ->assertRedirect($tenant->homeRoute())
            ->assertSessionHas('success');
    }

    public function test_an_active_hold_shows_the_amount_the_reason_and_the_pay_and_chat_links(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);

        $this->actingAs($t['tenant'])->get(route('account.on-hold'))
            ->assertOk()
            ->assertSee('₱15,000')
            ->assertSee('Three months unpaid, not answering.')
            ->assertSee('until this rent is paid')
            ->assertSee(route('tenancy.show', $t['reservation']), false)
            ->assertSee(route('tenancy.chat', $t['reservation']), false)
            ->assertSee('new reservations');
    }

    public function test_a_pending_hold_says_an_admin_is_reviewing_it_and_still_offers_payment(): void
    {
        $t = $this->heldTenancy(TenantHold::PENDING);

        $this->actingAs($t['tenant'])->get(route('account.on-hold'))
            ->assertOk()
            ->assertSee('An admin is reviewing this report')
            ->assertSee('Pending review')
            ->assertSee(route('tenancy.show', $t['reservation']), false);
    }

    public function test_a_hold_an_admin_made_shows_a_neutral_message_and_leaks_nothing(): void
    {
        $source = $this->heldTenancy(TenantHold::ACTIVE);
        $newUser = $this->makeTenant();
        $copy = TenantHold::create([
            'tenant_id'      => $newUser->user_id,
            'landlord_id'    => null,
            'reservation_id' => null,
            'source_hold_id' => $source['hold']->hold_id,
            'amount_owed'    => 0,
            'reason'         => 'Possible duplicate account of a tenant with an unpaid-rent hold.',
            'status'         => TenantHold::ACTIVE,
            'flagged_at'     => now(),
        ]);

        $page = $this->actingAs($newUser)->get(route('account.on-hold'))->assertOk();

        $page->assertSee('on hold while we review it')
            ->assertDontSee('Pay now')
            ->assertDontSee('Message your landlord')
            ->assertDontSee('duplicate')
            ->assertDontSee('unpaid-rent hold')
            ->assertDontSee($source['tenant']->first_name)
            ->assertDontSee($source['landlord']->first_name);
        $this->assertNotNull($copy->hold_id);
    }

    public function test_the_tenant_can_dispute_and_the_admins_are_told(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($t['tenant'])
            ->from(route('account.on-hold'))
            ->post(route('account.on-hold.dispute', $t['hold']), ['note' => 'I paid in cash on the 3rd, ask her.'])
            ->assertRedirect(route('account.on-hold'))
            ->assertSessionHas('success');

        $this->assertSame('I paid in cash on the 3rd, ask her.', $t['hold']->fresh()->tenant_note);
        $this->assertTrue(Notification::where('user_id', $admin->user_id)->where('title', 'Tenant replied to a hold')->exists());

        $this->actingAs($t['tenant'])->get(route('account.on-hold'))->assertSee('I paid in cash on the 3rd, ask her.');
    }

    public function test_a_dispute_needs_a_note_of_reasonable_length(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);

        $this->actingAs($t['tenant'])->post(route('account.on-hold.dispute', $t['hold']), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->actingAs($t['tenant'])->post(route('account.on-hold.dispute', $t['hold']), ['note' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('note');
        $this->actingAs($t['tenant'])->post(route('account.on-hold.dispute', $t['hold']), ['note' => ['x']])
            ->assertSessionHasErrors('note');
        $this->assertNull($t['hold']->fresh()->tenant_note);
    }

    public function test_a_tenant_cannot_dispute_someone_elses_hold(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);
        $stranger = $this->makeTenant();

        $this->actingAs($stranger)->post(route('account.on-hold.dispute', $t['hold']), ['note' => 'Not mine to answer.'])
            ->assertForbidden();
        $this->assertNull($t['hold']->fresh()->tenant_note);
    }

    public function test_a_finished_hold_cannot_be_disputed(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);
        app(HoldService::class)->release($t['hold'], $this->makeUserWithRole('Admin'), 'admin');

        $this->actingAs($t['tenant'])->post(route('account.on-hold.dispute', $t['hold']), ['note' => 'Too late.'])
            ->assertForbidden();
    }

    public function test_opening_the_page_lifts_the_hold_once_the_rent_is_paid(): void
    {
        $t = $this->heldTenancy(TenantHold::ACTIVE);
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makePayment($t['reservation'], ['billing_period' => $month, 'amount' => 5000]);
        }

        $this->actingAs($t['tenant'])->get(route('account.on-hold'))
            ->assertRedirect($t['tenant']->homeRoute());

        $hold = $t['hold']->fresh();
        $this->assertSame(TenantHold::RELEASED, $hold->status);
        $this->assertSame('paid', $hold->release_reason);
        $this->assertFalse($t['tenant']->fresh()->isOnHold());
    }
}
