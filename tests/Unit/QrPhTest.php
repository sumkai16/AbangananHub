<?php

namespace Tests\Unit;

use App\Support\QrPh;
use PHPUnit\Framework\TestCase;

class QrPhTest extends TestCase
{
    public function test_it_reads_a_valid_instapay_code(): void
    {
        $parsed = QrPh::parse(QrPh::make('AXCEE CABUSAS'));

        $this->assertSame('AXCEE CABUSAS', $parsed['account_name']);
        $this->assertSame('Cebu City', $parsed['city']);
        $this->assertSame('com.p2pqrpay', $parsed['network']);
    }

    public function test_a_wrong_checksum_is_refused(): void
    {
        $payload = QrPh::make('AXCEE CABUSAS');
        $tampered = substr($payload, 0, -4).($this->lastFour($payload) === '0000' ? '0001' : '0000');

        $this->assertNull(QrPh::parse($tampered));
    }

    public function test_a_changed_account_number_breaks_the_checksum(): void
    {
        $payload = str_replace('09171234567', '09179999999', QrPh::make('AXCEE CABUSAS'));

        $this->assertNull(QrPh::parse($payload));
    }

    public function test_a_website_link_is_refused(): void
    {
        $this->assertNull(QrPh::parse('https://example.com/pay-me-here-now-please-quickly'));
    }

    public function test_plain_text_and_garbage_are_refused(): void
    {
        $this->assertNull(QrPh::parse(''));
        $this->assertNull(QrPh::parse('hello'));
        $this->assertNull(QrPh::parse(str_repeat('0', 600)));
    }

    public function test_a_code_with_no_account_block_is_refused(): void
    {
        $tlv = fn (string $tag, string $value) => $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        $body = $tlv('00', '01').$tlv('01', '11').$tlv('52', '6016').$tlv('53', '608').$tlv('58', 'PH').$tlv('59', 'NO ACCOUNT').$tlv('60', 'Cebu').'6304';
        $payload = $body.$this->crc($body);

        $this->assertNull(QrPh::parse($payload));
    }

    public function test_a_code_for_another_country_is_refused(): void
    {
        $payload = QrPh::make('AXCEE CABUSAS');
        $other = str_replace('5802PH', '5802SG', substr($payload, 0, -4));

        $this->assertNull(QrPh::parse($other.$this->crc($other)));
    }

    private function lastFour(string $s): string
    {
        return substr($s, -4);
    }

    private function crc(string $data): string
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $ch) {
            $crc ^= ord($ch) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
