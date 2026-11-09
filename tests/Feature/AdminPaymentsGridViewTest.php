<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Admin Payments offers a list (the table) and a grid of cards, like Reservations. Both views are on the page and the
 * choice is kept in the browser, so the controller is untouched. The Release and Refund actions live in one partial
 * (admin/payments/partials/_actions) that both views include, so they cannot drift apart.
 */
class AdminPaymentsGridViewTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function pageWithThreePayments(): array
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $held = $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);
        $released = $this->makePayment($t['reservation'], ['status' => 'Released', 'amount' => 7000, 'released_at' => now()->setDate(2026, 11, 3)]);
        $recorded = $this->makePayment($t['reservation'], ['status' => 'Paid', 'amount' => 3000, 'recorded_by' => $t['landlord']->user_id]);

        $html = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.payments.index'))->assertOk()->getContent();

        return compact('html', 'held', 'released', 'recorded');
    }

    public function test_the_filter_bar_has_a_grid_and_list_toggle_that_is_remembered(): void
    {
        ['html' => $html] = $this->pageWithThreePayments();

        $this->assertStringContainsString('aria-label="Grid view"', $html);
        $this->assertStringContainsString('aria-label="List view"', $html);
        $this->assertStringContainsString('adminPaymentsView', $html);
        // The list is the default, as on Reservations.
        $this->assertMatchesRegularExpression("/localStorage\.getItem\('adminPaymentsView'\) \|\| 'table'/", $html);
    }

    public function test_the_grid_has_one_card_per_payment_with_its_figures(): void
    {
        ['html' => $html] = $this->pageWithThreePayments();

        $this->assertSame(3, substr_count($html, 'data-payment-card'));
        // Everything after the first marker is grid markup (the cards come last, before the footer).
        $cards = implode('', array_slice(explode('data-payment-card', $html), 1));
        foreach (['₱5,000.00', '₱7,000.00', '₱3,000.00'] as $amount) {
            $this->assertStringContainsString($amount, $cards, "{$amount} should show on a card.");
        }
        $this->assertStringContainsString('Released Nov 03, 2026', $cards);
        $this->assertStringContainsString('Recorded by landlord', $cards);
    }

    public function test_release_and_refund_are_offered_in_both_views_from_one_partial(): void
    {
        ['html' => $html, 'held' => $held, 'released' => $released] = $this->pageWithThreePayments();

        // The Release form posts to the payment's own address: once from the table row, once from the card.
        $this->assertSame(2, substr_count($html, route('admin.payments.release', $held)));
        $this->assertSame(0, substr_count($html, route('admin.payments.release', $released)));
        $this->assertSame(2, substr_count($html, 'Release this payment to the landlord?'));
    }

    public function test_a_status_tab_drops_the_status_pill_in_the_grid_as_it_does_in_the_list(): void
    {
        $this->pageWithThreePayments();
        $held = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.payments.index', ['status' => 'Held']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($held, 'data-payment-card'));
        // The card carries no status pill of its own when a tab already names the status.
        $this->assertDoesNotMatchRegularExpression('/data-payment-card.*?data-payment-status/s', $held);
    }

    public function test_an_empty_list_has_no_grid(): void
    {
        $html = $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.payments.index', ['search' => 'zzz-no-such-person']))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'data-payment-card'));
    }
}
