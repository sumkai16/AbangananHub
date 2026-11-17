<?php

namespace Tests\Feature;

use App\Models\PropertyMedia;
use App\Models\UnitMedia;
use App\Support\Images;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\LandlordSeeder;
use Database\Seeders\PropertySeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The seeded demo must not need the internet (the defense venue's connection is slow): listing and unit photos are local
 * files under public/images/seed, stored as site-relative paths.
 */
class SeedImagesLocalTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> every photo id the photo seeders name. */
    private function referencedIds(): array
    {
        $ids = [];
        foreach (['PropertySeeder.php', 'PromoSeeder.php'] as $file) {
            preg_match_all("/['\"](\d{10,}-[0-9a-f]{12})['\"]/", file_get_contents(base_path('database/seeders/'.$file)), $m);
            $ids = array_merge($ids, $m[1]);
        }

        return array_values(array_unique($ids));
    }

    public function test_every_photo_the_seeders_name_is_a_readable_local_jpeg(): void
    {
        $ids = $this->referencedIds();
        $this->assertGreaterThanOrEqual(30, count($ids));

        foreach ($ids as $id) {
            $path = public_path('images/seed/'.$id.'.jpg');
            $this->assertFileExists($path, "Missing seed photo {$id}");
            $info = @getimagesize($path);
            $this->assertNotFalse($info, "{$id}.jpg is not a readable image");
            $this->assertSame(IMAGETYPE_JPEG, $info[2], "{$id}.jpg is not a JPEG");
            $this->assertLessThanOrEqual(1200, max($info[0], $info[1]));
        }
    }

    public function test_seeded_listings_and_units_point_at_local_files_only(): void
    {
        config(['broadcasting.default' => 'null']);
        $this->seed([UserSeeder::class, LandlordSeeder::class, AmenitySeeder::class, PropertySeeder::class]);

        $urls = PropertyMedia::pluck('media_url')->merge(UnitMedia::pluck('media_url'))->unique();
        $this->assertNotEmpty($urls);

        foreach ($urls as $url) {
            $this->assertStringStartsWith('/images/seed/', $url);
            $this->assertStringNotContainsString('unsplash', $url);
            $this->assertFileExists(public_path(ltrim($url, '/')), "Seeded URL {$url} has no file");
        }
    }

    public function test_a_local_path_is_left_alone_by_the_resizer_and_made_absolute_for_the_api(): void
    {
        $this->assertSame('/images/seed/x.jpg', Images::src('/images/seed/x.jpg', 'card'));
        $this->assertSame('/images/seed/x.jpg', Images::resize('/images/seed/x.jpg', 320));
        $this->assertNull(Images::srcset('/images/seed/x.jpg'));

        $this->assertSame(url('/images/seed/x.jpg'), Images::absolute('/images/seed/x.jpg'));
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/a.jpg', Images::absolute('https://res.cloudinary.com/demo/image/upload/a.jpg'));
        $this->assertNull(Images::absolute(null));
        // Remote hosts are still resized as before.
        $this->assertStringContainsString('w=320', Images::resize('https://images.unsplash.com/photo-1?w=1200&q=80', 320));
    }
}
