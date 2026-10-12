<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\AiSearch\CandidateFinder;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\Landmarks;
use Illuminate\Console\Command;

/**
 * Checks WHICH PROPERTIES come back, not whether the AI read the sentence.
 * Feeds the expected intent of each case in tests/fixtures/ai-search-cases.json
 * straight into CandidateFinder (no AI call, no quota) and reports, per sentence,
 * how many results are exact vs closest and what was relaxed. It also re-checks
 * every "exact" row against the raw columns, independently of MatchExplainer.
 */
class AiSearchResults extends Command
{
    protected $signature = 'ai-search:results {--only= : run only cases whose sentence contains this text} {--top=3 : rows to print per case}';

    protected $description = 'Show which properties each sample search returns and flag exact matches that break the filters';

    public function handle(IntentValidator $validator, CandidateFinder $finder): int
    {
        $cases = json_decode(file_get_contents(base_path('tests/fixtures/ai-search-cases.json')), true);
        if ($only = $this->option('only')) {
            $cases = array_values(array_filter($cases, fn ($c) => stripos($c['q'], $only) !== false));
        }

        $this->info(Property::browsable()->count() . ' browsable properties in the database.');
        $empty = $violations = 0;

        foreach ($cases as $case) {
            $intent = $validator->validate($case['expect']);
            if (IntentValidator::isEmpty($intent)) {
                continue;
            }

            $found = $finder->find($intent);
            $this->newLine();
            $this->line('<options=bold>' . $case['q'] . '</>');
            $this->line(sprintf(
                '  %d result(s), %d exact%s',
                $found['rows']->count(),
                $found['exact_count'],
                $found['relaxed'] ? ', relaxed: ' . implode(' > ', $found['relaxed']) : '',
            ));

            foreach ($found['alternatives'] as $alt) {
                $a = $alt['property'];
                $this->line(sprintf('  → might also like: %s | %s | %s | from ₱%s', $a->title, $a->property_type, $a->city_municipality, number_format((float) $a->min_rental_fee)));
            }

            if ($found['rows']->isEmpty()) {
                if ($found['alternatives']->isEmpty()) {
                    $empty++;
                    $this->warn('  ✗ nothing to show');
                }

                continue;
            }

            foreach ($found['rows'] as $i => $row) {
                /** @var Property $p */
                $p = $row['property'];
                if ($i < (int) $this->option('top')) {
                    $this->line(sprintf(
                        '  %d. %s | %s | %s | from ₱%s | score %d%s',
                        $i + 1, $p->title, $p->property_type, $p->city_municipality,
                        number_format((float) $p->min_rental_fee), $row['score'],
                        $row['misses'] ? ' | misses: ' . implode(', ', array_column($row['misses'], 'text')) : ' | exact',
                    ));
                }
                if (! $row['misses'] && ($problem = $this->breaks($p, $intent))) {
                    $violations++;
                    $this->error("  ✗ exact row \"{$p->title}\" breaks the filters: $problem");
                }
            }
        }

        $this->newLine();
        $this->line("Cases with no results: $empty. Exact rows that break a filter: $violations.");

        return $violations ? self::FAILURE : self::SUCCESS;
    }

    /** Independent re-check of an "exact" row against raw columns. */
    private function breaks(Property $p, array $intent): ?string
    {
        $fees = $p->units->where('availability_status', 'Available')->where('verification_status', 'Approved')
            ->pluck('rental_fee')->map(fn ($f) => (float) $f);

        if ($intent['property_type'] !== null && $p->property_type !== $intent['property_type']) {
            return "type {$p->property_type}, wanted {$intent['property_type']}";
        }
        if ($intent['price_max'] !== null && ($fees->isEmpty() || $fees->min() > $intent['price_max'])) {
            return 'cheapest unit ₱' . number_format((float) $fees->min()) . ' > budget ₱' . number_format($intent['price_max']);
        }
        if ($intent['price_min'] !== null && ($fees->isEmpty() || $fees->max() < $intent['price_min'])) {
            return 'dearest unit below ₱' . number_format($intent['price_min']);
        }
        $loc = $intent['location'];
        if ($loc && $loc['kind'] === 'city' && $p->city_municipality !== $loc['value']) {
            return "city {$p->city_municipality}, wanted {$loc['value']}";
        }
        if ($loc && $loc['kind'] === 'landmark'
            && Landmarks::distanceKm((float) $p->latitude, (float) $p->longitude, $loc['lat'], $loc['lng']) > $intent['radius_km']) {
            return 'outside the radius';
        }

        return null;
    }
}
