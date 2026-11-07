<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Models\User;
use App\Services\AccountFilePurger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Deleting an account removes the private ID/selfie images it leaves behind. A user with no profile picture
 * or business logo never reaches Cloudinary.
 */
class AccountFilePurgeTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function applicantWithFiles(): array
    {
        $user = $this->makeTenant();
        $paths = [
            "verifications/{$user->user_id}/id_photo.jpg",
            "verifications/{$user->user_id}/id_back.jpg",
            "verifications/{$user->user_id}/selfie.jpg",
        ];
        foreach ($paths as $path) {
            Storage::disk('local')->put($path, 'x');
        }

        LandlordVerification::create([
            'user_id' => $user->user_id, 'government_id' => $paths[0], 'id_back' => $paths[1], 'selfie' => $paths[2],
            'id_type' => 'Driver License', 'id_image_hash' => str_repeat('c', 64), 'business_name' => 'B',
            'contact_number' => '0917', 'business_address' => 'A', 'verification_status' => 'Pending', 'submitted_at' => now(),
        ]);

        return [$user, $paths];
    }

    public function test_an_admin_delete_removes_the_verification_images(): void
    {
        [$user, $paths] = $this->applicantWithFiles();

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->delete(route('admin.users.destroy', $user))
            ->assertSessionHas('success');

        $this->assertNull(User::find($user->user_id));
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_deleting_your_own_account_removes_the_verification_images(): void
    {
        [$user, $paths] = $this->applicantWithFiles();

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->assertNull(User::find($user->user_id));
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_files_are_kept_when_the_delete_is_refused(): void
    {
        [$user, $paths] = $this->applicantWithFiles();
        $this->makeProperty($user);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->delete(route('admin.users.destroy', $user))
            ->assertSessionHas('error');

        $this->assertNotNull(User::find($user->user_id));
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_a_cloudinary_public_id_is_recovered_from_a_delivery_url(): void
    {
        $this->assertSame(
            'abanganganhub/profile-pictures/abc123',
            AccountFilePurger::publicIdFromUrl('https://res.cloudinary.com/demo/image/upload/c_fill,w_400/v1712345/abanganganhub/profile-pictures/abc123.jpg')
        );
        $this->assertNull(AccountFilePurger::publicIdFromUrl('https://example.com/avatar.jpg'));
        $this->assertNull(AccountFilePurger::publicIdFromUrl(null));
    }

    public function test_a_clean_user_snapshot_has_nothing_for_cloudinary(): void
    {
        $this->assertSame([], AccountFilePurger::snapshot($this->makeTenant())['cloudinary']);
    }
}
