<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The dashboard lists the bell's own notifications, each opening the page it is about, instead of a "Recent activity" feed. */
class LandlordDashboardNotificationsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    public function test_the_card_is_notifications_and_not_recent_activity(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Nothing new')
            ->assertDontSee('Recent activity')
            ->assertSee('href="'.route('notifications.index').'"', false)
            // The old "View all" went to Reservations; nothing in this card should any more.
            ->assertDontSee('No activity yet');
    }

    public function test_rows_are_real_notifications_that_open_through_the_open_route(): void
    {
        $landlord = $this->makeLandlord();
        $payment = Notification::notify($landlord->user_id, 'payment', 'Rent paid online', 'Ana paid ₱5,000.', '/landlord/payments');
        $review = Notification::notify($landlord->user_id, 'review', 'New review', 'Five stars at Sunrise House.', '/landlord/reviews');

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('Rent paid online')
            ->assertSee('Five stars at Sunrise House.')
            ->assertSee(route('notifications.open', $payment), false)
            ->assertSee(route('notifications.open', $review), false);
    }

    public function test_at_most_six_are_listed_newest_first_and_chat_messages_are_left_out(): void
    {
        $landlord = $this->makeLandlord();
        foreach (range(1, 8) as $i) {
            Notification::notify($landlord->user_id, 'payment', "Notice number {$i}", 'Details.', '/landlord/payments');
        }
        Notification::notify($landlord->user_id, 'message', 'Chat ping', 'Hello', '/conversations');

        $html = $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()->getContent();

        $this->assertSame(6, preg_match_all('/Notice number \d/', $html));
        $this->assertStringContainsString('Notice number 8', $html);
        $this->assertStringNotContainsString('Notice number 2', $html);
        $this->assertStringNotContainsString('Chat ping', $html);
    }

    public function test_another_landlords_notifications_are_not_shown(): void
    {
        $mine = $this->makeLandlord();
        $other = $this->makeLandlord();
        Notification::notify($other->user_id, 'payment', 'Someone else only', 'Private.', '/x');

        $this->actingAs($mine)->get(route('landlord.dashboard'))->assertOk()->assertDontSee('Someone else only');
    }
}
