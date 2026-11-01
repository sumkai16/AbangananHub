<?php

namespace App\Support;

/**
 * Whether two written names can be the same person. Used to compare the name on a landlord's GCash account with
 * the name on their verified ID. It is forgiving about order, case, accents, punctuation, a middle name or
 * initial and a suffix ("SANTOS, Maria L." matches "Maria Santos"), and strict about everything else. It only
 * flags a mismatch for an admin to look at; it never decides anything by itself.
 */
class NameMatch
{
    private const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv'];

    public static function matches(?string $a, ?string $b): bool
    {
        $a = self::tokens($a);
        $b = self::tokens($b);

        if ($a === [] || $b === []) {
            return false;
        }

        // Every word of the shorter name must be found in the longer one. A single word is too little to
        // vouch for a person, so it must be the whole name on both sides.
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];

        if (count($short) < 2) {
            return $short === $long;
        }

        foreach ($short as $word) {
            if (! self::found($word, $long)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function tokens(?string $name): array
    {
        $name = mb_strtolower(trim((string) $name));
        $name = \Illuminate\Support\Str::ascii($name);
        $words = preg_split('/[^a-z]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn ($w) => ! in_array($w, self::SUFFIXES, true)));
    }

    /** A word is found by itself, or as an initial of a longer word ("L" for "Lourdes"). */
    private static function found(string $word, array $words): bool
    {
        foreach ($words as $other) {
            if ($word === $other || (strlen($word) === 1 && str_starts_with($other, $word)) || (strlen($other) === 1 && str_starts_with($word, $other))) {
                return true;
            }
        }

        return false;
    }
}
