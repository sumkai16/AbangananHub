<?php

namespace Tests\Feature\Payments;

use App\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentsPageRemindersTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function overdueTenancy($landlord = null): array
    {
        // Moved in in August with nothing paid: August to October are all past due.
        return $this->makeTenancy(['target_move_in_date' => '2026-08-01', 'reservation_date' => '2026-08-01'], $landlord);
    }

    public function test_the_page_is_called_payments_and_offers_reminders_for_overdue_tenants(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->overdueTenancy();

        $this->actingAs($landlord)->get(route('landlord.payments.index'))
            ->assertOk()
            ->assertSee('Remind overdue (1)', false)
            ->assertSee(route('landlord.tenancies.remind', $reservation), false)
            ->assertDontSee('Rent &amp; Payments', false);
    }

    public function test_remind_overdue_notifies_each_overdue_tenant_once_a_day(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant] = $this->overdueTenancy();

        $this->actingAs($landlord)->post(route('landlord.payments.remindOverdue'))
            ->assertSessionHas('success');

        $first = Notification::where('user_id', $tenant->user_id)->where('title', 'Rent overdue')->count();
        $this->assertSame(1, $first);

        // A second click the same day sends nothing new and says why.
        $this->actingAs($landlord)->post(route('landlord.payments.remindOverdue'))
            ->assertSessionHas('warning');
        $this->assertSame(1, Notification::where('user_id', $tenant->user_id)->where('title', 'Rent overdue')->count());
    }

    public function test_walk_ins_and_other_landlords_tenants_are_not_reminded(): void
    {
        ['landlord' => $landlord, 'tenant' => $walkIn] = $this->overdueTenancy();
        $walkIn->forceFill(['is_walk_in' => true])->save();
        ['tenant' => $stranger] = $this->overdueTenancy();

        $this->actingAs($landlord)->post(route('landlord.payments.remindOverdue'))
            ->assertSessionHas('warning');

        $this->assertSame(0, Notification::where('user_id', $walkIn->user_id)->count());
        $this->assertSame(0, Notification::where('user_id', $stranger->user_id)->count());
    }

    public function test_only_landlords_can_use_it(): void
    {
        ['tenant' => $tenant] = $this->overdueTenancy();

        $this->actingAs($tenant)->post(route('landlord.payments.remindOverdue'))->assertForbidden();
    }
}
