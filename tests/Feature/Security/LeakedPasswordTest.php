<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class LeakedPasswordTest extends TestCase
{
    private const LEAKED = 'Password123!';

    /** Fake the range API so the response lists (or omits) the suffix of $password's SHA-1. */
    private function fakeRange(string $password, bool $leaked): void
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        $body = $leaked
            ? "0018A45C4D1DEF81644B54AB7F969B88D65:3\r\n{$suffix}:5000"
            : "0018A45C4D1DEF81644B54AB7F969B88D65:3\r\n003D68EB55068C33ACE09247EE4C639306B:3";

        Http::fake(["api.pwnedpasswords.com/range/{$prefix}" => Http::response($body, 200)]);
    }

    private function passes(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [Password::min(8)->uncompromised()]],
        )->passes();
    }

    public function test_breached_password_is_rejected(): void
    {
        $this->fakeRange(self::LEAKED, leaked: true);

        $this->assertFalse($this->passes(self::LEAKED));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pwnedpasswords.com/range/')
            && ! str_contains($request->url(), substr(strtoupper(sha1(self::LEAKED)), 5)));
    }

    public function test_unbreached_password_passes(): void
    {
        $this->fakeRange(self::LEAKED, leaked: false);

        $this->assertTrue($this->passes(self::LEAKED));
    }

    public function test_api_outage_does_not_block(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 500)]);

        $this->assertTrue($this->passes(self::LEAKED));
    }

    public function test_defaults_skip_the_network_in_unit_tests(): void
    {
        Http::fake();

        $this->assertTrue(Validator::make(
            ['password' => self::LEAKED],
            ['password' => [Password::defaults()]],
        )->passes());
        Http::assertNothingSent();
    }
}
