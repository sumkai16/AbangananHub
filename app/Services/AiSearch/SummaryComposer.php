<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Collection;

/**
 * Plain-PHP summary for when the AI can't write one (or isn't configured).
 * Built only from MatchExplainer output, so it can't say anything untrue.
 * English, Tagalog and Bisaya wording for the three cases: all exact, some
 * exact, none exact (plus nothing found).
 */
class SummaryComposer
{
    private const WORDS = [
        'en' => [
            'exact_one' => ':best fits everything you asked for: :hits, :price/month.',
            'exact_many' => ':n places fit everything. Top pick is :best: :hits, :price/month.',
            'mixed' => ':best fits everything you asked for: :hits, :price/month. :close is close, but :misses.',
            'closest' => 'No exact match. Closest options: :list.',
            'none' => "I couldn't find a match yet. Try removing a filter or searching a nearby area.",
            'suggest' => 'I also have a suggestion: :alt, a :type in :area from :price.',
            'suggest_only' => "I couldn't find any :wanted for that, but I have a suggestion: :alt, a :type in :area from :price.",
            'note' => 'No other :wanted fits. These are other types in your budget and area.',
            'and' => 'and',
        ],
        'tl' => [
            'exact_one' => 'Swak ang :best sa lahat ng hiniling mo: :hits, :price/month.',
            'exact_many' => ':n ang swak sa lahat. Top pick ang :best: :hits, :price/month.',
            'mixed' => 'Swak ang :best sa lahat ng hiniling mo: :hits, :price/month. Malapit naman ang :close, pero :misses.',
            'closest' => 'Walang eksaktong tugma. Pinakamalapit: :list.',
            'none' => 'Wala pang tugma. Subukang alisin ang isang filter o maghanap sa kalapit na lugar.',
            'suggest' => 'May suggestion din ako: :alt, isang :type sa :area, :price/month.',
            'suggest_only' => 'Wala akong makitang :wanted para diyan, pero may suggestion ako: :alt, isang :type sa :area, :price/month.',
            'note' => 'Wala nang ibang :wanted na pasok. Ito ang ibang uri sa budget at lugar mo.',
            'and' => 'at',
        ],
        'ceb' => [
            'exact_one' => 'Haum ang :best sa tanan nimong gihangyo: :hits, :price/month.',
            'exact_many' => ':n ang haum sa tanan. Top pick ang :best: :hits, :price/month.',
            'mixed' => 'Haum ang :best sa tanan nimong gihangyo: :hits, :price/month. Duol ang :close, apan :misses.',
            'closest' => 'Walay eksaktong tugma. Pinakaduol: :list.',
            'none' => 'Wala pay tugma. Sulayi ang pagtangtang sa usa ka filter o pangita sa duol nga lugar.',
            'suggest' => 'Naa pud koy suggestion: :alt, usa ka :type sa :area, :price/month.',
            'suggest_only' => 'Wala koy nakita nga :wanted para ana, apan naa koy suggestion: :alt, usa ka :type sa :area, :price/month.',
            'note' => 'Wala nay lain nga :wanted nga mohaum. Kini ang ubang klase sa imong budget ug lugar.',
            'and' => 'ug',
        ],
    ];

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  CandidateFinder rows
     * @param  Collection<int, array<string, mixed>>  $alternatives  other-type rows that still fit (CandidateFinder)
     * @param  ?string  $wanted  the property type the tenant asked for, if any
     * @return array{summary: string, summary_en: string, alt_note: string}
     */
    public function compose(Collection $rows, int $exactCount, string $language, ?Collection $alternatives = null, ?string $wanted = null): array
    {
        $lang = in_array($language, ['tl', 'ceb'], true) ? $language : 'en';
        $alternatives ??= collect();

        return [
            'summary' => $this->build($rows, $exactCount, $lang, $alternatives, $wanted),
            'summary_en' => $this->build($rows, $exactCount, 'en', $alternatives, $wanted),
            'alt_note' => $alternatives->isEmpty() ? '' : strtr(self::WORDS[$lang]['note'], [':wanted' => $this->label($wanted)]),
        ];
    }

    private function build(Collection $rows, int $exactCount, string $lang, Collection $alternatives, ?string $wanted): string
    {
        $w = self::WORDS[$lang];
        $suggestion = $alternatives->isNotEmpty() ? $this->suggest($w, $alternatives->first(), $rows->isEmpty() ? 'suggest_only' : 'suggest', $wanted) : '';

        if ($rows->isEmpty()) {
            return $suggestion !== '' ? $suggestion : $w['none'];
        }

        return trim($this->describe($rows, $exactCount, $w, $wanted) . ' ' . $suggestion);
    }

    /** One sentence pointing at the best alternative of another type. Only real columns. */
    private function suggest(array $w, array $row, string $key, ?string $wanted): string
    {
        $p = $row['property'];

        return strtr($w[$key], [
            ':alt' => $p->title,
            ':type' => $p->property_type,
            ':area' => $p->barangay ?: $p->city_municipality,
            ':price' => '₱' . number_format((float) $p->min_rental_fee),
            ':wanted' => $this->label($wanted),
        ]);
    }

    private function label(?string $type): string
    {
        return $type !== null ? mb_strtolower($type) : 'match';
    }

    private function describe(Collection $rows, int $exactCount, array $w, ?string $wanted): string
    {
        $close = $rows->count() - $exactCount;
        $join = fn (array $items) => $this->join(array_column($items, 'text'), $w['and']);
        // The price is stated separately and the type is what they asked for, so lead with the other facts.
        $facts = function (array $hits) use ($join) {
            $rest = array_values(array_filter($hits, fn ($h) => ! in_array($h['facet'], ['type', 'price'], true)));

            return $join($rest ?: $hits);
        };

        if ($exactCount > 0) {
            // Exact rows sort first, so the first row is the top pick.
            $top = $rows->first();
            $vars = [
                ':n' => $exactCount,
                ':best' => $top['property']->title,
                ':hits' => $facts($top['hits']),
                ':price' => '₱' . number_format((float) $top['property']->min_rental_fee),
            ];

            if ($close === 0) {
                return strtr($w[$exactCount === 1 ? 'exact_one' : 'exact_many'], $vars);
            }

            $near = $rows->first(fn ($r) => ! empty($r['misses']));

            return strtr($w['mixed'], $vars + [':close' => $near['property']->title, ':misses' => $join($near['misses'])]);
        }

        // Nothing fits fully: list the closest few (nearest first when a place was given) with what each misses and its price.
        $nearest = $rows->sortBy(fn ($r) => $r['distance_km'] ?? INF)->take(3);
        $items = $nearest->map(function ($r) use ($join, $facts) {
            $detail = $r['misses'] ? $join($r['misses']) : $facts($r['hits']);

            return $r['property']->title . ' (' . $detail . ', ₱' . number_format((float) $r['property']->min_rental_fee) . '/month)';
        })->values()->all();

        return strtr($w['closest'], [
            ':list' => $this->join($items, $w['and'], 3),
        ]);
    }

    /** @param list<string> $items */
    private function join(array $items, string $and, int $limit = 3): string
    {
        $items = array_slice(array_values($items), 0, $limit);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' ' . $and . ' ' . $last;
    }
}
