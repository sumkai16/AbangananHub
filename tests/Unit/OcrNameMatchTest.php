<?php

namespace Tests\Unit;

use App\Services\OcrService;
use Tests\TestCase;

/**
 * Name matching on text read from a Philippine ID. The sample is the PhilSys card layout: labels, then the value
 * on the next line, with the last name, given names and middle name on separate lines.
 */
class OcrNameMatchTest extends TestCase
{
    private const PHILSYS = <<<'TXT'
REPUBLIKA NG PILIPINAS
Republic of the Philippines
PAMBANSANG PAGKAKAKILANLAN
Philippine Identification Card
6813-2017-2150-9536
Apelyido/Last Name
CABUSAS
Mga Pangalan/Given Names
AXCEE
Gitnang Apelyido/Middle Name
FILISILDA
Petsa ng Kapanganakan/Date of Birth
SEPTEMBER 06, 2004
Tirahan/Address
PUROK SAN VICENTE, TUNGHAAN, MINGLANILLA, CEBU
TXT;

    public function test_exact_names_pass(): void
    {
        $r = (new OcrService)->matchName(self::PHILSYS, 'Axcee', 'Cabusas');

        $this->assertSame('pass', $r['status']);
    }

    public function test_the_middle_name_used_as_a_surname_still_passes(): void
    {
        // Many applicants sign up with their mother's maiden name as the last name; it is on the card too.
        $r = (new OcrService)->matchName(self::PHILSYS, 'Axcee', 'Filisilda');

        $this->assertSame('pass', $r['status']);
    }

    public function test_a_letter_or_two_misread_by_the_ocr_still_passes(): void
    {
        $noisy = str_replace(['CABUSAS', 'AXCEE'], ['CABUSA5', 'AXCFE'], self::PHILSYS);

        $r = (new OcrService)->matchName($noisy, 'Axcee', 'Cabusas');

        $this->assertSame('pass', $r['status']);
        $this->assertGreaterThanOrEqual(80, $r['confidence']);
    }

    public function test_only_one_name_found_is_partial(): void
    {
        $r = (new OcrService)->matchName(self::PHILSYS, 'Axcee', 'Santos');

        $this->assertSame('partial', $r['status']);
    }

    public function test_a_different_person_does_not_pass(): void
    {
        $r = (new OcrService)->matchName(self::PHILSYS, 'Maria', 'Santos');

        $this->assertNotSame('pass', $r['status']);
    }

    public function test_short_names_are_not_matched_loosely(): void
    {
        // "Li" must not match "Lu": words of three letters or fewer have to be exact.
        $r = (new OcrService)->matchName("LU\nOR\nSOMETHING ELSE", 'Li', 'Ng');

        $this->assertNotSame('pass', $r['status']);
    }

    public function test_no_text_is_unreadable_not_a_failed_name(): void
    {
        $r = (new OcrService)->matchName('', 'Axcee', 'Cabusas');

        $this->assertSame('unreadable', $r['status']);
        $this->assertSame(0, $r['confidence']);
    }

    public function test_the_philsys_number_is_found(): void
    {
        $this->assertSame('6813-2017-2150-9536', (new OcrService)->extractIdNumber(self::PHILSYS, 'PhilSys'));
    }

    public function test_the_philsys_card_is_recognised_as_that_type(): void
    {
        $this->assertSame('match', (new OcrService)->matchIdType(self::PHILSYS, 'PhilSys')['status']);
    }
}
