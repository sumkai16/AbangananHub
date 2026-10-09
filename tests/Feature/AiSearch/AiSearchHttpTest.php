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
}
