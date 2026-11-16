<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The admin list pages mark their cards and their results as swap regions (resources/js/page-swap.js), so a card click
 * replaces only those two. Each page must render, carry both regions once, and have its tags balanced inside them.
 */
class AdminPageSwapRegionsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public static function pages(): array
    {
        return array_map(fn ($r) => [$r], [
            'admin.audit-logs.index', 'admin.catalogue.properties.index', 'admin.catalogue.units.index',
            'admin.conversations.index', 'admin.deposit-charges.index', 'admin.payments.index',
            'admin.reports.index', 'admin.reservations.index', 'admin.reviews.index', 'admin.users.index',
        ]);
    }

    /** @dataProvider pages */
    public function test_each_list_page_has_a_cards_and_a_results_region(string $route): void
    {
        $html = $this->actingAs($this->makeUserWithRole('Admin'))->get(route($route))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-swap-region="cards"'), $route.' cards');
        $this->assertSame(1, substr_count($html, 'data-swap-region="results"'), $route.' results');

        // Whole-document balance: a region wrapper that closes in the wrong place leaves the page unbalanced.
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $this->assertSame(1, $xpath->query('//*[@id="main"]//*[@data-swap-region="results"]')->length, $route.' results sit inside main');
        $this->assertSame(1, $xpath->query('//*[@id="main"]//*[@data-swap-region="cards"]')->length, $route.' cards sit inside main');
    }
}
