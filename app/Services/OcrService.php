<?php

namespace App\Services;

use App\Support\VisionReading;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    /** How long a reading is kept: long enough for the wizard's preview to still be there at the final submit. */
    private const CACHE_MINUTES = 30;

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_vision.key', '');
    }

    /**
     * Ask Google Cloud Vision once for the text, the faces and the labels of a photo. Never throws: when Vision
     * cannot answer (no key, outage, quota) the reading is `unavailable` and is not kept, so a later call asks again.
     */
    public function read(string $imageBytes): VisionReading
    {
        if ($cached = $this->cached($imageBytes)) {
            return $cached;
        }

        if ($this->apiKey === '') {
            Log::warning('OcrService: GOOGLE_VISION_API_KEY not set');

            return VisionReading::unavailable();
        }

        try {
            $response = Http::timeout(15)->post(
                "https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}",
                [
                    'requests' => [
                        [
                            'image' => ['content' => base64_encode($imageBytes)],
                            'features' => [
                                ['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1],
                                ['type' => 'FACE_DETECTION', 'maxResults' => 1],
                                ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                            ],
                        ],
                    ],
                ]
            );

            if ($response->failed()) {
                Log::error('OcrService: Vision API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return VisionReading::unavailable();
            }

            $result = $response->json('responses.0') ?? [];

            if (isset($result['error'])) {
                Log::error('OcrService: Vision API returned an error', ['error' => $result['error']]);

                return VisionReading::unavailable();
            }
        } catch (\Exception $e) {
            Log::error('OcrService: Vision API exception', ['message' => $e->getMessage()]);

            return VisionReading::unavailable();
        }

        $reading = new VisionReading(
            (string) ($result['fullTextAnnotation']['text'] ?? ''),
            count($result['faceAnnotations'] ?? []),
            $this->labelsFrom($result['labelAnnotations'] ?? []),
            true,
        );

        $this->remember($imageBytes, $reading);

        return $reading;
    }

    /** A reading of this exact photo made in the last few minutes, or null. Never calls Vision. */
    public function cached(string $imageBytes): ?VisionReading
    {
        $payload = Cache::get($this->cacheKey($imageBytes));

        if (! is_string($payload)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        return new VisionReading((string) $data['text'], (int) $data['faces'], (array) $data['labels'], true);
    }

    private function cacheKey(string $imageBytes): string
    {
        return 'vision:'.hash('sha256', $imageBytes);
    }

    /**
     * The reading holds what is printed on a government ID, so it is stored encrypted: the cache store is the
     * database in production, and plain ID text must not sit in a table.
     */
    private function remember(string $imageBytes, VisionReading $reading): void
    {
        $payload = Crypt::encryptString(json_encode([
            'text' => $reading->text,
            'faces' => $reading->faceCount,
            'labels' => $reading->labels,
        ], JSON_THROW_ON_ERROR));

        Cache::put($this->cacheKey($imageBytes), $payload, now()->addMinutes(self::CACHE_MINUTES));
    }

    /**
     * @param  array<int, array{description?: string, score?: float|int}>  $annotations
     * @return array<string, float> lowercase description => best score
     */
    private function labelsFrom(array $annotations): array
    {
        $labels = [];

        foreach ($annotations as $annotation) {
            $name = mb_strtolower(trim((string) ($annotation['description'] ?? '')));

            if ($name !== '') {
                $labels[$name] = max($labels[$name] ?? 0.0, (float) ($annotation['score'] ?? 0));
            }
        }

        return $labels;
    }

    /**
     * Match extracted text against a user's full name.
     * Returns: [name => string|null, confidence => int, status => string]
     */
    public function matchName(string $extractedText, string $firstName, string $lastName): array
    {
        // No text at all means the reader returned nothing (a blurry photo, or the service refused the request).
        // That is not the same as "your name is not on the ID", and the applicant must not be told it is.
        if (trim($extractedText) === '') {
            return ['name' => null, 'confidence' => 0, 'status' => 'unreadable'];
        }

        $fullName = strtolower(trim("$firstName $lastName"));
        $reversedName = strtolower(trim("$lastName $firstName"));
        $textLower = strtolower($extractedText);

        // Check for direct substring match first
        if (str_contains($textLower, $fullName) || str_contains($textLower, $reversedName)) {
            return ['name' => "$firstName $lastName", 'confidence' => 100, 'status' => 'pass'];
        }

        // PH government IDs commonly split the name across separate lines
        // (Apellido/Last Name on one line, Mga Pangalan/Given Names on the
        // next), so the two components never appear adjacent in the raw
        // text. Check both name parts independently before falling back to
        // per-line fuzzy matching, which was capping real matches like
        // "CABUSAS" (last name only, on its own line) in the 50-79 "partial"
        // band instead of recognizing it as a genuine match.
        $firstNameLower = strtolower(trim($firstName));
        $lastNameLower = strtolower(trim($lastName));

        $firstNameFound = $firstNameLower !== '' && preg_match('/\b' . preg_quote($firstNameLower, '/') . '\b/', $textLower);
        $lastNameFound = $lastNameLower !== '' && preg_match('/\b' . preg_quote($lastNameLower, '/') . '\b/', $textLower);

        if ($firstNameFound && $lastNameFound) {
            return ['name' => "$firstName $lastName", 'confidence' => 95, 'status' => 'pass'];
        }

        // OCR slips a letter or two on a slightly soft photo (CABUSAS read as CABUSA5, AXCEE as AXCFE). Compare
        // word by word with a small edit-distance allowance before falling back to whole-line similarity.
        $words = $this->words($extractedText);
        $firstFuzzy = $this->nameFoundIn($firstName, $words);
        $lastFuzzy = $this->nameFoundIn($lastName, $words);

        if ($firstFuzzy && $lastFuzzy) {
            return ['name' => "$firstName $lastName", 'confidence' => 90, 'status' => 'pass'];
        }

        if ($firstFuzzy || $lastFuzzy) {
            return ['name' => $firstFuzzy ? $firstName : $lastName, 'confidence' => 65, 'status' => 'partial'];
        }

        // Fuzzy match each line against both name orderings
        $bestScore = 0;
        $bestMatch = null;
        $lines = preg_split('/\r?\n/', $extractedText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 3) {
                continue;
            }

            $lineLower = strtolower($line);

            // similar_text percentage against both orderings
            similar_text($lineLower, $fullName, $score1);
            similar_text($lineLower, $reversedName, $score2);

            $score = max($score1, $score2);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $line;
            }
        }

        $confidence = (int) round($bestScore);

        if ($confidence >= 80) {
            $status = 'pass';
        } elseif ($confidence >= 50) {
            $status = 'partial';
        } else {
            $status = 'fail';
        }

        return [
            'name'       => $bestMatch,
            'confidence' => $confidence,
            'status'     => $status,
        ];
    }

    /**
     * Lower-case words of a text, accents flattened and punctuation removed, so "CABUSAS," and "Cabusás" compare.
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        $text = strtr(mb_strtolower($text), ['ñ' => 'n', 'á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);

        return preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Is every word of a (possibly multi-word) name present in the text, allowing a small spelling error per word?
     * One edit for words of 4 to 6 letters and two for longer ones; short words must match exactly.
     *
     * @param  list<string>  $textWords
     */
    private function nameFoundIn(string $name, array $textWords): bool
    {
        $nameWords = $this->words($name);

        if ($nameWords === []) {
            return false;
        }

        foreach ($nameWords as $word) {
            $allowed = strlen($word) >= 7 ? 2 : (strlen($word) >= 4 ? 1 : 0);
            $found = false;

            foreach ($textWords as $candidate) {
                if (abs(strlen($candidate) - strlen($word)) <= $allowed && levenshtein($candidate, $word) <= $allowed) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }
}
