<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\PaymentQrShare;
use App\Models\Reservation;
use App\Support\QrPh;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentQrShareTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function chat(): array
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        return compact('landlord', 'tenant', 'conversation', 'unit');
    }

    public function test_landlord_shares_a_valid_qr_and_it_lands_in_the_chat(): void
    {
        ['landlord' => $landlord, 'conversation' => $conversation] = $this->chat();

        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), [
            'payload' => QrPh::make('AXCEE CABUSAS'), 'note' => 'Pay before the 5th',
        ])->assertCreated();

        $share = PaymentQrShare::firstOrFail();
        $this->assertSame('AXCEE CABUSAS', $share->account_name);
        $this->assertSame('payment_qr', Message::firstOrFail()->kind);
        $this->assertStringContainsString('<svg', $share->svg());
        $this->assertNotSame(QrPh::make('AXCEE CABUSAS'), DB::table('payment_qr_shares')->value('payload'), 'The code is stored encrypted.');
    }

    public function test_a_landlord_can_reuse_their_earlier_qr_in_another_chat_but_not_someone_elses(): void
    {
        ['landlord' => $landlord, 'conversation' => $conversation, 'unit' => $unit] = $this->chat();
        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('AXCEE CABUSAS')])->assertCreated();
        $earlier = PaymentQrShare::firstOrFail();

        $otherTenant = $this->makeTenant();
        $second = Conversation::create([
            'tenant_id' => $otherTenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        $this->actingAs($landlord)->postJson(route('paymentQr.store', $second), ['reuse_share_id' => $earlier->payment_qr_share_id])->assertCreated();
        $this->assertSame(2, PaymentQrShare::count());
        $this->assertSame('AXCEE CABUSAS', PaymentQrShare::latest('payment_qr_share_id')->first()->account_name);

        $stranger = $this->makeLandlord();
        $strangerChat = Conversation::create([
            'tenant_id' => $otherTenant->user_id, 'landlord_id' => $stranger->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);
        $this->actingAs($stranger)->postJson(route('paymentQr.store', $strangerChat), ['reuse_share_id' => $earlier->payment_qr_share_id])
            ->assertStatus(422);
    }

    public function test_a_link_or_random_text_is_refused(): void
    {
        ['landlord' => $landlord, 'conversation' => $conversation] = $this->chat();

        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => 'https://evil.example/pay-here'])
            ->assertStatus(422)->assertJsonValidationErrors('payload');
        $this->assertSame(0, PaymentQrShare::count());
    }

    public function test_only_the_conversations_landlord_can_share(): void
    {
        ['tenant' => $tenant, 'conversation' => $conversation] = $this->chat();

        $this->actingAs($tenant)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('X')])->assertForbidden();
        $this->actingAs($this->makeLandlord())->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('X')])->assertForbidden();
    }

    public function test_a_new_share_replaces_the_old_one_and_latest_for_finds_it(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'conversation' => $conversation, 'unit' => $unit] = $this->chat();
        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('OLD NAME')]);
        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('NEW NAME')]);

        $this->assertSame(1, PaymentQrShare::whereNull('replaced_at')->count());

        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id, 'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => '2026-10-01',
        ]);

        $this->assertSame('NEW NAME', PaymentQrShare::latestFor($reservation)->account_name);
    }

    public function test_another_tenants_share_is_not_found(): void
    {
        ['landlord' => $landlord, 'conversation' => $conversation, 'unit' => $unit] = $this->chat();
        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make('NAME')]);

        $otherTenant = $this->makeTenant();
        $reservation = Reservation::create([
            'tenant_id' => $otherTenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => '2026-10-01',
        ]);

        $this->assertNull(PaymentQrShare::latestFor($reservation));
    }
}
