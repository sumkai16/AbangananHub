<?php

namespace Tests\Feature;

use App\Models\UnitMedia;
use App\Services\ImageUploader;
use App\Support\ListingPhotos;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Photo limits (6 per property, 6 per unit, a unit still needs 3) and the shrink-at-upload options.
 * plans/image-and-demo-data-cleanup.md
 */
class ListingPhotosTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    /** Cloudinary replaced by a mock that records every image upload's options. */
    private function fakeCloudinary(array &$calls): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('upload')->andReturnUsing(function ($path, $options) use (&$calls) {
            $calls[] = $options;

            return new ApiResponse(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/x/'.count($calls).'.jpg', 'public_id' => 'x/'.count($calls)], []);
        });
        $client = Mockery::mock(Cloudinary::class);
        $client->shouldReceive('uploadApi')->andReturn($api);
        $this->app->instance(Cloudinary::class, $client);
    }

    /** A real 1x1 PNG: the CLI has no GD, so UploadedFile::fake()->image() cannot be used. */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    /** A valid new-unit form with $count photos. */
    private function unitForm(int $count): array
    {
        return [
            'unit_label' => 'Room A', 'rental_fee' => 3500, 'security_deposit' => 3500, 'occupancy_limit' => 2,
            'availability_status' => 'Available', 'description' => 'Test',
            'photos' => array_map(fn ($i) => $this->png("p{$i}.png"), range(1, max($count, 1))),
            'photo_sources' => array_fill(0, max($count, 1), 'upload'),
        ];
    }

    public function test_the_limits_are_six_and_a_unit_still_needs_three(): void
    {
        $this->assertSame(6, ListingPhotos::MAX_PROPERTY);
        $this->assertSame(6, ListingPhotos::MAX_UNIT);
        $this->assertSame(3, ListingPhotos::MIN_UNIT);
        $this->assertFalse(ListingPhotos::tooMany(3, 3, 6));
        $this->assertTrue(ListingPhotos::tooMany(4, 3, 6));
        $this->assertStringContainsString('6 photos', ListingPhotos::unitLimitMessage());
    }

    public function test_the_upload_options_shrink_to_2000px_with_automatic_quality(): void
    {
        $options = ImageUploader::listingPhoto('abanganan/units');

        $this->assertSame('abanganan/units', $options['folder']);
        $this->assertSame('image', $options['resource_type']);
        $this->assertSame(2000, $options['transformation']['width']);
        $this->assertSame(2000, $options['transformation']['height']);
        $this->assertSame('limit', $options['transformation']['crop']);
        $this->assertStringStartsWith('auto', $options['transformation']['quality']);
    }

    public function test_a_new_unit_with_three_photos_is_saved_and_every_upload_is_shrunk(): void
    {
        $calls = [];
        $this->fakeCloudinary($calls);
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.units.store', $property), $this->unitForm(3))->assertSessionHasNoErrors();

        $this->assertCount(3, $calls);
        foreach ($calls as $options) {
            $this->assertSame('limit', $options['transformation']['crop']);
            $this->assertSame(2000, $options['transformation']['width']);
        }
        $this->assertSame(3, $property->units()->where('unit_label', 'Room A')->first()->media()->count());
    }

    public function test_a_new_unit_with_seven_photos_is_refused(): void
    {
        $calls = [];
        $this->fakeCloudinary($calls);
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.units.store', $property), $this->unitForm(7))->assertSessionHasErrors('photos');

        $this->assertSame([], $calls);
    }

    public function test_a_new_unit_with_two_photos_is_still_refused(): void
    {
        $calls = [];
        $this->fakeCloudinary($calls);
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.units.store', $property), $this->unitForm(2))->assertSessionHasErrors('photos');
    }

    public function test_adding_photos_to_a_unit_counts_the_ones_it_already_has(): void
    {
        $calls = [];
        $this->fakeCloudinary($calls);
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);
        foreach (range(1, 4) as $i) {
            UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => 'Image', 'media_url' => "https://example.test/{$i}.jpg", 'source' => 'upload']);
        }

        // 4 existing + 3 new = 7: over the limit, even though 3 alone is fine.
        $form = $this->unitForm(3) + [];
        $form += ['unit_label' => 'Room A'];
        $this->actingAs($landlord)->put(route('landlord.properties.units.update', [$property, $unit]), $form)->assertSessionHasErrors('photos');
        $this->assertSame([], $calls);

        // 4 existing + 2 new = 6: allowed.
        $this->actingAs($landlord)->put(route('landlord.properties.units.update', [$property, $unit]), $this->unitForm(2))->assertSessionHasNoErrors();
        $this->assertSame(6, $unit->media()->where('media_type', 'Image')->count());
    }
}
