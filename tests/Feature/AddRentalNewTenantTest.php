<?php

namespace Tests\Feature;

use App\Mail\TenantInviteMail;
use App\Models\LandlordTenant;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** "Add a rental" with a new tenant typed into the modal (tenant_mode=new), instead of one from the list. */
class AddRentalNewTenantTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
        Mail::fake();
    }

    private function form($unit, array $overrides = []): array
    {
        return $overrides + [
            'tenant_mode' => 'new',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '0917 555 0142',
            'unit_id' => $unit->unit_id,
            'move_in_date' => '2026-11-01',
            'move_out_date' => '2027-04-30',
            'agreed_monthly_rent' => 4500,
            'rent_due_day' => 1,
            'occupants_count' => 1,
            'lease_mode' => 'later',
        ];
    }

    public function test_a_new_tenant_and_their_rental_are_created_together(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->form($unit, ['email' => 'maria.new.tenant@example.test']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $tenant = User::where('email', 'maria.new.tenant@example.test')->firstOrFail();
        $this->assertTrue((bool) $tenant->is_walk_in);
        $this->assertSame($landlord->user_id, $tenant->created_by_landlord_id);
        $this->assertTrue(LandlordTenant::where('landlord_id', $landlord->user_id)->where('tenant_id', $tenant->user_id)->exists());
        $this->assertSame('Reserved', Reservation::where('tenant_id', $tenant->user_id)->firstOrFail()->rental_status);
        Mail::assertQueued(TenantInviteMail::class, fn ($mail) => $mail->hasTo('maria.new.tenant@example.test'));
    }

    public function test_no_invite_is_sent_without_an_email(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($landlord)->post(route('landlord.contracts.store'), $this->form($unit))
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
        $this->assertSame(1, User::where('contact_number', '0917 555 0142')->count());
    }

    public function test_a_refused_rental_leaves_no_tenant_behind(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord), ['availability_status' => 'Occupied']);

        $this->actingAs($landlord)->post(route('landlord.contracts.store'), $this->form($unit));

        $this->assertSame(0, User::where('contact_number', '0917 555 0142')->count());
        $this->assertSame(0, LandlordTenant::where('landlord_id', $landlord->user_id)->count());
    }

    public function test_a_number_already_on_the_landlords_list_is_refused_in_any_format(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $existing = $this->makeTenant();
        $existing->forceFill(['contact_number' => '+63 917 555 0142'])->save();
        $row = LandlordTenant::ensureFor($landlord->user_id, $existing->user_id);

        $this->actingAs($landlord)->post(route('landlord.contracts.store'), $this->form($unit))
            ->assertSessionHasErrors('contact_number')
            ->assertSessionHas('duplicate_tenant_id', $row->landlord_tenant_id);

        $this->assertSame(0, Reservation::where('unit_id', $unit->unit_id)->count());
    }

    public function test_another_landlords_tenant_with_the_same_number_does_not_block(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $theirs = $this->makeTenant();
        $theirs->forceFill(['contact_number' => '09175550142'])->save();
        LandlordTenant::ensureFor($this->makeLandlord()->user_id, $theirs->user_id);

        $this->actingAs($landlord)->post(route('landlord.contracts.store'), $this->form($unit))
            ->assertSessionHasNoErrors();
    }

    public function test_a_new_tenant_needs_a_name_and_a_way_to_reach_them(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->form($unit, ['first_name' => '', 'contact_number' => '']))
            ->assertSessionHasErrors(['first_name', 'contact_number']);
    }

    public function test_an_email_with_an_account_is_refused(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $taken = $this->makeTenant();

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->form($unit, ['email' => $taken->email]))
            ->assertSessionHasErrors('email');
    }
}
