<?php

namespace Tests\Feature;

use App\Support\Images;
use Tests\TestCase;

/** The named listing-photo sizes, and the guard that keeps raw originals out of the views. */
class ImagesTest extends TestCase
{
    public function test_each_named_size_rewrites_a_cloudinary_url(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/abanganan/units/a.jpg';

        $this->assertStringContainsString('/upload/f_auto,q_auto,w_160/', Images::src($url, 'thumb'));
        $this->assertStringContainsString('/upload/f_auto,q_auto,w_640/', Images::src($url, 'card'));
        $this->assertStringContainsString('/upload/f_auto,q_auto,w_1200/', Images::src($url, 'gallery'));
        $this->assertStringContainsString('/upload/f_auto,q_auto,w_1600/', Images::src($url, 'full'));
    }

    public function test_each_named_size_rewrites_an_unsplash_url(): void
    {
        $url = 'https://images.unsplash.com/photo-1?w=1200&q=80';

        $this->assertStringContainsString('w=640', Images::src($url, 'card'));
        $this->assertStringContainsString('auto=format', Images::src($url, 'card'));
    }

    public function test_null_and_unknown_hosts_come_back_untouched(): void
    {
        $this->assertNull(Images::src(null, 'card'));
        $this->assertSame('https://example.test/a.jpg', Images::src('https://example.test/a.jpg', 'card'));
        $this->assertSame('/storage/legacy/a.jpg', Images::src('/storage/legacy/a.jpg', 'full'));
    }

    public function test_no_view_prints_a_listing_photo_url_without_a_named_size(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if (! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                // Videos cannot be resized as images; the card's own fallback is for hosts Images cannot resize.
                if (str_contains($line, 'media_url') && ! str_contains($line, 'Images::') && ! str_contains($line, '<video')
                    && ! str_ends_with(str_replace('\\', '/', $file->getPathname()), 'components/property-card.blade.php')) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "These lines print media_url without Images::src(..., 'thumb|card|gallery|full'):\n".implode("\n", $offenders));
    }
}
