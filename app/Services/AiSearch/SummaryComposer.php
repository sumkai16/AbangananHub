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
            'exact' => ':n exact :match found. :best is the cheapest at :price.',
            'mixed' => ':n exact :match, plus :m close :ones. Check what each one is missing.',
            'closest' => 'No exact match. The closest is :title: it fits :hits, but :misses.',
            'none' => 'Nothing matches yet. Try removing a filter or searching a nearby area.',
            'match' => ['match', 'matches'], 'one' => ['one', 'ones'], 'and' => 'and',
        ],
        'tl' => [
            'exact' => ':n eksaktong tugma. Pinakamura ang :best sa :price.',
            'mixed' => ':n eksaktong tugma, at :m malapit na tugma. Tingnan kung ano ang kulang sa bawat isa.',
            'closest' => 'Walang eksaktong tugma. Pinakamalapit ang :title: swak ang :hits, pero :misses.',
            'none' => 'Wala pang tugma. Subukang alisin ang isang filter o maghanap sa kalapit na lugar.',
            'match' => ['', ''], 'one' => ['', ''], 'and' => 'at',
        ],
        'ceb' => [
            'exact' => ':n eksaktong tugma. Pinakabarato ang :best sa :price.',
            'mixed' => ':n eksaktong tugma, ug :m duol nga tugma. Tan-awa kung unsa ang kulang sa matag usa.',
            'closest' => 'Walay eksaktong tugma. Pinakaduol ang :title: dapat ang :hits, apan :misses.',
            'none' => 'Wala pay tugma. Sulayi ang pagtangtang sa usa ka filter o pangita sa duol nga lugar.',
            'match' => ['', ''], 'one' => ['', ''], 'and' => 'ug',
        ],
    ];

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  CandidateFinder rows
     * @return array{summary: string, summary_en: string}
     */
    public function compose(Collection $rows, int $exactCount, string $language): array
    {
        $lang = in_array($language, ['tl', 'ceb'], true) ? $language : 'en';

        return [
            'summary' => $this->build($rows, $exactCount, $lang),
            'summary_en' => $this->build($rows, $exactCount, 'en'),
        ];
    }

    private function build(Collection $rows, int $exactCount, string $lang): string
    {
        $w = self::WORDS[$lang];
        if ($rows->isEmpty()) {
            return $w['none'];
        }

        $close = $rows->count() - $exactCount;
        if ($exactCount > 0 && $close === 0) {
            $cheapest = $rows->filter(fn ($r) => empty($r['misses']))->sortBy(fn ($r) => (float) $r['property']->min_rental_fee)->first();

            return strtr($w['exact'], [
                ':n' => $exactCount, ':match' => $w['match'][$exactCount === 1 ? 0 : 1],
                ':best' => $cheapest['property']->title, ':price' => '₱' . number_format((float) $cheapest['property']->min_rental_fee),
            ]);
        }
        if ($exactCount > 0) {
            return strtr($w['mixed'], [
                ':n' => $exactCount, ':match' => $w['match'][$exactCount === 1 ? 0 : 1],
                ':m' => $close, ':one' => $w['one'][$close === 1 ? 0 : 1], ':ones' => $w['one'][$close === 1 ? 0 : 1],
            ]);
        }

        $best = $rows->first();
        $join = fn (array $items) => $this->join(array_column($items, 'text'), $w['and']);

        return strtr($w['closest'], [
            ':title' => $best['property']->title,
            ':hits' => $best['hits'] ? $join($best['hits']) : '-',
            ':misses' => $join($best['misses']),
        ]);
    }

    /** @param list<string> $items */
    private function join(array $items, string $and): string
    {
        $items = array_slice(array_values($items), 0, 3);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' ' . $and . ' ' . $last;
    }
}
