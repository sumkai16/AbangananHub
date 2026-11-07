<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * "My Unit" became "My Rentals" (plans/my-rentals-page.md): one list of every place a tenant rents or has rented,
 * each opening the existing single-rental page.
 */
class MyRentalsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function header(string $html): string
    {
        $start = strpos($html, '<header id="site-header"');
        $this->assertNotFalse($start, 'The page has no site header.');

        return substr($html, $start, strpos($html, '</header>', $start) - $start);
    }

    public function test_every_rental_of_the_tenant_is_a_card_that_opens_its_own_page(): void
    {
        $first = $this->makeTenancy();
        $second = $this->makeTenancy([], null, $first['tenant']);

        $html = $this->actingAs($first['tenant'])->get(route('tenancy.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('tenancy.show', $first['reservation']).'"', $html);
        $this->assertStringContainsString('href="'.route('tenancy.show', $second['reservation']).'"', $html);
        $this->assertSame(2, substr_count($html, 'data-rental-card'));
    }

    public function test_another_tenants_rentals_never_show(): void
    {
        $mine = $this->makeTenancy();
        $theirs = $this->makeTenancy();

        $this->actingAs($mine['tenant'])->get(route('tenancy.index'))->assertOk()
            ->assertDontSee(route('tenancy.show', $theirs['reservation']), false);
    }

    public function test_a_finished_rental_is_listed_under_past_and_a_current_one_is_not(): void
    {
        $current = $this->makeTenancy();
        $past = $this->makeTenancy(['rental_status' => 'Completed', 'target_move_out_date' => '2026-09-30'], null, $current['tenant']);

        $html = $this->actingAs($current['tenant'])->get(route('tenancy.index'))->assertOk()->getContent();

        $pastAt = strpos($html, 'Past rentals');
        $this->assertNotFalse($pastAt, 'No "Past rentals" section.');
        $this->assertLessThan($pastAt, strpos($html, route('tenancy.show', $current['reservation'])));
        $this->assertGreaterThan($pastAt, strpos($html, route('tenancy.show', $past['reservation'])));
        $this->assertStringContainsString('Moved out Sep 30, 2026', $html);
    }

    public function test_a_reservation_that_has_not_started_is_not_a_rental(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Pending Rental Agreement']);

        $this->actingAs($t['tenant'])->get(route('tenancy.index'))->assertOk()
            ->assertDontSee(route('tenancy.show', $t['reservation']), false)
            ->assertSee('No rentals yet');
    }

    public function test_the_card_says_what_is_owed(): void
    {
        // Moved in Aug 1, rent 5,000 due on the 5th, nothing paid: Aug, Sep and Oct 5 are behind us.
        $t = $this->makeTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.index'))->assertOk()
            ->assertSee('₱15,000 overdue');
    }

    public function test_the_card_says_nothing_is_due_when_the_lease_starts_later(): void
    {
        $t = $this->makeTenancy(['target_move_in_date' => '2026-12-01']);

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.index'))->assertOk()->assertSee('Nothing due yet')->getContent();

        // "overdue" also appears in the page's own scripts; what must be absent is an amount owed.
        $this->assertDoesNotMatchRegularExpression('/₱[\d,.]+ overdue/u', $html);
    }

    public function test_the_header_link_is_my_rentals_and_goes_to_the_list(): void
    {
        $t = $this->makeTenancy();

        $header = $this->header($this->actingAs($t['tenant'])->get(route('home'))->assertOk()->getContent());

        $this->assertStringContainsString('My Rentals', $header);
        $this->assertStringContainsString('href="'.route('tenancy.index').'"', $header);
        $this->assertStringNotContainsString('My Unit', $header);
        $this->assertStringNotContainsString(route('tenancy.show', $t['reservation']), $header);
    }

    public function test_the_header_link_shows_for_a_past_tenant_and_hides_for_someone_who_never_rented(): void
    {
        $past = $this->makeTenancy(['rental_status' => 'Completed']);

        $this->assertStringContainsString('My Rentals', $this->header($this->actingAs($past['tenant'])->get(route('home'))->assertOk()->getContent()));
        $this->assertStringNotContainsString('My Rentals', $this->header($this->actingAs($this->makeTenant())->get(route('home'))->assertOk()->getContent()));
    }

    public function test_a_tenant_with_nothing_yet_still_gets_a_friendly_page(): void
    {
        $this->actingAs($this->makeTenant())->get(route('tenancy.index'))->assertOk()
            ->assertSee('No rentals yet')
            ->assertSee(route('properties.index'), false);
    }

    public function test_a_single_rental_page_points_back_to_the_list(): void
    {
        $t = $this->makeTenancy();

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('tenancy.index').'"', $html);
        $this->assertStringContainsString('My Rentals', $html);
    }

    public function test_the_list_needs_a_signed_in_tenant(): void
    {
        $this->get(route('tenancy.index'))->assertRedirect();
    }
}
