<?php

namespace App\Support;

/**
 * What one Google Vision request saw in a photo: the text on it, how many faces, and what the photo looks like
 * (labels). `available` is false when Vision could not be asked or did not answer, which is not the same as a photo
 * with nothing on it.
 */
final class VisionReading
{
    /** @param array<string, float> $labels lowercase description => score */
    public function __construct(
        public readonly string $text,
        public readonly int $faceCount,
        public readonly array $labels,
        public readonly bool $available,
    ) {}

    public static function unavailable(): self
    {
        return new self('', 0, [], false);
    }
}
