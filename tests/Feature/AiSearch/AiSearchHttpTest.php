<?php

namespace Tests\Feature\AiSearch;

use Illuminate\Support\Facades\RateLimiter;

class AiSearchHttpTest extends AiSearchTestCase
{
    public function test_the_landing_and_browse_pages_render_the_new_box(): void
    {
        $this->listing('Talamban Rooms');

        $this->get('/')->assertOk()->assertSee('Describe your ideal place');
        $this->get('/properties')->assertOk()->assertSee('Describe the place you want');
    }

    public function test_a_described_search_renders_the_shell_for_guests(): void
    {
        $this->get('/properties?q=' . urlencode('room near USC under 5k'))
            ->assertOk()
            ->assertSee('aiSearch(', false)
            ->assertSee('room near USC under 5k');
    }

    public function test_interpret_returns_chips_and_never_errors_without_an_ai(): void
    {
        $this->postJson('/search/interpret', ['q' => 'boarding house in Talamban under 5k with wifi'])
            ->assertOk()
            ->assertJsonPath('used_ai', false)
            ->assertJsonPath('fallback_reason', 'not_configured')
            ->assertJsonPath('intent.price_max', 5000)
            ->assertJsonFragment(['label' => 'Boarding House'])
            ->assertJsonFragment(['label' => 'Up to ₱5,000']);
    }

    public function test_interpret_rejects_an_empty_or_overlong_query(): void
    {
        $this->postJson('/search/interpret', ['q' => ''])->assertStatus(422);
        $this->postJson('/search/interpret', ['q' => str_repeat('a', 301)])->assertStatus(422);
    }

    public function test_results_return_rendered_cards_with_hit_and_gap_chips(): void
    {
        $this->listing('Talamban Rooms', [], 4500, ['Wi-Fi']);
        $intent = ['location' => ['kind' => 'barangay', 'value' => 'Talamban'], 'price_max' => 4000, 'amenities' => ['Wi-Fi'], 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertStringContainsString('Talamban Rooms', $out['html']);
        $this->assertStringContainsString('No exact match', $out['html']);
        $this->assertStringContainsString('₱500 over budget', $out['html']);
        $this->assertStringContainsString('Raise budget to ₱4,500', $out['html']);
        $this->assertSame(0, $out['exact_count']);
        $this->assertSame('miss', collect($out['chips'])->firstWhere('icon', 'peso')['state']);
    }

    public function test_a_thin_apartment_search_suggests_another_type_in_its_own_section(): void
    {
        $this->listing('Only Apartment', ['property_type' => 'Apartment'], 9000);
        $this->listing('Cozy Bedspace', ['property_type' => 'Bedspace'], 3000);
        $intent = ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'property_type' => 'Apartment', 'price_max' => 10000, 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertStringContainsString('You might also like', $out['html']);
        $this->assertStringContainsString('>Cozy Bedspace</a>, a Bedspace', $out['html']);
        $this->assertStringContainsString('Other option', $out['html']);
        $this->assertSame(1, $out['exact_count']);
    }

    public function test_the_summary_names_the_top_pick_and_what_the_close_one_misses(): void
    {
        $this->listing('Top Flat', ['property_type' => 'Apartment', 'barangay' => 'Talamban'], 4000);
        $this->listing('Pricey Flat', ['property_type' => 'Apartment'], 5200);
        $intent = ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'property_type' => 'Apartment', 'price_max' => 4500, 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertStringContainsString('>Top Flat</a> fits everything you asked for', $out['html']);
        $this->assertStringContainsString('<strong class="font-bold">₱4,000/month</strong>', $out['html']);
        $this->assertStringContainsString('>Pricey Flat</a> is close, but <strong class="font-bold">₱700</strong> over budget', $out['html']);
    }

    public function test_with_no_exact_match_it_lists_the_nearest_options_with_prices_and_links_each(): void
    {
        $near = $this->listing('Near Bedspace', ['property_type' => 'Bedspace', 'latitude' => 10.3800, 'longitude' => 123.9120], 3000); // ~3 km
        $far = $this->listing('Far Bedspace', ['property_type' => 'Bedspace', 'latitude' => 10.3900, 'longitude' => 123.9120], 2500); // ~4 km
        $intent = ['location' => ['kind' => 'landmark', 'value' => 'usc_talamban'], 'property_type' => 'Bedspace', 'price_max' => 4000, 'radius_km' => 2, 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertMatchesRegularExpression('/Closest options: <a [^>]*href="[^"]*' . $near->property_id . '"[^>]*>Near Bedspace<\/a> \(.*<strong[^>]*>₱3,000\/month<\/strong>\) and .*Far Bedspace/s', $out['html']);
    }

    public function test_listing_titles_in_a_summary_cannot_inject_markup(): void
    {
        $p = $this->listing('<script>alert(1)</script> Flat');

        $html = (string) \App\Services\AiSearch\SummaryFormatter::html('See ' . $p->title . ' at ₱4,500/month', collect([$p]));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Flat</a>', $html);
        $this->assertStringContainsString('<strong class="font-bold">₱4,500/month</strong>', $html);
    }

    public function test_results_come_with_map_pins_and_the_page_has_a_map_to_show_them(): void
    {
        $this->listing('Pinned Room', ['latitude' => 10.3540, 'longitude' => 123.9120], 3500);
        $intent = ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertSame('Pinned Room', $out['map'][0]['title']);
        $this->assertSame(1, $out['map'][0]['rank']);
        $this->assertSame('exact', $out['map'][0]['fit']);
        $this->assertNull($out['map_origin']);
        $this->assertEqualsWithDelta(10.354, (float) $out['map'][0]['latitude'], 0.0001);
        $this->assertSame(3500.0, (float) $out['map'][0]['rental_fee']);
        $this->assertStringContainsString('/properties/', $out['map'][0]['url']);

        // The shell has the map container and the data element the map script reads, and listens for the header button.
        $this->get('/properties?q=room+in+cebu')->assertOk()
            ->assertSee('id="browse-map"', false)
            ->assertSee('id="browse-map-data"', false)
            ->assertSee('@browse-toggle-map.window="toggleMap()"', false);
    }

    public function test_the_page_has_a_filters_panel_wired_to_the_header_button(): void
    {
        $this->get('/properties?q=room+in+cebu')->assertOk()
            ->assertSee('@browse-open-filters.window="openFilters()"', false)
            ->assertSee('id="ai-filters-title"', false)
            ->assertSee('Show results', false)
            ->assertSee('Pets allowed', false)
            ->assertSee('Boarding House', false);
    }

    public function test_filters_applied_in_the_panel_are_what_the_results_endpoint_searches_with(): void
    {
        $this->listing('Cheap Room', ['property_type' => 'Boarding House'], 3000);
        $this->listing('Pricey Room', ['property_type' => 'Boarding House'], 9000);
        // What ai-search.js posts after "Show results": the same intent with the panel's values written in.
        $intent = ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'property_type' => 'Boarding House', 'price_min' => 5000, 'price_max' => 12000, 'amenities' => [], 'rules' => [], 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $this->assertSame(1, $out['exact_count']);
        $this->assertStringContainsString('Pricey Room', $out['html']);
        $this->assertSame('₱5,000 to ₱12,000', collect($out['chips'])->firstWhere('icon', 'peso')['label']);
    }

    public function test_map_pins_say_how_well_each_fits_and_the_search_origin_is_sent(): void
    {
        $this->listing('Close Bedspace', ['property_type' => 'Bedspace', 'latitude' => 10.3800, 'longitude' => 123.9120], 3000); // ~3 km
        $this->listing('Other Flat', ['property_type' => 'Apartment', 'latitude' => 10.3540, 'longitude' => 123.9120], 3500);
        $intent = ['location' => ['kind' => 'landmark', 'value' => 'usc_talamban'], 'property_type' => 'Bedspace', 'price_max' => 4000, 'radius_km' => 2, 'language' => 'en'];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => $intent, 'used_ai' => false])->assertOk()->json();

        $close = collect($out['map'])->firstWhere('title', 'Close Bedspace');
        $this->assertSame('close', $close['fit']);
        $this->assertSame(1, $close['rank']);
        $this->assertStringContainsString('km away', $close['tag']);
        $this->assertGreaterThan(2.0, $close['distance_km']);

        $other = collect($out['map'])->firstWhere('title', 'Other Flat');
        $this->assertSame('other', $other['fit']);
        $this->assertNull($other['rank']);
        $this->assertSame('Apartment', $other['tag']);

        $this->assertSame('USC Talamban', $out['map_origin']['name']);
        $this->assertEquals(2.0, $out['map_origin']['radius_km']);
    }

    public function test_an_empty_intent_gets_the_nothing_matches_state(): void
    {
        $out = $this->postJson('/search/results', ['q' => 'hello', 'intent' => ['language' => 'en'], 'used_ai' => false])->assertOk()->json();

        $this->assertStringContainsString('Nothing matches yet', $out['html']);
    }

    public function test_over_the_hourly_limit_it_falls_back_to_keywords_instead_of_failing(): void
    {
        config(['ai_search.rate_per_hour' => 1]);
        RateLimiter::clear('ai-search:127.0.0.1');

        $this->postJson('/search/interpret', ['q' => 'apartment'])->assertOk();
        $this->postJson('/search/interpret', ['q' => 'apartment again'])
            ->assertOk()
            ->assertJsonPath('fallback_reason', 'rate_limited');
    }

    public function test_the_fast_reading_is_instant_keyword_only_and_does_not_use_the_hourly_allowance(): void
    {
        config(['ai_search.rate_per_hour' => 1]);
        RateLimiter::clear('ai-search:127.0.0.1');

        foreach (range(1, 3) as $_) {
            $this->postJson('/search/interpret', ['q' => 'apartment under 4k', 'fast' => true])
                ->assertOk()
                ->assertJsonPath('fallback_reason', 'fast')
                ->assertJsonPath('used_ai', false)
                ->assertJsonPath('intent.price_max', 4000);
        }

        // The real (AI) reading still has its one allowed call.
        $this->postJson('/search/interpret', ['q' => 'apartment under 4k'])->assertJsonPath('fallback_reason', 'not_configured');
    }

    public function test_the_results_page_has_the_mobile_card_carousel_wired_to_the_map(): void
    {
        $this->get('/properties?q=room+in+cebu')->assertOk()
            ->assertSee('x-ref="carousel"', false)
            ->assertSee('@browse-map-selected.window="onMapSelected($event.detail)"', false);
    }

    public function test_a_forged_used_ai_flag_is_charged_to_its_own_hourly_limit(): void
    {
        config(['ai_search.rank_with_ai' => true, 'ai_search.rate_per_hour' => 2]);
        \Illuminate\Support\Facades\RateLimiter::clear('ai-rank:127.0.0.1');

        $payload = ['q' => 'apartment', 'used_ai' => true, 'intent' => ['property_type' => 'Apartment']];

        foreach (range(1, 3) as $_) {
            $this->postJson('/search/results', $payload)->assertOk();
        }

        // Two calls were allowed; the third is over the bucket, so it was served without the AI.
        $this->assertTrue(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts('ai-rank:127.0.0.1', 2));
    }
}
