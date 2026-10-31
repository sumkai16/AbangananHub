<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\LandlordVerification;
use App\Models\UserPayoutAccount;
use App\Services\LandlordPayouts;
use App\Support\GcashNumber;
use App\Support\NameMatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Three checks before money goes to a landlord's GCash: the number looks like a Philippine mobile number, the
 * account name matches the landlord's own name or verified ID (a flag, never a block), and an administrator
 * approves the account before the first payout, and again whenever it changes.
 */
class PayoutAccountCheckTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => true, 'broadcasting.default' => 'null']);
        Mail::fake();
    }

    // ─── the number ─────────────────────────────────────────────

    /** @return array<string, array{string, ?string}> */
    public static function numbers(): array
    {
        return [
            'plain' => ['09171234567', '09171234567'],
            'spaced' => ['0917 123 4567', '09171234567'],
            'dashed' => ['0917-123-4567', '09171234567'],
            'plus 63' => ['+63 917 123 4567', '09171234567'],
            '63 no plus' => ['639171234567', '09171234567'],
            'no leading zero' => ['9171234567', '09171234567'],
            'too short' => ['091712345', null],
            'too long' => ['091712345678', null],
            'landline' => ['0322551234', null],
            'letters' => ['hello', null],
            'an email' => ['landlord@abangananhub.com', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_a_number_is_read_into_one_shape_or_refused(string $typed, ?string $expected): void
    {
        $this->assertSame($expected, GcashNumber::normalize($typed));
    }

    public function test_the_settings_form_refuses_a_bad_number_and_stores_a_good_one_normalized(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '12345', 'gcash_account_name' => 'Maria Santos'])
            ->assertSessionHasErrors(['gcash_number' => GcashNumber::MESSAGE]);
        $this->assertNull(UserPayoutAccount::find($landlord->user_id));

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '+63 917 123 4567', 'gcash_account_name' => 'Maria Santos'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09171234567', UserPayoutAccount::find($landlord->user_id)->gcash_number);
    }

    public function test_the_api_refuses_a_bad_number_too(): void
    {
        $landlord = $this->makeLandlord();
        Sanctum::actingAs($landlord, ['*']);

        $this->patchJson('/api/v1/landlord/profile/me', [
            'first_name' => $landlord->first_name, 'last_name' => $landlord->last_name, 'gcash_number' => 'banana', 'gcash_account_name' => 'X Y',
        ])->assertStatus(422)->assertJsonValidationErrors('gcash_number');
    }

    public function test_saving_the_same_number_typed_differently_is_not_a_change_and_needs_no_password(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Maria Santos');
        UserPayoutAccount::whereKey($landlord->user_id)->update(['payout_approved_at' => now()]);

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), ['gcash_number' => '0917 123 4567', 'gcash_account_name' => 'Maria Santos'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(UserPayoutAccount::find($landlord->user_id)->isApproved(), 'Nothing changed, so the approval stays.');
    }

    // ─── the name ───────────────────────────────────────────────

    /** @return array<string, array{string, string, bool}> */
    public static function names(): array
    {
        return [
            'same' => ['Maria Santos', 'Maria Santos', true],
            'order and case' => ['SANTOS, MARIA', 'maria santos', true],
            'middle name' => ['Maria Lourdes Santos', 'Maria Santos', true],
            'middle initial' => ['Maria L. Santos', 'Maria Lourdes Santos', true],
            'accents and suffix' => ['José Reyes Jr.', 'Jose Reyes', true],
            'different surname' => ['Maria Cruz', 'Maria Santos', false],
            'different person' => ['Juan Dela Cruz', 'Maria Santos', false],
            'one word only' => ['Maria', 'Maria Santos', false],
            'an email' => ['landlord@abangananhub.com', 'Maria Santos', false],
            'empty' => ['', 'Maria Santos', false],
        ];
    }

    #[DataProvider('names')]
    public function test_names_are_compared_forgivingly_but_not_loosely(string $a, string $b, bool $expected): void
    {
        $this->assertSame($expected, NameMatch::matches($a, $b));
        $this->assertSame($expected, NameMatch::matches($b, $a), 'The comparison is symmetric.');
    }

    public function test_the_gcash_name_is_judged_against_the_account_name_and_the_verified_id(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->forceFill(['first_name' => 'Maria', 'last_name' => 'Santos'])->save();

        $this->assertTrue(LandlordPayouts::nameMatchesIdentity($landlord, 'Maria Santos'));
        $this->assertFalse(LandlordPayouts::nameMatchesIdentity($landlord, 'Juan Dela Cruz'));
        $this->assertNull(LandlordPayouts::nameMatchesIdentity($landlord, ''), 'Nothing typed, nothing to judge.');

        LandlordVerification::forceCreate([
            'user_id' => $landlord->user_id, 'government_id' => 'x', 'id_type' => 'Passport', 'selfie' => 'x',
            'id_image_hash' => 'hash-'.$landlord->user_id, 'ocr_name' => 'LOURDES MARIA SANTOS', 'verification_status' => 'Approved',
            'submitted_at' => now(),
        ]);

        $this->assertTrue(LandlordPayouts::nameMatchesIdentity($landlord, 'Lourdes Santos'), 'The name on the approved ID counts too.');
    }

    public function test_a_mismatched_name_still_saves_and_tells_the_landlord_an_admin_will_check(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->forceFill(['first_name' => 'Maria', 'last_name' => 'Santos'])->save();

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), ['gcash_number' => '09171234567', 'gcash_account_name' => 'Juan Dela Cruz'])
            ->assertSessionHas('warning', fn ($message) => str_contains($message, 'does not match the name on your verified ID'));

        $this->assertSame('Juan Dela Cruz', UserPayoutAccount::find($landlord->user_id)->gcash_account_name);
    }

    // ─── approval ───────────────────────────────────────────────

    public function test_money_waits_until_an_admin_approves_the_account(): void
    {
        Queue::fake();
        $t = $this->makeTenancy();
        // Details first, money after: money already owed would start the 48-hour hold, which is a different wait.
        $t['landlord']->savePayoutAccount('09171234567', 'Test Landlord');
        $this->makePayment($t['reservation'], ['payout_status' => 'Pending Payout']);

        $this->assertStringContainsString('not approved', LandlordPayouts::blockedReason($t['landlord']));
        $this->assertNull(LandlordPayouts::sendFor($t['landlord']), 'Nothing is sent before approval.');
    }

    public function test_setting_a_gcash_account_asks_the_admins_to_approve_it(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $landlord = $this->makeLandlord();

        $landlord->savePayoutAccount('09171234567', 'Maria Santos');

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->user_id, 'title' => 'GCash account to approve']);
    }

    public function test_an_admin_approves_it_and_the_waiting_money_is_queued_to_go_out(): void
    {
        Queue::fake();
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payout_status' => 'Pending Payout']);
        $t['landlord']->savePayoutAccount('09171234567', 'Test Landlord');

        $this->actingAs($admin)->get(route('admin.payouts.index'))
            ->assertOk()->assertSee('GCash accounts to approve')->assertSee('Approve account');

        $this->actingAs($admin)->post(route('admin.payouts.approveAccount', $t['landlord']))->assertSessionHas('success');

        $account = UserPayoutAccount::find($t['landlord']->user_id);
        $this->assertTrue($account->isApproved());
        $this->assertSame($admin->user_id, (int) $account->payout_approved_by);
        $this->assertSame(1, AuditLog::where('action', 'payout_account.approve')->count());
        Queue::assertPushed(\App\Jobs\SendLandlordPayout::class, fn ($job) => $job->landlordId === $t['landlord']->user_id);
    }

    public function test_approving_twice_is_a_409_and_only_admins_can_approve(): void
    {
        Queue::fake();
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $t['landlord']->savePayoutAccount('09171234567', 'Test Landlord');

        $this->actingAs($t['landlord'])->post(route('admin.payouts.approveAccount', $t['landlord']))->assertForbidden();
        $this->actingAs($t['tenant'])->post(route('admin.payouts.approveAccount', $t['landlord']))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.payouts.approveAccount', $t['landlord']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.payouts.approveAccount', $t['landlord']))->assertStatus(409);
    }

    public function test_an_account_with_no_details_cannot_be_approved(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $landlord = $this->makeLandlord();

        $this->actingAs($admin)->post(route('admin.payouts.approveAccount', $landlord))->assertStatus(409);
    }

    public function test_changing_the_number_or_name_needs_approval_again(): void
    {
        Queue::fake();
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Maria Santos');
        UserPayoutAccount::whereKey($landlord->user_id)->update(['payout_approved_at' => now(), 'payout_approved_by' => null]);

        $landlord->savePayoutAccount('09171234567', 'Maria Santos');
        $this->assertTrue(UserPayoutAccount::find($landlord->user_id)->isApproved(), 'Saving the same details changes nothing.');

        $landlord->savePayoutAccount('09998887777', 'Maria Santos');
        $this->assertFalse(UserPayoutAccount::find($landlord->user_id)->isApproved(), 'A new number has to be looked at again.');
    }

    public function test_the_admin_page_flags_a_name_that_does_not_match_the_id(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $landlord = $this->makeLandlord();
        $landlord->forceFill(['first_name' => 'Maria', 'last_name' => 'Santos'])->save();
        $landlord->savePayoutAccount('09171234567', 'Juan Dela Cruz');

        $this->actingAs($admin)->get(route('admin.payouts.index'))
            ->assertOk()->assertSee('Name does not match their ID');
    }

    public function test_the_landlord_is_told_an_approval_is_pending(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Test Landlord');

        $this->actingAs($landlord)->get(route('landlord.payouts.index'))
            ->assertOk()->assertSee('has to approve your GCash account');
    }
}
