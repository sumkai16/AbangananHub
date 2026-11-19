<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Passphrase encryption for backup archives, with libsodium (built into PHP).
 * Key: Argon2id from the passphrase and a random salt. Data: XChaCha20-Poly1305 secretstream in 1 MB chunks, so a
 * wrong passphrase, a flipped byte or a cut-off file is detected, and nothing large is held in memory.
 * File: "ABHBAK1\n" | salt | ops (4 bytes) | mem (4 bytes) | stream header | chunks.
 */
final class BackupCrypto
{
    private const MAGIC = "ABHBAK1\n";

    private const CHUNK = 1048576;

    public const MIN_PASSPHRASE = 16;

    public static function encrypt(string $source, string $dest, string $passphrase): void
    {
        self::assertPassphrase($passphrase);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
        $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
        $key = self::key($passphrase, $salt, $ops, $mem);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        sodium_memzero($key);

        $in = self::open($source, 'rb');
        $out = self::open($dest, 'wb');
        try {
            fwrite($out, self::MAGIC.$salt.pack('NN', $ops, $mem).$header);
            $cur = self::readFull($in, self::CHUNK);
            while (true) {
                $next = self::readFull($in, self::CHUNK);
                $last = $next === '';
                fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state, $cur, '',
                    $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
                ));
                if ($last) {
                    break;
                }
                $cur = $next;
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public static function decrypt(string $source, string $dest, string $passphrase): void
    {
        $in = self::open($source, 'rb');
        $tmp = $dest.'.partial';
        $out = self::open($tmp, 'wb');
        $done = false;
        try {
            $head = self::readFull($in, strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (! str_starts_with($head, self::MAGIC) || strlen($head) < 8 + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('This is not an AbangananHub backup file.');
            }
            $salt = substr($head, 8, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            ['o' => $ops, 'm' => $mem] = unpack('No/Nm', substr($head, 8 + SODIUM_CRYPTO_PWHASH_SALTBYTES, 8));
            $header = substr($head, 8 + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8);

            $key = self::key($passphrase, $salt, $ops, $mem);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            sodium_memzero($key);

            $size = self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            $cur = self::readFull($in, $size);
            if ($cur === '') {
                throw new RuntimeException('The backup file is empty or cut off.');
            }
            while (true) {
                $next = self::readFull($in, $size);
                $last = $next === '';
                $r = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cur);
                if ($r === false) {
                    throw new RuntimeException('Wrong passphrase, or the backup file is damaged.');
                }
                [$plain, $tag] = $r;
                $isFinal = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
                if ($last !== $isFinal) {
                    throw new RuntimeException('The backup file is cut off or has extra data after its end.');
                }
                fwrite($out, $plain);
                if ($last) {
                    break;
                }
                $cur = $next;
            }
            $done = true;
        } finally {
            fclose($in);
            fclose($out);
            if ($done) {
                rename($tmp, $dest);
            } else {
                @unlink($tmp);
            }
        }
    }

    private static function assertPassphrase(string $passphrase): void
    {
        if (strlen($passphrase) < self::MIN_PASSPHRASE) {
            throw new RuntimeException('BACKUP_PASSPHRASE must be at least '.self::MIN_PASSPHRASE.' characters.');
        }
    }

    private static function key(string $passphrase, string $salt, int $ops, int $mem): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $passphrase, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    /** @return resource */
    private static function open(string $path, string $mode)
    {
        $h = @fopen($path, $mode);
        if ($h === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        return $h;
    }

    /** fread can return less than asked before the end of file; a chunk must be exactly full or the last one. */
    private static function readFull($h, int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n && ! feof($h)) {
            $part = fread($h, $n - strlen($buf));
            if ($part === false || $part === '') {
                break;
            }
            $buf .= $part;
        }

        return $buf;
    }
}
