<?php

namespace Tests\Feature\Payments;

use App\Models\Reservation;
use App\Services\ImageUploader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The landlord must attach a photo to mark the keys turned over (Nov 10 2026). Evidence, not proof. */
class HandoverPhotoTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private array $t;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
        config(['rentals.online_payments_enabled' => true]);

        $this->t = $this->makeOnlineTenancy([
            'rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'target_move_in_date' => '2026-11-01',
        ]);

        $this->makePayment($this->t['reservation'], [
            'payment_type' => 'Initial', 'billing_period' => null, 'payment_method' => 'GCash',
            'status' => 'Held', 'paid_at' => now(), 'paymongo_payment_id' => 'pay_test',
        ]);

        $this->app->instance(ImageUploader::class, new class extends ImageUploader
        {
            public function upload(UploadedFile $file, string $folder, array $options = []): array
            {
                return ['url' => 'https://img.test/handover.png', 'public_id' => $folder.'/x'];
            }
        });
    }

    private function route(): string
    {
        return route('landlord.reservations.markTurnedOver', $this->t['reservation']);
    }

    public function test_without_a_photo_nothing_starts(): void
    {
        $this->actingAs($this->t['landlord'])->post($this->route())->assertSessionHasErrors('handover_photo');

        $this->assertNull($this->t['reservation']->fresh()->keys_turned_over_at);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->t['landlord'])
            ->post($this->route(), ['handover_photo' => UploadedFile::fake()->create('keys.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('handover_photo');

        $this->assertNull($this->t['reservation']->fresh()->keys_turned_over_at);
    }

    public function test_with_a_photo_the_clock_starts_and_the_photo_is_kept(): void
    {
        $this->actingAs($this->t['landlord'])
            ->post($this->route(), ['handover_photo' => UploadedFile::fake()->image('keys.png')])
            ->assertSessionHas('success');

        $r = $this->t['reservation']->fresh();
        $this->assertNotNull($r->keys_turned_over_at);
        $this->assertSame('https://img.test/handover.png', $r->keys_handover_photo_url);
        $this->assertNotNull($r->move_in_deadline_at);
    }

    public function test_the_model_refuses_an_empty_photo(): void
    {
        $r = Reservation::find($this->t['reservation']->reservation_id);

        $this->assertFalse($r->markKeysTurnedOver('', ''));
        $this->assertNull($r->fresh()->keys_turned_over_at);
    }

    public function test_the_tenant_sees_the_photo_on_the_agreement_page(): void
    {
        $this->actingAs($this->t['landlord'])
            ->post($this->route(), ['handover_photo' => UploadedFile::fake()->image('keys.png')]);

        $this->actingAs($this->t['tenant'])->get(route('agreements.show', $this->t['reservation']))
            ->assertOk()->assertSee('See the photo your landlord sent')->assertSee('https://img.test/handover.png', false);
    }
}
