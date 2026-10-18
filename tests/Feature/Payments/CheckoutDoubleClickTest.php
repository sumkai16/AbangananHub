<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A double-click on Pay must never open two PayMongo sessions. The first click leaves a Pending placeholder
 * that has no session id until PayMongo answers; the second click has to be blocked by that placeholder, and
 * the state checks have to hold inside the lock, not only before it. PayMongo is faked.
 */
class CheckoutDoubleClickTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);
        Carbon::setTestNow('2026-10-14 10:00:00');

        Http::fake([self::BASE => Http::response(['data' => [
            'id'         => 'cs_new',
            'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_new'],
        ]], 200)]);
    }

    /** @return array<string, array{string}> */
    public static function surfaces(): array
    {
        return ['web' => ['web'], 'api' => ['api']];
    }

    private function start(string $surface, string $flow, array $t)
    {
        $reservation = $t['reservation'];
        $id = $reservation->reservation_id;

        if ($surface === 'api') {
            Sanctum::actingAs($t['tenant'], ['*']);

            return $this->postJson($flow === 'initial'
                ? "/api/v1/tenant/reservations/{$id}/pay"
                : "/api/v1/tenant/tenancy/{$id}/pay-rent");
        }

        return $this->actingAs($t['tenant'])->post(route($flow === 'initial' ? 'payments.checkout' : 'payments.rentCheckout', $reservation));
    }

    private function assertRefused($response, string $surface): void
    {
        if ($surface === 'api') {
            $response->assertStatus(422)->assertJsonValidationErrors('payment');
        } else {
            $response->assertSessionHasErrors('payment');
        }
    }

    private function assertNoSessionOpened(): void
    {
        $this->assertTrue(
            Http::recorded(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE)->isEmpty(),
            'no PayMongo session was opened'
        );
    }

    #[DataProvider('surfaces')]
    public function test_a_placeholder_without_a_session_id_blocks_a_second_initial_checkout(string $surface): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
        $this->makePendingCheckout($t['reservation'], 'x', ['paymongo_checkout_session_id' => null]);

        $this->assertRefused($this->start($surface, 'initial', $t), $surface);

        $this->assertNoSessionOpened();
        $this->assertSame(1, Payment::where('reservation_id', $t['reservation']->reservation_id)->count());
    }

    #[DataProvider('surfaces')]
    public function test_a_placeholder_without_a_session_id_blocks_a_second_rent_checkout(string $surface): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], [
            'payment_type' => 'Monthly', 'billing_period' => '2026-08-01', 'status' => 'Pending',
            'payment_method' => 'GCash', 'paid_at' => null, 'paymongo_checkout_session_id' => null,
        ]);

        $this->assertRefused($this->start($surface, 'rent', $t), $surface);

        $this->assertNoSessionOpened();
    }

    #[DataProvider('surfaces')]
    public function test_an_already_settled_initial_payment_is_refused(string $surface): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash']);

        $this->assertRefused($this->start($surface, 'initial', $t), $surface);

        $this->assertNoSessionOpened();
    }

    #[DataProvider('surfaces')]
    public function test_a_reservation_that_is_not_signed_is_refused(string $surface): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Pending Rental Agreement']);

        $this->assertRefused($this->start($surface, 'initial', $t), $surface);

        $this->assertNoSessionOpened();
    }

    #[DataProvider('surfaces')]
    public function test_a_first_click_still_opens_exactly_one_session(string $surface): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);

        $response = $this->start($surface, 'initial', $t);
        $surface === 'api' ? $response->assertOk() : $response->assertRedirect('https://checkout.paymongo.test/cs_new');

        $this->assertSame('cs_new', Payment::where('reservation_id', $t['reservation']->reservation_id)->value('paymongo_checkout_session_id'));
        $this->assertSame(1, Http::recorded(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE)->count());
    }

    public function test_checkout_is_refused_when_another_reservation_on_the_unit_is_signed(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
        $other = $this->makeTenant();
        Reservation::create([
            'tenant_id' => $other->user_id, 'property_id' => $t['reservation']->property_id, 'unit_id' => $t['reservation']->unit_id,
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => '2026-08-01', 'target_move_in_date' => '2026-08-01',
        ]);

        $this->start('web', 'initial', $t)->assertSessionHasErrors(['payment' => 'This unit is no longer available. Please contact your landlord.']);

        $this->assertNoSessionOpened();
    }
}
