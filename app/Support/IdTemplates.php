<?php

namespace App\Support;

/**
 * What each accepted government ID looks like in the text Google Vision reads off it: the phrases printed only on that
 * ID (anchors), its field labels, its ID number format and the phrases on its back. App\Services\IdTypeDetector scores
 * a photo against every template to name the ID it shows.
 *
 * Words printed on most Philippine IDs ("Republic of the Philippines", "Date of Birth", "Address", "Apelyido") are on
 * no template: they would make every ID look like every other. They belong to IdDocumentCheck, which only asks "is
 * this an ID at all".
 *
 * Built from real cards and official specimens (local, gitignored: storage/app/id-samples, checked with
 * `php artisan id-samples:check`). A type without samples yet is not `detectable`: it still checks an applicant who
 * picked it, but is never suggested to one who picked something else.
 */
final class IdTemplates
{
    public const PHILSYS = 'PhilSys';
    public const PRC = 'Professional ID Card';
    public const DRIVERS_LICENSE = "Driver's License";
    public const PASSPORT = 'Passport';
    public const UMID = 'UMID';
    public const POSTAL = 'Postal ID';
    public const SSS = 'SSS ID';

    /**
     * Phrases are lowercase and matched as whole words, any whitespace between words. Number patterns have no
     * delimiters; IdTypeDetector stops them from matching inside a longer number. A weak number (a bare run of
     * digits) counts less than one with its own shape.
     *
     * @var array<string, array{anchors: list<string>, labels: list<string>, number: string, number_strong: bool, back: list<string>, detectable: bool}>
     */
    private const TEMPLATES = [
        self::PHILSYS => [
            'anchors' => ['pambansang pagkakakilanlan', 'philippine identification card', 'philippine identification system', 'philsys', 'philid', 'ephilid'],
            'labels' => ['mga pangalan', 'gitnang apelyido', 'tirahan'],
            'number' => '\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}',
            'number_strong' => true,
            'back' => ['psa.gov.ph', 'psa office', 'uri ng dugo', 'kalagayang sibil', 'araw ng pagkakaloob'],
            'detectable' => true,
        ],
        self::DRIVERS_LICENSE => [
            'anchors' => ['land transportation office', "driver's license", 'drivers license', 'department of transportation', 'lto'],
            'labels' => ['license no', 'agency code', 'dl codes', 'eyes color', 'restrictions'],
            'number' => '[A-Z]\d{2}[- ]?\d{2}[- ]?\d{6,7}',
            'number_strong' => true,
            'back' => ['dl codes', 'organ donation', 'in case of emergency', 'serial number', 'articulated'],
            'detectable' => true,
        ],
        self::PASSPORT => [
            'anchors' => ['pasaporte', 'passport', 'department of foreign affairs', 'dfa'],
            'labels' => ['kodigo ng bansa', 'country code', 'issuing authority', 'nasyonalidad', 'pagkawalang bisa'],
            'number' => '[A-Z]\d{7}[A-Z]|[A-Z]{2}\d{7}',
            'number_strong' => true,
            'back' => [],
            'detectable' => true,
        ],
        self::PRC => [
            'anchors' => ['professional regulation commission', 'professional identification card'],
            'labels' => ['registration no', 'registration date'],
            'number' => '\d{7}',
            'number_strong' => false,
            'back' => ['prc.gov.ph', 'certification', 'duly registered professional'],
            'detectable' => true,
        ],
        self::UMID => [
            'anchors' => ['unified multi-purpose', 'umid'],
            'labels' => ['crn'],
            'number' => '\d{4}[- ]?\d{7}[- ]?\d',
            'number_strong' => true,
            'back' => [],
            'detectable' => true,
        ],
        self::POSTAL => [
            'anchors' => ['postal identity card', 'philippine postal corporation', 'phlpost'],
            'labels' => ['prn', 'issuing post office'],
            'number' => '\d{4}[- ]?\d{4}[- ]?\d{4}',
            'number_strong' => false,
            'back' => ['phlpost.gov.ph', 'phlpost', 'nearest post office', 'proof of identity of the cardholder'],
            'detectable' => true,
        ],
        // No SSS samples yet: checks an applicant who picked it, never suggested.
        self::SSS => [
            'anchors' => ['social security system', 'sss'],
            'labels' => [],
            'number' => '\d{2}[- ]?\d{7}[- ]?\d',
            'number_strong' => true,
            'back' => [],
            'detectable' => false,
        ],
    ];

    /** @return list<string> every accepted ID type, in the order the wizard lists them */
    public static function names(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /** @return array{anchors: list<string>, labels: list<string>, number: string, number_strong: bool, back: list<string>, detectable: bool}|null */
    public static function get(string $type): ?array
    {
        return self::TEMPLATES[$type] ?? null;
    }

    /** @return list<string> the types detection may name */
    public static function detectable(): array
    {
        return array_keys(array_filter(self::TEMPLATES, fn (array $t): bool => $t['detectable']));
    }
}
