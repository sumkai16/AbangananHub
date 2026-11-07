<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Payout;
use App\Models\UserPayoutAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The admin payouts page groups what is owed by what is in the way, puts the work that needs an admin behind one
 * button, and still lets an admin pay by hand when automatic payouts are off (plans/admin-payouts-redesign.md).
 */
class PayoutsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** A landlord owed $amount whose GCash account is in the given state. */
    private function landlordOwed(string $state, float $amount, array $name = ['Test', 'Landlord']): array
    {
        $t = $this->makeTenancy();
        $t['landlord']->forceFill(['first_name' => $name[0], 'last_name' => $name[1]])->save();
        $this->makePayment($t['reservation'], [
            'amount' => $amount, 'billing_period' => null, 'payment_type' => 'Other', 'payment_method' => 'GCash',
            'payout_status' => 'Pending Payout',
        ]);

        if ($state !== 'no_details') {
            $t['landlord']->savePayoutAccount('0917'.random_int(1000000, 9999999), $name[0].' '.$name[1]);
            // Saving a new destination starts a 48-hour hold and asks for approval; set exactly the state under test.
            UserPayoutAccount::whereKey($t['landlord']->user_id)->update([
                'payouts_hold_until' => $state === 'hold' ? now()->addDay() : null,
                'payout_approved_at' => in_array($state, ['not_approved'], true) ? null : now(),
                'paused_at' => $state === 'paused' ? now() : null,
            ]);
        }

        if ($state === 'failed') {
            Payout::create(['landlord_id' => $t['landlord']->user_id, 'amount' => $amount, 'status' => 'Failed', 'failure_reason' => 'GCash number is not registered']);
        }

        return $t;
    }

    private function page()
    {
        return $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.payouts.index'))->assertOk();
    }

    public function test_the_three_totals_add_up_to_everything_owed(): void
    {
        $this->landlordOwed('ready', 3000);
        $this->landlordOwed('not_approved', 1000);
        $this->landlordOwed('failed', 2000);
        $this->landlordOwed('paused', 500);
        $this->landlordOwed('hold', 700);
        $this->landlordOwed('no_details', 300);

        $summary = $this->page()->viewData('summary');

        $this->assertSame(7500.0, $summary['owed']);
        $this->assertSame(3000.0, $summary['ready']);
        $this->assertSame(3000.0, $summary['needs']);
        $this->assertSame(1500.0, $summary['waiting']);
        $this->assertSame($summary['owed'], $summary['ready'] + $summary['needs'] + $summary['waiting']);
        $this->assertSame(6, $summary['landlords']);
        $this->assertSame(10, $summary['fees'], 'One ready transfer: up to 10 pesos in PayMongo fees.');
    }

    public function test_every_row_says_what_is_in_the_way(): void
    {
        $this->landlordOwed('ready', 3000);
        $this->landlordOwed('not_approved', 1000);
        $this->landlordOwed('failed', 2000);
        $this->landlordOwed('paused', 500);
        $this->landlordOwed('hold', 700);
        $this->landlordOwed('no_details', 300);

        $this->page()
            ->assertSee('Ready')->assertSee('GCash not approved')->assertSee('Payout failed')
            ->assertSee('Paused')->assertSee('On hold')->assertSee('No GCash number');
    }

    public function test_rows_are_sorted_with_the_biggest_amount_first(): void
    {
        $this->landlordOwed('ready', 100, ['Small', 'Amount']);
        $this->landlordOwed('ready', 9000, ['Big', 'Amount']);
        $this->landlordOwed('ready', 4000, ['Middle', 'Amount']);

        $this->page()->assertSeeInOrder(['Big Amount', 'Middle Amount', 'Small Amount']);
    }

    public function test_the_needs_you_button_counts_failed_payouts_and_accounts_to_approve(): void
    {
        $this->landlordOwed('failed', 2000, ['Joselito', 'Ramos']);
        $this->landlordOwed('not_approved', 1000, ['Cristina', 'Gonzaga']);
        $this->landlordOwed('ready', 3000);

        $response = $this->page()
            ->assertSee('Needs you')
            ->assertSee('Failed payouts')->assertSee('GCash accounts to approve')
            ->assertSee("Retry Joselito Ramos's payout", false)
            ->assertSee("Approve Cristina Gonzaga's GCash account", false);

        $this->assertSame(2, $response->viewData('failed')->count() + $response->viewData('toApprove')->count());
    }

    public function test_with_nothing_to_do_the_button_gives_way_to_a_plain_line(): void
    {
        $this->landlordOwed('ready', 3000);

        $this->page()->assertSee('Nothing needs you right now.')->assertDontSee('aria-controls="needs-panel"', false);
    }

    public function test_a_failed_payout_is_retried_from_the_list_with_the_same_confirmation(): void
    {
        $t = $this->landlordOwed('failed', 2000);
        $payout = Payout::where('landlord_id', $t['landlord']->user_id)->sole();

        $this->page()
            ->assertSee(route('admin.payouts.retry', $payout), false)
            ->assertSee('Send this payout again?');
    }

    public function test_automatic_mode_shows_the_next_run_and_up_to_what_it_costs(): void
    {
        $this->landlordOwed('ready', 3000);
        $this->landlordOwed('ready', 2000);

        $response = $this->page()->assertSee('Automatic payouts on')->assertSee('next run')->assertSee('up to ₱20 in fees');

        $this->assertNotNull($response->viewData('nextRun'));
        $this->assertSame(0, $response->viewData('nextRun')->minute);
    }

    public function test_in_manual_mode_a_ready_row_is_paid_by_hand_and_no_run_is_promised(): void
    {
        config(['rentals.automatic_payouts_enabled' => false]);
        $this->landlordOwed('ready', 3000);

        $response = $this->page()
            ->assertSee('Manual mode: automatic payouts are switched off')
            ->assertSee('Pay by hand')->assertSee('Mark Paid Out')
            ->assertDontSee('next run');

        $this->assertNull($response->viewData('nextRun'));
        $this->assertSame(0, $response->viewData('summary')['fees']);
    }

    public function test_an_apostrophe_in_a_name_cannot_break_out_of_the_modal_payload(): void
    {
        $this->landlordOwed('ready', 3000, ['Mary', "D'Souza"]);

        $html = $this->page()->getContent();

        $this->assertStringNotContainsString("name: 'Mary D'Souza'", $html);
        $this->assertStringContainsString("name: 'Mary D\\&#039;Souza'", $html);
    }

    public function test_the_history_tabs_count_payouts_by_status(): void
    {
        $t = $this->landlordOwed('ready', 3000);
        $id = $t['landlord']->user_id;
        Payout::create(['landlord_id' => $id, 'amount' => 100, 'status' => 'Processing', 'paymongo_transfer_id' => 'tr_1']);
        Payout::create(['landlord_id' => $id, 'amount' => 200, 'status' => 'Failed', 'failure_reason' => 'Invalid account', 'resolved_at' => now()]);
        Payout::create(['landlord_id' => $id, 'amount' => 300, 'status' => 'Sent', 'paid_out_at' => now(), 'payout_reference' => 'tr_ok']);
        Payout::create(['landlord_id' => $id, 'amount' => 400, 'status' => 'Sent', 'paid_out_at' => now(), 'payout_reference' => 'tr_ok2']);

        $this->page()->assertSee('Check status')->assertSee('Invalid account')->assertSee('tr_ok2');
    }

    public function test_an_account_with_nothing_owed_still_shows_up_to_approve(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Test Landlord');

        $this->page()->assertSee('Nothing owed yet')->assertSee('Approve account');
    }
}
