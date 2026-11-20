<?php

namespace App\Services;

use App\Support\VisionReading;

/**
 * Does a photo look like a government ID? It scores what Google Vision read (see OcrService::read): text on the
 * card, words of the chosen ID type, common ID words, the applicant's own name, a face, and a card-like label.
 *
 * This catches the wrong photo (a wall, a selfie, a receipt, a page of a book), not a forged ID: a printed fake still
 * reaches the admin. Only a `fail` can hold the applicant back, and only for ATTEMPTS_BEFORE_CONTINUE tries; a worn
 * but real ID scores `unsure`, which never blocks.
 *
 * Which ID it is comes from IdTypeDetector. A real ID of another type than the one picked (a Driver's License picked
 * as PhilSys) is not a `fail`, but it is held for the same tries so the applicant can switch the type or retake.
 */
class IdDocumentCheck
{
    public const PASS = 'pass';
    public const UNSURE = 'unsure';
    public const FAIL = 'fail';
    public const UNAVAILABLE = 'unavailable';

    /** Failed checks one applicant gets before they may continue anyway (and the admin is told). */
    public const ATTEMPTS_BEFORE_CONTINUE = 2;

    /** Points a front needs to read as a clear ID. */
    public const PASS_POINTS = 3;

    /** Letters and digits (spaces not counted) a front must show before anything else is weighed. */
    private const MIN_FRONT_CHARS = 20;

    /** A back is often sparse, so it only has to show some text. */
    private const MIN_BACK_CHARS = 8;

    /** Distinct common ID words that make the `id_words` signal. */
    private const MIN_ID_WORDS = 2;

    /** How sure Vision must be of a card-like label. */
    private const MIN_LABEL_SCORE = 0.6;

    private const ID_WORDS = [
        'republic of the philippines', 'republika ng pilipinas', 'date of birth', 'petsa ng kapanganakan',
        'address', 'tirahan', 'sex', 'kasarian', 'nationality', 'signature', 'lagda',
        'valid until', 'expiry', 'date of issue',
    ];

    /** A label containing one of these, or equal to "card", counts as card-like. */
    private const CARD_LABEL_PARTS = ['identity document', 'license', 'passport', 'id card'];

    public function __construct(private OcrService $ocr, private IdTypeDetector $types) {}

    /**
     * @return array{status: string, type_match: string, detected_type: ?string, signals: array<string, bool>}
     */
    public function assessFront(VisionReading $reading, string $idType, string $firstName, string $lastName): array
    {
        if (! $reading->available) {
            return [
                'status' => self::UNAVAILABLE,
                'type_match' => IdTypeDetector::UNKNOWN,
                'detected_type' => null,
                'signals' => array_fill_keys(['has_text', 'type_keyword', 'name', 'id_words', 'face', 'card_label'], false),
            ];
        }

        ['status' => $typeMatch, 'detected' => $detected] = $this->types->matchFront($reading->text, $idType);

        $signals = [
            'has_text' => $this->charCount($reading->text) >= self::MIN_FRONT_CHARS,
            'type_keyword' => $typeMatch === IdTypeDetector::MATCH,
            // A partial match (one of the two names) is not counted: on its own it can appear on any page of text.
            'name' => $this->ocr->matchName($reading->text, $firstName, $lastName)['status'] === 'pass',
            'id_words' => $this->idWordsIn($reading->text) >= self::MIN_ID_WORDS,
            'face' => $reading->faceCount >= 1,
            'card_label' => $this->hasCardLabel($reading->labels),
        ];

        $points = ($signals['type_keyword'] ? 2 : 0)
            + ($signals['name'] ? 2 : 0)
            + ($signals['id_words'] ? 1 : 0)
            + ($signals['face'] ? 1 : 0)
            + ($signals['card_label'] ? 1 : 0);

        $status = match (true) {
            ! $signals['has_text'], $points === 0 => self::FAIL,
            $points >= self::PASS_POINTS => self::PASS,
            default => self::UNSURE,
        };

        return ['status' => $status, 'type_match' => $typeMatch, 'detected_type' => $detected, 'signals' => $signals];
    }

    /** The back has no face to find, so it only has to show some text. */
    public function assessBack(VisionReading $reading): string
    {
        if (! $reading->available) {
            return self::UNAVAILABLE;
        }

        return $this->charCount($reading->text) >= self::MIN_BACK_CHARS ? self::PASS : self::FAIL;
    }

    /**
     * Holds the applicant on the photo step: a failed side or a front that shows another ID type than the one picked,
     * and fewer than ATTEMPTS_BEFORE_CONTINUE tries used so far.
     */
    public function blocks(string $frontStatus, ?string $backStatus, int $failures, bool $typeMismatch = false): bool
    {
        return ($frontStatus === self::FAIL || $backStatus === self::FAIL || $typeMismatch)
            && $failures < self::ATTEMPTS_BEFORE_CONTINUE;
    }

    private function charCount(string $text): int
    {
        return mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');
    }

    /** How many different common ID words the text holds, as whole words ("sex" is not in "Essex"). */
    private function idWordsIn(string $text): int
    {
        $lower = mb_strtolower($text);
        $found = 0;

        foreach (self::ID_WORDS as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $lower) === 1) {
                $found++;
            }
        }

        return $found;
    }

    /** @param array<string, float> $labels */
    private function hasCardLabel(array $labels): bool
    {
        foreach ($labels as $name => $score) {
            if ($score < self::MIN_LABEL_SCORE) {
                continue;
            }

            if ($name === 'card') {
                return true;
            }

            foreach (self::CARD_LABEL_PARTS as $part) {
                if (str_contains($name, $part)) {
                    return true;
                }
            }
        }

        return false;
    }
}
