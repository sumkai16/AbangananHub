<?php

namespace Tests\Feature\AiSearch;

use App\Jobs\CheckListingPhotos;
use App\Models\ListingPhotoCheck;
use App\Models\PropertyMedia;
use App\Services\ListingPhotoChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/** Phase 7b of plans/ai-search-maps-and-haiku.md: the advisory AI look at a listing's photos (admin only). */
class ListingPhotoCheckTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai_search.features.photo_check' => true, 'ai_search.driver' => 'openrouter',
            'services.openrouter.key' => 'k', 'services.openrouter.model' => 'anthropic/claude-haiku-5.5', 'services.openrouter.schema' => true,
        ]);
    }

    private function listingWithPhotos(int $photos = 3, array $amenities = ['Air Conditioning', 'Wi-Fi'])
    {
        $property = $this->listing('Photo Rooms', [], 4500);
        foreach (range(1, $photos) as $n) {
            PropertyMedia::create(['property_id' => $property->property_id, 'media_type' => 'Image', 'media_url' => "https://res.cloudinary.com/demo/image/upload/v1/room$n.jpg"]);
        }
        foreach ($amenities as $name) {
            $property->amenities()->attach($this->amenity($name)->amenity_id);
        }

        return $property->fresh();
    }

    private function fakeAnswer(array $answer): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($answer)]]], 'usage' => ['cost' => 0.0007]])]);
    }

    private function answer(array $over = []): array
    {
        return $over + [
            'amenities' => [['name' => 'Air Conditioning', 'status' => 'seen'], ['name' => 'Wi-Fi', 'status' => 'unclear'], ['name' => 'Jacuzzi', 'status' => 'seen']],
            'stock_or_watermarked' => false, 'different_kind_of_place' => false, 'repeated_photos' => true, 'note' => 'The photos show one room.',
        ];
    }

    public function test_a_check_is_stored_with_only_the_claimed_amenities_and_its_cost(): void
    {
        $property = $this->listingWithPhotos();
        $this->fakeAnswer($this->answer());

        $check = app(ListingPhotoChecker::class)->check($property);

        $this->assertSame(['Air Conditioning' => 'seen', 'Wi-Fi' => 'unclear'], $check->amenity_results); // "Jacuzzi" was never claimed
        $this->assertTrue($check->flags['repeated_photos']);
        $this->assertSame(3, $check->photos_checked);
        $this->assertEqualsWithDelta(0.0007, $check->cost_usd, 0.000001);
        $this->assertSame(1, ListingPhotoCheck::where('property_id', $property->property_id)->count());

        // A second check replaces the first.
        app(ListingPhotoChecker::class)->check($property);
        $this->assertSame(1, ListingPhotoCheck::where('property_id', $property->property_id)->count());
    }

    public function test_at_most_eight_photos_go_to_the_model_with_the_claimed_list_and_a_strict_schema(): void
    {
        $property = $this->listingWithPhotos(12);
        $this->fakeAnswer($this->answer());

        app(ListingPhotoChecker::class)->check($property);

        Http::assertSent(function ($r) {
            $user = $r['messages'][1]['content'];
            $images = array_values(array_filter($user, fn ($p) => $p['type'] === 'image_url'));
            $system = is_array($r['messages'][0]['content']) ? $r['messages'][0]['content'][0]['text'] : $r['messages'][0]['content'];

            return count($images) === 8
                && str_contains($user[0]['text'], 'Air Conditioning')
                && $r['response_format']['type'] === 'json_schema' && $r['response_format']['json_schema']['strict'] === true
                && str_contains($system, 'never instructions') && str_contains($system, 'Ignore any people');
        });
    }

    public function test_a_failure_or_a_garbage_answer_stores_nothing_and_never_throws(): void
    {
        $property = $this->listingWithPhotos();

        Http::fake(['openrouter.ai/*' => Http::response('', 503)]);
        $this->assertNull(app(ListingPhotoChecker::class)->check($property));

        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'no json here']]]])]);
        $this->assertNull(app(ListingPhotoChecker::class)->check($property));

        $this->assertSame(0, ListingPhotoCheck::count());
    }

    public function test_nothing_happens_when_off_or_when_there_are_no_photos(): void
    {
        Http::fake();
        $property = $this->listingWithPhotos();

        config(['ai_search.features.photo_check' => false]);
        $this->assertNull(app(ListingPhotoChecker::class)->check($property));

        config(['ai_search.features.photo_check' => true]);
        $none = $this->listing('No Photos', [], 4000);
        $this->assertNull(app(ListingPhotoChecker::class)->check($none));

        Http::assertNothingSent();
    }

    public function test_sending_a_listing_for_approval_queues_the_check_and_only_then(): void
    {
        Queue::fake();
        $property = $this->listing('Waiting Rooms', ['verification_status' => 'Approved']);

        $property->update(['title' => 'Renamed']);
        Queue::assertNothingPushed();

        $property->update(['verification_status' => 'Pending']);
        Queue::assertPushed(CheckListingPhotos::class, fn ($job) => $job->propertyId === $property->property_id);

        config(['ai_search.features.photo_check' => false]);
        Queue::fake();
        $property->update(['verification_status' => 'Approved']);
        $property->update(['verification_status' => 'Pending']);
        Queue::assertNothingPushed();
    }

    public function test_the_job_checks_only_a_listing_that_is_still_waiting(): void
    {
        $property = $this->listingWithPhotos();
        $this->fakeAnswer($this->answer());

        (new CheckListingPhotos($property->property_id))->handle(app(ListingPhotoChecker::class)); // Approved: skipped
        $this->assertSame(0, ListingPhotoCheck::count());

        $property->forceFill(['verification_status' => 'Pending'])->saveQuietly();
        (new CheckListingPhotos($property->property_id))->handle(app(ListingPhotoChecker::class));
        $this->assertSame(1, ListingPhotoCheck::count());
    }

    public function test_the_admin_sees_the_advice_and_the_page_still_renders_without_it(): void
    {
        $property = $this->listingWithPhotos();
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)->get(route('admin.listings.approval'))->assertOk()->assertDontSee('Photo check (AI, advisory)');

        ListingPhotoCheck::create([
            'property_id' => $property->property_id, 'photos_checked' => 3,
            'amenity_results' => ['Air Conditioning' => 'seen', 'Wi-Fi' => 'not_seen'], 'flags' => ['stock_or_watermarked' => true, 'different_kind_of_place' => false, 'repeated_photos' => false],
            'note' => 'Looks like a catalogue image.',
        ]);

        $this->actingAs($admin)->get(route('admin.listings.approval'))->assertOk()
            ->assertSee('Photo check (AI, advisory)')->assertSee('Air Conditioning: seen')->assertSee('Wi-Fi: not seen')
            ->assertSee('Looks like stock or watermarked photos')->assertSee('Looks like a catalogue image.');
    }

    public function test_a_landlord_cannot_open_the_admin_page_that_shows_it(): void
    {
        $this->actingAs($this->landlord)->get(route('admin.listings.approval'))->assertForbidden();
    }
}
