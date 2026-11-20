<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSearchLog;
use App\Models\AiSearchReview;
use App\Services\AiSearch\IntentValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * What tenants actually search for, what it costs, and where the platform came up empty (Phase 8 of
 * plans/ai-search-maps-and-haiku.md). Admin only: search text can hold personal details, which is also why
 * `ai_search_logs` is pruned after 30 days. Read-only except for "Save as test case", which records an admin's
 * corrected reading of one search (ai_search_reviews) for `ai-search:export-fixtures`.
 */
class SearchInsightsController extends Controller
{
    private const DAYS = [7, 14, 30];

    private const LOG_LIMIT = 3000;

    public function index(Request $request)
    {
        $days = in_array((int) $request->query('days'), self::DAYS, true) ? (int) $request->query('days') : 30;
        $logs = AiSearchLog::where('created_at', '>=', now()->subDays($days))->latest('id')->limit(self::LOG_LIMIT)->get();
        $total = $logs->count();

        $latencies = $logs->pluck('latency_ms')->sort()->values();
        $zero = $logs->where('result_count', 0);

        $stats = [
            'total' => $total,
            'ai_share' => $total ? round(100 * $logs->where('used_ai', true)->count() / $total) : 0,
            'zero_rate' => $total ? round(100 * $zero->count() / $total) : 0,
            'avg_ms' => $total ? (int) round($latencies->avg()) : 0,
            'p95_ms' => $total ? (int) $latencies->get((int) floor(($total - 1) * 0.95)) : 0,
            'cost' => round((float) $logs->sum('cost_usd'), 4),
            'cost_per_search' => $total ? round((float) $logs->sum('cost_usd') / $total, 5) : 0,
            'up' => $logs->where('feedback', 1)->count(),
            'down' => $logs->where('feedback', -1)->count(),
        ];

        return view('admin.search-insights.index', [
            'days' => $days,
            'dayOptions' => self::DAYS,
            'stats' => $stats,
            'perDay' => $logs->groupBy(fn ($l) => $l->created_at->format('Y-m-d'))->map(fn ($g) => ['n' => $g->count(), 'cost' => round((float) $g->sum('cost_usd'), 4)])->sortKeysDesc(),
            'fallbacks' => $logs->whereNotNull('fallback_reason')->countBy('fallback_reason')->sortDesc(),
            'zeroQueries' => $zero->groupBy(fn ($l) => mb_strtolower(trim($l->query)))->map->count()->sortDesc()->take(15),
            'unmapped' => $this->intentValues($logs, fn ($i) => (array) ($i['unmapped'] ?? []))->take(15),
            'unknownPlaces' => $this->intentValues($logs, fn ($i) => ($i['location']['kind'] ?? null) === 'text' ? [(string) ($i['location']['value'] ?? '')] : [])->take(15),
            'relaxed' => $this->flat($logs->pluck('relaxed'))->take(8),
            'gaps' => $this->supplyGaps($logs)->take(15),
            'toReview' => $logs->filter(fn ($l) => $l->exact_count === 0)->take(10),
            'reviewed' => AiSearchReview::count(),
        ]);
    }

    /** Saves an admin's corrected reading of a logged search as a test case. */
    public function review(Request $request, IntentValidator $validator)
    {
        $data = $request->validate([
            'log_id' => ['required', 'integer'],
            'intent' => ['required', 'string', 'max:4000'],
        ]);

        $log = AiSearchLog::findOrFail($data['log_id']);
        $intent = json_decode($data['intent'], true);
        if (! is_array($intent)) {
            return back()->withErrors(['intent' => 'That is not valid JSON.'])->withInput();
        }

        try {
            $validator->validate($intent);
        } catch (Throwable) {
            return back()->withErrors(['intent' => 'That is not a search the validator can read.'])->withInput();
        }

        AiSearchReview::create(['ai_search_log_id' => $log->id, 'query' => $log->query, 'intent' => $intent, 'reviewed_by' => $request->user()->user_id]);

        return back()->with('success', 'Saved. Run `php artisan ai-search:export-fixtures` to add it to the test sentences.');
    }

    /**
     * @param  Collection<int, AiSearchLog>  $logs
     * @return Collection<string, int>
     */
    private function intentValues(Collection $logs, callable $pick): Collection
    {
        return $logs->flatMap(fn ($l) => $pick((array) $l->intent))
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))->filter()->countBy()->sortDesc();
    }

    /** @return Collection<string, int> */
    private function flat(Collection $lists): Collection
    {
        return $lists->flatMap(fn ($list) => (array) $list)->filter()->countBy()->sortDesc();
    }

    /**
     * "Dormitory near Cebu IT Park": searches with a type and a place that found nothing exactly, grouped.
     *
     * @param  Collection<int, AiSearchLog>  $logs
     * @return Collection<string, int>
     */
    private function supplyGaps(Collection $logs): Collection
    {
        return $logs->filter(fn ($l) => $l->exact_count === 0 && ($l->intent['property_type'] ?? null) && ($l->intent['location']['name'] ?? null))
            ->map(fn ($l) => $l->intent['property_type'] . ' ' . (($l->intent['location']['kind'] ?? '') === 'landmark' ? 'near ' : 'in ') . $l->intent['location']['name'])
            ->countBy()->sortDesc();
    }
}
