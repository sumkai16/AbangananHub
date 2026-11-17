<?php

namespace Tests\Unit;

use App\Services\Backup\BackupCrypto;
use App\Services\Backup\RetentionPolicy;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\TestCase;

/** The two pieces of the nightly backup that must never be wrong: the encryption and which copies get deleted. */
class BackupTest extends TestCase
{
    private const PASS = 'a-long-enough-passphrase';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/backup-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function roundTrip(string $plain, string $pass = self::PASS, string $openWith = self::PASS): string
    {
        file_put_contents("{$this->dir}/in", $plain);
        BackupCrypto::encrypt("{$this->dir}/in", "{$this->dir}/enc", $pass);
        BackupCrypto::decrypt("{$this->dir}/enc", "{$this->dir}/out", $openWith);

        return file_get_contents("{$this->dir}/out");
    }

    public function test_round_trip_small_empty_and_multi_chunk(): void
    {
        $this->assertSame('hello', $this->roundTrip('hello'));
        $this->assertSame('', $this->roundTrip(''));
        $big = random_bytes(2 * 1048576 + 12345);          // 3 chunks, the last one partial
        $this->assertSame($big, $this->roundTrip($big));
        $exact = random_bytes(1048576);                     // exactly one full chunk
        $this->assertSame($exact, $this->roundTrip($exact));
    }

    public function test_ciphertext_does_not_contain_the_plaintext(): void
    {
        file_put_contents("{$this->dir}/in", 'PHILSYS 6813-2017-2150-9536');
        BackupCrypto::encrypt("{$this->dir}/in", "{$this->dir}/enc", self::PASS);
        $this->assertStringNotContainsString('PHILSYS', file_get_contents("{$this->dir}/enc"));
    }

    public function test_wrong_passphrase_fails_and_leaves_no_output(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            $this->roundTrip('secret', self::PASS, 'a-different-passphrase');
        } finally {
            $this->assertFileDoesNotExist("{$this->dir}/out");
            $this->assertFileDoesNotExist("{$this->dir}/out.partial");
        }
    }

    public function test_flipped_byte_and_cut_off_file_are_detected(): void
    {
        file_put_contents("{$this->dir}/in", random_bytes(2500000));
        BackupCrypto::encrypt("{$this->dir}/in", "{$this->dir}/enc", self::PASS);
        $enc = file_get_contents("{$this->dir}/enc");

        $flipped = $enc;
        $flipped[1500000] = $flipped[1500000] ^ "\x01";
        file_put_contents("{$this->dir}/bad", $flipped);
        $this->assertFailsToOpen(fn () => BackupCrypto::decrypt("{$this->dir}/bad", "{$this->dir}/o1", self::PASS));

        // cut exactly at a chunk boundary: every remaining chunk is valid, only the missing final tag gives it away
        $boundary = 8 + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
            + (1048576 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
        file_put_contents("{$this->dir}/cut", substr($enc, 0, $boundary));
        $this->assertFailsToOpen(fn () => BackupCrypto::decrypt("{$this->dir}/cut", "{$this->dir}/o2", self::PASS));
        $this->assertFileDoesNotExist("{$this->dir}/o2");
    }

    public function test_short_passphrase_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->roundTrip('x', 'short');
    }

    private function assertFailsToOpen(callable $fn): void
    {
        try {
            $fn();
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Expected a RuntimeException.');
    }

    // ── retention ─────────────────────────────────────────────

    private function names(string ...$dates): array
    {
        return array_map(fn ($d) => "abangananhub-{$d}-0230.tar.gz.enc", $dates);
    }

    private function expired(array $names, string $now, int $days = 14, int $weeks = 8, int $months = 6): array
    {
        return RetentionPolicy::expired($names, CarbonImmutable::parse($now, 'Asia/Manila'), $days, $weeks, $months, 'abangananhub', 'Asia/Manila');
    }

    public function test_everything_inside_the_daily_window_is_kept(): void
    {
        $names = $this->names('2026-11-16', '2026-11-15', '2026-11-10', '2026-11-03');
        $this->assertSame([], $this->expired($names, '2026-11-16 08:00'));
    }

    public function test_old_copies_thin_out_to_one_per_week_then_one_per_month(): void
    {
        $names = $this->names(
            '2026-11-16',                                        // today
            '2026-10-26', '2026-10-27', '2026-10-28',            // same old week: only the first survives
            '2026-08-03', '2026-08-04',                          // inside 6 months: first of August survives via the month rule
            '2026-02-02',                                        // older than 8 weeks and 6 months: goes
        );
        $drop = $this->expired($names, '2026-11-16 08:00');
        sort($drop);
        $this->assertSame($this->names('2026-02-02', '2026-08-04', '2026-10-27', '2026-10-28'), $drop);
    }

    public function test_the_newest_copy_and_foreign_files_are_never_deleted(): void
    {
        $names = array_merge($this->names('2020-01-01'), ['notes.txt', 'abangananhub-latest.tar.gz.enc']);
        $this->assertSame([], $this->expired($names, '2026-11-16 08:00'));     // the 2020 copy is the newest we have
        $names[] = $this->names('2026-11-16')[0];
        $this->assertSame($this->names('2020-01-01'), $this->expired($names, '2026-11-16 08:00'));
    }
}
