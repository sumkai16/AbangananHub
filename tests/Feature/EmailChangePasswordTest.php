<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Changing the email address (where password resets go) needs the current password. */
class EmailChangePasswordTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function payload($user, array $over = []): array
    {
        return array_merge(['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email], $over);
    }

    public function test_a_new_email_is_refused_without_the_current_password(): void
    {
        $user = $this->makeTenant();
        $old = $user->email;

        $this->actingAs($user)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->payload($user, ['email' => 'hijack@example.test']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame($old, $user->fresh()->email);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = $this->makeTenant();

        $this->actingAs($user)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->payload($user, ['email' => 'hijack@example.test', 'current_password' => 'not-it']))
            ->assertSessionHasErrors('current_password');

        $this->assertNotSame('hijack@example.test', $user->fresh()->email);
    }

    public function test_the_right_password_changes_the_email_and_asks_for_verification_again(): void
    {
        $user = $this->makeTenant();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->payload($user, ['email' => 'new.address@example.test', 'current_password' => 'password']))
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('new.address@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_other_profile_edits_with_the_same_email_need_no_password(): void
    {
        $user = $this->makeTenant();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->payload($user, ['bio' => 'Hello there']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Hello there', $user->fresh()->bio);
    }

    public function test_the_form_asks_for_the_password_field(): void
    {
        $this->actingAs($this->makeTenant())->get(route('profile.edit'))
            ->assertOk()->assertSee('name="current_password"', false);
    }
}
