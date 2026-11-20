<?php

namespace Tests\Feature\AiSearch;

use App\Http\Controllers\AiSearchFeedbackController;
use App\Models\AiSearchLog;
use App\Models\AiSearchReview;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/** Phase 8 of plans/ai-search-maps-and-haiku.md: Search insights, thumbs, and real searches becoming tests. */
class SearchInsightsTest extends AiSearchTestCase
{
    private function admin(): User
    {
        return $this->makeUserWithRole('Admin');
    }

    private function log(array $attrs = []): AiSearchLog
    {
        return AiSearchLog::create($attrs + [
            'user_id' => null, 'query' => 'bedspace near Lahug', 'language' => 'en',
            'intent' => ['property_type' => 'Bedspace', 'location' => ['kind' => 'landmark', 'name' => 'Lahug', 'value' => 'lahug'], 'unmapped' => []],
            'result_count' => 3, 'exact_count' => 3, 'relaxed' => [], 'driver' => 'openrouter', 'used_ai' => true,
            'latency_ms' => 1000, 'ai_calls' => 1, 'cost_usd' => 0.0005,
        ]);
    }

    // ─── the page ───────────────────────────────────────────────

    public function test_only_an_admin_can_open_it(): void
    {
        $this->get(route('admin.search-insights.index'))->assertRedirect();
        $this->actingAs($this->makeTenant())->get(route('admin.search-insights.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.search-insights.index'))->assertOk()->assertSee('Search insights');
    }

    public function test_the_numbers_match_the_log_rows(): void
    {
        $this->log(['cost_usd' => 0.001, 'latency_ms' => 800, 'feedback' => 1]);
        $this->log(['query' => 'dorm near the airport', 'result_count' => 0, 'exact_count' => 0, 'used_ai' => false, 'fallback_reason' => 'rate_limited', 'latency_ms' => 200, 'cost_usd' => 0, 'feedback' => -1,
            'intent' => ['property_type' => 'Dormitory', 'location' => ['kind' => 'landmark', 'name' => 'Mactan-Cebu Airport', 'value' => 'mactan_airport'], 'unmapped' => ['near the beach']], 'relaxed' => ['budget +15%']]);
        $this->log(['created_at' => now()->subDays(60)]); // outside every period

        $response = $this->actingAs($this->admin())->get(route('admin.search-insights.index', ['days' => 30]))->assertOk();

        $response->assertViewHas('stats', fn ($s) => $s['total'] == 2 && $s['ai_share'] == 50 && $s['zero_rate'] == 50
            && $s['cost'] == 0.001 && $s['avg_ms'] == 500 && $s['up'] == 1 && $s['down'] == 1);
        $response->assertSee('dorm near the airport');            // top zero-result search
        $response->assertSee('rate limited');                      // why the AI was not used
        $response->assertSee('near the beach');                    // an unmapped wish
        $response->assertSee('Dormitory near Mactan-Cebu Airport'); // a supply gap
        $response->assertSee('budget +15%');
    }

    public function test_an_unknown_period_falls_back_to_30_days_and_an_empty_log_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.search-insights.index', ['days' => 9999]))
            ->assertOk()->assertViewHas('days', 30)->assertSee('No searches in this period.');
    }

    // ─── thumbs ─────────────────────────────────────────────────

    public function test_a_search_carries_a_token_that_rates_only_that_search(): void
    {
        $this->listing('Talamban Rooms', [], 4500);

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => ['property_type' => 'Boarding House', 'language' => 'en'], 'used_ai' => false])->assertOk()->json();
        $this->assertNotEmpty($out['feedback_token']);
        $log = AiSearchLog::latest('id')->first();

        $this->postJson('/search/feedback', ['token' => $out['feedback_token'], 'value' => -1])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(-1, $log->fresh()->feedback);

        // A rating can be changed.
        $this->postJson('/search/feedback', ['token' => $out['feedback_token'], 'value' => 1])->assertOk();
        $this->assertSame(1, $log->fresh()->feedback);
    }

    public function test_a_forged_or_old_token_and_a_bad_value_change_nothing(): void
    {
        $log = $this->log();
        $other = $this->log();

        $this->postJson('/search/feedback', ['token' => 'not-a-token', 'value' => 1])->assertStatus(422);
        $this->postJson('/search/feedback', ['token' => (string) $log->id, 'value' => 1])->assertStatus(422); // a bare id is not a token
        $this->postJson('/search/feedback', ['token' => AiSearchFeedbackController::tokenFor($log), 'value' => 5])->assertStatus(422);
        $this->assertNull($log->fresh()->feedback);
        $this->assertNull($other->fresh()->feedback);

        $old = $this->log(['created_at' => now()->subDays(2)]);
        $this->postJson('/search/feedback', ['token' => Crypt::encryptString((string) $old->id), 'value' => 1])->assertOk()->assertJson(['ok' => false]);
        $this->assertNull($old->fresh()->feedback);
    }

    // ─── reviews and the fixture export ─────────────────────────

    public function test_an_admin_saves_a_corrected_reading_and_bad_json_is_refused(): void
    {
        $log = $this->log(['query' => 'bedspace sa Lahug 3k']);
        $admin = $this->admin();
        $good = json_encode(['location' => ['kind' => 'landmark', 'value' => 'lahug'], 'property_type' => 'Bedspace', 'price_max' => 3000]);

        $this->actingAs($admin)->post(route('admin.search-insights.review'), ['log_id' => $log->id, 'intent' => $good])->assertSessionHas('success');
        $review = AiSearchReview::sole();
        $this->assertSame('bedspace sa Lahug 3k', $review->query);
        $this->assertSame(3000, $review->intent['price_max']);
        $this->assertSame($admin->user_id, (int) $review->reviewed_by);

        $this->actingAs($admin)->post(route('admin.search-insights.review'), ['log_id' => $log->id, 'intent' => 'not json'])->assertSessionHasErrors('intent');
        $this->actingAs($this->makeTenant())->post(route('admin.search-insights.review'), ['log_id' => $log->id, 'intent' => $good])->assertForbidden();
        $this->assertSame(1, AiSearchReview::count());
    }

    public function test_the_export_appends_reviewed_searches_once_in_the_fixture_format(): void
    {
        $path = base_path('tests/fixtures/ai-search-cases.json');
        $before = file_get_contents($path);

        try {
            $q = 'unit test sentence ' . uniqid();
            AiSearchReview::create(['query' => $q, 'intent' => ['location' => ['kind' => 'landmark', 'value' => 'lahug'], 'property_type' => 'Bedspace', 'price_max' => 3000, 'amenities' => ['Wi-Fi']]]);

            $this->artisan('ai-search:export-fixtures')->assertSuccessful();

            $cases = json_decode(file_get_contents($path), true);
            $added = collect($cases)->firstWhere('q', $q);
            $this->assertSame(['location' => 'lahug', 'property_type' => 'Bedspace', 'price_max' => 3000, 'amenities' => ['Wi-Fi']], $added['expect']);
            $this->assertNotNull(AiSearchReview::first()->exported_at);

            // Nothing waiting: nothing changes, and the sentence is not added twice.
            $this->artisan('ai-search:export-fixtures')->assertSuccessful();
            $this->assertCount(1, collect(json_decode(file_get_contents($path), true))->where('q', $q));
        } finally {
            file_put_contents($path, $before); // leave the real fixtures exactly as they were
        }
    }
}
