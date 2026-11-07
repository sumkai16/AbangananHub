<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Services\FaceMatch;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Rekognition\RekognitionClient;
use Aws\Result;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The face check is a signal for the admin: it must never throw, and a failure must read as "not checked".
 */
class FaceMatchTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function service(MockHandler $mock): FaceMatch
    {
        return new FaceMatch(new RekognitionClient([
            'version' => 'latest', 'region' => 'ap-southeast-1',
            'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => $mock,
        ]));
    }

    public function test_a_similar_face_is_a_match_with_its_score(): void
    {
        $mock = new MockHandler([new Result(['FaceMatches' => [['Similarity' => 96.4]]])]);

        $this->assertSame(['status' => 'match', 'similarity' => 96.4], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_a_weak_similarity_is_a_mismatch_but_keeps_its_score(): void
    {
        config(['services.rekognition.threshold' => 80]);
        $mock = new MockHandler([new Result(['FaceMatches' => [['Similarity' => 52.0]]])]);

        $this->assertSame(['status' => 'mismatch', 'similarity' => 52.0], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_a_real_but_lowish_score_is_review_not_mismatch(): void
    {
        // A genuine applicant scored 77.4% on their own ID: similar enough that an admin should look, not "different".
        config(['services.rekognition.threshold' => 80, 'services.rekognition.review_floor' => 60]);
        $mock = new MockHandler([new Result(['FaceMatches' => [['Similarity' => 77.4]]])]);

        $this->assertSame(['status' => 'review', 'similarity' => 77.4], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_no_match_at_all_is_a_mismatch_with_zero(): void
    {
        $mock = new MockHandler([new Result(['FaceMatches' => [], 'UnmatchedFaces' => [['Confidence' => 99]]])]);

        $this->assertSame(['status' => 'mismatch', 'similarity' => 0.0], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_a_picture_without_a_face_is_reported_as_no_face(): void
    {
        $mock = new MockHandler([
            fn ($cmd, $req) => new AwsException('There are no faces in the image', $cmd, ['code' => 'InvalidParameterException']),
        ]);

        $this->assertSame(['status' => 'no_face', 'similarity' => null], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_an_aws_failure_never_throws_and_reads_as_not_checked(): void
    {
        $mock = new MockHandler([
            fn ($cmd, $req) => new AwsException('Throttled', $cmd, ['code' => 'ThrottlingException']),
        ]);

        $this->assertSame(['status' => 'unavailable', 'similarity' => null], $this->service($mock)->compare('id', 'selfie'));
    }

    public function test_without_keys_the_check_is_skipped(): void
    {
        config(['services.rekognition.key' => null, 'services.rekognition.secret' => null]);

        $this->assertSame(['status' => 'unavailable', 'similarity' => null], (new FaceMatch)->compare('id', 'selfie'));
    }

    public function test_the_admin_sees_the_face_match_result(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $applicant = $this->makeTenant();
        $verification = LandlordVerification::create([
            'user_id' => $applicant->user_id, 'government_id' => 'x', 'id_image_hash' => str_repeat('b', 64),
            'id_type' => 'Driver License', 'selfie' => 'x', 'business_name' => 'Test', 'contact_number' => '0917',
            'business_address' => 'Somewhere', 'verification_status' => 'Pending', 'submitted_at' => now(),
            'face_match_status' => 'match', 'face_match_score' => 91.25,
        ]);

        $this->actingAs($admin)->get(route('admin.verifications.show', $verification))->assertOk()
            ->assertSee('Face match:', false)->assertSee('91.25%', false);

        $verification->update(['face_match_status' => 'review', 'face_match_score' => 77.4]);

        $this->actingAs($admin)->get(route('admin.verifications.show', $verification))->assertOk()
            ->assertSee('somewhat similar', false)->assertSee('77.4%', false);

        $verification->update(['face_match_status' => 'unavailable', 'face_match_score' => null]);

        $this->actingAs($admin)->get(route('admin.verifications.show', $verification))->assertOk()
            ->assertSee('Face match not checked');
    }
}
