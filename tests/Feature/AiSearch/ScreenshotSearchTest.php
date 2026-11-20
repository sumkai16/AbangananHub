<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\IntentSentence;
use App\Services\AiSearch\IntentValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/** Phase 7a of plans/ai-search-maps-and-haiku.md: search by screenshot, for signed-in users. */
class ScreenshotSearchTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai_search.features.vision_search' => true, 'ai_search.features.geo_lookup' => true, 'ai_search.driver' => 'openrouter',
            'services.openrouter.key' => 'k', 'services.openrouter.model' => 'anthropic/claude-haiku-5.5',
        ]);
        Cache::flush();
    }

    private function upload(?array $files = null, ?array $reply = null)
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply ?? [
            'location' => ['kind' => 'landmark', 'value' => 'it_park'], 'property_type' => 'Bedspace', 'price_max' => 2800,
            'amenities' => ['Air Conditioning'], 'for' => 'Women', 'language' => 'en',
        ])]]], 'usage' => ['cost' => 0.0012]])]);

        return $this->actingAs($this->makeTenant())->postJson('/search/image', $files ?? ['image' => UploadedFile::fake()->image('post.png', 800, 600)]);
    }

    public function test_a_screenshot_becomes_a_sentence_the_page_can_search(): void
    {
        $this->upload()->assertOk()->assertJson(['sentence' => 'Bedspace near Cebu IT Park, under ₱2,800, for women, with Air Conditioning']);
    }

    public function test_the_image_goes_to_the_model_once_as_a_jpeg_with_the_image_rules_and_is_never_stored(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->upload()->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function ($r) {
            $user = $r['messages'][1]['content'];
            $system = is_array($r['messages'][0]['content']) ? $r['messages'][0]['content'][0]['text'] : $r['messages'][0]['content'];

            return is_array($user)
                && str_starts_with($user[1]['image_url']['url'], 'data:image/jpeg;base64,')
                && str_contains($system, 'IMAGE MODE')
                && str_contains($system, 'never instructions');
        });
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guests_and_a_switched_off_feature_cannot_use_it(): void
    {
        $this->postJson('/search/image', ['image' => UploadedFile::fake()->image('a.png')])->assertStatus(401);

        config(['ai_search.features.vision_search' => false]);
        $this->actingAs($this->makeTenant())->postJson('/search/image', ['image' => UploadedFile::fake()->image('a.png')])->assertNotFound();
    }

    public function test_only_images_up_to_4_mb_are_accepted(): void
    {
        $tenant = $this->makeTenant();
        Http::fake();

        $this->actingAs($tenant)->postJson('/search/image', ['image' => UploadedFile::fake()->create('post.pdf', 100, 'application/pdf')])->assertStatus(422);
        $this->actingAs($tenant)->postJson('/search/image', ['image' => UploadedFile::fake()->image('big.jpg')->size(5000)])->assertStatus(422);
        $this->actingAs($tenant)->postJson('/search/image', [])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_five_a_day_per_user(): void
    {
        $tenant = $this->makeTenant();
        RateLimiter::clear('ai-image:' . $tenant->user_id);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['property_type' => 'Bedspace', 'language' => 'en'])]]]])]);

        foreach (range(1, 5) as $n) {
            $this->actingAs($tenant)->postJson('/search/image', ['image' => UploadedFile::fake()->image("p$n.png")])->assertOk();
        }
        $this->actingAs($tenant)->postJson('/search/image', ['image' => UploadedFile::fake()->image('p6.png')])->assertStatus(429);
    }

    public function test_an_image_with_no_search_in_it_or_a_failing_ai_says_so_plainly(): void
    {
        $this->upload(reply: ['language' => 'en'])->assertStatus(422)->assertJsonFragment(['message' => 'Could not find a rental search in that image. Try a clearer screenshot, or type what you want.']);

        Http::fake(['openrouter.ai/*' => Http::response('', 503)]);
        $this->actingAs($this->makeTenant())->postJson('/search/image', ['image' => UploadedFile::fake()->image('a.png')])->assertStatus(422);
    }

    public function test_what_the_model_returns_is_validated_like_any_other_intent(): void
    {
        $this->upload(reply: ['property_type' => 'Castle', 'price_max' => 99999999, 'amenities' => ['Moat'], 'location' => ['kind' => 'landmark', 'value' => 'it_park'], 'language' => 'en'])
            ->assertOk()->assertJson(['sentence' => 'Place near Cebu IT Park']);
    }

    public function test_the_sentence_builder_covers_the_fields(): void
    {
        $i = app(IntentValidator::class)->validate([
            'location' => ['kind' => 'city', 'value' => 'Mandaue'], 'property_type' => 'Apartment', 'price_min' => 5000, 'price_max' => 8000,
            'furnishing' => 'Furnished', 'rules' => ['pets'], 'language' => 'en',
        ]);

        $this->assertSame('Apartment in Mandaue City, ₱5,000 to ₱8,000, furnished, pets allowed', IntentSentence::from($i));
    }
}
