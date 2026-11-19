<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Landlord sign-up: a link from the tenant sign-up, a page of its own, and a straight line into the ID and face
 * wizard. The account stays a Tenant until an admin approves it.
 */
class LandlordSignupTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function account(array $extra = []): array
    {
        return $extra + [
            'first_name' => 'Lara', 'last_name' => 'Landlord', 'email' => 'lara.landlord@example.test',
            'contact_number' => '09171234567', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd',
        ];
    }

    public function test_the_tenant_sign_up_links_to_the_landlord_one(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('Become a landlord?')
            ->assertSee(route('register.landlord'), false)
            ->assertDontSee('name="as_landlord"', false);
    }

    public function test_the_popup_on_every_page_links_to_the_landlord_sign_up_too(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('Become a landlord?')
            ->assertSee(route('register.landlord'), false);
    }

    public function test_the_landlord_page_shows_the_steps_and_posts_the_landlord_flag(): void
    {
        $this->get(route('register.landlord'))->assertOk()
            ->assertSee('Sign up as a landlord')
            ->assertSee('Verify ID and face')
            ->assertSee('Admin review')
            ->assertSee('name="as_landlord"', false)
            ->assertSee('intent=landlord', false)
            ->assertSee('Sign up as a tenant')
            ->assertSee('data-step="1"', false)
            ->assertSee('data-step="2"', false)
            ->assertSee('Continue to ID verification');
    }

    public function test_the_landlord_form_opens_on_the_account_step_after_an_email_error(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->from(route('register.landlord'))
            ->post(route('register'), $this->account(['email' => 'taken@example.test', 'as_landlord' => '1']))
            ->assertSessionHasErrors('email');

        $this->get(route('register.landlord'))->assertOk()->assertSee('step: 2', false);
    }

    public function test_a_free_email_is_reported_available(): void
    {
        $this->getJson(route('register.emailAvailable', ['email' => 'brand.new@example.test']))
            ->assertOk()->assertExactJson(['available' => true]);
    }

    public function test_a_registered_email_is_reported_taken_whatever_its_case(): void
    {
        User::factory()->create(['email' => 'already.here@example.test']);

        $this->getJson(route('register.emailAvailable', ['email' => 'Already.Here@Example.test']))
            ->assertOk()->assertExactJson(['available' => false]);
    }

    public function test_something_that_is_not_an_email_says_nothing(): void
    {
        foreach (['', 'nope', 'a@', str_repeat('a', 300).'@x.test'] as $bad) {
            $this->getJson(route('register.emailAvailable', ['email' => $bad]))
                ->assertOk()->assertExactJson(['available' => null]);
        }

        // An array (?email[]=x) is the wrong type, not an error page.
        $this->getJson('/register/email-available?email[]=x')->assertOk()->assertExactJson(['available' => null]);
    }

    public function test_the_email_check_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(route('register.emailAvailable', ['email' => "n{$i}@example.test"]))->assertOk();
        }

        $this->getJson(route('register.emailAvailable', ['email' => 'one.too.many@example.test']))->assertStatus(429);
    }

    public function test_the_tenant_form_also_checks_the_email_live(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('registerForm', false)
            ->assertSee('register\/email-available', false);
    }

    public function test_a_landlord_sign_up_lands_in_the_wizard_and_stays_a_tenant(): void
    {
        $this->post(route('register'), $this->account(['as_landlord' => '1']))
            ->assertRedirect(route('register.landlord'));

        $user = User::where('email', 'lara.landlord@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Tenant'));
        $this->assertFalse($user->hasRole('Landlord'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_tenant_sign_up_still_goes_to_browse(): void
    {
        $this->post(route('register'), $this->account())
            ->assertRedirect(route('properties.index'));
    }

    public function test_the_wizard_page_opens_for_the_new_account(): void
    {
        $this->post(route('register'), $this->account(['as_landlord' => '1']));

        // The same address now shows step 3 (the ID and face check) with the four-step indicator.
        $this->get(route('register.landlord'))->assertOk()
            ->assertSee('Sign up as a landlord')
            ->assertSee('Four short steps')
            ->assertSee('verificationWizard', false)
            ->assertSee('Admin review')
            ->assertDontSee('name="as_landlord"', false)
            // The sign-up shell, not the site menu.
            ->assertDontSee('Browse Rentals')
            ->assertSee('Log out');
    }

    public function test_all_four_steps_use_the_same_sign_up_shell(): void
    {
        // Step 1 and 2 (guest): the sign-up shell, no site menu and no split photo panel.
        $this->get(route('register.landlord'))->assertOk()
            ->assertSee('Log in')
            ->assertDontSee('Browse Rentals')
            ->assertDontSee('Rent with');

        // Step 3 (signed in): the same shell, and the wizard form returns to this address after submit.
        $user = $this->makeTenant();
        $this->actingAs($user)->get(route('register.landlord'))->assertOk()
            ->assertSee('name="signup_flow"', false)
            ->assertDontSee('Browse Rentals');

        // Step 4 (application pending): still this address, still the shell, indicator on the last step.
        \App\Models\LandlordVerification::create([
            'user_id' => $user->user_id, 'government_id' => 'x', 'id_image_hash' => str_repeat('c', 64),
            'id_type' => 'Driver License', 'selfie' => 'x', 'business_name' => 'Test Rentals', 'contact_number' => '0917',
            'business_address' => 'Somewhere', 'verification_status' => 'Pending', 'submitted_at' => now(),
        ]);

        $this->actingAs($user->fresh())->get(route('register.landlord'))->assertOk()
            ->assertSee('Sign up as a landlord')
            ->assertSee('step: 4', false)
            ->assertDontSee('Browse Rentals');
    }

    public function test_a_landlord_is_sent_home_from_the_sign_up_address(): void
    {
        $landlord = $this->makeUserWithRole('Landlord');

        $this->actingAs($landlord)->get(route('register.landlord'))->assertRedirect($landlord->homeRoute());
    }

    public function test_the_old_apply_address_still_works(): void
    {
        // Reached directly, it is the ordinary page with the site menu, not the sign-up shell.
        $this->actingAs($this->makeTenant())->get(route('landlord.verification.create'))->assertOk()
            ->assertSee('Browse Rentals');
    }

    private function fakeGoogleUser(string $email): void
    {
        $social = (new \Laravel\Socialite\Two\User)
            ->setRaw(['email_verified' => true])
            ->map(['id' => 'g-'.md5($email), 'nickname' => null, 'name' => 'Sam Social', 'email' => $email, 'avatar' => null]);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($social);
    }

    public function test_social_sign_up_with_the_landlord_intent_ends_in_the_wizard(): void
    {
        $this->withSession(['auth.intent' => 'landlord']);
        $this->fakeGoogleUser('sam.social@example.test');

        $this->get('/auth/google/callback')->assertRedirect(route('register.landlord'));
    }

    public function test_social_sign_up_without_the_intent_goes_home(): void
    {
        $this->fakeGoogleUser('sam.other@example.test');

        $this->get('/auth/google/callback')->assertRedirect(route('properties.index'));
    }

    public function test_the_social_buttons_carry_the_intent_only_on_the_landlord_page(): void
    {
        $this->get(route('register'))->assertOk()->assertDontSee('intent=landlord', false);
    }
}
