<?php

namespace App\Support;

use App\Models\Property;
use App\Models\PropertyUnit;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The editable body of a lease (plans/editable-lease-document.md).
 *
 * The body is sanitized HTML written by the landlord in the lease editor. Numbers and names never live in
 * it as typed text: the editor inserts merge fields (`<span data-field="monthly_rent">`) that are replaced
 * from the lease's own data when the agreement is sent, so the wording cannot disagree with what the ledger
 * and escrow charge. The locked key-terms block in leases/_document is rendered from the same data.
 */
class LeaseBody
{
    /** Merge fields the editor offers, key => label. */
    public const FIELDS = [
        'tenant_name'      => 'Tenant name',
        'landlord_name'    => 'Landlord name',
        'property'         => 'Property',
        'unit'             => 'Unit',
        'address'          => 'Address',
        'monthly_rent'     => 'Monthly rent',
        'due_day'          => 'Rent due day',
        'security_deposit' => 'Security deposit',
        'start_date'       => 'Start date',
        'end_date'         => 'End date',
        'notice_days'      => 'Notice days',
        'utilities'        => 'Utilities',
    ];

    /** Starting points for a new lease, key => label. Property types first, then the two generic ones. */
    public const STARTERS = [
        'standard'       => 'Standard',
        'Bedspace'       => 'Bedspace',
        'Boarding House' => 'Boarding house',
        'Dormitory'      => 'Dormitory',
        'Apartment'      => 'Apartment',
        'House'          => 'House for rent',
        'blank'          => 'Blank page',
    ];

    /** The clause each property type adds to the standard wording. */
    private const TYPE_CLAUSES = [
        'Bedspace' => ['Shared room', 'The Tenant rents one bed space in a shared room, not the whole room. The Tenant uses only the assigned bed, locker and storage, keeps personal belongings within that space, respects roommates\' sleep and privacy, and does not let another person sleep in the bed. The Landlord may assign other tenants to the room\'s other beds.'],
        'Boarding House' => ['Shared facilities', 'The kitchen, bathrooms and common areas are shared with other boarders. The Tenant cleans up after each use, labels food kept in shared storage, and keeps noise down in shared spaces. The Landlord may enter common areas at any time.'],
        'Dormitory' => ['Dormitory living', 'Roommates are assigned by the Landlord. The Tenant follows the dormitory\'s curfew and visitor hours, signs in guests with a valid ID, and keeps the room ready for routine inspection with reasonable notice.'],
        'Apartment' => ['Use of the unit', 'The Tenant has exclusive use of the unit. The Tenant does not paint, drill or make other alterations without the Landlord\'s written consent, follows the building\'s rules for parking and common areas, and pays to replace lost keys or access cards.'],
        'House' => ['House and grounds', 'The Tenant keeps the yard and garden tidy and handles minor upkeep such as light bulbs and clearing drains. The Tenant makes no structural changes and keeps pets only with the Landlord\'s written consent.'],
    ];

    private const MAX_LENGTH = 30000;

    /** Strip everything but the few tags the editor can produce. Safe to call on any input, any number of times. */
    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.DefinitionID', 'lease-body');
        $config->set('HTML.DefinitionRev', 1);
        $config->set('HTML.Allowed', 'h2,h3,p,strong,em,u,ul,ol,li,br,span[data-field]');
        $config->set('Core.EscapeInvalidTags', false);
        $config->set('AutoFormat.RemoveEmpty', true);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addAttribute('span', 'data-field', 'Text');
        }

        // Unknown merge field keys are dropped on the way in, so a hand-edited request cannot smuggle one through.
        $clean = (new HTMLPurifier($config))->purify(Str::limit($html, self::MAX_LENGTH, ''));

        return trim((string) preg_replace_callback(
            '#<span data-field="([^"]*)">(.*?)</span>#s',
            fn ($m) => array_key_exists($m[1], self::FIELDS) ? $m[0] : '',
            $clean,
        ));
    }

    /** What each merge field says, from a LeaseTerms snapshot array. */
    public static function values(array $terms): array
    {
        $peso = fn ($n) => '₱'.number_format((float) $n, 2);
        $date = fn (?string $d) => $d ? Carbon::parse($d)->format('F j, Y') : null;
        $day = (int) ($terms['due_day'] ?? 1);
        $ordinal = $day.(in_array($day % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$day % 10] ?? 'th'));
        $deposit = $terms['deposit'] ?? null;

        return [
            'tenant_name'      => $terms['tenant']['name'] ?: 'the Tenant',
            'landlord_name'    => $terms['landlord']['name'] ?: 'the Landlord',
            'property'         => $terms['property']['title'] ?? '',
            'unit'             => $terms['unit'] ?? '',
            'address'          => $terms['property']['address'] ?? '',
            'monthly_rent'     => $peso($terms['monthly_rent'] ?? 0),
            'due_day'          => $ordinal,
            'security_deposit' => $deposit !== null && $deposit > 0 ? $peso($deposit) : 'none',
            'start_date'       => $date($terms['start'] ?? null) ?? "the Tenant's move-in date",
            'end_date'         => ($terms['end'] ?? null) ? $date($terms['end']) : 'not fixed (month to month)',
            'notice_days'      => (string) ($terms['notice_days'] ?? ''),
            'utilities'        => self::utilitiesSentence($terms['utilities'] ?? []),
        ];
    }

    private static function utilitiesSentence(array $u): string
    {
        $parts = array_filter([
            ! empty($u['included']) ? 'Included in the rent: '.implode(', ', $u['included']).'.' : null,
            ! empty($u['not_included']) ? 'Not included, paid by the Tenant: '.implode(', ', $u['not_included']).'.' : null,
            ! empty($u['separately_metered']) ? 'Utilities are separately metered, and the Tenant pays for their own consumption.' : null,
        ]);

        return $parts ? implode(' ', $parts) : 'Utility charges are as agreed between the parties.';
    }

    /** Replace merge fields with this lease's own values. Run once, when the agreement is sent. */
    public static function resolve(string $html, array $terms): string
    {
        $values = self::values($terms);

        return preg_replace_callback(
            '#<span data-field="([^"]*)">.*?</span>#s',
            fn ($m) => e($values[$m[1]] ?? ''),
            self::clean($html),
        );
    }

    /** The wording the editor opens on for this unit: its lease template's text, else the starter. */
    public static function templateFor(?Property $property, ?PropertyUnit $unit = null): string
    {
        $body = LeaseTemplates::for($property, $unit)?->body;

        return filled($body) ? $body : self::starter();
    }

    /**
     * The standard clauses as editable text. Not tied to a property: everything that differs between properties
     * is a merge field, and house rules are listed by the lease itself (leases/_document), not written here.
     */
    public static function starter(?string $type = null): string
    {
        if ($type === 'blank') {
            return '<h3>Terms</h3><p>Write your lease here. Use "Insert field" for names, amounts and dates.</p>';
        }

        $f = fn (string $key) => '<span data-field="'.$key.'">'.self::FIELDS[$key].'</span>';
        $typeClause = isset(self::TYPE_CLAUSES[$type])
            ? '<h3>'.e(self::TYPE_CLAUSES[$type][0]).'</h3><p>'.e(self::TYPE_CLAUSES[$type][1]).'</p>'
            : '';

        return implode('', [
            '<h3>Premises and use</h3><p>The Landlord rents to the Tenant the unit '.$f('unit').' at '.$f('property').', '.$f('address').'. The premises are for residential use only. The Tenant may not sublet or transfer the premises without the Landlord\'s written consent.</p>',
            '<h3>Term</h3><p>This lease starts on '.$f('start_date').'. Its end date is '.$f('end_date').'.</p>',
            '<h3>Rent</h3><p>The Tenant pays '.$f('monthly_rent').' per month, due on the '.$f('due_day').' day of each month. Payments made through AbangananHub or recorded there by the Landlord form the rent ledger for this tenancy.</p>',
            '<h3>Security deposit</h3><p>The Tenant pays a security deposit of '.$f('security_deposit').'. It covers unpaid rent and damage beyond normal wear and tear. The Landlord returns it within 30 days after the Tenant moves out, less any deductions, which the Landlord lists in writing.</p>',
            '<h3>Utilities</h3><p>'.$f('utilities').'</p>',
            $typeClause,
            '<h3>Care and repairs</h3><p>The Tenant keeps the premises clean and in good condition and reports any damage or needed repair promptly. The Landlord handles repairs from normal wear and tear and to the building\'s structure, plumbing and wiring. The Tenant pays for damage caused by the Tenant, their household or guests. The Landlord gives reasonable notice before entering the premises, except in an emergency.</p>',
            '<h3>Ending the tenancy</h3><p>Either party may end this lease by giving at least '.$f('notice_days').' days\' written notice, including by message on AbangananHub. On moving out, the Tenant returns the premises and all keys in the condition received, apart from normal wear and tear.</p>',
            '<h3>Whole agreement</h3><p>This document is the whole agreement between the parties. Changes are valid only if made in writing and accepted by both.</p>',
        ]);
    }
}
