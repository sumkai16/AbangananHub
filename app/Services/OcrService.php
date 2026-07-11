<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_VISION_API_KEY', '');
    }

    /**
     * Extract all text from an image using Google Cloud Vision API.
     * Falls back to empty string on failure.
     */
    public function extractText(string $imagePath): string
    {
        if (empty($this->apiKey)) {
            Log::warning('OcrService: GOOGLE_VISION_API_KEY not set');
            return '';
        }

        $imageData = base64_encode(file_get_contents($imagePath));

        try {
            $response = Http::timeout(15)->post(
                "https://vision.googleapis.com/v1/images:annotate?key={$this->apiKey}",
                [
                    'requests' => [
                        [
                            'image' => [
                                'content' => $imageData,
                            ],
                            'features' => [
                                [
                                    'type' => 'DOCUMENT_TEXT_DETECTION',
                                    'maxResults' => 1,
                                ],
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
                return '';
            }

            $result = $response->json();

            return $result['responses'][0]['fullTextAnnotation']['text'] ?? '';
        } catch (\Exception $e) {
            Log::error('OcrService: Vision API exception', ['message' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Match extracted text against a user's full name.
     * Returns: [name => string|null, confidence => int, status => string]
     */
    public function matchName(string $extractedText, string $firstName, string $lastName): array
    {
        if (empty($extractedText)) {
            return ['name' => null, 'confidence' => 0, 'status' => 'fail'];
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

        // Fuzzy match each line against both name orderings
        $bestScore = 0;
        $bestMatch = null;
        $lines = preg_split('/\r?\n/', $extractedText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 3) continue;

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
     * Extract ID number using regex patterns per ID type.
     */
    public function extractIdNumber(string $extractedText, string $idType): ?string
    {
        $patterns = [
            'PhilSys'              => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
            'Professional ID Card' => '/\b\d{7}\b/',
            'Driver\'s License'    => '/\b[A-Z]\d{2}[-\s]?\d{2}[-\s]?\d{6,7}\b/',
            'Passport'             => '/\b[A-Z]{1,2}\d{7,8}\b/',
            'UMID'                 => '/\b\d{4}[-\s]?\d{7}[-\s]?\d{1}\b/',
            'Postal ID'            => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
            'SSS ID'               => '/\b\d{2}[-\s]?\d{7}[-\s]?\d{1}\b/',
        ];

        $pattern = $patterns[$idType] ?? null;

        if (! $pattern) {
            return null;
        }

        // Clean OCR artifacts (common misreads)
        $cleanText = str_replace(['O', 'o', 'l', 'I'], ['0', '0', '1', '1'], $extractedText);

        if (preg_match($pattern, $extractedText, $matches)) {
            return $matches[0];
        }

        // Retry with cleaned text
        if (preg_match($pattern, $cleanText, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Check if the extracted text contains keywords matching the selected ID type.
     * Returns: [match => bool, status => string, keywords_found => array]
     */
    public function matchIdType(string $extractedText, string $idType): array
    {
        $keywordMap = [
            'PhilSys'              => ['Philippine Identification', 'PhilSys', 'PhilID', 'PSN', 'REPUBLIKA NG PILIPINAS', 'Identification System', 'PHILSYS', 'National ID'],
            'Professional ID Card' => ['Professional Regulation', 'PRC', 'Professional ID', 'PROFESSIONAL REGULATION COMMISSION', 'KOMISYON SA REGULASYON'],
            "Driver's License"     => ['Land Transportation', 'LTO', "Driver's License", 'Drivers License', 'NON-PROFESSIONAL', 'PROFESSIONAL', 'LAND TRANSPORTATION OFFICE', 'TANGGAPAN NG TRANSPORTASYONG LUPA'],
            'Passport'             => ['Passport', 'Department of Foreign Affairs', 'DFA', 'PASAPORTE', 'REPUBLIC OF THE PHILIPPINES'],
            'UMID'                 => ['Unified Multi-Purpose', 'UMID', 'MULTI-PURPOSE', 'Unified Multi'],
            'Postal ID'            => ['Philippine Postal', 'Post Office', 'PHLPost', 'Postal ID', 'POSTAL CORPORATION', 'KOREO'],
            'SSS ID'               => ['Social Security System', 'SSS', 'SOCIAL SECURITY'],
        ];

        $keywords = $keywordMap[$idType] ?? [];

        if (empty($keywords) || empty($extractedText)) {
            return ['match' => false, 'status' => 'unknown', 'keywords_found' => []];
        }

        $textLower = strtolower($extractedText);
        $found = [];

        foreach ($keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                $found[] = $keyword;
            }
        }

        if (count($found) > 0) {
            return ['match' => true, 'status' => 'match', 'keywords_found' => $found];
        }

        return ['match' => false, 'status' => 'mismatch', 'keywords_found' => []];
    }
}