<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The browse filters that come from a property's own facts (as opposed to its
 * amenities): living arrangement, who it is suitable for, furnishing, and which
 * house rules the tenant needs to be absent.
 *
 * Everything read from the query string goes through fromRequest(), which drops
 * anything that isn't one of the known options — a hand-edited URL can't reach
 * the query builder with an arbitrary value.
 */
class BrowseFilters
{
    /** Query keys owned by this class (in addition to `amenities`). */
    public const KEYS = ['living', 'for', 'furnishing', 'rules'];

    /**
     * "Suitable for" — a woman looking for a place can rent a Women Only property or one with
     * no preference, so the filter matches the chosen value OR "No Preference".
     * key (query value) => occupancy_preference it maps to
     */
    public const SUITABLE_FOR = ['Men' => 'Men Only', 'Women' => 'Women Only'];

    public const FURNISHING = ['Furnished', 'Semi-furnished', 'Unfurnished'];

    /**
     * Rule filters read as "must allow": a property matches when it does NOT carry the
     * restricting house rule. key => [chip label, restricting preset in house_rules]
     */
    public const RULES = [
        'pets'      => ['Pets allowed', 'No Pets'],
        'smoking'   => ['Smoking allowed', 'No Smoking'],
        'overnight' => ['Overnight guests OK', 'No Overnight Guests'],
        'curfew'    => ['No curfew', 'Curfew'],
    ];

    /**
     * Amenities that are now house rules (or are answered by them). They stay in the database
     * but are left out of the filter panel so a tenant isn't offered the same thing twice.
     */
    public const AMENITIES_REPLACED_BY_RULES = ['Curfew', 'Pet Friendly', 'Visitors Allowed'];

    /**
     * Rewrite the query string in place so every filter has the shape the page expects, before
     * anything reads it. A hand-edited URL (`?amenities=abc`, `?price_max=1 OR 1`, `?type[]=x`)
     * is cleaned or dropped instead of reaching a view or the query builder as the wrong type.
     * These were 500 errors, not injection: values are always bound.
     */
    public static function sanitize(Request $request): void
    {
        $clean = [];

        foreach (['location', 'type', 'sort'] as $key) {
            $value = $request->query($key);
            if (is_string($value)) {
                $clean[$key] = $value;
            }
        }

        foreach (['price_min', 'price_max'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && is_numeric($value) && (float) $value >= 0) {
                $clean[$key] = $value;
            }
        }

        $ids = array_values(array_unique(array_map('intval', array_filter(
            (array) $request->query('amenities', []),
            fn ($id) => (is_string($id) || is_int($id)) && ctype_digit((string) $id)
        ))));
        if ($ids !== []) {
            $clean['amenities'] = $ids;
        }

        // Both bags: request('x') reads the merged input, $request->query('x') reads the query string.
        foreach (['location', 'type', 'sort', 'price_min', 'price_max', 'amenities'] as $key) {
            if (array_key_exists($key, $clean)) {
                $request->query->set($key, $clean[$key]);
                $request->request->set($key, $clean[$key]);
            } else {
                $request->query->remove($key);
                $request->request->remove($key);
            }
        }
    }

    /**
     * @return array{living: ?string, for: ?string, furnishing: ?string, rules: array<int, string>}
     */
    public static function fromRequest(Request $request): array
    {
        $living = $request->query('living');
        $for = $request->query('for');
        $furnishing = $request->query('furnishing');

        return [
            'living'     => is_string($living) && array_key_exists($living, PropertyPolicies::LIVING_ARRANGEMENTS) ? $living : null,
            'for'        => is_string($for) && array_key_exists($for, self::SUITABLE_FOR) ? $for : null,
            'furnishing' => is_string($furnishing) && in_array($furnishing, self::FURNISHING, true) ? $furnishing : null,
            'rules'      => array_values(array_intersect(array_keys(self::RULES), array_filter((array) $request->query('rules', []), 'is_string'))),
        ];
    }

    /** How many filters are on, amenities included — drives the Filters badge and the modal counter. */
    public static function activeCount(Request $request): int
    {
        $f = self::fromRequest($request);

        return count(array_filter((array) $request->query('amenities', [])))
            + (int) ($f['living'] !== null)
            + (int) ($f['for'] !== null)
            + (int) ($f['furnishing'] !== null)
            + count($f['rules'])
            + (int) ($request->filled('price_min') || $request->filled('price_max'));
    }
}
