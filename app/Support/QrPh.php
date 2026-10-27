<?php

namespace App\Support;

/**
 * Reads and checks a QR Ph code: the text inside the QR a landlord shares, e.g. a Maya or GCash
 * "Receive money" QR, or any InstaPay QR.
 *
 * The browser decodes the screenshot (jsQR) and sends only the text. Nothing about that text is trusted
 * until it passes here: QR Ph is EMVCo's merchant-presented format, a run of tag-length-value fields
 * that starts with `000201` and ends with a CRC-16 over everything before it. A QR that is really a
 * link to a website, or a random image, fails this and is never shown to a tenant. Because the page
 * redraws the QR from this checked text, what a tenant scans is always exactly what passed.
 */
class QrPh
{
    /**
     * The fields a tenant needs, or null if this is not a valid QR Ph code.
     *
     * @return array{account_name: string, city: ?string, network: ?string}|null
     */
    public static function parse(string $payload): ?array
    {
        $payload = trim($payload);

        if (strlen($payload) < 30 || strlen($payload) > 512 || ! str_starts_with($payload, '000201')) {
            return null;
        }

        if (! self::crcMatches($payload)) {
            return null;
        }

        $fields = self::fields($payload);

        if ($fields === null || ($fields['58'] ?? null) !== 'PH' || empty($fields['59'])) {
            return null;
        }

        // Tags 26 to 51 carry the account the money goes to. A code without one cannot be paid.
        $accounts = array_filter($fields, fn ($value, $tag) => (int) $tag >= 26 && (int) $tag <= 51, ARRAY_FILTER_USE_BOTH);

        if ($accounts === []) {
            return null;
        }

        $network = null;
        foreach ($accounts as $value) {
            $network = self::fields($value)['00'] ?? null;
            if ($network) {
                break;
            }
        }

        return [
            'account_name' => mb_substr(trim($fields['59']), 0, 100),
            'city' => isset($fields['60']) ? trim($fields['60']) : null,
            'network' => $network,
        ];
    }

    /**
     * Splits a TLV string into tag => value. Null when a length runs past the end, which is what a
     * truncated or hand-edited payload looks like.
     *
     * @return array<string, string>|null
     */
    private static function fields(string $data): ?array
    {
        $out = [];
        $i = 0;
        $len = strlen($data);

        while ($i < $len) {
            if ($i + 4 > $len) {
                return null;
            }

            $tag = substr($data, $i, 2);
            $size = substr($data, $i + 2, 2);

            if (! ctype_digit($tag) || ! ctype_digit($size) || $i + 4 + (int) $size > $len) {
                return null;
            }

            $out[$tag] = substr($data, $i + 4, (int) $size);
            $i += 4 + (int) $size;
        }

        return $out;
    }

    /** The last field must be `6304` + CRC-16/CCITT-FALSE of everything before the four check digits. */
    private static function crcMatches(string $payload): bool
    {
        if (substr($payload, -8, 4) !== '6304') {
            return false;
        }

        return strtoupper(substr($payload, -4)) === self::crc16(substr($payload, 0, -4));
    }

    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    /** Builds a valid payload. Tests and the demo seed use it; production never makes codes. */
    public static function make(string $accountName, string $account = '09171234567', string $city = 'Cebu City'): string
    {
        $tlv = fn (string $tag, string $value) => $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;

        $body = $tlv('00', '01')
            .$tlv('01', '11')
            .$tlv('27', $tlv('00', 'com.p2pqrpay').$tlv('01', 'PAPHPHM1XXX').$tlv('04', $account))
            .$tlv('52', '6016')
            .$tlv('53', '608')
            .$tlv('58', 'PH')
            .$tlv('59', $accountName)
            .$tlv('60', $city)
            .'6304';

        return $body.self::crc16($body);
    }
}
