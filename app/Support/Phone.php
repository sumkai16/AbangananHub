<?php

namespace App\Support;

/** One rule for every phone number a user types, so "abc" cannot be saved as a contact number. */
final class Phone
{
    /** Digits with an optional +, spaces, dashes and brackets; 7 to 20 characters (09171234567, +63 917 123 4567, (032) 123-4567). */
    public const RULE = 'regex:/^[0-9+()\s-]{7,20}$/';
}
