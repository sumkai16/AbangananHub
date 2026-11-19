<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSignInHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_user_page_shows_recent_sign_ins(): void
    {
        $target = User::factory()->create();
        AuthEvent::create([
            'user_id'    => $target->user_id,
            'email'      => $target->email,
            'event'      => 'login',
            'channel'    => 'web',
            'ip_address' => '203.0.113.77',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36',
        ]);
        AuthEvent::create([
            'user_id'    => $target->user_id,
            'email'      => $target->email,
            'event'      => 'login_failed',
            'channel'    => 'web',
            'ip_address' => '198.51.100.5',
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/users/'.$target->user_id)
            ->assertOk()
            ->assertSee('Recent sign-ins')
            ->assertSee('Signed in')
            ->assertSee('203.0.113.77')
            ->assertSee('Failed sign-in')
            ->assertSee('198.51.100.5')
            ->assertSee('View all')
            ->assertDontSee('No sign-ins recorded yet.');
    }

    public function test_user_page_shows_empty_state_without_events(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->get('/admin/users/'.$target->user_id)
            ->assertOk()
            ->assertSee('No sign-ins recorded yet.');
    }

    public function test_only_this_users_events_are_listed(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        AuthEvent::create(['user_id' => $other->user_id, 'email' => $other->email, 'event' => 'login', 'channel' => 'web', 'ip_address' => '192.0.2.99']);

        $this->actingAs($this->admin())
            ->get('/admin/users/'.$target->user_id)
            ->assertOk()
            ->assertDontSee('192.0.2.99');
    }
}
