<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lists the Gemini models the configured key can call for text generation.
 * Google renames and retires models often; a 404 from ai-search:eval usually
 * means GEMINI_MODEL is no longer one of these. Free (listing costs no quota).
 */
class AiSearchModels extends Command
{
    protected $signature = 'ai-search:models';

    protected $description = 'List Gemini models available to GEMINI_API_KEY (pick one for GEMINI_MODEL)';

    public function handle(): int
    {
        $key = config('services.gemini.key');
        if (blank($key)) {
            $this->error('GEMINI_API_KEY is not set in .env.');

            return self::FAILURE;
        }

        $response = Http::timeout(15)->withHeaders(['x-goog-api-key' => $key])
            ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 200]);

        if ($response->failed()) {
            $this->error('Google returned HTTP ' . $response->status() . ': ' . ($response->json('error.message') ?? $response->body()));

            return self::FAILURE;
        }

        $current = config('services.gemini.model');
        $rows = collect($response->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => [str_replace('models/', '', $m['name']), $m['displayName'] ?? '', str_replace('models/', '', $m['name']) === $current ? '← current' : ''])
            ->values()->all();

        $this->table(['GEMINI_MODEL value', 'Name', ''], $rows);
        $this->line('Pick a Flash or Flash-Lite model (fast, and on the free tier), then check its limits in AI Studio.');

        return self::SUCCESS;
    }
}
