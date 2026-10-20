<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * The two PayMongo checkout-session calls needed to recover from an abandoned checkout.
 *
 * Kept apart from the controllers so the web and API payment controllers share one implementation and
 * tests can fake the HTTP layer. Both calls fail closed: when PayMongo cannot be reached or answers
 * something unexpected, the caller must assume the session may still be payable.
 */
class PaymongoCheckout
{
    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    /** The session's status as PayMongo reports it (e.g. 'active', 'expired', 'paid'), or null if unknown. */
    public static function status(string $sessionId): ?string
    {
        $response = self::client()->get(self::BASE.'/'.$sessionId);

        return $response->ok() ? $response->json('data.attributes.status') : null;
    }

    /**
     * Make sure this session can no longer be paid. True when it has expired (already, or because we just
     * expired it); false whenever that cannot be confirmed, including when it has in fact been paid.
     */
    public static function abandon(string $sessionId): bool
    {
        if (self::client()->post(self::BASE.'/'.$sessionId.'/expire')->successful()) {
            return true;
        }

        return self::status($sessionId) === 'expired';
    }

    private static function client()
    {
        return Http::withBasicAuth(config('services.paymongo.secret_key'), '');
    }
}
