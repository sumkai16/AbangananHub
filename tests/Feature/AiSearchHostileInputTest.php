<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The AI search box sends free text (interpret) and a browser-built `intent` array (results).
 * The intent is user-controlled, so it is fed SQL text, wrong types and huge values: it must
 * answer cleanly and never run an injected SLEEP().
 */
class AiSearchHostileInputTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null', 'ai_search.driver' => 'null']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function intents(): array
    {
        $sql = "x' OR '1'='1' AND SLEEP(3) -- ";

        return [
            'empty'                    => [[]],
            'sql in location value'    => [['location' => ['kind' => 'city', 'value' => $sql]]],
            'sql in free-text location' => [['location' => ['kind' => 'text', 'value' => $sql]]],
            'array as location value'  => [['location' => ['kind' => 'city', 'value' => ['a', 'b']]]],
            'unknown location kind'    => [['location' => ['kind' => $sql, 'value' => 'x']]],
            'location as a string'     => [['location' => $sql]],
            'sql in property_type'     => [['property_type' => $sql]],
            'array property_type'      => [['property_type' => ['a']]],
            'text prices'              => [['price_min' => $sql, 'price_max' => $sql]],
            'array prices'             => [['price_min' => [1], 'price_max' => ['x' => 2]]],
            'sql amenities'            => [['amenities' => [$sql, ['nested']]]],
            'amenities as a string'    => [['amenities' => $sql]],
            'sql in rules'             => [['rules' => [$sql]]],
            'sql in living/for/furn'   => [['living' => $sql, 'for' => $sql, 'furnishing' => $sql]],
            'host with sql ids'        => [['host' => ['landlord_ids' => [$sql, '1 OR 1=1'], 'name' => $sql]]],
            'text radius'              => [['radius_km' => $sql]],
            'huge radius'              => [['radius_km' => 1e308, 'price_max' => PHP_INT_MAX]],
            'point with text lat/lng'  => [['location' => ['kind' => 'point', 'lat' => $sql, 'lng' => [1]]]],
        ];
    }

    #[DataProvider('intents')]
    public function test_results_survives_hostile_intent(array $intent): void
    {
        $started = microtime(true);

        $response = $this->postJson('/search/results', ['q' => "x' OR 1=1 -- ", 'intent' => $intent, 'used_ai' => false]);

        $this->assertLessThan(500, $response->getStatusCode(), 'results returned ' . $response->getStatusCode() . ': ' . ($response->exception?->getMessage() ?? $response->getContent()) . ' @ ' . ($response->exception ? basename($response->exception->getFile()) . ':' . $response->exception->getLine() : ''));
        $this->assertLessThan(2.5, microtime(true) - $started, 'took too long: did SLEEP() run?');
    }

    public function test_interpret_survives_hostile_text(): void
    {
        foreach (["x' OR '1'='1' AND SLEEP(3) -- ", "'; DROP TABLE users; --", str_repeat('%_\\', 200), '<script>alert(1)</script>'] as $q) {
            $started = microtime(true);

            $response = $this->postJson('/search/interpret', ['q' => $q, 'fast' => true]);

            $this->assertLessThan(500, $response->getStatusCode(), "interpret returned {$response->getStatusCode()} for [$q]");
            $this->assertLessThan(2.5, microtime(true) - $started);
        }
    }

    public function test_interpret_rejects_non_text(): void
    {
        $this->postJson('/search/interpret', ['q' => ['a'], 'fast' => true])->assertStatus(422);
        $this->postJson('/search/interpret', ['q' => str_repeat('a', 5000), 'fast' => true])->assertStatus(422);
    }
}
