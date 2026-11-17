<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;

/**
 * Which archives to delete. Keeps every copy from the last $days days, the first copy of each of the last $weeks
 * weeks, and the first copy of each of the last $months months, so a problem found late can still be undone.
 * The newest copy is never deleted, and a file whose name is not ours is never touched.
 */
final class RetentionPolicy
{
    public static function parse(string $name, string $prefix, string $tz): ?CarbonImmutable
    {
        if (! preg_match('/^'.preg_quote($prefix, '/').'-(\d{4}-\d{2}-\d{2})-(\d{2})(\d{2})\.tar\.gz\.enc$/', $name, $m)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$m[1]} {$m[2]}:{$m[3]}", $tz) ?: null;
    }

    /**
     * @param  list<string>  $names
     * @return list<string> the names to delete
     */
    public static function expired(array $names, CarbonImmutable $now, int $days, int $weeks, int $months, string $prefix, string $tz): array
    {
        $dated = [];
        foreach ($names as $name) {
            if ($when = self::parse($name, $prefix, $tz)) {
                $dated[$name] = $when;
            }
        }
        asort($dated);                                   // oldest first
        $keep = [];
        $newest = array_key_last($dated);
        if ($newest !== null) {
            $keep[$newest] = true;
        }

        $dayLimit = $now->subDays($days);
        $weekLimit = $now->subWeeks($weeks);
        $monthLimit = $now->subMonths($months);
        $seenWeek = $seenMonth = [];
        foreach ($dated as $name => $when) {
            if ($when >= $dayLimit) {
                $keep[$name] = true;
            }
            $week = $when->format('o-W');
            if ($when >= $weekLimit && ! isset($seenWeek[$week])) {
                $seenWeek[$week] = true;
                $keep[$name] = true;
            }
            $month = $when->format('Y-m');
            if ($when >= $monthLimit && ! isset($seenMonth[$month])) {
                $seenMonth[$month] = true;
                $keep[$name] = true;
            }
        }

        return array_values(array_diff(array_keys($dated), array_keys($keep)));
    }
}
