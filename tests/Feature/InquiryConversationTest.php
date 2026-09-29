<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression: a tenant whose earlier thread about a property was cancelled
 * could never inquire about it again. conversations is unique on
 * (tenant, landlord, property), and the inquiry skipped the cancelled row
 * and tried to insert a second one.
 *
 * Dev database, rolled back — see ViewingSchedulingTest for why.
 */
class InquiryConversationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inquiry_reopens_a_cancelled_conversation(): void
    {
        config(['broadcasting.default' => 'null']);

        $unit = PropertyUnit::where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->whereHas('property', fn ($q) => $q->where('verification_status', 'Approved')->where('publication_status', 'Published'))
            ->whereDoesntHave('reservations', fn ($q) => $q->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
            ->with('property')
            ->firstOrFail();
        $property = $unit->property;

        $tenant = User::whereHas('roles', fn ($q) => $q->where('role', 'Tenant'))
            ->where('user_id', '!=', $property->landlord_id)
            ->whereNotIn('user_id', Conversation::where('property_id', $property->property_id)->pluck('tenant_id'))
            ->firstOrFail();

        $old = Conversation::create([
            'tenant_id'   => $tenant->user_id,
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'status'      => 'Cancelled',
        ]);

        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame('Open', $old->status);
        $this->assertSame($unit->unit_id, $old->unit_id);
        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
        $this->assertTrue(Reservation::where('conversation_id', $old->conversation_id)->where('rental_status', 'Inquiry')->exists());
    }
}
