<?php

namespace Tests\Feature;

use App\Mail\TenantInviteMail;
use App\Models\LandlordTenant;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TenantDetail;
use App\Models\User;
use App\Support\TenantInvites;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The Tenants page: add, view, edit, invite and remove tenants on a landlord's own list.
 * plans/landlord-tenants-contracts-direct-payments.md, phase 3.
 */
class TenantsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function addTenant(User $landlord, array $overrides = [])
    {
        return $this->actingAs($landlord)->post(route('landlord.tenants.store'), $overrides + [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana.santos.tenant@example.test',
            'contact_number' => '09175550123', 'emergency_contact_name' => 'Liza Santos',
            'emergency_contact_relationship' => 'Parent', 'emergency_contact_number' => '09185550456',
        ]);
    }

    private function contractFor(LandlordTenant $row, string $status): Reservation
    {
        $unit = $this->makeUnit($this->makeProperty($row->landlord));

        return Reservation::create([
            'tenant_id' => $row->tenant_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => $status, 'reservation_date' => '2026-01-01',
            'target_move_in_date' => '2026-01-01', 'target_move_out_date' => '2026-12-31', 'agreed_monthly_rent' => 5000,
        ]);
    }

    public function test_adding_a_tenant_puts_them_on_the_list_as_pending_and_emails_an_invite(): void
    {
        $landlord = $this->makeLandlord();

        $this->addTenant($landlord)->assertRedirect(route('landlord.tenants.index'))->assertSessionHas('success');

        $tenant = User::where('email', 'ana.santos.tenant@example.test')->firstOrFail();
        $this->assertTrue($tenant->is_walk_in);
        $this->assertSame('inactive', $tenant->account_status);
        $this->assertTrue($tenant->hasRole('Tenant'));
        $this->assertSame('Liza Santos', TenantDetail::find($tenant->user_id)->emergency_contact_name);

        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->where('tenant_id', $tenant->user_id)->firstOrFail();
        $this->assertSame('TN-0001', $row->code());
        Mail::assertSent(TenantInviteMail::class, fn ($mail) => $mail->hasTo('ana.santos.tenant@example.test'));

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))
            ->assertOk()->assertSee('TN-0001')->assertSee('Ana')->assertSee('Pending');
    }

    public function test_a_tenant_without_email_needs_a_mobile_number_and_gets_no_invite(): void
    {
        $landlord = $this->makeLandlord();

        $this->addTenant($landlord, ['email' => '', 'contact_number' => ''])->assertSessionHasErrors('contact_number');
        $this->addTenant($landlord, ['email' => ''])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_an_email_that_already_has_an_account_is_refused(): void
    {
        $existing = $this->makeTenant();

        $this->addTenant($this->makeLandlord(), ['email' => $existing->email])->assertSessionHasErrors('email');

        $this->assertSame(0, LandlordTenant::where('tenant_id', $existing->user_id)->count());
    }

    public function test_the_view_shows_the_emergency_contact_to_the_owner_only(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord);
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();

        $this->actingAs($landlord)->get(route('landlord.tenants.show', $row))
            ->assertOk()->assertSee('Liza Santos')->assertSee('Resend invite');

        $this->actingAs($this->makeLandlord())->get(route('landlord.tenants.show', $row))->assertForbidden();
    }

    public function test_editing_keeps_blank_details_and_a_new_email_gets_a_new_invite(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord);
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();
        Mail::fake();

        $this->actingAs($landlord)->patch(route('landlord.tenants.update', $row), [
            'first_name' => 'Ana Marie', 'last_name' => 'Santos', 'email' => 'ana.new@example.test', 'contact_number' => '09175550123',
            'emergency_contact_name' => '', 'emergency_contact_number' => '',
        ])->assertSessionHasNoErrors();

        $tenant = $row->tenant->fresh();
        $this->assertSame('Ana Marie', $tenant->first_name);
        $this->assertSame('Liza Santos', TenantDetail::find($tenant->user_id)->emergency_contact_name, 'Blank keeps what is saved.');
        Mail::assertSent(TenantInviteMail::class, fn ($mail) => $mail->hasTo('ana.new@example.test'));
    }

    public function test_a_tenant_with_their_own_login_cannot_be_edited_by_the_landlord(): void
    {
        $landlord = $this->makeLandlord();
        $platformTenant = $this->makeTenant();
        $row = LandlordTenant::ensureFor($landlord->user_id, $platformTenant->user_id);

        $this->actingAs($landlord)->patch(route('landlord.tenants.update', $row), [
            'first_name' => 'Changed', 'last_name' => 'Name', 'contact_number' => '09170000000',
        ])->assertForbidden();

        $this->assertNotSame('Changed', $platformTenant->fresh()->first_name);
    }

    public function test_removal_is_refused_with_a_live_contract(): void
    {
        $landlord = $this->makeLandlord();
        $row = LandlordTenant::ensureFor($landlord->user_id, $this->makeTenant()->user_id);
        $this->contractFor($row, 'Occupied');

        $this->actingAs($landlord)->delete(route('landlord.tenants.destroy', $row))->assertSessionHas('error');

        $this->assertNull($row->fresh()->archived_at);
    }

    public function test_a_tenant_added_by_hand_with_no_contracts_is_deleted(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord, ['email' => '']);
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();
        $tenantId = $row->tenant_id;

        $this->actingAs($landlord)->delete(route('landlord.tenants.destroy', $row))->assertSessionHas('success');

        $this->assertNull(User::find($tenantId));
        $this->assertNull(LandlordTenant::find($row->landlord_tenant_id));
    }

    public function test_a_former_tenant_is_archived_and_their_payments_stay(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord, ['email' => '']);
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();
        $contract = $this->contractFor($row, 'Completed');
        Payment::create([
            'reservation_id' => $contract->reservation_id, 'payment_type' => 'Monthly', 'billing_period' => '2026-01-01',
            'amount' => 5000, 'payment_method' => 'Cash', 'status' => 'Paid', 'paid_at' => '2026-01-01', 'recorded_by' => $landlord->user_id,
        ]);

        $this->actingAs($landlord)->delete(route('landlord.tenants.destroy', $row))->assertSessionHas('success');

        $this->assertNotNull($row->fresh()->archived_at);
        $this->assertNotNull(User::find($row->tenant_id));
        $this->assertSame(1, Payment::where('reservation_id', $contract->reservation_id)->count());
        $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertDontSee($row->code());
    }

    public function test_accepting_the_invite_turns_the_placeholder_into_a_real_account(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord);
        $tenant = User::where('email', 'ana.santos.tenant@example.test')->firstOrFail();
        $token = Password::broker(TenantInvites::BROKER)->createToken($tenant);
        Auth::logout(); // the tenant opens the link, not the landlord

        $this->get(route('invite.show', ['token' => $token, 'email' => $tenant->email]))->assertOk();

        $this->post(route('invite.store'), [
            'token' => $token, 'email' => $tenant->email,
            'password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertFalse($tenant->is_walk_in);
        $this->assertSame('active', $tenant->account_status);
        $this->assertNotNull($tenant->email_verified_at);
        $this->assertAuthenticatedAs($tenant);
    }

    public function test_a_wrong_invite_token_is_refused(): void
    {
        $landlord = $this->makeLandlord();
        $this->addTenant($landlord);
        $tenant = User::where('email', 'ana.santos.tenant@example.test')->firstOrFail();
        Auth::logout();

        $this->post(route('invite.store'), [
            'token' => 'not-a-real-token', 'email' => $tenant->email,
            'password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234',
        ])->assertSessionHasErrors('email');

        $this->assertTrue($tenant->fresh()->is_walk_in);
        $this->assertGuest();
    }
}
