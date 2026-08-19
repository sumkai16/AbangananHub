<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Attach to `latitude`, passing the submitted `longitude` in — the pin has to
 * be checked as a pair, but a rule only validates one field at a time.
 */
class WithinCebu implements ValidationRule
{
    public function __construct(private readonly mixed $longitude)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! is_numeric($this->longitude)) {
            return;
        }

        $bounds = config('cebu.bounds');
        $lat = (float) $value;
        $lng = (float) $this->longitude;

        $inside = $lat >= $bounds['min_lat'] && $lat <= $bounds['max_lat']
            && $lng >= $bounds['min_lng'] && $lng <= $bounds['max_lng'];

        if (! $inside) {
            $fail('Please pin a location inside Cebu.');
        }
    }
}
