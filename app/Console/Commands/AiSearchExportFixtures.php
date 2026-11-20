<?php

namespace App\Console\Commands;

use App\Models\AiSearchReview;
use App\Services\AiSearch\IntentValidator;
use Illuminate\Console\Command;

/**
 * Turns the searches an admin reviewed (Search insights) into test sentences for `ai-search:eval`, appended to
 * tests/fixtures/ai-search-cases.json in the same format as the rest. Sentences already in the file are skipped, and
 * a reviewed row is marked exported so it never goes out twice. This is the "training" loop: the model is not retrained,
 * the prompt and the data are, and the eval says whether the change helped.
 */
class AiSearchExportFixtures extends Command
{
    protected $signature = 'ai-search:export-fixtures';

    protected $description = 'Append admin-reviewed real searches to the eval fixtures';

    /** What a fixture checks (see AiSearchEval::FIELDS). Location is written as the landmark key or the city. */
    private const FIELDS = ['property_type', 'price_min', 'price_max', 'amenities', 'rules', 'for', 'furnishing', 'sort', 'bedrooms_min', 'deposit_max', 'occupants'];

    public function handle(IntentValidator $validator): int
    {
        $reviews = AiSearchReview::whereNull('exported_at')->orderBy('id')->get();
        if ($reviews->isEmpty()) {
            $this->info('Nothing to export: no reviewed search is waiting.');

            return self::SUCCESS;
        }

        $path = base_path('tests/fixtures/ai-search-cases.json');
        $raw = file_get_contents($path);
        $crlf = str_contains($raw, "\r\n");
        $cases = json_decode($raw, true);
        $have = array_map(fn ($q) => mb_strtolower(trim($q)), array_column($cases, 'q'));
        $added = 0;

        foreach ($reviews as $review) {
            $key = mb_strtolower(trim($review->query));
            if (! in_array($key, $have, true)) {
                $cases[] = ['q' => $review->query, 'expect' => (object) $this->expect($validator->validate($review->intent))];
                $have[] = $key;
                $added++;
            }
            $review->update(['exported_at' => now()]);
        }

        $json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = preg_replace_callback('/^( +)/m', fn ($m) => str_repeat(' ', intdiv(strlen($m[1]), 4)), $json); // 1-space indent, like the file
        file_put_contents($path, $crlf ? str_replace("\n", "\r\n", $json) : $json);

        $this->info("Added $added sentence(s) to the fixtures (" . ($reviews->count() - $added) . ' already there). Run `php artisan ai-search:eval` to score them.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function expect(array $intent): array
    {
        $expect = [];
        if ($loc = $intent['location']) {
            $expect['location'] = $loc['value']; // a landmark key, or the city / area name: what the eval compares
        }
        foreach (self::FIELDS as $field) {
            $value = $intent[$field] ?? null;
            if ($value !== null && $value !== []) {
                $expect[$field] = $value;
            }
        }

        return $expect;
    }
}
