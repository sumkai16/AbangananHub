<?php

namespace App\Support;

/**
 * The documents a lease can ask a tenant to upload before signing (plans/required-tenant-documents.md).
 * A lease template stores the choices; the sent lease freezes them in `lease_snapshot.required_documents`.
 */
class RequiredDocuments
{
    /** key => label. `other_N` keys carry the landlord's own label. */
    public const TYPES = [
        'valid_id' => 'Valid government ID (photo of the card)',
        'barangay_clearance' => 'Barangay clearance',
        'police_clearance' => 'Police or NBI clearance',
        'proof_of_income' => 'Proof of income or employment',
        'school_enrollment' => 'School ID or enrollment proof',
    ];

    public const MAX_CUSTOM = 5;

    /** Short labels for the template editor's toggles. */
    public static function options(): array
    {
        return self::TYPES;
    }

    /**
     * The stored shape from a form: standard keys ticked, plus custom labels. Unknown keys and blank labels are dropped.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $custom
     * @return list<array{key:string,label?:string}>|null
     */
    public static function normalize(array $keys, array $custom): ?array
    {
        $list = [];
        foreach (array_keys(self::TYPES) as $key) {
            if (in_array($key, $keys, true)) {
                $list[] = ['key' => $key];
            }
        }
        foreach (array_slice(array_values(array_filter(array_map(fn ($l) => is_string($l) ? trim($l) : '', $custom))), 0, self::MAX_CUSTOM) as $label) {
            $list[] = ['key' => 'other', 'label' => mb_substr($label, 0, 100)];
        }

        return $list ?: null;
    }

    /**
     * What gets frozen into a lease: one {key,label} per requirement, with numbered keys for custom ones so each
     * is its own row (`other_1`, `other_2`).
     *
     * @param  list<array{key:string,label?:string}>|null  $stored
     * @return list<array{key:string,label:string}>
     */
    public static function forSnapshot(?array $stored): array
    {
        $out = [];
        $n = 0;
        foreach ($stored ?? [] as $item) {
            $key = $item['key'] ?? '';
            if ($key === 'other' && filled($item['label'] ?? null)) {
                $out[] = ['key' => 'other_'.(++$n), 'label' => $item['label']];
            } elseif (isset(self::TYPES[$key])) {
                $out[] = ['key' => $key, 'label' => self::TYPES[$key]];
            }
        }

        return $out;
    }
}
