<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** The shared Back link returns to the page the person came from, unless a form page pins it with `fixed`. */
class BackLinkComponentTest extends TestCase
{
    public function test_back_uses_browser_history_with_the_href_as_fallback(): void
    {
        $html = Blade::render('<x-back-link href="/properties" to="listing" />');

        $this->assertStringContainsString('href="/properties"', $html);
        $this->assertStringContainsString('h.back()', $html);
        $this->assertStringContainsString('document.referrer', $html);
    }

    public function test_a_fixed_back_link_always_goes_to_its_href(): void
    {
        $html = Blade::render('<x-back-link fixed href="/landlord/properties" to="My Properties" />');

        $this->assertStringContainsString('href="/landlord/properties"', $html);
        $this->assertStringNotContainsString('h.back()', $html);
    }
}
