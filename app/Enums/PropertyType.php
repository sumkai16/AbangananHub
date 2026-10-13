<?php

namespace App\Enums;

/**
 * The one list of property types. Validation, filters, dropdowns and the AI
 * vocabulary all read it, so adding or retiring a type is this file plus a
 * migration for the `properties.property_type` ENUM column. The two views that
 * carry per-type artwork (`components/category-strip`, `landlord/properties/
 * wizard/info`) still list their own icons by hand.
 *
 * Not cast on the Property model on purpose: `$property->property_type` stays a
 * plain string, which is what the views and queries compare against.
 */
enum PropertyType: string
{
    case Apartment = 'Apartment';
    case Dormitory = 'Dormitory';
    case House = 'House';
    case BoardingHouse = 'Boarding House';
    case Bedspace = 'Bedspace';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Comma list for a Laravel `in:` rule. */
    public static function rule(): string
    {
        return implode(',', self::values());
    }

    /**
     * value => label for a dropdown, with an optional leading "any" entry.
     *
     * @return array<string, string>
     */
    public static function options(?string $any = null): array
    {
        $options = array_combine(self::values(), self::values());

        return $any === null ? $options : ['' => $any] + $options;
    }
}
