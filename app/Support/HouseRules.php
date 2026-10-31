<?php

namespace App\Support;

/**
 * Titled sections of house rules on a policy (Curfew, Visitors, ...). The property's own listed rules always come
 * first and are never replaced; a policy only adds sections after them.
 */
class HouseRules
{
    public const MAX_SECTIONS = 12;

    public const MAX_RULES = 30;

    /** Section titles the editor offers as one-click suggestions, each with a few plain starting rules. */
    public const SUGGESTED = [
        'General rules' => ['Respect other tenants and neighbors.', 'Keep the unit and common areas clean.', 'No illegal activities on the premises.'],
        'Curfew and quiet hours' => ['Keep noise down after 10 PM.', 'Gates and doors are locked at night.'],
        'Visitors' => ['Visitors are allowed until 9 PM only.', 'Overnight guests need the Landlord\'s approval.'],
        'Rent payments' => ['Rent is due on the date in the summary above.', 'Late payments may incur the late fee stated above.'],
        'Utilities' => ['Report leaks and electrical problems right away.', 'Use water and electricity responsibly.'],
        'Maintenance' => ['Report damage as soon as you notice it.', 'Tenants pay for damage they or their guests cause.'],
        'Cleanliness' => ['Dispose of trash properly.', 'Wash dishes and tidy shared spaces after use.'],
        'Parking' => ['Park only in assigned areas.', 'Do not block the entrance or other vehicles.'],
        'Safety' => ['No smoking indoors.', 'No open flames or hazardous items.'],
    ];

    /**
     * The stored shape from a form: blank rules dropped, sections with nothing in them dropped, blank titles named.
     *
     * A section can be hidden: kept in the editor but left off the lease (`hidden` is stored only when true).
     *
     * @param  array<int, array{title?: ?string, rules?: array<int, ?string>, hidden?: mixed}>  $sections
     * @return list<array{title:string,rules:list<string>,hidden?:true}>|null
     */
    public static function normalize(array $sections): ?array
    {
        $out = [];
        foreach (array_slice(array_values($sections), 0, self::MAX_SECTIONS) as $section) {
            $rules = array_slice(array_values(array_filter(array_map(
                fn ($r) => is_string($r) ? mb_substr(trim($r), 0, 200) : '',
                (array) ($section['rules'] ?? []),
            ))), 0, self::MAX_RULES);
            if ($rules) {
                $title = trim((string) ($section['title'] ?? ''));
                $entry = ['title' => $title !== '' ? mb_substr($title, 0, 80) : 'Rules', 'rules' => $rules];
                if (filter_var($section['hidden'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $entry['hidden'] = true;
                }
                $out[] = $entry;
            }
        }

        return $out ?: null;
    }

    /**
     * What a lease shows: the listing's rules first, then the policy's sections that are not hidden.
     *
     * @param  list<string>  $listed
     * @param  list<array{title:string,rules:list<string>}>|null  $sections
     * @return list<array{title:string,rules:list<string>}>
     */
    public static function forSnapshot(array $listed, ?array $sections): array
    {
        $listed = array_values(array_filter($listed));
        $out = $listed ? [['title' => 'Property rules', 'rules' => $listed]] : [];

        return [...$out, ...self::visible($sections)];
    }

    /**
     * The sections a lease prints (hidden ones left out), without the `hidden` flag.
     *
     * @return list<array{title:string,rules:list<string>}>
     */
    public static function visible(?array $sections): array
    {
        return collect($sections ?? [])->reject(fn ($s) => ! empty($s['hidden']))
            ->map(fn ($s) => ['title' => $s['title'], 'rules' => $s['rules']])->values()->all();
    }
}
