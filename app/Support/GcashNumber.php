<?php

namespace App\Support;

/**
 * A GCash number is a Philippine mobile number. Landlords type it every way people do (0917 123 4567,
 * +63 917 123 4567, 9171234567), so it is read into one shape, 09XXXXXXXXX, before it is checked or stored.
 * A number that cannot be read is refused instead of being saved and failing at the first payout.
 */
class GcashNumber
{
    /** The number as 09XXXXXXXXX, or null when it is not a Philippine mobile number. */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }

    public const MESSAGE = 'Enter a Philippine mobile number, like 0917 123 4567.';
}
