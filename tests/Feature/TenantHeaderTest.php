<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * PM feedback on the tenant pages (plans/tenant-feedback-nov-5.md): a shorter header for signed-in users, the message
 * icon beside the bell, red badges on both, and no "Become a Landlord" (sign-up has its own path now).
 */
class TenantHeaderTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function header(string $html): string
    {
        $start = strpos($html, '<header id="site-header"');
        $this->assertNotFalse($start, 'The page has no site header.');

        return substr($html, $start, strpos($html, '</header>', $start) - $start);
    }

    public function test_a_signed_in_tenant_has_no_areas_or_how_it_works_in_the_header(): void
    {
        $html = $this->actingAs($this->makeTenant())->get(route('home'))->assertOk()->getContent();
        $header = $this->header($html);

        $this->assertStringNotContainsString('href="'.route('properties.areas').'"', $header);
        $this->assertStringNotContainsString(route('about').'#how-it-works', $header);
        // Still reachable: the landing page itself carries the section.
        $this->assertStringContainsString(route('about').'#how-it-works', $html);
    }

    public function test_a_guest_keeps_areas_and_how_it_works_in_the_header(): void
    {
        $header = $this->header($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('properties.areas').'"', $header);
        $this->assertStringContainsString(route('about').'#how-it-works', $header);
    }

    public function test_a_tenant_is_not_offered_become_a_landlord_or_the_landlord_footer_links(): void
    {
        $this->actingAs($this->makeTenant())->get(route('home'))->assertOk()
            ->assertDontSee('Become a Landlord')
            ->assertDontSee('For Landlords')
            // The one way left for an existing tenant account to apply.
            ->assertSee('List your property');
    }

    public function test_a_landlord_keeps_the_landlord_footer_links_without_become_a_landlord(): void
    {
        $this->actingAs($this->makeLandlord())->get(route('home'))->assertOk()
            ->assertSee('For Landlords')
            ->assertSee('Landlord Dashboard')
            ->assertDontSee('Become a Landlord');
    }

    public function test_the_about_page_does_not_send_applicants_to_a_menu_item_that_is_gone(): void
    {
        $this->get(route('about'))->assertOk()->assertDontSee('in your account menu');
    }

    public function test_the_message_icon_sits_right_beside_the_bell_at_every_width(): void
    {
        foreach ([$this->makeTenant(), $this->makeLandlord()] as $user) {
            $header = $this->header($this->actingAs($user)->get(route('home'))->assertOk()->getContent());

            $this->assertSame(1, preg_match('/<a [^>]*aria-label="Messages"[^>]*>/', $header, $link), 'No message icon in the header.');
            $this->assertStringNotContainsString('hidden', $link[0], 'The message icon is hidden at some width.');

            $messages = strpos($header, 'aria-label="Messages"');
            $bell = strpos($header, 'aria-label="Notifications"');
            $this->assertGreaterThan($messages, $bell);

            $between = substr($header, $messages, $bell - $messages);
            $this->assertStringNotContainsString('<a ', $between, 'A link sits between the message icon and the bell.');
            $this->assertSame(1, substr_count($between, '<button'), 'Only the bell button should follow the message icon.');
        }
    }

    public function test_the_message_badge_follows_the_live_count_and_both_badges_are_red(): void
    {
        $t = $this->makeTenancy();
        Notification::notify($t['tenant']->user_id, 'payment', 'Rent overdue', 'Your rent is overdue.');

        $header = $this->header($this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent());

        $this->assertStringContainsString('x-show="$store.navBadges.messages > 0"', $header);
        $this->assertStringContainsString('$store.navBadges.messages > 99', $header);
        $this->assertSame(2, substr_count($header, 'bg-[#EF4444] ring-2 ring-white text-white'), 'The bell and the message icon should share one red badge.');
        $this->assertStringNotContainsString('bg-[#FF8A66] ring-2 ring-white', $header);
    }

    public function test_the_floating_bubble_is_gone_from_tenant_pages_but_message_pop_ups_stay(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertDontSee('w-[52px] h-[52px]', false)
            ->assertSee('messageNotifications()', false);

        $this->actingAs($t['landlord'])->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('w-[52px] h-[52px]', false);
    }

    public function test_the_search_button_has_room_inside_the_one_line_search_box(): void
    {
        $this->get(route('properties.index', ['type' => 'Apartment']))->assertOk()
            ->assertSee('pl-5 pr-2.5 py-2.5', false)
            ->assertDontSee('pl-4 pr-1.5 py-1.5', false);
    }
}
