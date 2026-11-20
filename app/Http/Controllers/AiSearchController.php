<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Services\AiSearch\AiFeatures;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiUsage;
use App\Services\AiSearch\RefinePatch;
use App\Services\AiSearch\Travel;
use App\Support\Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The two calls behind the AI search box. interpret() reads the sentence and
 * returns the filter chips; results() takes the (possibly chip-edited) filters
 * and returns the rendered summary + cards. Both always answer 200: when the AI
 * is down, limited or out of quota the page gets keyword results and a notice.
 */
class AiSearchController extends Controller
{
    public function __construct(private AiSearchService $search) {}

    public function interpret(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:' . config('ai_search.max_query_length', 300)],
            'fast' => ['sometimes', 'boolean'],
        ]);

        // `fast`: the page asks for an instant keyword reading first (no AI, not rate limited), then the real one.
        if ($request->boolean('fast')) {
            return response()->json($this->search->interpret($data['q'], 'fast'));
        }

        return response()->json($this->search->interpret($data['q'], $this->limited($request) ? 'rate_limited' : null));
    }

    /**
     * A follow-up ("cheaper", "closer", "may aircon") on the search the page already shows. `patch` is a quick chip
     * (no AI, not rate limited); `q` is typed text (one AI call, rate limited like interpret).
     */
    public function refine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:' . config('ai_search.max_query_length', 300)],
            'previous' => ['required', 'array'],
            'patch' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
        ]);
        abort_if(blank($data['q'] ?? null) && empty($data['patch']), 422, 'Say what to change.');

        $patch = (array) ($data['patch'] ?? []);

        return response()->json($this->search->refine(
            (string) ($data['q'] ?? ''),
            $data['previous'],
            $patch,
            (array) ($data['context'] ?? []),
            $patch === [] && $this->limited($request) ? 'rate_limited' : null,
        ));
    }

    /**
     * "Why this fits you": asked for by the page after the cards are shown. Always answers 200, with nothing in it
     * when the feature is off, the AI is down or a limit is hit (the cards just have no line).
     */
    public function reasons(Request $request): JsonResponse
    {
        $data = $request->validate(['intent' => ['required', 'array']]);

        if ($this->limited($request, 'ai-reasons')) {
            return response()->json(['reasons' => (object) [], 'summary' => '', 'summary_en' => '']);
        }

        $out = $this->search->reasons($data['intent'], $request->user()?->user_id);
        $out['reasons'] = (object) $out['reasons'];

        return response()->json($out);
    }

    /**
     * Search by screenshot (logged-in users only): reads the image into a search and answers with a plain sentence
     * the page then searches like any typed one. The image is re-encoded in memory (which drops EXIF and location
     * data), sent once, and never stored. 5 a day per user.
     */
    public function image(Request $request): JsonResponse
    {
        abort_unless(AiFeatures::on('vision_search'), 404);

        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        $key = 'ai-image:' . $request->user()->user_id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'You have used today\'s screenshot searches. Try again tomorrow, or type what you want.'], 429);
        }
        RateLimiter::hit($key, 86400);

        $dataUri = $this->reencode($request->file('image')->getRealPath());
        if ($dataUri === null) {
            return response()->json(['message' => 'That image could not be read. Try a screenshot as a JPG or PNG.'], 422);
        }

        $read = $this->search->interpretImage($dataUri);
        if ($read === null) {
            return response()->json(['message' => 'Could not find a rental search in that image. Try a clearer screenshot, or type what you want.'], 422);
        }

        return response()->json(['sentence' => $read['sentence']]);
    }

    /** The image as a JPEG data URI, at most 1024 px on the long side, rebuilt from its pixels (no metadata survives). */
    private function reencode(string $path): ?string
    {
        $bytes = @file_get_contents($path);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        if (max($w, $h) > 1024) {
            $scale = 1024 / max($w, $h);
            $image = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))) ?: $image;
        }

        ob_start();
        imagejpeg($image, null, 82);

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }

    public function results(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:' . config('ai_search.max_query_length', 300)],
            'intent' => ['required', 'array'],
            'used_ai' => ['boolean'],
            'fallback_reason' => ['nullable', 'string', 'max:30'],
            'usage_token' => ['nullable', 'string', 'max:40'],
        ]);

        // What the interpret call of this same search spent, so the log shows the whole search.
        if (filled($data['usage_token'] ?? null)) {
            app(AiUsage::class)->absorb(Cache::pull('ai_usage:' . $data['usage_token']));
        }

        // `used_ai` comes from the browser, so it can only ever ask for the AI ranking call, never skip a limit:
        // that call is charged to its own hourly bucket (only when ranking is on at all).
        $usedAi = (bool) ($data['used_ai'] ?? false);
        if ($usedAi && config('ai_search.rank_with_ai') && $this->limited($request, 'ai-rank')) {
            $usedAi = false;
        }

        $result = $this->search->results(
            $data['q'] ?? '',
            $data['intent'],
            $usedAi,
            $request->user()?->user_id,
            $data['fallback_reason'] ?? null,
        );

        $favoritedIds = $request->user()
            ? Favorite::where('tenant_id', $request->user()->user_id)->pluck('property_id')->all()
            : [];

        return response()->json([
            'html' => view('properties.partials.ai-results', $result + ['favoritedIds' => $favoritedIds, 'filtersUrl' => $this->filtersUrl($result['intent'])])->render(),
            'chips' => $result['chips'],
            'intent' => $result['intent'],
            'exact_count' => $result['exact_count'],
            'map' => $this->mapPins($result['rows'], $result['alternatives']),
            'feedback_token' => $result['feedback_token'] ?? null,
            'map_origin' => $this->mapOrigin($result['intent']),
            'map_avoid' => $this->mapAvoid($result['intent']),
            // "Refine" quick chips and what the follow-up needs to know about these results.
            'quick' => RefinePatch::quick($result['intent'], $result['rows']->count()),
            'context' => ['cheapest_fee' => $result['rows']->isEmpty() ? null : $result['rows']->map(fn ($r) => (float) $r['property']->min_rental_fee)->min()],
        ]);
    }

    /**
     * Pins for the results map: PropertyController::index()'s $mapProperties shape plus what the search
     * knows (resources/js/maps/ai-map-layer.js draws it): `rank` matches the card's number, `fit` is
     * exact / close / other (a different type, from the "You might also like" strip), `tag` is the one
     * thing to print under a close pin, `hits` / `misses` feed the preview card.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, array<string, mixed>>  $alternatives
     * @return list<array<string, mixed>>
     */
    private function mapPins($rows, $alternatives): array
    {
        $pin = function (array $row, ?int $rank, string $fit) {
            $p = $row['property'];

            return [
                'property_id' => $p->property_id,
                'title' => $p->title,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'rental_fee' => $p->min_rental_fee,
                'url' => route('properties.show', $p->property_id),
                'property_type' => $p->property_type,
                'image' => Images::resize($p->media->first()?->media_url, 480),
                'rank' => $rank,
                'fit' => $fit,
                'tag' => match ($fit) {
                    'close' => (string) ($row['misses'][0]['text'] ?? ''),
                    'other' => (string) $p->property_type,
                    default => '',
                },
                'rating' => $p->avg_rating !== null ? round((float) $p->avg_rating, 1) : null,
                'reviews' => (int) $p->review_count,
                'area' => trim(($p->barangay ? $p->barangay . ', ' : '') . $p->city_municipality),
                'distance_km' => $row['distance_km'] !== null ? round((float) $row['distance_km'], 1) : null,
                'hits' => array_slice(array_column($row['hits'], 'text'), 0, 3),
                'misses' => array_slice(array_column($row['misses'], 'text'), 0, 2),
            ];
        };

        return $rows->values()->map(fn ($r, $i) => $pin($r, $i + 1, empty($r['misses']) ? 'exact' : 'close'))
            ->concat($alternatives->values()->map(fn ($r) => $pin($r, null, 'other')))
            ->values()->all();
    }

    /**
     * The place the tenant searched near, for the circle and marker; null unless it was a landmark or their own
     * position. A named business shows all of its places, so there is no radius to draw (radius_km 0).
     */
    private function mapOrigin(array $intent): ?array
    {
        $loc = $intent['location'];
        if ($loc && $loc['kind'] === 'area') {
            return ['kind' => 'area', 'name' => 'Map area', 'bounds' => [$loc['south'], $loc['west'], $loc['north'], $loc['east']]];
        }
        if (! $loc && ($intent['anchors'] ?? []) !== []) {
            $first = $intent['anchors'][0];

            return ['kind' => 'landmark', 'name' => $first['name'], 'lat' => $first['lat'], 'lng' => $first['lng'], 'radius_km' => (float) $first['radius_km']];
        }
        if (! $loc || ! in_array($loc['kind'], ['landmark', 'point'], true)) {
            return null;
        }

        $isPoint = $loc['kind'] === 'point';

        return [
            'kind' => $loc['kind'],
            'name' => $isPoint ? 'You' : $loc['name'],
            'lat' => (float) $loc['lat'],
            'lng' => (float) $loc['lng'],
            'radius_km' => $isPoint && $intent['host'] !== null ? 0.0 : Travel::effectiveRadiusKm($intent),
        ];
    }

    /** One interpret per search counts against the hour's allowance; over it the keyword fallback answers. */
    private function limited(Request $request, string $bucket = 'ai-search'): bool
    {
        $key = $bucket . ':' . ($request->user()?->getAuthIdentifier() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, (int) config('ai_search.rate_per_hour', 20))) {
            return true;
        }
        RateLimiter::hit($key, 3600);

        return false;
    }

    /**
     * The circles of the places to stay away from, drawn dashed on the results map.
     *
     * @return list<array{name: string, lat: float, lng: float, radius_km: float}>
     */
    private function mapAvoid(array $intent): array
    {
        return collect($intent['avoid'] ?? [])->filter(fn ($a) => $a['lat'] !== null)
            ->map(fn ($a) => ['name' => $a['name'], 'lat' => (float) $a['lat'], 'lng' => (float) $a['lng'], 'radius_km' => (float) $a['radius_km']])
            ->values()->all();
    }

    /** The classic browse page with whatever the AI understood already filled in, for "refine with filters". */
    private function filtersUrl(array $intent): string
    {
        $loc = $intent['location'];

        return route('properties.index', array_filter([
            'location' => $loc && $loc['kind'] !== 'area' ? ($loc['kind'] === 'landmark' ? ($loc['city'] ?? $loc['name']) : $loc['name']) : null,
            'type' => $intent['property_type'],
            'price_min' => $intent['price_min'],
            'price_max' => $intent['price_max'],
            'living' => $intent['living'],
            'for' => $intent['for'],
            'furnishing' => $intent['furnishing'],
            'rules' => $intent['rules'],
        ], fn ($v) => $v !== null && $v !== []));
    }
}
