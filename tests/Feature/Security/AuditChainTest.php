<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\Security\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->actingAs($this->admin);
        Cache::forget('audit-chain:last');
    }

    private function rows(int $n = 3): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = AuditLog::record('settings.update', "Change {$i}", null, null, ['b' => $i, 'a' => ['y' => 1, 'x' => 2]])->log_id;
        }

        return $ids;
    }

    public function test_new_rows_chain_to_previous(): void
    {
        [$first, $second] = $this->rows(2);

        $a = AuditLog::find($first);
        $b = AuditLog::find($second);

        $this->assertSame(str_repeat('0', 64), $a->prev_hash);
        $this->assertSame(64, strlen($a->row_hash));
        $this->assertSame($a->row_hash, $b->prev_hash);
        $this->assertNotSame($a->row_hash, $b->row_hash);
    }

    public function test_verify_passes_on_untouched_log(): void
    {
        $this->rows(3);

        $this->assertSame(['ok' => true, 'checked' => 3, 'broken_at' => null], AuditChain::verify());
    }

    public function test_verify_passes_on_empty_log(): void
    {
        $this->assertSame(['ok' => true, 'checked' => 0, 'broken_at' => null], AuditChain::verify());
    }

    public function test_verify_detects_edited_summary(): void
    {
        [, $middle] = $this->rows(3);

        DB::table('audit_logs')->where('log_id', $middle)->update(['summary' => 'x']);

        $result = AuditChain::verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($middle, $result['broken_at']);
    }

    public function test_verify_detects_deleted_row(): void
    {
        [, $middle, $last] = $this->rows(3);

        DB::table('audit_logs')->where('log_id', $middle)->delete();

        $result = AuditChain::verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($last, $result['broken_at']);
    }

    public function test_chain_verifies_when_metadata_keys_are_unordered(): void
    {
        [$id] = $this->rows(1);

        DB::table('audit_logs')->where('log_id', $id)->update([
            'metadata' => '{"a":{"x":2,"y":1},"b":1}',
        ]);

        $this->assertTrue(AuditChain::verify()['ok']);
    }

    public function test_clearing_every_hash_is_detected(): void
    {
        [$first] = $this->rows(2);
        DB::table('audit_logs')->update(['prev_hash' => null, 'row_hash' => null]);

        $result = AuditChain::verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($first, $result['broken_at']);
    }

    public function test_unhashed_row_after_chain_started_is_broken(): void
    {
        [, $second] = $this->rows(2);
        DB::table('audit_logs')->where('log_id', $second)->update(['prev_hash' => null, 'row_hash' => null]);

        $result = AuditChain::verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($second, $result['broken_at']);
    }

    public function test_command_reports_and_caches(): void
    {
        [, $middle] = $this->rows(3);
        $other = User::factory()->create();
        $other->assignRole('Admin');

        $this->artisan('audit:verify')
            ->expectsOutput('Audit log intact: 3 entries checked.')
            ->assertExitCode(0);
        $this->assertTrue(Cache::get('audit-chain:last')['ok']);
        $this->assertSame(0, Notification::where('title', 'Audit log was altered')->count());

        DB::table('audit_logs')->where('log_id', $middle)->update(['summary' => 'tampered']);

        $this->artisan('audit:verify')->assertExitCode(1);
        $this->assertFalse(Cache::get('audit-chain:last')['ok']);
        $this->assertSame($middle, Cache::get('audit-chain:last')['broken_at']);
        $this->assertSame(2, Notification::where('title', 'Audit log was altered')->count());

        // A second failing run the same day does not notify again.
        $this->artisan('audit:verify')->assertExitCode(1);
        $this->assertSame(2, Notification::where('title', 'Audit log was altered')->count());
    }

    public function test_badge_shows_state(): void
    {
        $this->get(route('admin.audit-logs.index'))->assertOk()->assertSee('Not checked yet');

        Cache::forever('audit-chain:last', ['ok' => true, 'checked' => 1, 'broken_at' => null, 'at' => now()]);
        $this->get(route('admin.audit-logs.index'))->assertOk()->assertSee('Log intact');

        Cache::forever('audit-chain:last', ['ok' => false, 'checked' => 1, 'broken_at' => 7, 'at' => now()]);
        $this->get(route('admin.audit-logs.index'))->assertOk()->assertSee('Log altered at entry #7');
    }
}
