<?php

namespace App\Services;

use App\Support\IdTemplates;

/**
 * Which government ID is in the photo? Scores the text Vision read against every template in App\Support\IdTemplates
 * and names a type only when one clearly leads, so a vague photo is "unknown", never a wrong guess.
 *
 * This names the ID; it does not prove the ID is genuine (IdDocumentCheck and the admin's review cover the rest).
 */
class IdTypeDetector
{
    public const MATCH = 'match';
    public const MISMATCH = 'mismatch';
    public const UNKNOWN = 'unknown';

    /** Points a type needs before detection may name it: more than one anchor phrase alone. */
    public const DETECT_MIN = 4;

    /** How far the named type must lead the runner-up. */
    public const DETECT_LEAD = 3;

    /** Points the picked type needs to count as a match when nothing was detected (one anchor phrase is enough). */
    public const MATCH_MIN = 3;

    private const ANCHOR_POINTS = 3;
    private const MAX_ANCHORS = 2;
    private const LABEL_POINTS = 1;
    private const MAX_LABELS = 3;
    private const STRONG_NUMBER_POINTS = 2;
    private const WEAK_NUMBER_POINTS = 1;
    private const MRZ_POINTS = 4;

    /** How well the front's text fits one ID type's template. */
    public function scoreFront(string $text, string $type): int
    {
        $template = IdTemplates::get($type);

        if ($template === null || trim($text) === '') {
            return 0;
        }

        $text = $this->normalize($text);

        $score = min(self::MAX_ANCHORS, $this->phrasesIn($text, $template['anchors'])) * self::ANCHOR_POINTS
            + min(self::MAX_LABELS, $this->phrasesIn($text, $template['labels'])) * self::LABEL_POINTS;

        if ($this->findNumber($text, $template['number']) !== null) {
            $score += $template['number_strong'] ? self::STRONG_NUMBER_POINTS : self::WEAK_NUMBER_POINTS;
        }

        if ($type === IdTemplates::PASSPORT && $this->hasMrz($text)) {
            $score += self::MRZ_POINTS;
        }

        return $score;
    }

    /** The ID type the front shows, or null when no type clearly leads. */
    public function detectFront(string $text): ?string
    {
        $scores = [];

        foreach (IdTemplates::detectable() as $type) {
            $scores[$type] = $this->scoreFront($text, $type);
        }

        return $this->leader($scores);
    }

    /**
     * Does the front belong to the type the applicant picked?
     *
     * @return array{status: string, detected: ?string}
     */
    public function matchFront(string $text, string $chosen): array
    {
        $detected = $this->detectFront($text);

        if ($detected !== null) {
            return ['status' => $detected === $chosen ? self::MATCH : self::MISMATCH, 'detected' => $detected];
        }

        // Nothing clearly leads (a soft photo, or a type detection never names, like SSS for now): one of the picked
        // type's own phrases is still enough to call it a match.
        $status = $this->scoreFront($text, $chosen) >= self::MATCH_MIN ? self::MATCH : self::UNKNOWN;

        return ['status' => $status, 'detected' => null];
    }

    /** The ID type the back shows, from the phrases printed on each type's back, or null. */
    public function detectBack(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $text = $this->normalize($text);
        $scores = [];

        foreach (IdTemplates::detectable() as $type) {
            $back = IdTemplates::get($type)['back'];
            $scores[$type] = min(self::MAX_ANCHORS, $this->phrasesIn($text, $back)) * self::ANCHOR_POINTS;
        }

        return $this->leader($scores);
    }

    /**
     * The ID number printed on the front, in that type's format. A misread O or I is retried as 0 or 1. For a
     * passport the number in the machine-readable lines wins when its check digit is right: those lines are printed
     * in a font made for reading by machine.
     */
    public function idNumber(string $text, string $type): ?string
    {
        $template = IdTemplates::get($type);

        if ($template === null || trim($text) === '') {
            return null;
        }

        if ($type === IdTemplates::PASSPORT && ($fromMrz = $this->passportNumberFromMrz($text)) !== null) {
            return $fromMrz;
        }

        return $this->findNumber($text, $template['number'])
            ?? $this->findNumber(str_replace(['O', 'o', 'l', 'I'], ['0', '0', '1', '1'], $text), $template['number']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param array<string, int> $scores */
    private function leader(array $scores): ?string
    {
        arsort($scores);
        $types = array_keys($scores);
        $top = $scores[$types[0]] ?? 0;
        $second = isset($types[1]) ? $scores[$types[1]] : 0;

        return $top >= self::DETECT_MIN && $top - $second >= self::DETECT_LEAD ? $types[0] : null;
    }

    /** Lowercase, with curly quotes made straight, so "DRIVER’S" reads as "driver's". */
    private function normalize(string $text): string
    {
        return mb_strtolower(strtr($text, ['’' => "'", '‘' => "'", '`' => "'"]));
    }

    /**
     * How many of the phrases are in the text, each as whole words ("umid" is not in "humid"), any whitespace or a
     * line break between its words.
     *
     * @param  list<string>  $phrases
     */
    private function phrasesIn(string $text, array $phrases): int
    {
        $found = 0;

        foreach ($phrases as $phrase) {
            $words = array_map(fn (string $w): string => preg_quote($w, '/'), preg_split('/\s+/', $phrase));
            $pattern = '/(?<![\p{L}\p{N}])'.implode('\s+', $words).'(?![\p{L}\p{N}])/u';

            if (preg_match($pattern, $text) === 1) {
                $found++;
            }
        }

        return $found;
    }

    /**
     * The first number in the given format that is not part of a longer hyphenated number (a 12-digit Postal pattern
     * must not match the first three groups of a 16-digit PhilSys number). Only a hyphen joins groups: a space
     * between two numbers is just layout ("G33-24-002015 2029/04/23" is a license number, then an expiry date).
     */
    private function findNumber(string $text, string $pattern): ?string
    {
        $regex = '/(?<![A-Za-z0-9])(?<!\d-)(?:'.$pattern.')(?![A-Za-z0-9])(?!-\d)/i';

        return preg_match($regex, $text, $m) === 1 ? strtoupper($m[0]) : null;
    }

    /** The first machine-readable line of a Philippine passport: "P<PHL" then the name. */
    private function hasMrz(string $text): bool
    {
        return preg_match('/p\s*[<«]\s*phl/i', $text) === 1;
    }

    /**
     * The passport number from the second machine-readable line, when its check digit is right. The line starts with
     * the 9-character number (padded with "<"), its check digit, then "PHL".
     */
    private function passportNumberFromMrz(string $text): ?string
    {
        $compact = strtoupper(preg_replace('/[ \t]+/', '', $text) ?? '');

        if (preg_match('/([A-Z0-9<]{9})(\d)PHL\d{6}/', $compact, $m) !== 1) {
            return null;
        }

        return $this->mrzCheckDigit($m[1]) === (int) $m[2] ? rtrim($m[1], '<') : null;
    }

    /** ICAO 9303 check digit: weights 7, 3, 1 repeating; digits are themselves, A–Z are 10–35, "<" is 0. */
    private function mrzCheckDigit(string $field): int
    {
        $weights = [7, 3, 1];
        $sum = 0;

        foreach (str_split($field) as $i => $char) {
            $value = match (true) {
                ctype_digit($char) => (int) $char,
                ctype_alpha($char) => ord($char) - 55,
                default => 0,
            };
            $sum += $value * $weights[$i % 3];
        }

        return $sum % 10;
    }
}
