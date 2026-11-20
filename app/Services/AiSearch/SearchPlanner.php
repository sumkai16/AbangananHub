<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles searches the one-shot reading cannot: "between IT Park and USC Talamban", "near my school and my work".
 * The model gets a few tools of OURS and may call them for at most `ai_search.planner.max_rounds` rounds before it must
 * answer with submit_search. A tool is plain PHP: it returns numbers and place names, never listing titles or text
 * the model could be steered by, and nothing it returns or submits is trusted: the final answer goes through
 * IntentValidator like any other intent (RULES.md, AI search).
 *
 * The "How I searched" note is built here from the tool log, in our own words, never from anything the model wrote.
 * Any failure returns null and the search simply keeps what the one-shot reading found.
 *
 * Tools: find_place(name), midpoint(places[]), count_matches(intent), submit_search(intent, anchors).
 */
class SearchPlanner
{
    public function __construct(
        private OpenRouterDriver $driver,
        private IntentValidator $validator,
        private CandidateFinder $finder,
        private GeoLookup $geo,
        private NearestArea $areas,
    ) {}

    /**
     * @param  array<string, mixed>  $intent  what the one-shot reading found (validated)
     * @return null|array{intent: array<string, mixed>, steps: list<string>}
     */
    public function run(string $query, array $intent, string $plan): ?array
    {
        $max = (int) config('ai_search.planner.max_rounds', 3);
        $deadline = microtime(true) + (int) config('ai_search.planner.budget_seconds', 12);
        $steps = [];

        $messages = [
            ['role' => 'system', 'content' => Prompts::planner(Vocabulary::forPrompt())],
            ['role' => 'user', 'content' => json_encode(['plan' => $plan, 'first_reading' => RefinePatch::forPrompt($intent)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n" . Prompts::interpretUser($query)],
        ];

        for ($round = 1; $round <= $max; $round++) {
            if (microtime(true) > $deadline) {
                return null;
            }

            // On the last round the only tool on offer is the answer (Haiku's endpoint supports tool_choice "auto" only,
            // so a specific tool cannot be forced), and the model is told to finish.
            $last = $round === $max;
            $last && $messages[] = ['role' => 'user', 'content' => 'No more lookups. Call submit_search now with the best search you can make.'];
            $response = $this->driver->send([
                'model' => config('services.openrouter.model'),
                'messages' => $messages,
                'tools' => $last ? array_values(array_filter(self::tools(), fn ($t) => $t['function']['name'] === 'submit_search')) : self::tools(),
                'tool_choice' => 'auto',
                'max_tokens' => 2000,
                'provider' => ['require_parameters' => true],
            ], max(3, (int) ($deadline - microtime(true))));

            $message = (array) $response->json('choices.0.message', []);
            $calls = array_values(array_filter((array) ($message['tool_calls'] ?? []), 'is_array'));
            if ($calls === []) {
                return null; // it answered in words: keep the first reading
            }

            $messages[] = ['role' => 'assistant', 'content' => $message['content'] ?? null, 'tool_calls' => $message['tool_calls']];

            foreach ($calls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
                $args = is_array($args) ? $args : [];

                if ($name === 'submit_search') {
                    return $this->submit($args, $intent, $steps);
                }

                $messages[] = ['role' => 'tool', 'tool_call_id' => (string) ($call['id'] ?? ''), 'content' => json_encode($this->tool($name, $args, $steps), JSON_UNESCAPED_UNICODE)];
            }
        }

        return null;
    }

    /** @return array<string, mixed> what a tool answers; always plain data */
    private function tool(string $name, array $args, array &$steps): array
    {
        try {
            return match ($name) {
                'find_place' => $this->findPlace((string) ($args['name'] ?? ''), $steps),
                'midpoint' => $this->midpoint((array) ($args['places'] ?? []), $steps),
                'count_matches' => $this->countMatches((array) ($args['intent'] ?? []), $steps),
                default => ['error' => 'unknown tool'],
            };
        } catch (Throwable $e) {
            Log::warning('AI search planner tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return ['error' => 'tool failed'];
        }
    }

    /** @return array<string, mixed> */
    private function findPlace(string $name, array &$steps): array
    {
        $place = $this->resolve($name);
        if ($place === null) {
            $steps[] = 'Could not find "' . mb_substr(trim(strip_tags($name)), 0, 40) . '" on the map';

            return ['found' => false];
        }
        $steps[] = 'Found ' . $place['name'];

        return ['found' => true] + $place;
    }

    /** @return array<string, mixed> */
    private function midpoint(array $names, array &$steps): array
    {
        $points = array_values(array_filter(array_map(fn ($n) => is_string($n) ? $this->resolve($n) : null, array_slice($names, 0, 3))));
        if (count($points) < 2) {
            return ['found' => false];
        }

        $lat = round(array_sum(array_column($points, 'lat')) / count($points), 5);
        $lng = round(array_sum(array_column($points, 'lng')) / count($points), 5);
        $area = $this->areas->nearest($lat, $lng);
        $km = round(Landmarks::distanceKm($points[0]['lat'], $points[0]['lng'], $points[1]['lat'], $points[1]['lng']), 1);
        $steps[] = 'Looked between them' . ($area ? ': ' . $area['name'] : '');

        return ['found' => true, 'lat' => $lat, 'lng' => $lng, 'area' => $area['name'] ?? null, 'km_between_first_two' => $km];
    }

    /**
     * How many places fit an intent, as numbers and area names only: no titles, no descriptions, nothing a listing
     * owner wrote, so nothing in the answer can steer the model.
     *
     * @return array<string, mixed>
     */
    private function countMatches(array $raw, array &$steps): array
    {
        $intent = $this->validator->validate($this->withResolvedAnchors($raw));
        if (IntentValidator::isEmpty($intent)) {
            return ['total' => 0];
        }

        $found = $this->finder->find($intent);
        $rows = $found['rows'];
        $areas = $rows->map(fn ($r) => $r['property']->barangay ?: $r['property']->city_municipality)->filter()->countBy()->sortDesc()->take(3)->keys()->all();
        $steps[] = $found['exact_count'] . ' ' . ($found['exact_count'] === 1 ? 'place fits' : 'places fit') . ' exactly';

        return [
            'exact' => $found['exact_count'],
            'total' => $rows->count(),
            'cheapest_fee' => $rows->isEmpty() ? null : (int) round($rows->map(fn ($r) => (float) $r['property']->min_rental_fee)->min()),
            'top_areas' => $areas,
        ];
    }

    /** @return null|array{name: string, lat: float, lng: float, city: ?string} */
    private function resolve(string $name): ?array
    {
        $name = trim(strip_tags($name));
        if ($name === '') {
            return null;
        }
        if ($landmark = Landmarks::find($name)) {
            return ['name' => $landmark['name'], 'lat' => $landmark['lat'], 'lng' => $landmark['lng'], 'city' => $landmark['city']];
        }

        return AiFeatures::on('geo_lookup') ? $this->geo->find($name) : null;
    }

    /** An intent from the model may name anchors without coordinates: look them up here, in PHP. */
    private function withResolvedAnchors(array $raw): array
    {
        $anchors = [];
        foreach (array_slice((array) ($raw['anchors'] ?? []), 0, 3) as $anchor) {
            $name = is_array($anchor) ? (string) ($anchor['name'] ?? $anchor['place'] ?? '') : (is_string($anchor) ? $anchor : '');
            if ($place = $this->resolve($name)) {
                $anchors[] = ['name' => $place['name'], 'lat' => $place['lat'], 'lng' => $place['lng']] + (is_array($anchor) ? array_intersect_key($anchor, ['radius_km' => 1, 'max_minutes' => 1]) : []);
            }
        }
        $raw['anchors'] = $anchors;

        return $raw;
    }

    /** @return null|array{intent: array<string, mixed>, steps: list<string>} */
    private function submit(array $args, array $first, array $steps): ?array
    {
        $raw = (array) ($args['intent'] ?? []);
        $raw['anchors'] = $args['anchors'] ?? $raw['anchors'] ?? [];
        $intent = $this->validator->validate($this->withResolvedAnchors($raw));

        // A plan that found no place to measure from is no plan: keep the first reading.
        if ($intent['anchors'] === [] && $intent['location'] === null) {
            return null;
        }
        $intent['plan_steps'] = array_slice(array_values(array_unique($steps)), 0, 5);

        return ['intent' => $intent, 'steps' => $intent['plan_steps']];
    }

    /** @return list<array<string, mixed>> */
    public static function tools(): array
    {
        $fn = fn (string $name, string $description, array $properties, array $required) => [
            'type' => 'function',
            'function' => ['name' => $name, 'description' => $description, 'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => $required]],
        ];
        $name = ['type' => 'string', 'description' => 'A place as the tenant wrote it, or a landmark key'];

        return [
            $fn('find_place', 'Find a place in Cebu by name. Returns its coordinates, or found=false.', ['name' => $name], ['name']),
            $fn('midpoint', 'The point halfway between 2 or 3 places, with the area it is in.', ['places' => ['type' => 'array', 'items' => $name]], ['places']),
            $fn('count_matches', 'How many available places fit a search: counts, the cheapest rent and the top areas. Use it to compare options or check that a search is not empty.', ['intent' => ['type' => 'object', 'description' => 'The same fields as the search answer, with anchors as [{name}]']], ['intent']),
            $fn('submit_search', 'Give the final search. Call this when done. anchors are the places that must ALL be close (max 3).', [
                'intent' => ['type' => 'object', 'description' => 'The search with the same fields as the first reading'],
                'anchors' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'radius_km' => ['type' => 'number'], 'max_minutes' => ['type' => 'object', 'properties' => ['minutes' => ['type' => 'integer'], 'mode' => ['type' => 'string', 'enum' => Schemas::TRAVEL_MODES]]]], 'required' => ['name']]],
            ], ['intent', 'anchors']),
        ];
    }
}
