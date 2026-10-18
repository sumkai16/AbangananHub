<?php

namespace Tests\Feature\AiSearch;

use App\Models\Property;
use App\Models\RentalBusiness;
use App\Models\User;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\ChipBuilder;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\NameMatcher;

/**
 * Search by business or listing name, and the "not on AbangananHub" notice
 * (plans/business-first-identity-and-name-search.md, phase 2). Personal names are deliberately not searchable.
 */
class NameSearchTest extends AiSearchTestCase
{
    private User $santos;
    private User $lozada;
    private Property $hilltop;
    private Property $mabolo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->santos = $this->landlordWithBusiness('Maria', 'Santos', 'Santos Rentals Cebu');
        $this->lozada = $this->landlordWithBusiness('Wilfredo', 'Lozada', 'Lozada Urban Living');

        $this->hilltop = $this->listingBy($this->santos, 'Hilltop Rooms', ['property_type' => 'Boarding House']);
        $this->mabolo = $this->listingBy($this->lozada, 'Mabolo Garden Apartment', [
            'property_type' => 'Apartment', 'barangay' => 'Mabolo', 'address' => 'Mabolo, Cebu City, Cebu',
        ]);
    }

    private function landlordWithBusiness(string $first, string $last, string $business): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole('Landlord');
        RentalBusiness::create(['landlord_id' => $user->user_id, 'business_name' => $business, 'contact_number' => '09170000000']);

        return $user;
    }

    private function listingBy(User $landlord, string $title, array $attributes = []): Property
    {
        $property = $this->makeProperty($landlord, array_merge(['title' => $title, 'address' => 'Talamban, Cebu City, Cebu', 'barangay' => 'Talamban'], $attributes));
        $this->makeUnit($property, ['rental_fee' => 4000]);

        return $property->fresh();
    }

    /** interpret (keyword reading, no AI) then results, like the page does. @return array{0: array, 1: array} */
    private function search(string $query): array
    {
        $service = app(AiSearchService::class);
        $read = $service->interpret($query, 'fast');

        return [$read['intent'], $service->results($query, $read['intent'], false)];
    }

    private function titles(array $result): array
    {
        return $result['rows']->pluck('property.title')->all();
    }

    // ─── a business ─────────────────────────────────────────────

    public function test_a_sentence_with_a_name_marker_finds_the_business_through_its_surname(): void
    {
        [$intent, $result] = $this->search('property ni maria santos');

        $this->assertSame('business', $intent['host']['kind']);
        $this->assertSame([$this->santos->user_id], $intent['host']['landlord_ids']);
        $this->assertSame('Santos Rentals Cebu', $intent['host']['name']);
        // "maria" is not in the business name, so this is a near miss and the page must say so.
        $this->assertSame('maria santos', $intent['host']['typed']);
        $this->assertNull($intent['name_miss']);

        $this->assertSame(['Hilltop Rooms'], $this->titles($result));
        $this->assertStringContainsString('closest business name', $result['summary']);
        $this->assertContains('By Santos Rentals Cebu', array_column($result['chips'], 'label'));
        $this->assertNull($result['name_notice']);
    }

    public function test_the_exact_business_name_matches_without_a_marker(): void
    {
        [$intent, $result] = $this->search('Lozada Urban Living');

        $this->assertSame([$this->lozada->user_id], $intent['host']['landlord_ids']);
        $this->assertNull($intent['host']['typed']);
        $this->assertSame(['Mabolo Garden Apartment'], $this->titles($result));
        $this->assertStringStartsWith('Showing places by Lozada Urban Living.', $result['summary']);
    }

    public function test_a_marker_with_the_distinctive_word_is_not_a_near_miss(): void
    {
        [$intent] = $this->search('room kang Lozada');

        $this->assertSame([$this->lozada->user_id], $intent['host']['landlord_ids']);
        $this->assertNull($intent['host']['typed']);
    }

    public function test_a_named_business_never_leaks_other_landlords_listings_even_with_filters(): void
    {
        $this->listingBy($this->santos, 'Santos Studio', ['property_type' => 'Apartment']);

        [, $result] = $this->search('apartment ni santos');

        // Santos has an apartment; Lozada's apartment is cheaper-or-equal but is not theirs.
        $this->assertSame(['Santos Studio'], $this->titles($result));
    }

    public function test_words_inside_a_business_name_are_not_also_a_filter(): void
    {
        $tudtud = $this->landlordWithBusiness('Gerardo', 'Tudtud', 'Tudtud Boarding Houses');
        $this->listingBy($tudtud, 'Tunghaan Rooms', ['property_type' => 'Boarding House']);
        $this->listingBy($tudtud, 'Tudtud Corner Apartment', ['property_type' => 'Apartment']);
        NameMatcher::flush();

        [$intent, $result] = $this->search('mga places listed by tudtud boarding houses');

        // "boarding houses" belongs to the business name, so it is not a property-type filter...
        $this->assertSame([$tudtud->user_id], $intent['host']['landlord_ids']);
        $this->assertNull($intent['property_type']);
        $this->assertSame(['Tudtud Boarding Houses'], [$intent['host']['name']]);
        // ...and every place they list shows, whatever its type, with no "you might also like" strip.
        $this->assertEqualsCanonicalizing(['Tunghaan Rooms', 'Tudtud Corner Apartment'], $this->titles($result));
        $this->assertTrue($result['alternatives']->isEmpty());
        $this->assertNotContains('Boarding House', array_column($result['chips'], 'label'));
    }

    public function test_a_type_asked_for_outside_the_name_is_kept(): void
    {
        $tudtud = $this->landlordWithBusiness('Gerardo', 'Tudtud', 'Tudtud Boarding Houses');
        $this->listingBy($tudtud, 'Tunghaan Rooms', ['property_type' => 'Boarding House']);
        $this->listingBy($tudtud, 'Tudtud Corner Apartment', ['property_type' => 'Apartment']);
        NameMatcher::flush();

        [$intent, $result] = $this->search('apartment by tudtud boarding houses');

        $this->assertSame('Apartment', $intent['property_type']);
        $this->assertSame(['Tudtud Corner Apartment'], $this->titles($result));
    }

    public function test_a_listing_titles_area_and_type_words_are_not_filters_either(): void
    {
        [$intent] = $this->search('Mabolo Garden Apartment');

        $this->assertNull($intent['property_type']);
        $this->assertNull($intent['location']);
    }

    // ─── more from the business ─────────────────────────────────

    public function test_the_businesss_other_places_show_under_the_results(): void
    {
        $this->listingBy($this->santos, 'Santos Studio', ['property_type' => 'Apartment']);

        [, $result] = $this->search('apartment ni santos');

        $this->assertSame(['Santos Studio'], $this->titles($result));
        $more = $result['more_from_host'];
        $this->assertSame('Santos Rentals Cebu', $more['name']);
        // The filter hid Hilltop Rooms from the results; it is still one of their places.
        $this->assertSame(['Hilltop Rooms'], $more['properties']->pluck('title')->all());
        $this->assertSame(1, $more['total']);

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('More from Santos Rentals Cebu', $html);
        $this->assertStringContainsString('1 other place they list', $html);
        $this->assertStringNotContainsString('See all places by', $html);
    }

    public function test_nothing_is_added_when_every_place_is_already_shown_or_there_is_no_business(): void
    {
        $this->listingBy($this->santos, 'Santos Studio', ['property_type' => 'Apartment']);

        [, $all] = $this->search('ni santos');
        $this->assertNull($all['more_from_host']);

        [, $listing] = $this->search('Mabolo Garden Apartment');
        $this->assertNull($listing['more_from_host'], 'a named listing has no business to show more of');

        [, $plain] = $this->search('boarding house in Talamban');
        $this->assertNull($plain['more_from_host']);
    }

    public function test_the_strip_is_capped_and_links_to_the_business_page(): void
    {
        foreach (range(1, 8) as $n) {
            $this->listingBy($this->santos, "Extra Room {$n}", ['property_type' => 'Apartment']);
        }

        [, $result] = $this->search('boarding house ni santos');

        $more = $result['more_from_host'];
        $this->assertSame(['Hilltop Rooms'], $this->titles($result));
        $this->assertCount(6, $more['properties']);
        $this->assertSame(8, $more['total']);
        $this->assertNotContains('Hilltop Rooms', $more['properties']->pluck('title')->all());

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('See all places by Santos Rentals Cebu', $html);
        $this->assertStringContainsString(route('landlord.profile.show', $this->santos) . '#properties', $html);
    }

    // ─── a listing ──────────────────────────────────────────────

    public function test_a_listing_title_finds_that_listing_first(): void
    {
        [$intent, $result] = $this->search('Mabolo Garden Apartment');

        $this->assertSame('listing', $intent['host']['kind']);
        $this->assertSame([$this->mabolo->property_id], $intent['host']['property_ids']);
        $this->assertSame('Mabolo Garden Apartment', $this->titles($result)[0]);
    }

    public function test_a_title_made_of_place_and_type_words_never_becomes_a_name(): void
    {
        $this->listingBy($this->lozada, 'IT Park Dormitory, Apas', ['property_type' => 'Dormitory', 'barangay' => 'Apas', 'address' => 'Apas, Cebu City, Cebu']);
        NameMatcher::flush();

        [$intent] = $this->search('dormitory near it park');

        $this->assertNull($intent['host']);
        $this->assertNull($intent['name_miss']);
    }

    // ─── what must not match ────────────────────────────────────

    public function test_a_personal_first_name_alone_matches_nothing(): void
    {
        [$intent] = $this->search('Wilfredo');

        $this->assertNull($intent['host']);
        $this->assertNull($intent['name_miss']);
    }

    public function test_generic_words_in_a_business_name_do_not_match(): void
    {
        [$intent] = $this->search('rentals in cebu with urban living');

        $this->assertNull($intent['host']);
    }

    public function test_a_business_with_nothing_live_is_not_matched(): void
    {
        $this->landlordWithBusiness('Gina', 'Ghost', 'Ghost Rentals');
        NameMatcher::flush();

        [$intent] = $this->search('ghost rentals');

        $this->assertNull($intent['host']);
    }

    public function test_a_short_lone_word_needs_a_marker(): void
    {
        $this->landlordWithBusiness('Ana', 'Yap', 'Yap Western Cebu Rentals');
        $this->listingBy(User::whereHas('rentalBusiness', fn ($q) => $q->where('business_name', 'Yap Western Cebu Rentals'))->firstOrFail(), 'Yap Rooms');
        NameMatcher::flush();

        [$without] = $this->search('yap');
        [$with] = $this->search('rooms ni yap');

        $this->assertNull($without['host']);
        $this->assertNotNull($with['host']);
    }

    // ─── not on AbangananHub ────────────────────────────────────

    public function test_a_name_we_do_not_have_says_so_and_keeps_the_rest_of_the_search(): void
    {
        [$intent, $result] = $this->search('boarding house ni Juan Dela Cruz');

        $this->assertNull($intent['host']);
        $this->assertSame('juan dela cruz', $intent['name_miss']);
        $this->assertSame(['kind' => 'name', 'name' => 'juan dela cruz', 'others' => true], $result['name_notice']);
        // The name is not a filter: boarding houses still show, and a "By ..." chip does not exist.
        $this->assertSame(['Hilltop Rooms'], $this->titles($result));

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('No listing or business called', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('Other places that match the rest of your search', $html);
    }

    public function test_a_name_we_do_not_have_and_nothing_else_asked_shows_only_the_notice(): void
    {
        [, $result] = $this->search('property ni Juan Dela Cruz');

        $this->assertSame('name', $result['name_notice']['kind']);
        $this->assertTrue($result['rows']->isEmpty());

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('No listing or business called', $html);
        $this->assertStringContainsString('Browse all places', $html);
        $this->assertStringNotContainsString('Nothing matches yet', $html);
        // Never claims anything about people.
        $this->assertStringNotContainsStringIgnoringCase('no landlord', $html);
    }

    public function test_an_unknown_place_that_finds_nothing_gets_its_own_wording(): void
    {
        $service = app(AiSearchService::class);
        $intent = app(IntentValidator::class)->validate(['location' => ['kind' => 'text', 'value' => 'Zzyzx Heights']]);

        $result = $service->results('zzyzx heights', $intent, false);

        $this->assertSame('place', $result['name_notice']['kind']);
        $this->assertStringContainsString('doesn&rsquo;t match any place or listing name', view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render());
    }

    public function test_an_ordinary_search_has_no_name_and_no_notice(): void
    {
        [$intent, $result] = $this->search('boarding house in Talamban under 5k');

        $this->assertNull($intent['host']);
        $this->assertNull($intent['name_miss']);
        $this->assertNull($result['name_notice']);
        $this->assertNotEmpty($this->titles($result));
    }

    // ─── plumbing ───────────────────────────────────────────────

    public function test_the_ai_filing_a_name_as_a_place_is_overridden_by_the_name(): void
    {
        $intent = app(IntentValidator::class)->validate(['location' => ['kind' => 'text', 'value' => 'Maria Santos']]);

        $applied = app(NameMatcher::class)->apply('property ni maria santos', $intent);

        $this->assertNull($applied['location']);
        $this->assertNotNull($applied['host']);
    }

    public function test_a_posted_back_host_is_rechecked_against_our_tables(): void
    {
        $validator = app(IntentValidator::class);

        $forged = $validator->validate(['host' => ['kind' => 'business', 'landlord_ids' => [999999], 'property_ids' => [888888], 'name' => 'Anything']]);
        $this->assertNull($forged['host']);

        $ours = $validator->validate(['host' => ['landlord_ids' => [$this->santos->user_id, 999999], 'name' => 'Spoofed Name']]);
        $this->assertSame([$this->santos->user_id], $ours['host']['landlord_ids']);
        $this->assertSame('Santos Rentals Cebu', $ours['host']['name']);
    }

    public function test_a_posted_back_intent_with_a_host_survives_a_second_validation(): void
    {
        $validator = app(IntentValidator::class);
        [$intent] = $this->search('Lozada Urban Living');

        $this->assertSame($intent, $validator->validate($intent));
    }

    public function test_removing_the_host_chip_clears_the_name(): void
    {
        [$intent] = $this->search('Lozada Urban Living');

        $cleared = ChipBuilder::remove($intent, 'host', null);

        $this->assertNull($cleared['host']);
    }

    public function test_the_http_endpoints_round_trip_a_named_search(): void
    {
        $read = $this->postJson('/search/interpret', ['q' => 'property ni maria santos'])->assertOk()
            ->assertJsonPath('intent.host.name', 'Santos Rentals Cebu')
            ->assertJsonFragment(['label' => 'By Santos Rentals Cebu'])
            ->json();

        $out = $this->postJson('/search/results', ['q' => 'property ni maria santos', 'intent' => $read['intent'], 'used_ai' => false])
            ->assertOk()->json();

        $this->assertStringContainsString('Hilltop Rooms', $out['html']);
        $this->assertStringNotContainsString('Mabolo Garden Apartment', $out['html']);
    }
}
