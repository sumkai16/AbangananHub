<?php

namespace Tests\Unit;

use App\Services\IdDocumentCheck;
use App\Services\OcrService;
use App\Support\VisionReading;
use Tests\TestCase;

/**
 * Does a front-of-ID photo look like an ID? Scored from what Vision read: text, ID-type words, common ID words, the
 * applicant's name, a face on the card and a card-like label. Only "fail" can block, and only twice.
 */
class IdDocumentCheckTest extends TestCase
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
Petsa ng Kapanganakan/Date of Birth
SEPTEMBER 06, 2004
Tirahan/Address
PUROK SAN VICENTE, TUNGHAAN, MINGLANILLA, CEBU
TXT;

    private function check(): IdDocumentCheck
    {
        return new IdDocumentCheck(new OcrService);
    }

    private function reading(string $text, int $faces = 0, array $labels = []): VisionReading
    {
        return new VisionReading($text, $faces, $labels, true);
    }

    private function front(VisionReading $reading, string $idType = 'PhilSys'): array
    {
        return $this->check()->assessFront($reading, $idType, 'Axcee', 'Cabusas');
    }

    public function test_a_clear_philsys_front_passes(): void
    {
        $result = $this->front($this->reading(self::PHILSYS, 1, ['identity document' => 0.9]));

        $this->assertSame('pass', $result['status']);
        $this->assertSame('match', $result['type_match']);
        $this->assertSame([
            'has_text' => true, 'type_keyword' => true, 'name' => true,
            'id_words' => true, 'face' => true, 'card_label' => true,
        ], $result['signals']);
    }

    public function test_a_blank_photo_fails(): void
    {
        $this->assertSame('fail', $this->front($this->reading(''))['status']);
    }

    public function test_a_selfie_without_text_fails(): void
    {
        $this->assertSame('fail', $this->front($this->reading('', 1, ['selfie' => 0.97]))['status']);
    }

    public function test_a_receipt_fails(): void
    {
        $text = "SM SUPERMARKET\nTOTAL 125.00\nCASH 200.00\nCHANGE 75.00\nTHANK YOU";

        $this->assertSame('fail', $this->front($this->reading($text, 0, ['receipt' => 0.9, 'font' => 0.8]))['status']);
    }

    public function test_a_receipt_that_carries_the_applicants_first_name_still_fails(): void
    {
        // A partial name match (one of the two names) is not enough on its own to look like an ID.
        $text = "ORDER FOR AXCEE\nTOTAL 560.00 PAID\nTHANK YOU FOR SHOPPING";

        $this->assertSame('fail', $this->front($this->reading($text, 0, ['receipt' => 0.9]))['status']);
    }

    public function test_a_faded_id_with_a_face_and_card_label_is_unsure_not_fail(): void
    {
        $text = 'REPUBLIKA PILIPINAS 681';

        $result = $this->front($this->reading($text, 1, ['identity document' => 0.8]));

        $this->assertSame('unsure', $result['status']);
    }

    public function test_text_needs_twenty_characters_not_counting_spaces_even_with_a_face_and_label(): void
    {
        $labels = ['identity document' => 0.9];

        $nineteen = $this->front($this->reading('ABCDEFGHI J KLMNOPQRS', 1, $labels)); // 19 letters + 1 space
        $twenty = $this->front($this->reading('ABCDEFGHIJ KLMNOPQRST', 1, $labels));   // 20 letters + 1 space

        $this->assertSame('fail', $nineteen['status']);
        $this->assertSame('unsure', $twenty['status']);
    }

    public function test_two_different_id_words_count_but_one_does_not(): void
    {
        $filler = 'LOREM IPSUM DOLOR SIT AMET CONSECTETUR ';

        $one = $this->front($this->reading($filler.'ADDRESS ADDRESS'));
        $two = $this->front($this->reading($filler.'ADDRESS date of birth'));
        $inside = $this->front($this->reading($filler.'ESSEX SUSSEX')); // "sex" inside another word is not the word

        $this->assertFalse($one['signals']['id_words']);
        $this->assertTrue($two['signals']['id_words']);
        $this->assertFalse($inside['signals']['id_words']);
    }

    public function test_only_id_like_labels_count_and_only_when_confident(): void
    {
        $filler = 'LOREM IPSUM DOLOR SIT AMET CONSECTETUR';
        $counts = fn (array $labels): bool => $this->front($this->reading($filler, 0, $labels))['signals']['card_label'];

        $this->assertTrue($counts(['identity document' => 0.6]));
        $this->assertTrue($counts(["driver's license" => 0.8]));
        $this->assertTrue($counts(['passport' => 0.9]));
        $this->assertTrue($counts(['id card' => 0.7]));
        $this->assertTrue($counts(['card' => 0.7]));
        $this->assertFalse($counts(['card' => 0.5]));
        $this->assertFalse($counts(['credit card' => 0.95]));
        $this->assertFalse($counts(['text' => 0.99, 'paper' => 0.9]));
    }

    public function test_the_wrong_id_type_is_reported_without_failing_a_real_id(): void
    {
        $license = "LAND TRANSPORTATION OFFICE\nDRIVER'S LICENSE\nCABUSAS, AXCEE FILISILDA\nDate of Birth 2004-09-06\nAddress PUROK SAN VICENTE";

        $result = $this->front($this->reading($license, 1, ["driver's license" => 0.9]), 'PhilSys');

        $this->assertSame('mismatch', $result['type_match']);
        $this->assertSame('pass', $result['status']);
    }

    public function test_an_unavailable_reading_is_unavailable(): void
    {
        $result = $this->front(VisionReading::unavailable());

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('unknown', $result['type_match']);
    }

    public function test_back_with_text_passes_and_blank_back_fails(): void
    {
        $check = $this->check();

        $this->assertSame('pass', $check->assessBack($this->reading("Conditions: 1 Wear eyeglasses\nRestrictions A B")));
        $this->assertSame('fail', $check->assessBack($this->reading('')));
        $this->assertSame('fail', $check->assessBack($this->reading('a . ,')));
        $this->assertSame('unavailable', $check->assessBack(VisionReading::unavailable()));
    }

    public function test_blocks_until_two_failures(): void
    {
        $check = $this->check();

        $this->assertTrue($check->blocks('fail', null, 0));
        $this->assertTrue($check->blocks('fail', null, 1));
        $this->assertFalse($check->blocks('fail', null, 2));
        $this->assertFalse($check->blocks('unsure', 'pass', 0));
        $this->assertTrue($check->blocks('pass', 'fail', 0));
        $this->assertFalse($check->blocks('unavailable', null, 0));
        $this->assertFalse($check->blocks('pass', 'unavailable', 0));
    }
}
