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
