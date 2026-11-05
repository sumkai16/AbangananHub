<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The tenant site header follows the page width, the bell is visible and numbered, and a pop-up exists for live notices. */
class ShellNavbarAndToastTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const CAPPED_HEADER = 'max-w-[1400px] mx-auto flex items-center justify-between px-4 sm:px-6 lg:px-8 h-[64px]';

    private const WIDE_HEADER = 'max-w-none mx-auto flex items-center justify-between px-4 sm:px-6 lg:px-8 h-[64px]';

    public function test_the_header_runs_full_width_on_the_messages_page_like_the_page_does(): void
    {
        $t = $this->makeTenancy();

        $html = $this->actingAs($t['tenant'])->get(route('conversations.index'))->assertOk()->getContent();

        $this->assertStringContainsString(self::WIDE_HEADER, $html);
        $this->assertStringNotContainsString(self::CAPPED_HEADER, $html);
    }

    public function test_the_header_stays_capped_on_other_tenant_pages(): void
    {
        $t = $this->makeTenancy();

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString(self::CAPPED_HEADER, $html);
        $this->assertStringNotContainsString(self::WIDE_HEADER, $html);
    }

    public function test_the_tenant_bell_shows_a_numbered_coral_badge(): void
    {
        $t = $this->makeTenancy();
        Notification::notify($t['tenant']->user_id, 'payment', 'Rent overdue', 'Your rent is overdue.');

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString("x-text=\"unreadCount > 99 ? '99+' : unreadCount\"", $html);
        $this->assertStringContainsString('unreadCount: 1', $html);
        $this->assertStringContainsString('bg-[#FF8A66] ring-2 ring-white', $html);
    }

    public function test_both_shells_carry_the_notification_pop_up(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()->assertSee('noticeToasts()', false)->assertSee('.NotificationCreated', false);

        $this->actingAs($t['landlord'])->get(route('landlord.dashboard'))
            ->assertOk()->assertSee('noticeToasts()', false);
    }

    public function test_the_record_modal_is_two_columns_on_large_screens_and_holds_the_full_balance_shortcut(): void
    {
        $t = $this->makeTenancy();

        $html = $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('lg:max-w-3xl', $html);
        $this->assertStringContainsString('lg:grid lg:grid-cols-2', $html);
        $this->assertStringContainsString('Fill full balance', $html);
        // The shortcut left the balance tile for the modal.
        $this->assertStringNotContainsString('Record full balance', $html);
    }
}
