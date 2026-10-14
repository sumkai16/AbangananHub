<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * One logo, drawn in one place (components/brand-logo). Pages used to hand-copy it, and the
 * auth pages used a placeholder house-in-a-square instead of the real mark.
 */
class BrandLogoTest extends TestCase
{
    public function test_light_surface_logo_swaps_to_the_white_mark_in_dark_mode(): void
    {
        $html = Blade::render('<x-brand-logo />');

        $this->assertStringContainsString('logo-mark.svg', $html);
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('logo-mark-on-dark.svg', $html);
        $this->assertStringContainsString('hidden dark:block', $html);
        $this->assertStringContainsString('Abanganan<span class="text-[#FF8A66]">Hub</span>', $html);
    }

    public function test_always_dark_surface_logo_uses_only_the_white_mark_and_wordmark(): void
    {
        $html = Blade::render('<x-brand-logo tone="on-dark" />');

        $this->assertStringContainsString('logo-mark-on-dark.svg', $html);
        $this->assertStringNotContainsString('logo-mark.svg', $html);
        $this->assertStringContainsString('text-white', $html);
    }

    public function test_the_mark_is_decorative_beside_the_wordmark_and_named_without_it(): void
    {
        $this->assertStringNotContainsString('alt="AbangananHub"', Blade::render('<x-brand-logo />'));
        $this->assertStringContainsString('alt="AbangananHub"', Blade::render('<x-brand-logo :wordmark="false" />'));
    }

    public function test_public_and_auth_pages_show_the_real_logo(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/this-page-does-not-exist'] as $url) {
            $this->get($url)->assertSee('images/brand/logo-mark', false);
        }
    }

    public function test_no_view_hand_draws_the_wordmark(): void
    {
        // The landlord and admin sidebars keep their own wordmark because it collapses with the
        // sidebar; they still use the real mark.
        $allowed = ['components/brand-logo.blade.php', 'layouts/landlord.blade.php', 'layouts/admin.blade.php'];

        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->reject(fn ($path) => in_array($path, $allowed, true))
            ->filter(fn ($path) => str_contains(File::get(resource_path('views/'.$path)), 'Abanganan<span'))
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Use <x-brand-logo> instead of drawing the wordmark by hand.');
    }
}
