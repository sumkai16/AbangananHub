<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Notification;
use App\Support\QrPh;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** "Ask {landlord} to share a payment QR": the tenant's one tap that tells the landlord through the system. */
class PaymentQrRequestTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function tenancyWithChat(): array
    {
        $parts = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $conversation = Conversation::create([
            'tenant_id' => $parts['tenant']->user_id, 'landlord_id' => $parts['landlord']->user_id,
            'property_id' => $parts['reservation']->property_id, 'unit_id' => $parts['reservation']->unit_id, 'type' => 'inquiry',
        ]);
        $parts['reservation']->forceFill(['conversation_id' => $conversation->conversation_id])->save();

        return $parts + ['conversation' => $conversation];
    }

    public function test_asking_notifies_the_landlord_and_posts_a_line_in_the_chat(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation, 'conversation' => $conversation] = $this->tenancyWithChat();

        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation))->assertSessionHas('success');

        $this->assertNotNull($reservation->fresh()->qr_requested_at);
        $note = Notification::where('user_id', $landlord->user_id)->latest('notification_id')->firstOrFail();
        $this->assertStringContainsString('asks for your payment QR', $note->title);
        $this->assertSame($conversation->conversation_id, $note->conversation_id);
        $this->assertTrue($conversation->messages()->where('kind', 'system')->where('message', 'like', '%to share a payment QR%')->exists());
    }

    public function test_a_second_tap_inside_the_hour_does_nothing_and_after_it_asks_again(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->tenancyWithChat();

        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation));
        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation))->assertSessionHas('warning');
        $this->assertSame(1, Notification::where('user_id', $landlord->user_id)->where('title', 'like', '%asks for your payment QR')->count());

        $this->travel(61)->minutes();
        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation))->assertSessionHas('success');
        $this->assertSame(2, Notification::where('user_id', $landlord->user_id)->where('title', 'like', '%asks for your payment QR')->count());
    }

    public function test_only_the_tenant_of_that_reservation_can_ask(): void
    {
        ['reservation' => $reservation] = $this->tenancyWithChat();

        $this->actingAs($this->makeTenant())->post(route('paymentQr.request', $reservation))->assertForbidden();
        $this->assertNull($reservation->fresh()->qr_requested_at);
    }

    public function test_sharing_a_qr_closes_the_request_and_the_button_says_so_to_the_landlord(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation, 'conversation' => $conversation] = $this->tenancyWithChat();

        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation));
        $this->actingAs($landlord)->get(route('conversations.show', $conversation), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Asked');

        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('MARIA SANTOS')])->assertCreated();
        $this->assertNull($reservation->fresh()->qr_requested_at);
    }

    public function test_the_pay_card_shows_the_ask_button_then_the_asked_line(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->tenancyWithChat();

        $page = fn () => $this->actingAs($tenant)->get(route('agreements.show', $reservation))->assertOk();

        $page()->assertSee(route('paymentQr.request', $reservation), false)->assertDontSee('We\'ll tell you when it arrives', false);

        $this->actingAs($tenant)->post(route('paymentQr.request', $reservation));
        $page()->assertSee('We&rsquo;ll tell you when it arrives', false)->assertDontSee(route('paymentQr.request', $reservation), false);
    }
}
