<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Stored XSS: text a user typed once is shown to other people later (an admin, the landlord, the other
 * side of a chat). Every place that text is saved is filled with a script payload here, then every page
 * that shows it is rendered as each role. Blade's `{{ }}` escapes it; a stray `{!! !!}` would not, and
 * this test fails the moment one does. The check is on the browser-visible HTML, not the database.
 */
class StoredXssTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const PAYLOADS = [
        "<script>alert('X55')</script>",
        '"><img src=x onerror=alert(1)>',
        "'><svg/onload=alert('X55')>",
        '</script><script>alert(1)</script>',
    ];

    /** Raw (unescaped) fragments that must never reach the page. Escaped output turns `<` into `&lt;`. */
    private const FORBIDDEN = ["<script>alert('X55')", '"><img src=x', 'onerror=alert(1)>', '<svg/onload', '</script><script>alert(1)'];

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    private function plant(): void
    {
        [$a, $b, $c, $d] = self::PAYLOADS;

        $t = $this->makeTenancy(['agreement_terms_notes' => $a, 'rental_status' => 'Occupied']);
        $landlord = $t['landlord'];
        $tenant = $t['tenant'];
        $reservation = $t['reservation'];

        $landlord->update(['first_name' => $a, 'last_name' => $b]);
        $tenant->update(['first_name' => $c, 'last_name' => $d]);

        $property = $reservation->property;
        $property->update(['title' => $a, 'description' => $b, 'address' => $c . ', Lahug, Cebu City']);
        $reservation->unit->update(['unit_label' => $d]);

        $review = Review::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'landlord_id' => $landlord->user_id,
            'rating' => 4, 'review_comment' => $b, 'landlord_reply' => $c, 'landlord_replied_at' => now(), 'is_hidden' => false,
        ]);

        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $property->property_id, 'unit_id' => $reservation->unit_id,
        ]);
        foreach ([$tenant, $landlord] as $sender) {
            $m = new Message(['conversation_id' => $conversation->conversation_id, 'sender_id' => $sender->user_id, 'message' => $a . $b . $d, 'is_read' => false]);
            $m->sent_at = now();
            $m->save();
        }

        Report::create([
            'reporter_id' => $tenant->user_id, 'property_id' => $property->property_id, 'reported_user_id' => $landlord->user_id,
            'report_reason' => $d, 'report_status' => 'Pending',
        ]);

        $this->ids = [
            'landlord' => $landlord, 'tenant' => $tenant, 'property' => $property->property_id, 'reservation' => $reservation->reservation_id,
            'conversation' => $conversation->conversation_id, 'review' => $review->review_id,
        ];
    }

    /** @return array<string, list<string>> */
    private function pages(): array
    {
        $i = $this->ids;

        return [
            'Admin' => ['admin/users', 'admin/users/' . $i['tenant']->user_id, 'admin/catalogue/properties', 'admin/catalogue/units', 'admin/reviews',
                'admin/reports', 'admin/reservations', 'admin/conversations', 'admin/listings/approval', 'admin/payments', 'admin/dashboard',
                'admin/audit-logs', 'admin/ratings', 'admin/verifications', 'admin/payouts'],
            'Landlord' => ['landlord/dashboard', 'landlord/properties', 'landlord/units', 'landlord/reservations', 'landlord/tenants', 'landlord/reviews',
                'landlord/payments', 'landlord/contracts', 'landlord/complaints', 'landlord/analytics', 'conversations',
                'conversations/' . $i['conversation'], 'landlord/tenancies/' . $i['reservation'], 'notifications', 'landlord/profile'],
            'Tenant' => ['tenant/reservations', 'tenant/reports', 'conversations', 'conversations/' . $i['conversation'], 'favorites', 'notifications'],
            'Guest' => ['properties', 'properties/' . $i['property'], '/'],
        ];
    }

    public function test_planted_script_text_is_escaped_on_every_page(): void
    {
        $this->plant();

        $checked = 0;
        $showedEscaped = [];
        foreach ($this->pages() as $role => $paths) {
            foreach ($paths as $path) {
                $user = match ($role) {
                    'Admin'    => $this->makeUserWithRole('Admin'),
                    'Landlord' => $this->ids['landlord'],
                    'Tenant'   => $this->ids['tenant'],
                    default    => null,
                };
                $user ? $this->actingAs($user) : $this->app['auth']->forgetGuards();

                $response = $this->get('/' . ltrim($path, '/'));
                $this->assertLessThan(500, $response->getStatusCode(), "$role /$path returned {$response->getStatusCode()}");

                if ($response->getStatusCode() !== 200) {
                    continue;
                }

                $html = $response->getContent();
                foreach (self::FORBIDDEN as $raw) {
                    $this->assertStringNotContainsString($raw, $html, "$role /$path printed planted text unescaped: [$raw]");
                }
                $checked++;
                if (str_contains($html, '&lt;script&gt;alert(&#039;X55&#039;)') || str_contains($html, '&lt;img src=x onerror')) {
                    $showedEscaped[] = "$role /$path";
                }
            }
        }

        // Positive control: the payload really was rendered (escaped) on many pages, so "not found raw" means something.
        $this->assertGreaterThan(15, count($showedEscaped), 'The planted text barely appeared on any page: ' . implode(', ', $showedEscaped));
        $this->assertGreaterThan(20, $checked, 'Too few pages rendered, so the test is not really checking anything.');
    }
}
