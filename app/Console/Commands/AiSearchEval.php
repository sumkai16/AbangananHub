<?php

namespace App\Console\Commands;

use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\LanguageDetector;
use App\Services\AiSearch\NullDriver;
use App\Services\AiSearch\Vocabulary;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs the real AI against tests/fixtures/ai-search-cases.json (30 sentences in
 * English, Tagalog and Bisaya with the intent we expect) and reports a pass rate
 * per field. This is how prompts get tuned and how Gemini and Anthropic are compared.
 * Spends real API quota: ~1 call per case.
 */
class AiSearchEval extends Command
{
    protected $signature = 'ai-search:eval {--delay=7 : seconds between calls (free tier is ~10 requests/minute)} {--only= : run only cases whose sentence contains this text} {--file= : another fixture file (default tests/fixtures/ai-search-cases.json). A case with "previous" is a follow-up} {--verbose-fail : print what the AI returned for each failure}';

    protected $description = 'Score the configured AI driver on the sample search sentences';

    private const FIELDS = ['location', 'property_type', 'price_min', 'price_max', 'amenities', 'rules', 'for', 'furnishing', 'language', 'sort', 'bedrooms_min', 'deposit_max', 'occupants', 'max_minutes', 'avoid', 'radius_km', 'mode'];

    public function handle(AiSearchDriver $driver, IntentValidator $validator): int
    {
        if ($driver instanceof NullDriver) {
            $this->error('No AI driver is configured. Set AI_SEARCH_DRIVER and the matching key in .env.');

            return self::FAILURE;
        }

        $cases = json_decode(file_get_contents(base_path($this->option('file') ?: 'tests/fixtures/ai-search-cases.json')), true);
        if ($only = $this->option('only')) {
            $cases = array_values(array_filter($cases, fn ($c) => stripos($c['q'], $only) !== false));
        }

        $score = array_fill_keys(self::FIELDS, [0, 0]); // [passed, checked]
        $errors = 0;

        foreach ($cases as $i => $case) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($cases), $case['q']));
            try {
                if (isset($case['previous'])) {
                    // A follow-up: the search so far plus a message. Runs the whole service path, safeguards included.
                    $out = app(AiSearchService::class)->refine($case['q'], $case['previous']);
                    $out['used_ai'] || throw new \RuntimeException('answered by the keyword reader, not the AI: ' . ($out['fallback_reason'] ?? 'unknown'));
                    $intent = $out['intent'] + ['mode' => $out['mode']];
                } else {
                    $intent = $validator->validate($driver->interpret($case['q'], Vocabulary::forPrompt()));
                }
                // Same override the app applies (AiSearchService::interpret), so the score matches what users get.
                $intent['language'] = LanguageDetector::detect($case['q']) ?? $intent['language'];
            } catch (Throwable $e) {
                $errors++;
                $this->warn('   ✗ driver error: ' . $e->getMessage());
                $this->pause($i, count($cases));

                continue;
            }

            $failed = [];
            foreach ($case['expect'] as $field => $want) {
                if ($field === 'unmapped') {
                    continue;
                }
                $score[$field][1]++;
                if ($this->matches($field, $want, $intent)) {
                    $score[$field][0]++;
                } else {
                    $failed[] = $field . ' wanted ' . json_encode($want, JSON_UNESCAPED_UNICODE);
                }
            }
            // A sentence with nothing to filter on must not invent filters.
            if ($case['expect'] === [] && ! IntentValidator::isEmpty($intent)) {
                $failed[] = 'expected no filters';
            }

            if ($failed) {
                $this->warn('   ✗ ' . implode('; ', $failed));
                if ($this->option('verbose-fail')) {
                    $this->line('     got ' . json_encode($intent, JSON_UNESCAPED_UNICODE));
                }
            }
            $this->pause($i, count($cases));
        }

        $this->newLine();
        $rows = [];
        foreach ($score as $field => [$ok, $n]) {
            if ($n > 0) {
                $rows[] = [$field, "$ok / $n", round(100 * $ok / $n) . '%'];
            }
        }
        $this->table(['Field', 'Passed', 'Rate'], $rows);
        $errors && $this->warn("$errors case(s) failed with a driver error (quota, timeout or bad JSON).");

        return self::SUCCESS;
    }

    private function matches(string $field, mixed $want, array $intent): bool
    {
        return match ($field) {
            'location' => ($intent['location']['value'] ?? null) === $want,
            'amenities', 'rules' => array_diff((array) $want, $intent[$field]) === [],
            'avoid' => array_column($intent['avoid'] ?? [], 'name') === $want,
            default => ($intent[$field] ?? null) === $want,
        };
    }

    private function pause(int $i, int $total): void
    {
        if ($i < $total - 1) {
            sleep((int) $this->option('delay'));
        }
    }
}
