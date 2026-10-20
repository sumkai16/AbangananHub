<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a push through Expo's push endpoint. Fire-and-forget: a dead token,
 * a network blip, or Expo being down must never fail the action that
 * triggered the notification (a payment, a message, a status change) — so
 * every failure path here logs and returns rather than throwing.
 *
 * Called from Notification::notify() only — see that method's docblock for
 * why every notification creation site funnels through one place.
 */
class ExpoPushNotifier
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(User $user, string $title, string $message, ?string $link = null): void
    {
        $tokens = $user->devices()->orderBy('device_id')->pluck('expo_push_token')->all();

        if (! $tokens) {
            return;
        }

        try {
            // One message per registered device; Expo answers with one ticket per message, in order.
            $response = Http::timeout(5)->post(self::ENDPOINT, array_map(fn (string $token) => [
                'to'    => $token,
                'title' => $title,
                'body'  => $message,
                'data'  => array_filter(['link' => $link]),
            ], $tokens));

            if ($response->failed()) {
                Log::warning('Expo push failed', [
                    'user_id' => $user->user_id,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);

                return;
            }

            // A 200 from Expo's own endpoint can still carry a per-message
            // DeviceNotRegistered error — that means the token is dead
            // (app uninstalled, or a new token issued that never got
            // re-registered). Drop that device so future sends don't keep trying.
            $tickets = $response->json('data') ?? [];
            if (isset($tickets['status'])) {
                $tickets = [$tickets];
            }

            foreach (array_values($tickets) as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'error'
                    && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered'
                    && isset($tokens[$i])) {
                    $user->devices()->where('expo_push_token', $tokens[$i])->delete();
                }
            }
        } catch (Throwable $e) {
            Log::warning('Expo push threw', [
                'user_id' => $user->user_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
