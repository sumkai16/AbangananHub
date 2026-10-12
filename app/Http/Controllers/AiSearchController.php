<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Services\AiSearch\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The two calls behind the AI search box. interpret() reads the sentence and
 * returns the filter chips; results() takes the (possibly chip-edited) filters
 * and returns the rendered summary + cards. Both always answer 200: when the AI
 * is down, limited or out of quota the page gets keyword results and a notice.
 */
class AiSearchController extends Controller
{
    public function __construct(private AiSearchService $search)
    {
    }

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

    public function results(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:' . config('ai_search.max_query_length', 300)],
            'intent' => ['required', 'array'],
            'used_ai' => ['boolean'],
            'fallback_reason' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $this->search->results(
            $data['q'] ?? '',
            $data['intent'],
            (bool) ($data['used_ai'] ?? false),
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
            'map_origin' => $this->mapOrigin($result['intent']),
        ]);
    }

    /**
     * Pins for the results map: PropertyController::index()'s $mapProperties shape plus what the search
     * knows (resources/js/maps/ai-map-layer.js draws it): `rank` matches the card's number, `fit` is
     * exact / close / other (a different type, from the "You might also like" strip), `tag` is the one
     * thing to print under a close pin, `hits` / `misses` feed the preview card.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $alternatives
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
                'image' => \App\Support\Images::resize($p->media->first()?->media_url, 480),
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

    /** The place the tenant searched near, for the circle and marker; null unless it was a landmark. */
    private function mapOrigin(array $intent): ?array
    {
        $loc = $intent['location'];
        if (! $loc || $loc['kind'] !== 'landmark') {
            return null;
        }

        return ['name' => $loc['name'], 'lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'radius_km' => (float) $intent['radius_km']];
    }

    /** One interpret per search counts against the hour's allowance; over it the keyword fallback answers. */
    private function limited(Request $request): bool
    {
        $key = 'ai-search:' . ($request->user()?->getAuthIdentifier() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, (int) config('ai_search.rate_per_hour', 20))) {
            return true;
        }
        RateLimiter::hit($key, 3600);

        return false;
    }

    /** The classic browse page with whatever the AI understood already filled in, for "refine with filters". */
    private function filtersUrl(array $intent): string
    {
        $loc = $intent['location'];

        return route('properties.index', array_filter([
            'location' => $loc ? ($loc['kind'] === 'landmark' ? ($loc['city'] ?? $loc['name']) : $loc['name']) : null,
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
