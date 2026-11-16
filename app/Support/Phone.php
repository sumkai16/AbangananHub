<?php

namespace App\Support;

/** One rule for every phone number a user types, so "abc" cannot be saved as a contact number. */
final class Phone
{
    /** Digits with an optional +, spaces, dashes and brackets; 7 to 20 characters (09171234567, +63 917 123 4567, (032) 123-4567). */
    public const RULE = 'regex:/^[0-9+()\s-]{7,20}$/';

    /** Digits only, with +63 / 63 turned into a leading 0, so "+63 917 555 0142" and "0917-555-0142" compare equal. */
    public static function digits(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return str_starts_with($digits, '63') && strlen($digits) === 12 ? '0'.substr($digits, 2) : $digits;
    }
}
