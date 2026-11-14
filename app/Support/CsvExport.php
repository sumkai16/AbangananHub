<?php

namespace App\Support;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a CSV download that is safe to open in Excel (UTF-8 BOM, formula-injection guard). */
class CsvExport
{
    /** Prefix any cell a spreadsheet could read as a formula with a single quote so it shows as plain text. */
    public static function safe(mixed $value): string
    {
        $text = (string) $value;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * Rows of an Eloquent query, newest first, in chunks of 1000, stopping after $cap rows.
     * (take() does not combine with lazyById, so the cap is enforced here.)
     */
    public static function capped(Builder $query, int $cap): Generator
    {
        $seen = 0;

        foreach ($query->lazyByIdDesc(1000) as $row) {
            if ($seen++ >= $cap) {
                return;
            }

            yield $row;
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows  each row is a list of cell values
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'safe'], $headers));

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }

            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
