<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The admin review page tells the admin what the ID photo check made of the photos, and when the applicant carried on
 * after failing it. These are signals; the admin still decides.
 */
class AdminVerificationIdCheckChipsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function reviewPage(array $check)
    {
        $applicant = $this->makeTenant();
        $verification = LandlordVerification::create(array_merge([
            'user_id' => $applicant->user_id, 'government_id' => 'x.jpg', 'id_type' => 'PhilSys', 'selfie' => 'y.jpg',
            'id_image_hash' => str_repeat('c', 64), 'verification_status' => 'Pending',
        ], $check));

        return $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.verifications.show', $verification));
    }

    public function test_a_failed_check_that_was_sent_anyway_is_flagged(): void
    {
        $this->reviewPage(['id_check_status' => 'fail', 'id_type_match' => 'mismatch', 'id_check_overridden' => true])
            ->assertOk()
            ->assertSee('Not an ID by the check')
            ->assertSee('ID type not confirmed')
            ->assertSee('Sent with a failed ID check')
            ->assertSee('the application was sent anyway')
            ->assertSee('Look closely at the ID photos before deciding');
    }

    public function test_a_passing_check_shows_green_chips_and_no_warning(): void
    {
        $this->reviewPage(['id_check_status' => 'pass', 'id_type_match' => 'match', 'id_check_overridden' => false])
            ->assertOk()
            ->assertSee('Looks like an ID')
            ->assertSee('ID type matches')
            ->assertDontSee('Sent with a failed ID check')
            ->assertDontSee('Look closely at the ID photos');
    }

    public function test_an_unsure_check_reads_as_might_not_be_an_id(): void
    {
        $this->reviewPage(['id_check_status' => 'unsure', 'id_type_match' => 'unknown'])
            ->assertOk()
            ->assertSee('Might not be an ID')
            ->assertSee('ID type not confirmed');
    }

    public function test_an_application_made_before_the_check_existed_reads_as_not_checked(): void
    {
        $this->reviewPage([])
            ->assertOk()
            ->assertSee('ID not checked')
            ->assertDontSee('ID type matches')
            ->assertDontSee('ID type not confirmed')
            ->assertDontSee('Sent with a failed ID check');
    }

    public function test_an_unavailable_check_reads_as_not_checked(): void
    {
        $this->reviewPage(['id_check_status' => 'unavailable', 'id_type_match' => 'unknown'])
            ->assertOk()
            ->assertSee('ID not checked');
    }
}
