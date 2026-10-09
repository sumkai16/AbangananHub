<?php

namespace App\Services\AiSearch;

/**
 * Tells English from Tagalog and Bisaya by the words typed. A model is unreliable at this
 * (it often says "mixed"), and the answer only decides which language the summary is written
 * in, so a plain word count is enough and costs nothing. Returns null when it cannot tell,
 * and the caller keeps the model's guess.
 */
class LanguageDetector
{
    // Words that are distinctly Bisaya / Tagalog (not shared, and not English).
    private const CEBUANO = ['naa', 'naay', 'ubos', 'duol', 'dapit', 'ug', 'nga', 'dili', 'molapas', 'pangita', 'gipangita', 'nako', 'ko', 'libo', 'ngadto', 'hangtod', 'unsa', 'bay', 'balay', 'tabi', 'puwede', 'kinse', 'tulo', 'lima', 'maximum', 'kuwarto', 'ka', 'adtong', 'diha', 'gusto', 'unta', 'pwede'];
    private const TAGALOG = ['malapit', 'may', 'ang', 'ng', 'na', 'para', 'hindi', 'lalampas', 'hanap', 'gusto', 'pababa', 'hanggang', 'ako', 'ko', 'naghahanap', 'kwarto', 'bahay', 'murang', 'pang', 'lalaki', 'babae', 'sa', 'libo', 'limang', 'pwede', 'hihigit', 'magdala', 'bisita', 'lang', 'mga', 'paradahan', 'aso', 'pusa'];
    // Words only one of the two uses; they decide ties.
    private const CEBUANO_ONLY = ['naa', 'naay', 'ubos', 'duol', 'dapit', 'ug', 'nga', 'dili', 'molapas', 'pangita', 'gipangita', 'nako', 'ngadto', 'hangtod', 'unsa', 'bay', 'balay', 'kinse', 'tulo', 'ka', 'diha', 'unta', 'iro', 'iring'];
    private const TAGALOG_ONLY = ['malapit', 'may', 'ang', 'ng', 'hindi', 'lalampas', 'hanap', 'pababa', 'hanggang', 'naghahanap', 'kwarto', 'bahay', 'murang', 'pang', 'limang', 'hihigit', 'magdala', 'bisita', 'mga', 'paradahan', 'aso', 'pusa'];

    public static function detect(string $text): ?string
    {
        $words = array_values(array_filter(explode(' ', Landmarks::normalize($text))));
        if (count($words) < 2) {
            return null;
        }

        $ceb = count(array_intersect($words, self::CEBUANO_ONLY));
        $tl = count(array_intersect($words, self::TAGALOG_ONLY));

        if ($ceb === 0 && $tl === 0) {
            // Shared words only ("sa", "libo", "ko") are too weak to call; plain English has none of them.
            $shared = count(array_intersect($words, array_merge(self::CEBUANO, self::TAGALOG)));

            return $shared >= 2 ? null : 'en';
        }

        return $ceb === $tl ? null : ($ceb > $tl ? 'ceb' : 'tl');
    }
}
