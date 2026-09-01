<?php

namespace App\Support;

class AmenityIcons
{
    /**
     * One SVG path (24x24 viewBox, stroke-based) per seeded amenity name.
     * Keyed on the literal names AmenitySeeder writes — names are the stable
     * identity there (upserted on amenity_name), so a name-keyed map is safe
     * against amenity_id churn. Shared glyphs where the distinction between
     * two amenities doesn't earn its own drawing (e.g. both submeters use the
     * same bolt/droplet family). Falls back to the app's existing checkmark
     * for anything unmapped, including the free-text "Others" entry.
     */
    private const PATHS = [
        'Wi-Fi' => 'M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 18.75h.008v.008H12v-.008Z',

        'Air Conditioning' => 'M12 3v18M5.5 6.5l13 11M18.5 6.5l-13 11M3 12h18',
        'Electric Fan' => 'M12 3v18M5.5 6.5l13 11M18.5 6.5l-13 11M3 12h18',

        'Backup Generator' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        'Submeter (Electricity)' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        'Submeter (Water)' => 'M12 2.25c-3.75 4.5-6.75 8.373-6.75 11.25a6.75 6.75 0 0 0 13.5 0c0-2.877-3-6.75-6.75-11.25Z',

        'Shared Kitchen' => 'M4.5 9.75h15M6 9.75V6a1.5 1.5 0 0 1 1.5-1.5h9A1.5 1.5 0 0 1 18 6v3.75M4.5 9.75l1.5 9.75a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5l1.5-9.75',
        'Private Kitchen' => 'M4.5 9.75h15M6 9.75V6a1.5 1.5 0 0 1 1.5-1.5h9A1.5 1.5 0 0 1 18 6v3.75M4.5 9.75l1.5 9.75a1.5 1.5 0 0 0 1.5 1.5h9a1.5 1.5 0 0 0 1.5-1.5l1.5-9.75',
        'Refrigerator' => 'M6 3.75h12a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V5.25A1.5 1.5 0 0 1 6 3.75ZM4.5 9.75h15M9 6v2.25M9 12.75v3',
        'Microwave' => 'M3.75 6.75h16.5v10.5H3.75V6.75ZM6.75 9.75h6v4.5h-6v-4.5Z',
        'Water Dispenser' => 'M12 2.25c-3.75 4.5-6.75 8.373-6.75 11.25a6.75 6.75 0 0 0 13.5 0c0-2.877-3-6.75-6.75-11.25Z',
        'Washing Machine' => 'M4.5 3.75h15a.75.75 0 0 1 .75.75v15a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75v-15a.75.75 0 0 1 .75-.75ZM12 14.25a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',
        'Laundry Area' => 'M4.5 3.75h15a.75.75 0 0 1 .75.75v15a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75v-15a.75.75 0 0 1 .75-.75ZM12 14.25a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',

        'Private Bathroom' => 'M12 2.25v3M5.25 8.25h13.5M8.5 8.25v9M15.5 8.25v9M4.5 20.25h15',
        'Shared Bathroom' => 'M12 2.25v3M5.25 8.25h13.5M8.5 8.25v9M15.5 8.25v9M4.5 20.25h15',
        'Hot Shower' => 'M12 2.25v3M5.25 8.25h13.5M8.5 8.25v9M15.5 8.25v9M4.5 20.25h15',
        'Bed Included' => 'M2.25 18.75V9a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 9v9.75M2.25 15.75h19.5M6 12.75h4.5a1.5 1.5 0 0 0 1.5-1.5V9.75a1.5 1.5 0 0 0-1.5-1.5H6a1.5 1.5 0 0 0-1.5 1.5v3Z',
        'Study Table' => 'M3 6.75h18M4.5 6.75v13.5M19.5 6.75v13.5M9 6.75V3.75a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v3',
        'Wardrobe / Cabinet' => 'M5.25 3h13.5v18H5.25V3ZM12 3v18',

        'Elevator' => 'M8.25 15 12 18.75 15.75 15M8.25 9 12 5.25 15.75 9',
        'Parking Space' => 'M6.75 3.75h10.5a3 3 0 0 1 3 3v10.5a3 3 0 0 1-3 3H6.75a3 3 0 0 1-3-3V6.75a3 3 0 0 1 3-3Zm3 4.5v7.5m0-7.5h3a2.25 2.25 0 1 1 0 4.5h-3',
        'Motorcycle Parking' => 'M6.75 3.75h10.5a3 3 0 0 1 3 3v10.5a3 3 0 0 1-3 3H6.75a3 3 0 0 1-3-3V6.75a3 3 0 0 1 3-3Zm3 4.5v7.5m0-7.5h3a2.25 2.25 0 1 1 0 4.5h-3',
        // Corner-bracket viewfinder + lens circle — the camera icon this
        // replaced (Heroicons' detailed body-and-lens path) blurred into an
        // illegible blob at the 14-16px this renders at; four brackets and
        // a circle survive that scale.
        'CCTV' => 'M3.75 8.25v-2.5A1.5 1.5 0 0 1 5.25 4.25h2.5M20.25 8.25v-2.5a1.5 1.5 0 0 0-1.5-1.5h-2.5M3.75 15.75v2.5a1.5 1.5 0 0 0 1.5 1.5h2.5M20.25 15.75v2.5a1.5 1.5 0 0 1-1.5 1.5h-2.5M9 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z',
        '24/7 Security' => 'M12 2.25c3.5 2 7 2.5 7 2.5v7c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10v-7s3.5-.5 7-2.5Z',
        'Gated Entrance' => 'M4.5 21V6.75A2.25 2.25 0 0 1 6.75 4.5h10.5A2.25 2.25 0 0 1 19.5 6.75V21M9 21V12.75h6V21M4.5 21h15',
        'Balcony' => 'M3 21h18M5.25 21V9.75L12 4.5l6.75 5.25V21M9 21v-6h6v6',
        'Rooftop Access' => 'M3 21h18M5.25 21V9.75L12 4.5l6.75 5.25V21M9 21v-6h6v6',

        // Reduced from five packed shapes (palm + 4 toe circles) to three —
        // legible as a paw at 16px, where the full print blurred together.
        'Pet Friendly' => 'M12 21c-2.5 0-4.5-1.3-4.5-3.5S9.5 14 12 14s4.5 1.3 4.5 3.5S14.5 21 12 21ZM7.5 10.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM20.5 10.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
        'Curfew' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        // Was a two-person "user group" glyph — ~15 curve segments, one of
        // Heroicons' densest icons, unreadable this small. A door reads the
        // same idea ("visitors welcome in") in four strokes.
        'Visitors Allowed' => 'M4.5 21V4.5A1.5 1.5 0 0 1 6 3h9a1.5 1.5 0 0 1 1.5 1.5V21M4.5 21h12M13.5 12h.008v.008h-.008V12Z',
        // Was a detailed truck/bus glyph with window and body-panel lines —
        // simplified to a body outline and two wheels, the minimum that still
        // reads as "bus" at icon scale.
        'Near Public Transport' => 'M6 16.5V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75v9.75M4.5 16.5h15M7.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm9 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
        // Was a graduation-cap glyph with tassel/brim shading lines —
        // simplified to the cap's diamond silhouette and strap.
        'Near School / University' => 'M12 3 3 8l9 5 9-5-9-5ZM6 11v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5',
        'Near Market / Grocery' => 'M3.75 4.5H6l.9 12.15A2.25 2.25 0 0 0 9.15 18.9h8.7a2.25 2.25 0 0 0 2.238-2.02l.9-8.13H6.15M9 21.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm8.25 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z',
    ];

    /** The checkmark every amenity rendered with before icons existed — the fallback for an unmapped name. */
    private const DEFAULT_PATH = 'M4.5 12.75l6 6 9-13.5';

    public static function path(?string $name): string
    {
        return self::PATHS[$name] ?? self::DEFAULT_PATH;
    }
}
