<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Models\User;
use App\Services\FaceMatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The ID photo check in the landlord application: the preview counts failed tries, the submit applies the same rule,
 * and a photo that is not an ID gets two tries before the applicant may continue (flagged for the admin).
 */
class VerificationIdCheckTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['services.google_vision.key' => 'test-key']);
        $this->mock(FaceMatch::class)->shouldReceive('compare')->andReturn(['status' => 'unavailable', 'similarity' => null]);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    /** A small valid JPEG; a different shade gives different bytes, so each photo has its own hash. */
    private function photo(int $shade): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, $shade, 120, 200 - $shade));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function dataUrl(string $bytes): string
    {
        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    /** What Vision reads on a PhilSys card in the applicant's own name. */
    private function idText(User $user): string
    {
        return "REPUBLIKA NG PILIPINAS\nPhilippine Identification Card\n6813-2017-2150-9536\nApelyido/Last Name\n"
            .strtoupper($user->last_name)."\nMga Pangalan/Given Names\n".strtoupper($user->first_name)
            ."\nDate of Birth\nSEPTEMBER 06, 2004\nAddress\nPUROK SAN VICENTE, MINGLANILLA, CEBU";
    }

    /** @return array{text: string, faces: int, labels: array<string, float>} */
    private function goodId(User $user): array
    {
        return ['text' => $this->idText($user), 'faces' => 1, 'labels' => ['identity document' => 0.9]];
    }

    /**
     * Vision answers by photo: each photo's reading is listed under the sha1 of its bytes; 'down' is an outage.
     * A photo not listed reads as blank (no text, no face, no label).
     *
     * @param  array<string, array{text?: string, faces?: int, labels?: array<string, float>}|string>  $byPhoto
     */
    private function fakeVision(array $byPhoto): void
    {
        Http::fake(['vision.googleapis.com/*' => function ($request) use ($byPhoto) {
            $reading = $byPhoto[sha1(base64_decode($request['requests'][0]['image']['content']))] ?? [];

            if ($reading === 'down') {
                return Http::response('unavailable', 503);
            }

            $labels = [];
            foreach ($reading['labels'] ?? [] as $name => $score) {
                $labels[] = ['description' => $name, 'score' => $score];
            }

            return Http::response(['responses' => [[
                'fullTextAnnotation' => ['text' => $reading['text'] ?? ''],
                'faceAnnotations' => array_fill(0, $reading['faces'] ?? 0, ['detectionConfidence' => 0.9]),
                'labelAnnotations' => $labels,
            ]]]);
        }]);
    }

    private function preview(User $user, string $front, ?string $back = null, string $type = 'PhilSys')
    {
        return $this->actingAs($user)->postJson(route('landlord.verification.ocrCheck'), array_filter([
            'id_image' => $this->dataUrl($front),
            'id_back' => $back === null ? null : $this->dataUrl($back),
            'id_type' => $type,
        ]));
    }

    private function submit(User $user, string $front, ?string $back, string $selfie)
    {
        return $this->actingAs($user)->post(route('landlord.verification.store'), array_filter([
            'id_type' => 'PhilSys',
            'id_image' => $this->dataUrl($front),
            'id_back' => $back === null ? null : $this->dataUrl($back),
            'selfie' => $this->dataUrl($selfie),
            'liveness_passed' => true,
            'business_name' => 'Test Rentals',
            'contact_number' => '09171234567',
            'business_address' => 'Cebu City',
        ]));
    }

    private function saved(User $user): ?LandlordVerification
    {
        return LandlordVerification::where('user_id', $user->user_id)->first();
    }

    // ── The wizard page ───────────────────────────────────────────────────────

    public function test_the_wizard_loads_its_script_with_a_version_so_phones_never_run_a_stale_copy(): void
    {
        $version = filemtime(public_path('js/verification-wizard.js'));

        $this->actingAs($this->makeTenant())
            ->get(route('landlord.verification.create'))
            ->assertOk()
            ->assertSee('js/verification-wizard.js?v='.$version, false)
            ->assertSee('idCheckCard', false);
    }

    public function test_the_face_check_screen_describes_three_movements_then_the_photo(): void
    {
        $this->actingAs($this->makeTenant())
            ->get(route('landlord.verification.create'))
            ->assertOk()
            ->assertSee('Three quick movements prove')
            ->assertSee('look at the camera and we take the photo for you')
            ->assertDontSee('Four quick movements')
            ->assertDontSee('Look up')
            ->assertSee('livenessChecklist', false);
    }

    // ── The preview counts failed tries ───────────────────────────────────────

    public function test_one_preview_with_front_and_back_counts_as_one_attempt(): void
    {
        $user = $this->makeTenant();
        [$front, $back] = [$this->photo(10), $this->photo(20)];
        $this->fakeVision([]);

        $this->preview($user, $front, $back)
            ->assertOk()
            ->assertJsonPath('id_check.status', 'fail')
            ->assertJsonPath('id_check.back_status', 'fail')
            ->assertJsonPath('id_check.blocked', true)
            ->assertJsonPath('id_check.attempts_left', 1);

        $this->assertSame(1, session('id_check_failures'));
    }

    public function test_the_second_failed_preview_lets_the_applicant_continue(): void
    {
        $user = $this->makeTenant();
        [$front, $back] = [$this->photo(10), $this->photo(20)];
        $this->fakeVision([]);

        $this->preview($user, $front, $back);
        $this->preview($user, $this->photo(11), $this->photo(21))
            ->assertJsonPath('id_check.status', 'fail')
            ->assertJsonPath('id_check.blocked', false)
            ->assertJsonPath('id_check.attempts_left', 0);

        $this->assertSame(2, session('id_check_failures'));
    }

    public function test_a_passing_preview_does_not_count_and_keeps_the_name_and_text_keys(): void
    {
        $user = $this->makeTenant();
        [$front, $back] = [$this->photo(10), $this->photo(20)];
        $this->fakeVision([
            sha1($front) => $this->goodId($user),
            sha1($back) => ['text' => "Conditions: 1 Wear eyeglasses\nRestrictions A B"],
        ]);

        $this->preview($user, $front, $back)
            ->assertOk()
            ->assertJsonPath('id_check.status', 'pass')
            ->assertJsonPath('id_check.back_status', 'pass')
            ->assertJsonPath('id_check.blocked', false)
            ->assertJsonPath('id_check.attempts_left', 2)
            ->assertJsonPath('status', 'pass')
            ->assertJsonPath('type_match', 'match')
            ->assertJsonPath('id_number', '6813-2017-2150-9536')
            ->assertJsonPath('back_extracted', "Conditions: 1 Wear eyeglasses\nRestrictions A B");

        $this->assertSame(0, (int) session('id_check_failures'));
    }

    public function test_a_failing_back_alone_counts_once_and_blocks(): void
    {
        $user = $this->makeTenant();
        [$front, $back] = [$this->photo(10), $this->photo(20)];
        $this->fakeVision([sha1($front) => $this->goodId($user)]);

        $this->preview($user, $front, $back)
            ->assertJsonPath('id_check.status', 'pass')
            ->assertJsonPath('id_check.back_status', 'fail')
            ->assertJsonPath('id_check.blocked', true);

        $this->assertSame(1, session('id_check_failures'));
    }

    public function test_a_passport_has_no_back_to_check(): void
    {
        $user = $this->makeTenant();
        $front = $this->photo(10);
        $this->fakeVision([sha1($front) => [
            'text' => "REPUBLIC OF THE PHILIPPINES\nPASSPORT\nDate of Birth SEPT 06 2004\nNationality FILIPINO",
            'faces' => 1, 'labels' => ['passport' => 0.9],
        ]]);

        $this->preview($user, $front, null, 'Passport')
            ->assertJsonPath('id_check.status', 'pass')
            ->assertJsonPath('id_check.back_status', null)
            ->assertJsonPath('id_check.blocked', false);
    }

    public function test_an_unsure_preview_does_not_count(): void
    {
        $user = $this->makeTenant();
        $front = $this->photo(10);
        $this->fakeVision([sha1($front) => ['text' => 'REPUBLIKA PILIPINAS 681', 'faces' => 1, 'labels' => ['identity document' => 0.8]]]);

        $this->preview($user, $front)
            ->assertJsonPath('id_check.status', 'unsure')
            ->assertJsonPath('id_check.blocked', false);

        $this->assertSame(0, (int) session('id_check_failures'));
    }

    public function test_an_unavailable_preview_never_blocks_or_counts(): void
    {
        $user = $this->makeTenant();
        $front = $this->photo(10);
        $this->fakeVision([sha1($front) => 'down']);

        $this->preview($user, $front)
            ->assertOk()
            ->assertJsonPath('id_check.status', 'unavailable')
            ->assertJsonPath('id_check.blocked', false);

        $this->assertSame(0, (int) session('id_check_failures'));
    }

    // ── The submit applies the same rule ──────────────────────────────────────

    public function test_store_saves_and_flags_a_failing_id_even_after_a_blocked_preview(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([]);
        // A failed look the wizard would have held Continue for. Its answer can be lost or overtaken, and turning the
        // applicant away now would throw away the finished wizard (photos and selfie), so the submit never does.
        $this->preview($user, $front, $back);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('fail', $saved->id_check_status);
        $this->assertTrue($saved->id_check_overridden);
        Storage::disk('local')->assertExists("verifications/{$user->user_id}/id_photo.jpg");
        $this->assertNull(session('id_check_failures'));
    }

    public function test_store_without_a_preview_flags_but_does_not_block(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([]);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('fail', $saved->id_check_status);
        $this->assertTrue($saved->id_check_overridden);
    }

    public function test_store_after_two_failures_saves_flags_and_clears_the_count(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([]);
        $this->preview($user, $front, $back);
        $this->preview($user, $front, $back);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('fail', $saved->id_check_status);
        $this->assertTrue($saved->id_check_overridden);
        $this->assertNull(session('id_check_failures'));
    }

    public function test_a_failing_back_makes_the_saved_check_fail_even_when_the_front_passes(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([sha1($front) => $this->goodId($user)]);
        $this->preview($user, $front, $back);
        $this->preview($user, $front, $back);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('fail', $saved->id_check_status);
        $this->assertSame('match', $saved->id_type_match);
        $this->assertTrue($saved->id_check_overridden);
    }

    public function test_store_saves_pass_and_type_match(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([
            sha1($front) => $this->goodId($user),
            sha1($back) => ['text' => "Conditions: 1 Wear eyeglasses\nRestrictions A B"],
        ]);
        $this->preview($user, $front, $back);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('pass', $saved->id_check_status);
        $this->assertSame('match', $saved->id_type_match);
        $this->assertFalse($saved->id_check_overridden);
        $this->assertSame('6813-2017-2150-9536', $saved->id_number);
        $this->assertSame('pass', $saved->ocr_status);
        Storage::disk('local')->assertExists("verifications/{$user->user_id}/id_photo.jpg");
    }

    public function test_an_outage_at_submit_saves_the_application_marked_not_checked(): void
    {
        $user = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        $this->fakeVision([sha1($front) => 'down', sha1($back) => 'down']);

        $this->submit($user, $front, $back, $selfie)->assertSessionDoesntHaveErrors();

        $saved = $this->saved($user);
        $this->assertSame('unavailable', $saved->id_check_status);
        $this->assertFalse($saved->id_check_overridden);
    }

    // ── Rejections happen before anything on disk changes ─────────────────────

    public function test_a_refused_resubmission_keeps_the_earlier_files(): void
    {
        $user = $this->makeTenant();
        $other = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        Storage::disk('local')->put("verifications/{$user->user_id}/id_photo.jpg", 'earlier id');
        LandlordVerification::create([
            'user_id' => $user->user_id, 'government_id' => "verifications/{$user->user_id}/id_photo.jpg",
            'id_type' => 'PhilSys', 'selfie' => "verifications/{$user->user_id}/selfie.jpg", 'id_image_hash' => str_repeat('a', 64),
            'verification_status' => 'Rejected',
        ]);
        LandlordVerification::create([
            'user_id' => $other->user_id, 'government_id' => 'x.jpg', 'id_type' => 'PhilSys', 'selfie' => 'y.jpg',
            'id_image_hash' => str_repeat('b', 64), 'id_number' => '6813-2017-2150-9536', 'verification_status' => 'Approved',
        ]);
        $this->fakeVision([
            sha1($front) => $this->goodId($user),
            sha1($back) => ['text' => "Conditions: 1 Wear eyeglasses\nRestrictions A B"],
        ]);

        $this->submit($user, $front, $back, $selfie)->assertSessionHasErrors('id_image');

        $this->assertSame('earlier id', Storage::disk('local')->get("verifications/{$user->user_id}/id_photo.jpg"));
    }

    public function test_a_duplicate_id_number_is_refused_before_anything_is_stored(): void
    {
        $user = $this->makeTenant();
        $other = $this->makeTenant();
        [$front, $back, $selfie] = [$this->photo(10), $this->photo(20), $this->photo(30)];
        LandlordVerification::create([
            'user_id' => $other->user_id, 'government_id' => 'x.jpg', 'id_type' => 'PhilSys', 'selfie' => 'y.jpg',
            'id_image_hash' => str_repeat('b', 64), 'id_number' => '6813-2017-2150-9536', 'verification_status' => 'Approved',
        ]);
        $this->fakeVision([
            sha1($front) => $this->goodId($user),
            sha1($back) => ['text' => "Conditions: 1 Wear eyeglasses\nRestrictions A B"],
        ]);

        $this->submit($user, $front, $back, $selfie)->assertSessionHasErrors('id_image');

        $this->assertNull($this->saved($user));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
