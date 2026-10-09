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
        ]);
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
