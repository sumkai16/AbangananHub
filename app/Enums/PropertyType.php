<?php

namespace App\Enums;

/**
 * The one list of property types. Validation, filters, dropdowns and the AI
 * vocabulary all read it, so adding or retiring a type is this file plus a
 * migration for the `properties.property_type` ENUM column. The browse chips
 * (`components/category-strip` and the landing page's type pills) read icon()
 * from here; `landlord/properties/wizard/info` still lists its own by hand.
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

    /** Heroicons-style outline path (24x24 viewBox, 2px stroke) for the type's chip. */
    public function icon(): string
    {
        return match ($this) {
            self::Apartment => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
            self::Dormitory => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21',
            self::House => 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6',
            self::BoardingHouse => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
            self::Bedspace => 'M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14',
        };
    }

    /** Icon for the "All" chip that sits in front of the types. */
    public const ALL_ICON = 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z';

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
