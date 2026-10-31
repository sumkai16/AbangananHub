<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Page titles carry the navy icon tile (Messages, My Reservations, Notifications...), and the
 * browse type chips carry the same icons on the landing page as on /properties.
 */
class PageIconTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private const TILE = 'w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-[#060D26]';

    public function test_the_tile_renders_a_path_or_a_custom_svg(): void
    {
        $this->assertStringContainsString(self::TILE, Blade::render('<x-page-icon path="M0 0h1" />'));
        $this->assertStringContainsString('d="M0 0h1"', Blade::render('<x-page-icon path="M0 0h1" />'));
        $this->assertStringContainsString('<i>x</i>', Blade::render('<x-page-icon><i>x</i></x-page-icon>'));
    }

    public function test_page_header_draws_its_tile_from_icon_path(): void
    {
        $html = Blade::render('<x-page-header title="T" icon-path="M1 1h2" />');

        $this->assertStringContainsString(self::TILE, $html);
        $this->assertStringContainsString('d="M1 1h2"', $html);
        $this->assertStringNotContainsString(self::TILE, Blade::render('<x-page-header title="T" />'));
    }

    public function test_list_pages_that_lacked_an_icon_now_have_one(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        foreach (['admin.conversations.index', 'admin.reviews.index', 'admin.listings.approval', 'admin.payouts.index', 'admin.deposit-charges.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee(self::TILE, false);
        }

        $this->actingAs($this->makeTenant())->get(route('tenant.reports.index'))->assertOk()->assertSee(self::TILE, false);
        $this->actingAs($this->makeLandlord())->get(route('landlord.units.index'))->assertOk()->assertSee(self::TILE, false);
    }

    public function test_landing_type_pills_use_the_same_icons_as_the_browse_strip(): void
    {
        $landing = $this->get(route('home'))->assertOk();
        $browse = $this->get(route('properties.index'))->assertOk();

        foreach (PropertyType::cases() as $type) {
            $landing->assertSee($type->icon(), false);
            $browse->assertSee($type->icon(), false);
        }
        $landing->assertSee(PropertyType::ALL_ICON, false);
    }
}
