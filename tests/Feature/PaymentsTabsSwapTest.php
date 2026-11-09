<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Rent and Payouts are two tabs of one Payments page, so switching between them swaps the work area in place
 * (resources/js/page-swap.js) instead of loading a new page. The script finds the tabs by `data-page-swap`, and the
 * swap needs both pages to hand back the same `#main` shell.
 */
class PaymentsTabsSwapTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_both_pages_mark_their_tabs_for_an_in_place_swap(): void
    {
        $landlord = $this->makeLandlord();

        foreach ([route('landlord.payments.index'), route('landlord.payouts.index')] as $url) {
            $html = $this->actingAs($landlord)->get($url)->assertOk()->getContent();

            foreach ([route('landlord.payments.index'), route('landlord.payouts.index')] as $tab) {
                $this->assertMatchesRegularExpression(
                    '/<a href="' . preg_quote($tab, '/') . '"[^>]*data-page-swap/s',
                    $html,
                    "The tab to {$tab} on {$url} is not marked for an in-place swap."
                );
            }

            // The swap replaces the children of <main id="main">, so the tabs and the page header must live inside it.
            $this->assertMatchesRegularExpression('/<main id="main"[^>]*>.*Payments sections.*<\/main>/s', $html);
        }
    }

    public function test_the_payouts_tab_keeps_the_sidebars_payments_item_lit(): void
    {
        $html = $this->actingAs($this->makeLandlord())->get(route('landlord.payouts.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a href="' . preg_quote(route('landlord.payments.index'), '/') . '"\s+aria-current="page"/', $html);
    }

    public function test_the_calendar_script_registers_even_when_a_tab_swap_loads_it_late(): void
    {
        $js = file_get_contents(public_path('js/payments-calendar.js'));

        // alpine:init fires once, at page start. A script added later by a tab swap must register straight away.
        $this->assertStringContainsString('window.Alpine', $js);
        $this->assertStringContainsString("'alpine:init'", $js);
    }
}
