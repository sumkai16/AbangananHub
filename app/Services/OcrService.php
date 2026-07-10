<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    protected string $tesseractPath;

    public function __construct()
    {
        $this->tesseractPath = env(
            'TESSERACT_PATH',
            'C:\Program Files\Tesseract-OCR\tesseract.exe'
        );
    }

    /**
     * Extract all text from an image file path.
     */
    public function extractText(string $imagePath): string
    {
        $ocr = new TesseractOCR($imagePath);
        $ocr->executable($this->tesseractPath);
        $ocr->lang('eng');
        $ocr->psm(6); // Assume uniform block of text

        try {
            return trim($ocr->run());
        } catch (\Exception $e) {
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
            'PhilSys'              => ['Philippine Identification', 'PhilSys', 'PhilID', 'PSN'],
            'Professional ID Card' => ['Professional Regulation', 'PRC', 'Professional ID'],
            "Driver's License"     => ['Land Transportation', 'LTO', "Driver's License", 'Drivers License', 'NON-PROFESSIONAL', 'PROFESSIONAL'],
            'Passport'             => ['Passport', 'Department of Foreign Affairs', 'DFA'],
            'UMID'                 => ['Unified Multi-Purpose', 'UMID'],
            'Postal ID'            => ['Philippine Postal', 'Post Office', 'PHLPost', 'Postal ID'],
            'SSS ID'               => ['Social Security System', 'SSS'],
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