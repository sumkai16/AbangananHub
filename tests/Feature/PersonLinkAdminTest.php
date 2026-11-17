<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Report;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Names on the admin pages open the person's profile (admin.users.show) in a new tab, through <x-person-link>.
 */
class PersonLinkAdminTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function link($user): string
    {
        return 'href="'.route('admin.users.show', $user->user_id).'" target="_blank"';
    }

    public function test_the_reservation_page_links_the_tenant_and_the_landlord_in_a_new_tab(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reservations.show', $t['reservation']))
            ->assertOk()
            ->assertSee($this->link($t['tenant']), false)
            ->assertSee($this->link($t['landlord']), false)
            ->assertSee('opens profile in a new tab');
    }

    public function test_the_reservations_list_links_the_landlord(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reservations.index'))
            ->assertOk()->assertSee($this->link($t['landlord']), false);
    }

    public function test_the_payments_page_links_the_landlord(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.payments.index', ['status' => 'All']))
            ->assertOk()->assertSee($this->link($t['landlord']), false);
    }

    public function test_the_unit_page_links_the_tenant_in_its_reservations_table(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $property = $t['reservation']->property;

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.units.show', [$property, $t['reservation']->unit]))
            ->assertOk()->assertSee($this->link($t['tenant']), false);
    }

    public function test_the_reports_page_links_the_reporter(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        Report::create(['reporter_id' => $t['tenant']->user_id, 'property_id' => $t['reservation']->property_id, 'report_reason' => 'Fake listing', 'report_status' => 'Pending']);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reports.index', ['status' => 'All']))
            ->assertOk()->assertSee($this->link($t['tenant']), false);
    }

    public function test_a_conversation_links_both_people(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $conversation = Conversation::create([
            'tenant_id' => $t['tenant']->user_id, 'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id, 'unit_id' => $t['reservation']->unit_id, 'type' => 'inquiry',
        ]);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee($this->link($t['tenant']), false)
            ->assertSee($this->link($t['landlord']), false);
    }

    public function test_the_component_prints_a_plain_name_when_there_is_nobody_to_link(): void
    {
        $html = $this->actingAs($this->makeUserWithRole('Admin'))
            ->blade('<x-person-link :user="null" name="Walk-in guest" />')->__toString();

        $this->assertStringContainsString('Walk-in guest', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }
}
