<?php

namespace Tests\Unit;

use App\Services\IdTypeDetector;
use App\Support\IdTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Which ID is in the photo, from the text Vision read. The fixtures in tests/fixtures/id-text are made-up text in the
 * layout of each real card (fake names and numbers): the real samples stay local and never enter the repo.
 */
class IdTypeDetectorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(base_path("tests/fixtures/id-text/{$name}.txt"));
    }

    private function detector(): IdTypeDetector
    {
        return new IdTypeDetector;
    }

    /** @return array<string, array{string, string}> */
    public static function fronts(): array
    {
        return [
            'PhilSys' => ['philsys-front', IdTemplates::PHILSYS],
            "Driver's License" => ['drivers-license-front', IdTemplates::DRIVERS_LICENSE],
            'Passport' => ['passport-front', IdTemplates::PASSPORT],
            'PRC' => ['prc-front', IdTemplates::PRC],
            'UMID' => ['umid-front', IdTemplates::UMID],
            'Postal ID' => ['postal-front', IdTemplates::POSTAL],
        ];
    }

    #[DataProvider('fronts')]
    public function test_each_front_is_detected_as_its_own_type(string $fixture, string $type): void
    {
        $text = $this->fixture($fixture);

        $this->assertSame($type, $this->detector()->detectFront($text));
        $this->assertSame(['status' => 'match', 'detected' => $type], $this->detector()->matchFront($text, $type));
    }

    #[DataProvider('fronts')]
    public function test_a_front_picked_as_the_wrong_type_is_a_mismatch_naming_the_real_type(string $fixture, string $type): void
    {
        $wrong = $type === IdTemplates::PASSPORT ? IdTemplates::PHILSYS : IdTemplates::PASSPORT;

        $this->assertSame(
            ['status' => 'mismatch', 'detected' => $type],
            $this->detector()->matchFront($this->fixture($fixture), $wrong),
        );
    }

    public function test_the_old_passport_keyword_bug_is_gone(): void
    {
        // "Republic of the Philippines" is on a UMID, a license and a Postal ID: it made all of them "match" Passport.
        $umid = $this->detector()->matchFront($this->fixture('umid-front'), IdTemplates::PASSPORT);
        $license = $this->detector()->matchFront($this->fixture('drivers-license-front'), IdTemplates::PASSPORT);
        $postal = $this->detector()->matchFront($this->fixture('postal-front'), IdTemplates::PASSPORT);

        $this->assertSame('mismatch', $umid['status']);
        $this->assertSame('mismatch', $license['status']);
        $this->assertSame('mismatch', $postal['status']);
    }

    public function test_the_old_license_keyword_bug_is_gone(): void
    {
        // "Professional" is on every PRC card: it made a PRC card "match" Driver's License.
        $result = $this->detector()->matchFront($this->fixture('prc-front'), IdTemplates::DRIVERS_LICENSE);

        $this->assertSame('mismatch', $result['status']);
        $this->assertSame(IdTemplates::PRC, $result['detected']);
    }

    public function test_a_receipt_and_blank_text_are_unknown_not_a_guess(): void
    {
        foreach ([$this->fixture('receipt'), '', "  \n "] as $text) {
            $this->assertNull($this->detector()->detectFront($text));
            $this->assertSame(['status' => 'unknown', 'detected' => null], $this->detector()->matchFront($text, IdTemplates::PHILSYS));
        }
    }

    public function test_one_short_word_of_the_picked_type_is_a_match_when_nothing_else_leads(): void
    {
        // A worn card where only "LTO" survived: not enough to name the type, enough for the type that was picked.
        $result = $this->detector()->matchFront("LTO\nNON-PROFESSIONAL", IdTemplates::DRIVERS_LICENSE);

        $this->assertSame(['status' => 'match', 'detected' => null], $result);
    }

    public function test_a_type_word_inside_another_word_does_not_count(): void
    {
        // "humid" holds "umid" and "Walton" holds "lto".
        $text = 'A humid day in Walton, high humidity all week';

        $this->assertSame('unknown', $this->detector()->matchFront($text, IdTemplates::UMID)['status']);
        $this->assertSame('unknown', $this->detector()->matchFront($text, IdTemplates::DRIVERS_LICENSE)['status']);
    }

    public function test_curly_apostrophes_and_a_phrase_split_across_lines_still_match(): void
    {
        $text = "DRIVER’S LICENSE\nLAND\nTRANSPORTATION OFFICE";

        $this->assertSame(IdTemplates::DRIVERS_LICENSE, $this->detector()->detectFront($text));
    }

    public function test_sss_is_checked_when_picked_but_never_suggested(): void
    {
        $text = "SOCIAL SECURITY SYSTEM\nSS No. 12-3456789-0\nSSS ID";

        $this->assertNull($this->detector()->detectFront($text));
        $this->assertSame('match', $this->detector()->matchFront($text, IdTemplates::SSS)['status']);
        $this->assertSame('unknown', $this->detector()->matchFront('hello world', IdTemplates::SSS)['status']);
    }

    public function test_backs_are_detected_from_their_own_phrases(): void
    {
        $this->assertSame(IdTemplates::DRIVERS_LICENSE, $this->detector()->detectBack($this->fixture('drivers-license-back')));
        $this->assertSame(IdTemplates::POSTAL, $this->detector()->detectBack($this->fixture('postal-back')));
        $this->assertNull($this->detector()->detectBack($this->fixture('receipt')));
        $this->assertNull($this->detector()->detectBack(''));
    }

    public function test_every_template_type_is_listed_once_and_only_sss_is_undetectable(): void
    {
        $this->assertSame(
            ['PhilSys', "Driver's License", 'Passport', 'Professional ID Card', 'UMID', 'Postal ID', 'SSS ID'],
            IdTemplates::names(),
        );
        $this->assertSame(array_values(array_diff(IdTemplates::names(), ['SSS ID'])), IdTemplates::detectable());
    }

    // ── ID numbers ────────────────────────────────────────────────────────────

    public function test_the_id_number_is_read_in_each_types_format(): void
    {
        $d = $this->detector();

        $this->assertSame('1234-5678-9101-1213', $d->idNumber($this->fixture('philsys-front'), IdTemplates::PHILSYS));
        $this->assertSame('A12-34-567890', $d->idNumber($this->fixture('drivers-license-front'), IdTemplates::DRIVERS_LICENSE));
        $this->assertSame('0028-1215160-9', $d->idNumber($this->fixture('umid-front'), IdTemplates::UMID));
        $this->assertSame('0012345', $d->idNumber($this->fixture('prc-front'), IdTemplates::PRC));
        $this->assertSame('1234 5678 9012', $d->idNumber($this->fixture('postal-front'), IdTemplates::POSTAL));
    }

    public function test_a_license_number_followed_by_a_date_on_the_same_line_is_still_found(): void
    {
        // Seen on a real card: "G33-24-002015 2029/04/23".
        $this->assertSame('G33-24-002015', $this->detector()->idNumber("G33-24-002015 2029/04/23\nLicense No.", IdTemplates::DRIVERS_LICENSE));
    }

    public function test_a_longer_number_is_not_read_as_a_shorter_types_number(): void
    {
        // The 16-digit PhilSys number holds a 12-digit run: it must not be read as a Postal ID number.
        $this->assertNull($this->detector()->idNumber('1234-5678-9101-1213', IdTemplates::POSTAL));
    }

    public function test_the_passport_number_comes_from_the_machine_readable_lines_when_their_check_digit_is_right(): void
    {
        // The printed number is misread (a 7 as a 1) but the MRZ line is clean and its check digit (1) is right.
        $text = "Passport no P123456IA\nP<PHLDELA<CRUZ<<JUANA<<<<<<<<<<<<<<<<<<<<<<\nP1234567A1PHL9001011F3001011<<<<<<<<<<<<<<02";

        $this->assertSame('P1234567A', $this->detector()->idNumber($text, IdTemplates::PASSPORT));
    }

    public function test_a_wrong_check_digit_falls_back_to_the_printed_number(): void
    {
        $text = "Passport no P7654321B\nP<PHLDELA<CRUZ<<JUANA<<<<<<<<<<<<<<<<<<<<<<\nP1234567A5PHL9001011F3001011<<<<<<<<<<<<<<02";

        $this->assertSame('P7654321B', $this->detector()->idNumber($text, IdTemplates::PASSPORT));
    }

    public function test_no_number_text_or_an_unknown_type_gives_null(): void
    {
        $this->assertNull($this->detector()->idNumber('', IdTemplates::PHILSYS));
        $this->assertNull($this->detector()->idNumber('nothing here', IdTemplates::PHILSYS));
        $this->assertNull($this->detector()->idNumber('1234-5678-9101-1213', 'Library Card'));
    }
}
