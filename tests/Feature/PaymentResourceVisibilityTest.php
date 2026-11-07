<?php

namespace Tests\Feature;

use App\Http\Resources\PaymentResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Gateway ids and payout bookkeeping are for the property's landlord and admins. A tenant receives the same
 * resource nested under their reservation and must not see them.
 */
class PaymentResourceVisibilityTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const INTERNAL = [
        'paymongo_payment_intent_id', 'paymongo_payment_id', 'paymongo_checkout_session_id',
        'released_by', 'payout_id', 'paid_out_at', 'paid_out_by', 'payout_reference',
    ];

    private function tenancyWithSettledPayment(): array
    {
        $t = $this->makeTenancy();
        $payment = $this->makePayment($t['reservation'], [
            'status'                       => 'Released',
            'payout_status'                => 'Paid Out',
            'released_by'                  => $t['landlord']->user_id,
            'paymongo_payment_intent_id'   => 'pi_secret',
            'paymongo_payment_id'          => 'pay_secret',
            'paymongo_checkout_session_id' => 'cs_secret',
        ]);

        return [$t, $payment];
    }

    private function resolveAs($user, $payment): array
    {
        $this->actingAs($user);

        return (new PaymentResource($payment->fresh()))->resolve();
    }

    public function test_the_tenant_response_has_no_gateway_or_payout_fields(): void
    {
        [$t] = $this->tenancyWithSettledPayment();

        Sanctum::actingAs($t['tenant']);
        $response = $this->getJson("/api/v1/tenant/reservations/{$t['reservation']->reservation_id}/agreement")->assertOk();

        $response->assertJsonPath('data.payments.0.status', 'Released');
        foreach (self::INTERNAL as $key) {
            $response->assertJsonMissingPath("data.payments.0.$key");
        }
        $this->assertStringNotContainsString('cs_secret', $response->getContent());
        $this->assertStringNotContainsString('TEST-PAYOUT', $response->getContent());
    }

    public function test_the_property_landlord_and_an_admin_get_every_field(): void
    {
        [$t, $payment] = $this->tenancyWithSettledPayment();

        foreach ([$t['landlord'], $this->makeUserWithRole('Admin')] as $viewer) {
            $data = $this->resolveAs($viewer, $payment);

            foreach (self::INTERNAL as $key) {
                $this->assertArrayHasKey($key, $data, "$key is visible to the landlord and admin");
            }
            $this->assertSame('cs_secret', $data['paymongo_checkout_session_id']);
            $this->assertSame('TEST-PAYOUT', $data['payout_reference']);
        }
    }

    public function test_another_landlord_does_not_get_them(): void
    {
        [, $payment] = $this->tenancyWithSettledPayment();

        $data = $this->resolveAs($this->makeLandlord(), $payment);

        $this->assertArrayNotHasKey('paymongo_payment_id', $data);
        $this->assertArrayNotHasKey('payout_reference', $data);
    }
}
