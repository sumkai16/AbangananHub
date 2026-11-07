<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Review;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Every state-changing route (POST / PUT / PATCH / DELETE) is sent hostile bodies as the landlord, the
 * tenant and an admin, with real ids filled into its URL: SQL text, arrays where text is expected, and
 * oversized values. A refusal (403, 404, 419, 422, a redirect) is the right answer; a 5xx is a bug.
 * Nothing leaves the machine: mail, HTTP, queues, notifications and storage are faked.
 */
class HostilePostInputTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** Routes that would call a real third party or end the session; covered elsewhere. */
    private const SKIP = ['logout', 'api/v1/auth/logout', 'search/interpret', 'search/results', 'api/v1/broadcasting/auth'];

    private const FIELDS = [
        'first_name', 'last_name', 'email', 'password', 'password_confirmation', 'current_password', 'phone', 'contact_number',
        'message', 'body', 'comment', 'review_comment', 'rating', 'reply', 'landlord_reply', 'reason', 'report_reason', 'notes',
        'admin_note', 'admin_notes', 'title', 'description', 'address', 'unit_label', 'property_type', 'status', 'role', 'roles',
        'amount', 'rental_fee', 'security_deposit', 'monthly_rent', 'agreed_monthly_rent', 'method', 'type', 'reference_no',
        'reference', 'date', 'start', 'end', 'time', 'name', 'content', 'lease_body', 'agreement_terms_notes', 'latitude', 'longitude',
        'property_id', 'unit_id', 'tenant_id', 'user_id', 'reservation_id', 'amenities', 'rules', 'house_rules', 'photos',
    ];

    private function hostileBodies(): array
    {
        $sql = "x' OR '1'='1' AND SLEEP(3) -- ";

        return [
            'sql'   => array_fill_keys(self::FIELDS, $sql),
            'array' => array_fill_keys(self::FIELDS, ['a', ['b' => 'c']]),
            'huge'  => array_fill_keys(self::FIELDS, str_repeat('9', 5000)),
            'xss'   => array_fill_keys(self::FIELDS, "<script>alert('X55')</script>"),
        ];
    }

    public function test_every_state_changing_route_survives_hostile_bodies(): void
    {
        Http::fake();
        Mail::fake();
        Queue::fake();
        Notification::fake();
        Storage::fake();

        $t = $this->makeTenancy();
        $landlord = $t['landlord'];
        $tenant = $t['tenant'];
        $reservation = $t['reservation'];
        $property = $reservation->property;
        $payment = $this->makePayment($reservation);
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $property->property_id, 'unit_id' => $reservation->unit_id,
        ]);
        $message = new Message(['conversation_id' => $conversation->conversation_id, 'sender_id' => $tenant->user_id, 'message' => 'hi', 'is_read' => false]);
        $message->sent_at = now();
        $message->save();
        $review = Review::create(['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'landlord_id' => $landlord->user_id, 'rating' => 5, 'review_comment' => 'ok', 'is_hidden' => false]);
        $admin = $this->makeUserWithRole('Admin');

        $values = [
            'reservation' => $reservation->reservation_id, 'property' => $property->property_id, 'property_id' => $property->property_id,
            'propertyId' => $property->property_id, 'unit' => $reservation->unit_id, 'conversation' => $conversation->conversation_id,
            'review' => $review->review_id, 'payment' => $payment->payment_id, 'user' => $tenant->user_id, 'media' => 999999,
            'document' => 999999, 'notification' => 'x', 'concern' => 999999, 'claim' => 999999, 'depositCharge' => 999999, 'payout' => 999999,
            'verification' => 999999, 'report' => 999999, 'viewing' => 999999, 'blockedDate' => 999999, 'leaseTemplate' => 999999,
            'landlordTenant' => 999999, 'provider' => 'google', 'id' => 1, 'hash' => 'x',
        ];

        $failures = [];
        $count = 0;

        foreach (Route::getRoutes() as $route) {
            $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true));
            $uri = $route->uri();

            if (! $method || in_array($uri, self::SKIP, true) || str_starts_with($uri, '_') || str_starts_with($uri, 'sanctum') || str_starts_with($uri, 'storage')) {
                continue;
            }

            $url = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $values[$m[1]] ?? '1', $uri);
            $isApi = str_starts_with($uri, 'api/');

            foreach ([$landlord, $tenant, $admin] as $user) {
                foreach ($this->hostileBodies() as $kind => $body) {
                    $isApi ? Sanctum::actingAs($user) : $this->actingAs($user);

                    $started = microtime(true);
                    $response = $isApi
                        ? $this->json($method, '/' . $url, $body)
                        : $this->withoutMiddleware(ValidateCsrfToken::class)->call($method, '/' . $url, $body);
                    $took = microtime(true) - $started;
                    $count++;

                    if ($response->getStatusCode() >= 500) {
                        $failures["$method /$uri"] ??= sprintf('%d (%s) %s @ %s', $response->getStatusCode(), $kind, $response->exception?->getMessage() ?? '', $response->exception ? basename($response->exception->getFile()) . ':' . $response->exception->getLine() : '');
                    } elseif ($took > 2.5) {
                        $failures["$method /$uri"] ??= "took {$took}s ($kind): did SLEEP() run?";
                    }
                }
            }
        }

        $this->assertGreaterThan(500, $count, 'Too few requests were sent for the test to mean anything.');
        $this->assertSame([], $failures, "Routes that failed:\n" . collect($failures)->map(fn ($v, $k) => "  $k => " . mb_substr($v, 0, 220))->implode("\n"));
    }
}
