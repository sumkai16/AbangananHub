<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Payments is money only: each tenancy's ledger lives under Payments, the contract page under Contracts, and each
 * links to the other. plans/payments-ledger-split.md.
 */
class PaymentsLedgerSplitTest extends TestCase
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

    /** The href of the sidebar item marked as the current page. */
    private function activeNavHref(string $html): ?string
    {
        return preg_match('/<a href="([^"]+)"\s+aria-current="page"/', $html, $m) ? $m[1] : null;
    }

    public function test_the_ledger_page_is_under_payments_and_is_money_only(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('landlord.payments.ledger', $this->tenancy))
            ->assertOk()
            ->assertSee('Rent ledger')
            ->assertSee('Record payment')
            ->assertSee('Needs action')
            ->assertSee('Deposit held')
            ->assertDontSee('View contract')
            // Not a bare substring check: the remind and record forms post to /tenancies/{id}/…
            ->assertDontSee('href="'.route('landlord.tenancies.show', $this->tenancy).'"', false);

        $this->assertSame(route('landlord.payments.index'), $this->activeNavHref($response->getContent()));
    }

    public function test_another_landlord_cannot_open_the_ledger(): void
    {
        $this->actingAs($this->makeLandlord())->get(route('landlord.payments.ledger', $this->tenancy))->assertForbidden();
    }

    public function test_the_contract_page_is_under_contracts_and_has_no_ledger(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('landlord.tenancies.show', $this->tenancy))
            ->assertOk()
            ->assertSee('Open ledger')
            ->assertSee(route('landlord.payments.ledger', $this->tenancy), false)
            ->assertSee('End of tenancy')
            ->assertDontSee('Record payment')
            ->assertDontSee('Rent ledger');

        $this->assertSame(route('landlord.contracts.index'), $this->activeNavHref($response->getContent()));
    }

    public function test_the_contract_modal_links_to_the_ledger(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.contracts.show', $this->tenancy))
            ->assertOk()
            ->assertSee(route('landlord.payments.ledger', $this->tenancy), false)
            ->assertDontSee('View contract');
    }

    public function test_the_payments_list_opens_the_ledger(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.payments.index'))
            ->assertOk()
            ->assertSee('Payments')
            ->assertSee(route('landlord.payments.ledger', $this->tenancy), false);
    }
}
