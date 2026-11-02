<?php

namespace App\Support;

/**
 * Writes CSV rows that are safe to open in Excel or Sheets. A text cell that starts with = + - @ (or a tab or
 * carriage return) is run as a formula there, so a tenant named `=HYPERLINK(...)` would execute for whoever opens
 * the export. Such cells get a leading apostrophe, which the spreadsheet hides. Plain numbers, including negative
 * ones, are left alone.
 */
final class Csv
{
    /** @param resource $handle */
    public static function put($handle, array $row): int|false
    {
        return fputcsv($handle, array_map(self::cell(...), $row));
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) && ! preg_match('/^-?\d[\d,]*(\.\d+)?$/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
