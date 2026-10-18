<?php

namespace Tests\Feature;

use App\Models\UnitMedia;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Unit photos and videos remember their Cloudinary public id, and deleting one asks Cloudinary
 * for the right asset. Before, the id was guessed from the URL and videos were never deleted.
 */
class UnitMediaTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    /** Replaces the Cloudinary client with a mock so no test reaches the network. */
    private function fakeCloudinary(): \Mockery\MockInterface
    {
        $api = Mockery::mock(UploadApi::class);
        $client = Mockery::mock(Cloudinary::class);
        $client->shouldReceive('uploadApi')->andReturn($api);
        $this->app->instance(Cloudinary::class, $client);

        return $api;
    }

    private function media(array $attributes = []): UnitMedia
    {
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        return $unit->media()->create(array_merge([
            'media_type' => 'Image',
            'media_url'  => 'https://res.cloudinary.com/demo/image/upload/v1/abanganan/units/abc123.jpg',
            'source'     => 'upload',
        ], $attributes));
    }

    public function test_the_stored_public_id_is_used_for_an_image(): void
    {
        $api = $this->fakeCloudinary();
        $api->shouldReceive('destroy')->once()->with('abanganan/units/abc123', ['resource_type' => 'image']);

        $this->media(['cloudinary_public_id' => 'abanganan/units/abc123'])->destroyFromCloudinary();
    }

    public function test_a_video_is_destroyed_as_a_video_in_its_own_folder(): void
    {
        $api = $this->fakeCloudinary();
        $api->shouldReceive('destroy')->once()->with('abanganan/units/videos/clip9', ['resource_type' => 'video']);

        $this->media([
            'media_type'           => 'Video',
            'media_url'            => 'https://res.cloudinary.com/demo/video/upload/v1/abanganan/units/videos/clip9.mp4',
            'cloudinary_public_id' => 'abanganan/units/videos/clip9',
        ])->destroyFromCloudinary();
    }

    public function test_a_row_without_a_stored_id_falls_back_to_the_url_guess(): void
    {
        $api = $this->fakeCloudinary();
        $api->shouldReceive('destroy')->once()->with('abanganan/units/abc123', ['resource_type' => 'image']);

        $this->media()->destroyFromCloudinary();
    }

    public function test_a_cloudinary_failure_is_logged_and_never_thrown(): void
    {
        $api = $this->fakeCloudinary();
        $api->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('network down'));

        $this->media(['cloudinary_public_id' => 'abanganan/units/abc123'])->destroyFromCloudinary();

        $this->addToAssertionCount(1); // reaching here means nothing was thrown
    }

    public function test_deleting_a_unit_photo_over_http_destroys_the_asset_and_the_row(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);
        foreach (range(1, 4) as $n) {
            $unit->media()->create([
                'media_type'           => 'Image',
                'media_url'            => "https://res.cloudinary.com/demo/image/upload/v1/abanganan/units/p{$n}.jpg",
                'cloudinary_public_id' => "abanganan/units/p{$n}",
                'source'               => 'upload',
            ]);
        }
        $photo = $unit->media()->first();

        $api = $this->fakeCloudinary();
        $api->shouldReceive('destroy')->once()->with($photo->cloudinary_public_id, ['resource_type' => 'image']);

        $this->actingAs($landlord)
            ->delete(route('landlord.properties.units.media.destroy', [$property, $unit, $photo->media_id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('unit_media', ['media_id' => $photo->media_id]);
    }
}
