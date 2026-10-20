<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The name check runs once, at submission. While the application is Pending or Approved the
 * account name is pinned, on every route that can change it, so it cannot drift from the ID.
 */
class NameLockTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function applicant(?string $status, string $role = 'Tenant'): User
    {
        $user = $this->makeUserWithRole($role);
        $user->forceFill(['first_name' => 'Roberto', 'last_name' => 'Reyes'])->save();

        if ($status) {
            LandlordVerification::create([
                'user_id' => $user->user_id, 'government_id' => 'x', 'id_type' => 'Driver License', 'selfie' => 'x',
                'id_image_hash' => str_repeat('b', 64), 'business_name' => 'B', 'contact_number' => '0917',
                'business_address' => 'A', 'verification_status' => $status, 'submitted_at' => now(),
            ]);
        }

        return $user->fresh();
    }

    public function test_rule_depends_on_the_application_status(): void
    {
        $this->assertFalse($this->applicant(null)->nameIsLocked());
        $this->assertTrue($this->applicant('Pending')->nameIsLocked());
        $this->assertTrue($this->applicant('Approved')->nameIsLocked());
        $this->assertFalse($this->applicant('Rejected')->nameIsLocked());
    }

    public function test_settings_ignore_a_name_change_while_pending_but_save_the_rest(): void
    {
        $user = $this->applicant('Pending');

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => 'Robertoe', 'last_name' => 'Reyes', 'email' => $user->email, 'bio' => 'Hello there',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Roberto', $user->first_name);
        $this->assertSame('Hello there', $user->bio);
    }

    public function test_a_request_that_omits_the_name_still_passes_validation_when_locked(): void
    {
        $user = $this->applicant('Pending');

        $this->actingAs($user)->patch(route('profile.update'), ['email' => $user->email, 'bio' => 'Only a bio'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Only a bio', $user->fresh()->bio);
    }

    public function test_general_profile_keeps_the_name_but_allows_other_edits(): void
    {
        $user = $this->applicant('Approved');

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => 'Robertoe', 'last_name' => 'Rey', 'email' => $user->email, 'contact_number' => '09990001111',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(['Roberto', 'Reyes'], [$user->first_name, $user->last_name]);
        $this->assertSame('09990001111', $user->contact_number);
    }

    public function test_landlord_profile_keeps_the_name_of_an_approved_landlord(): void
    {
        $landlord = $this->applicant('Approved', 'Landlord');

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), [
            'first_name' => 'Someone', 'last_name' => 'Else',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Roberto', $landlord->fresh()->first_name);
    }

    public function test_the_mobile_api_cannot_rename_a_locked_account(): void
    {
        $user = $this->applicant('Pending');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['first_name' => 'Robertoe', 'bio' => 'via api'])->assertOk();

        $user->refresh();
        $this->assertSame('Roberto', $user->first_name);
        $this->assertSame('via api', $user->bio);
    }

    public function test_rejected_and_unapplied_users_can_still_rename(): void
    {
        foreach ([$this->applicant('Rejected'), $this->applicant(null)] as $user) {
            $this->actingAs($user)->patch(route('profile.update'), ['first_name' => 'Roberta', 'last_name' => 'Reyes', 'email' => $user->email])
                ->assertSessionHasNoErrors();
            $this->assertSame('Roberta', $user->fresh()->first_name);
        }
    }

    public function test_forms_show_the_inputs_read_only_with_the_reason(): void
    {
        $user = $this->applicant('Pending');

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('readonly', false)
            ->assertSee('Your name is locked');

        $free = $this->applicant(null);
        $this->actingAs($free)->get(route('profile.edit'))->assertOk()->assertDontSee('Your name is locked');
    }
}
