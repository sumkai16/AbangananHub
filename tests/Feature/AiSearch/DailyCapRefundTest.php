<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiSearchUnavailable;
use Illuminate\Support\Facades\Cache;

class DailyCapRefundTest extends AiSearchTestCase
{
    private function useDriver(bool $fail): void
    {
        $driver = new class($fail) implements AiSearchDriver
        {
            public function __construct(private bool $fail) {}

            public function interpret(string $query, array $vocab): array
            {
                $this->fail && throw new AiSearchUnavailable('down');

                return ['language' => 'en', 'property_type' => 'Apartment'];
            }

            public function rank(array $intent, array $candidates, string $language, bool $exact): array
            {
                return ['order' => [], 'summary' => '', 'summary_en' => ''];
            }
        };
        config(['ai_search.driver' => 'gemini']);
        $this->app->instance(AiSearchDriver::class, $driver);
    }

    private function used(): int
    {
        return (int) Cache::get('ai_search:daily:' . now('Asia/Manila')->format('Ymd'), 0);
    }

    public function test_a_failed_provider_call_gives_the_search_back_to_the_day(): void
    {
        config(['ai_search.daily_cap' => 2]);
        $this->useDriver(fail: true);
        $svc = app(AiSearchService::class);

        foreach (['apartment one', 'apartment two', 'apartment three'] as $q) {
            $result = $svc->interpret($q);
            $this->assertSame('unavailable', $result['fallback_reason']); // never 'busy': the outage spent nothing
        }

        $this->assertSame(0, $this->used());
    }

    public function test_a_successful_call_still_counts(): void
    {
        config(['ai_search.daily_cap' => 5]);
        $this->useDriver(fail: false);

        app(AiSearchService::class)->interpret('apartment one');

        $this->assertSame(1, $this->used());
    }
}
