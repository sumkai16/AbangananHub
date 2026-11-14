<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StepUpTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-9';

    private User $admin;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.step_up_enabled' => true]);

        $this->admin = User::factory()->create(['password' => self::PASSWORD]);
        $this->admin->assignRole('Admin');
        $this->target = User::factory()->create(['account_status' => 'active']);
        $this->target->assignRole('Tenant');
    }

    private function suspend()
    {
        return $this->actingAs($this->admin)
            ->from('/admin/users')
            ->patch(route('admin.users.updateStatus', $this->target), ['status' => 'suspended']);
    }

    private function confirm(string $password = self::PASSWORD)
    {
        return $this->actingAs($this->admin)
            ->postJson(route('admin.stepUp.confirm'), ['password' => $password]);
    }

    public function test_stale_action_is_blocked(): void
    {
        $this->suspend()
            ->assertRedirect('/admin/users')
            ->assertSessionHas('step_up_required', true);

        $this->assertSame('active', $this->target->fresh()->account_status);
    }

    public function test_action_works_after_step_up(): void
    {
        $this->confirm()->assertOk()->assertJson(['fresh' => true]);

        $this->suspend()->assertSessionMissing('step_up_required');

        $this->assertSame('suspended', $this->target->fresh()->account_status);
    }

    public function test_status_reports_freshness(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.stepUp.status'))->assertOk()->assertJson(['fresh' => false]);

        $this->confirm()->assertOk();

        $this->getJson(route('admin.stepUp.status'))->assertOk()->assertJson(['fresh' => true]);
    }

    public function test_step_up_expires_after_15_minutes(): void
    {
        $this->confirm()->assertOk();
        $this->travel(14)->minutes();
        $this->suspend()->assertSessionMissing('step_up_required');
        $this->target->fresh()->update(['account_status' => 'active']);

        $this->travel(2)->minutes();
        $this->suspend()->assertSessionHas('step_up_required', true);

        $this->assertSame('active', $this->target->fresh()->account_status);
    }

    public function test_wrong_password_is_refused_and_recorded(): void
    {
        $this->confirm('not-the-password')
            ->assertStatus(422)
            ->assertJson(['message' => 'That password is not right.']);

        $events = AuthEvent::where('event', 'password_confirm_failed')->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->admin->user_id, $events->first()->user_id);
        $this->assertStringNotContainsString('not-the-password', json_encode($events->first()->toArray()));

        // Still not fresh.
        $this->suspend()->assertSessionHas('step_up_required', true);
    }

    public function test_empty_confirm_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.stepUp.confirm'), [])->assertStatus(422);
        $this->assertSame(0, AuthEvent::where('event', 'password_confirm_failed')->count());
    }

    public function test_json_request_gets_423(): void
    {
        $this->actingAs($this->admin)
            ->patchJson(route('admin.users.updateStatus', $this->target), ['status' => 'suspended'])
            ->assertStatus(423)
            ->assertJson(['needs_password' => true]);

        $this->assertSame('active', $this->target->fresh()->account_status);
    }

    public function test_disabled_flag_lets_everything_through(): void
    {
        config(['security.step_up_enabled' => false]);

        $this->suspend()->assertSessionMissing('step_up_required');

        $this->assertSame('suspended', $this->target->fresh()->account_status);
    }

    public function test_confirm_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->confirm('wrong')->assertStatus(422);
        }

        $this->confirm('wrong')->assertStatus(429);
    }

    public function test_step_up_modal_is_included_in_the_admin_layout(): void
    {
        $this->actingAs($this->admin)->withSession(['auth.two_factor_passed' => $this->admin->user_id])
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Confirm your password');
    }
}
