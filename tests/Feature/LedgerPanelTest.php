<?php

namespace Tests\Feature;

use App\Http\Middleware\EmbeddedPage;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * "Open ledger" opens over the Payments list instead of leaving it: a bottom sheet on a phone, full screen on a desktop.
 * The panel holds the real ledger page in an iframe, so the page itself must be able to render without its sidebar and
 * top bar, and every redirect it makes (record, void, remind) must stay in that mode. plans/ledger-panel.md.
 */
class LedgerPanelTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private Reservation $tenancy;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');

        $this->landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($this->landlord));
        $this->tenancy = Reservation::create([
            'tenant_id' => $this->makeTenant()->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-06-01',
            'target_move_in_date' => '2026-06-01', 'target_move_out_date' => '2027-05-31',
            'agreed_monthly_rent' => 5000, 'rent_due_day' => 1,
        ]);
    }

    private function ledgerUrl(array $query = []): string
    {
        return route('landlord.payments.ledger', $this->tenancy) . ($query ? '?' . http_build_query($query) : '');
    }

    public function test_the_full_page_keeps_its_sidebar_and_carries_the_panel(): void
    {
        $this->actingAs($this->landlord)->get($this->ledgerUrl())
            ->assertOk()
            ->assertSee('id="landlord-sidebar"', false)
            ->assertSee('data-page-panel-root', false)
            ->assertSee('Breadcrumb', false);
    }

    public function test_on_desktop_the_panel_starts_at_the_sidebars_edge_and_follows_a_collapsed_sidebar(): void
    {
        $html = $this->actingAs($this->landlord)->get($this->ledgerUrl())->assertOk()->getContent();

        // Expanded sidebar is 16rem wide (w-64); the panel leaves it uncovered and stays under it for its dropdowns.
        $this->assertMatchesRegularExpression('/<section data-page-panel-section[^>]*lg:left-64/s', $html);
        $this->assertMatchesRegularExpression('/<section data-page-panel-section[^>]*z-\[45\]/s', $html);
        // A collapsed sidebar is 5rem wide; the layout moves the panel's edge with it.
        $this->assertMatchesRegularExpression('/\.sidebar-pre-collapsed \[data-page-panel-section\]\s*\{\s*left:\s*5rem;/', $html);
    }

    public function test_embed_mode_drops_the_chrome_but_keeps_the_ledger(): void
    {
        $this->actingAs($this->landlord)->get($this->ledgerUrl(['embed' => 1]))
            ->assertOk()
            ->assertDontSee('id="landlord-sidebar"', false)
            ->assertDontSee('data-page-panel-root', false)
            ->assertDontSee('Breadcrumb', false)
            ->assertDontSee('speculationrules', false)
            ->assertSee('Record payment')
            ->assertSee('Deposit held');
    }

    public function test_a_browser_marked_iframe_load_is_embed_mode_too(): void
    {
        $this->actingAs($this->landlord)->get($this->ledgerUrl(), ['Sec-Fetch-Dest' => 'iframe'])
            ->assertOk()
            ->assertDontSee('id="landlord-sidebar"', false);
    }

    public function test_the_referer_of_an_embedded_page_keeps_a_form_post_embedded(): void
    {
        // A plain-http address (a phone on the LAN) does not get Sec-Fetch-Dest, so the page the form was posted
        // from is the other signal.
        $request = Request::create(url('/landlord/tenancies/1/remind'), 'POST');
        $request->headers->set('referer', url('/landlord/payments/ledger/1?embed=1'));

        $redirect = (new EmbeddedPage)->handle($request, fn () => redirect(url('/landlord/payments/ledger/1')));

        $this->assertSame(url('/landlord/payments/ledger/1?embed=1'), $redirect->getTargetUrl());
    }

    public function test_a_redirect_from_an_embedded_request_stays_embedded(): void
    {
        $request = Request::create(url('/landlord/tenancies/1/remind'), 'POST');
        $request->headers->set('Sec-Fetch-Dest', 'iframe');

        $redirect = (new EmbeddedPage)->handle($request, fn () => redirect(url('/landlord/payments/ledger/1?tab=deposit#deposit')));

        $this->assertSame(url('/landlord/payments/ledger/1?tab=deposit&embed=1#deposit'), $redirect->getTargetUrl());
    }

    public function test_a_redirect_is_left_alone_outside_embed_mode_and_for_other_sites(): void
    {
        $plain = (new EmbeddedPage)->handle(Request::create(url('/x'), 'POST'), fn () => redirect(url('/landlord/payments')));
        $this->assertSame(url('/landlord/payments'), $plain->getTargetUrl());

        $embedded = Request::create(url('/x'), 'POST', ['embed' => 1]);
        $elsewhere = (new EmbeddedPage)->handle($embedded, fn () => redirect('https://example.org/receipt'));
        $this->assertSame('https://example.org/receipt', $elsewhere->getTargetUrl());
    }

    public function test_an_action_posted_from_the_panel_comes_back_to_the_embedded_ledger(): void
    {
        $this->actingAs($this->landlord)
            ->from($this->ledgerUrl(['embed' => 1]))
            ->post(route('landlord.tenancies.remind', $this->tenancy), [], ['Sec-Fetch-Dest' => 'iframe'])
            ->assertRedirect($this->ledgerUrl(['embed' => 1]));
    }

    public function test_every_ledger_link_asks_for_the_panel(): void
    {
        $link = 'href="' . route('landlord.payments.ledger', $this->tenancy) . '"';

        $list = $this->actingAs($this->landlord)->get(route('landlord.payments.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<a [^>]*' . preg_quote($link, '/') . '[^>]*data-page-panel/s', $list);

        $contract = $this->actingAs($this->landlord)->get(route('landlord.contracts.show', $this->tenancy))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<a [^>]*' . preg_quote($link, '/') . '[^>]*data-page-panel/s', $contract);
    }

    public function test_the_ledger_stays_private_to_its_landlord_in_embed_mode(): void
    {
        $this->actingAs($this->makeLandlord())->get($this->ledgerUrl(['embed' => 1]))->assertForbidden();
    }
}
