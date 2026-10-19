<?php

namespace Tests\Feature;

use App\Services\ImageUploader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

class ProfileSettingsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_strength_counts_four_fillable_things_and_names_what_is_missing(): void
    {
        $user = $this->makeTenant();
        $user->forceFill(['profile_picture' => null, 'contact_number' => '09181112222', 'bio' => null])->save();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('Add a profile photo and a short bio');

        $user->forceFill(['profile_picture' => 'https://img.test/me.jpg', 'bio' => 'Hi'])->save();
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee('100%')
            ->assertSee('Your account is fully set up.');
    }

    public function test_a_landlord_manages_payout_and_business_details_on_account_settings(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);

        $this->get(route('profile.edit'))->assertOk()
            ->assertSee('Payout details')->assertSee('Business information')->assertSee('gcash_number', false);

        $this->patch(route('landlord.profile.update'), [
            'gcash_number' => '09171112222', 'gcash_account_name' => 'Maria Santos',
            'business_name' => 'Santos Rentals', 'business_address' => 'Cebu City',
        ])->assertRedirect(route('profile.edit'));

        $landlord = $landlord->fresh();
        $this->assertSame('09171112222', $landlord->payoutAccount->gcash_number);
        $this->assertSame('Santos Rentals', $landlord->rentalBusiness->business_name);
    }

    public function test_the_old_landlord_edit_url_redirects_and_a_tenant_sees_no_landlord_sections(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord)->get(route('landlord.profile.edit'))->assertRedirect(route('profile.edit'));

        $this->actingAs($this->makeTenant())->get(route('profile.edit'))
            ->assertOk()->assertDontSee('Payout details')->assertDontSee('Business information');
    }

    private function fakeUploader(): void
    {
        $this->app->instance(ImageUploader::class, new class extends ImageUploader
        {
            public function upload(UploadedFile $file, string $folder, array $options = []): array
            {
                return ['url' => 'https://img.test/'.$file->getClientOriginalName(), 'public_id' => $folder.'/x'];
            }
        });
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    public function test_the_avatar_pencil_opens_a_file_picker_instead_of_leaving_the_page(): void
    {
        $this->actingAs($this->makeTenant())->get(route('profile.edit'))
            ->assertSee('id="avatar-pick"', false)
            ->assertSee('action="'.route('profile.photo').'"', false);
    }

    public function test_the_tenant_profile_page_sends_edits_to_account_settings(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->get(route('tenant.profile.show'))
            ->assertOk()
            ->assertSee('href="'.route('profile.edit').'"', false);

        // The retired form's old URL still lands somewhere useful.
        $this->actingAs($tenant)->get('/tenant/profile/edit')->assertRedirect(route('profile.edit'));
    }

    public function test_choosing_a_photo_saves_it_for_any_role(): void
    {
        $this->fakeUploader();

        foreach ([$this->makeTenant(), $this->makeLandlord()] as $user) {
            $this->actingAs($user)->post(route('profile.photo'), ['photo' => $this->png('me.png')])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHasNoErrors();

            $this->assertSame('https://img.test/me.png', $user->fresh()->profile_picture);
        }
    }

    public function test_a_non_image_is_refused_and_the_old_photo_is_kept(): void
    {
        $this->fakeUploader();
        $user = $this->makeTenant();
        $user->forceFill(['profile_picture' => 'https://img.test/old.png'])->save();

        $this->actingAs($user)->post(route('profile.photo'), ['photo' => UploadedFile::fake()->createWithContent('cv.pdf', '%PDF-1.4 not an image')])
            ->assertSessionHasErrors('photo');

        $this->assertSame('https://img.test/old.png', $user->fresh()->profile_picture);
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('profile.photo'), ['photo' => $this->png('me.png')])->assertRedirect(route('login'));
    }
}
