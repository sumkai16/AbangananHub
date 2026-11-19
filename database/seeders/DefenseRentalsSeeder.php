<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\DepositCharge;
use App\Models\Favorite;
use App\Models\LeaseTemplate;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentQrShare;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TenantConcern;
use App\Models\TenantDetail;
use App\Models\UnitMedia;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Models\UserRole;
use App\Models\ViewingRequest;
use App\Services\MoveInPaymentRecorder;
use App\Services\PaymentClaims;
use App\Support\LeaseTerms;
use App\Support\MoveInPaymentBreakdown;
use App\Support\QrPh;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The landlord and tenant side of the capstone-defense world: every sidebar number on Maria Santos's account is
 * above zero, and the presenter's tenant (Axcee Cabusas) has a rental that is two months overdue, an agreement
 * waiting for its move-in payment, and a cancelled reservation with a refund on its way.
 *
 * Runs after the base seeders (UserSeeder ... DemoSeeder) and after DefenseAdminSeeder. It only ADDS: three
 * approved listings with a few units each, one rejected and one suspended listing, and the people around them
 * (new tenants @abangananhub.com, password `password`). Idempotent (everything is found by email, title or natural
 * key before it is created), deterministic, and dated from now(): the overdue months are always the two before this
 * one, the viewing is always in the future, the ending-soon tenancy always ends inside the renewal window.
 *
 * Photos are never created here: new listings copy the media rows of one of Maria's seeded listings.
 */
class DefenseRentalsSeeder extends TenantSeeder
{
    /** What is seeded and where to see it. The defense script (docs/defense-script.md) is built from this. */
    public const SCENARIOS = [
        ['key' => 'landlord-badges', 'who' => 'landlord@abangananhub.com', 'where' => 'any landlord page (sidebar)', 'shows' => 'Maria\'s sidebar has a number on Reservations, Properties, Rentals, Reviews, Concerns and Payments.'],
        ['key' => 'overdue-rental', 'who' => 'axcee@abangananhub.com', 'where' => '/tenant/tenancy (Unit 1, 1-Bedroom Apartment in Poblacion, Consolacion)', 'shows' => 'A rental two months overdue (₱19,000): the pay picker, the pay sheet on a phone, and the Deposit tab with a deposit charge.'],
        ['key' => 'overdue-landlord-side', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/payments', 'shows' => 'The same tenancy as Overdue, with Remind overdue and the ledger.'],
        ['key' => 'signed-waiting-payment', 'who' => 'axcee@abangananhub.com', 'where' => '/tenant/reservations, then the agreement (Studio Apartments, Lahug)', 'shows' => 'A signed agreement waiting for the move-in payment, direct payments, no QR shared yet, so "Ask Maria to share a payment QR" shows.'],
        ['key' => 'unread-chat', 'who' => 'axcee@abangananhub.com', 'where' => '/conversations (Maria, Studio Apartments, Lahug)', 'shows' => 'A thread with two unread messages from the landlord.'],
        ['key' => 'cancelled-refund', 'who' => 'axcee@abangananhub.com', 'where' => '/tenant/reservations (Casa Verde Rooms, Mandaue)', 'shows' => 'A cancelled reservation with "Your refund is on its way".'],
        ['key' => 'favorites', 'who' => 'axcee@abangananhub.com', 'where' => '/favorites', 'shows' => 'Three saved listings.'],
        ['key' => 'open-concern', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/concerns', 'shows' => 'An open electrical concern from Axcee, in the tenancy chat.'],
        ['key' => 'qr-request-open', 'who' => 'landlord@abangananhub.com', 'where' => '/conversations (Kyle Bautista, Studio Apartments, Lahug) and the bell', 'shows' => 'Kyle asked for a payment QR: a notification, a system line, and the Share payment QR button marked Asked.'],
        ['key' => 'payment-report-to-confirm', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/payments and the chat with Noemi Gutierrez', 'shows' => 'A GCash payment report waiting to be confirmed or rejected, with the QR Maria shared in that chat.'],
        ['key' => 'ending-soon', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/contracts', 'shows' => 'Liam Dizon\'s rental ends in 30 days (renewal window), so Rentals has a number.'],
        ['key' => 'completed-with-charge', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/contracts?status=all (Ana Rivera)', 'shows' => 'An ended rental with a deposit charge and a partly returned deposit.'],
        ['key' => 'new-inquiry-and-viewing', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/reservations and its Viewings tab', 'shows' => 'A new inquiry from Bianca Uy and a viewing request to answer.'],
        ['key' => 'unreplied-reviews', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/reviews', 'shows' => 'Three reviews without a reply.'],
        ['key' => 'rejected-listing', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/properties', 'shows' => 'A listing the admin rejected, with the reason, to fix and resubmit.'],
        ['key' => 'suspended-listing', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/properties', 'shows' => 'A suspended listing, hidden from tenants.'],
        ['key' => 'lease-policies', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/lease-policies', 'shows' => 'A custom policy on a property and a different one on a single unit (Unit 3).'],
        ['key' => 'walk-in-cash', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/tenants', 'shows' => 'A walk-in tenant with no account who pays cash.'],
        ['key' => 'payout-ready', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/payouts', 'shows' => 'Maria\'s GCash payout account is approved, so online payouts work.'],
        ['key' => 'payment-stories', 'who' => 'landlord@abangananhub.com', 'where' => '/landlord/payments and /landlord/contracts', 'shows' => 'Three more payment stories: a part payer (Rico Salazar), one who pays months ahead (Selena Tan) and one who always hands cash (Jun Pascual).'],
        ['key' => 'clean-tenant', 'who' => 'tenant.new@abangananhub.com', 'where' => 'the whole site', 'shows' => 'A tenant with no activity: browse, search, inquire and sign up from nothing.'],
    ];

    private const APARTMENT = '1-Bedroom Apartment in Poblacion, Consolacion';
    private const STUDIOS = 'Studio Apartments, Lahug';
    private const CASA = 'Casa Verde Rooms, Mandaue';
    private const REJECTED = 'Tisa Garden Rooms, Cebu City';
    private const SUSPENDED = 'Mambaling Annex Rooms, Cebu City';

    private ?User $maria = null;
    private ?User $admin = null;
    /** @var array<string, Property> */
    private array $props = [];

    public function run(): void
    {
        // A seed run never needs Reverb: nothing it writes should try to push to a socket.
        config(['broadcasting.default' => 'null']);

        $this->maria = User::where('email', 'landlord@abangananhub.com')->firstOrFail();
        $this->admin = User::where('email', 'admin@abangananhub.com')->firstOrFail();
        $axcee = User::where('email', 'axcee@abangananhub.com')->firstOrFail();

        $this->listings();
        $this->policies();
        $this->payoutAccount();
        $this->details($axcee);

        $this->axceeOverdueRental($axcee);
        $this->axceeSignedAgreement($axcee);
        $this->axceeCancelledWithRefund($axcee);
        $this->favorites($axcee);

        $this->kyleQrRequest();
        $this->liamEndingSoon();
        $this->anaCompleted();
        $this->noemiPaymentReport();
        $this->walkInCash();
        $this->paymentStories();
        $this->biancaInquiryAndViewing();
        $this->reviews();
        $this->cleanTenant();
    }

    // ─── Listings ────────────────────────────────────────────────────────────────────────────────────────────────

    private function listings(): void
    {
        $model = Property::where('landlord_id', $this->maria->user_id)->whereHas('media')
            ->with(['media', 'units.media', 'amenities'])->orderBy('property_id')->first();

        $labels = static fn (string $prefix, int $n) => array_map(fn ($i) => $prefix.' '.$i, range(1, $n));

        $this->props['apartment'] = $this->listing($model, self::APARTMENT, 'Apartment', 'Poblacion', 'Consolacion', 10.3790, 123.9580,
            'Bright one-bedroom units five minutes from the Consolacion town centre. Tiled floors, private comfort room, steady water and fiber Wi-Fi.',
            'Approved', 'Published', null, $labels('Unit', 11), 9500, 2000);
        $this->props['studios'] = $this->listing($model, self::STUDIOS, 'Apartment', 'Lahug', 'Cebu City', 10.3283, 123.8982,
            'Compact studios near JY Square and Ayala, for working professionals. Furnished, aircon, with a guard on duty.',
            'Approved', 'Published', null, $labels('Studio', 2), 7500, 7500);
        $this->props['casa'] = $this->listing($model, self::CASA, 'Boarding House', 'Subangdaku', 'Mandaue City', 10.3380, 123.9300,
            'Private rooms in a family house, quiet street, Wi-Fi included. Jeepneys to Colon and SM City Cebu nearby.',
            'Approved', 'Published', null, $labels('Room', 2), 4500, 4500);
        $this->props['rejected'] = $this->listing($model, self::REJECTED, 'Boarding House', 'Tisa', 'Cebu City', 10.3010, 123.8680,
            'Rooms in Tisa with a shared kitchen.',
            'Rejected', 'Published', 'The photos do not show the rooms being rented and the address pin is on the wrong street. Please upload clear photos of each room and fix the pin, then resubmit.', ['Room 1'], 3800, 3800);
        $this->props['suspended'] = $this->listing($model, self::SUSPENDED, 'Boarding House', 'Mambaling', 'Cebu City', 10.2835, 123.8755,
            'Annex rooms behind the main house in Mambaling.',
            'Approved', 'Suspended', null, ['Room 1'], 3500, 3500);
    }

    /** A listing of Maria's with its units, found by title; photos and amenities copied from one of her seeded listings. */
    private function listing(?Property $model, string $title, string $type, string $barangay, string $city, float $lat, float $lng, string $description, string $verification, string $publication, ?string $reason, array $unitLabels, int $rent, int $deposit): Property
    {
        $property = Property::firstOrCreate(
            ['landlord_id' => $this->maria->user_id, 'title' => $title],
            [
                'description'                  => $description,
                'house_rules'                  => $model?->house_rules,
                'property_type'                => $type,
                'living_arrangement'           => 'Private',
                'occupancy_preference'         => 'No Preference',
                'water_included'               => true,
                'electricity_included'         => false,
                'internet_included'            => true,
                'association_fees_included'    => false,
                'utilities_separately_metered' => true,
                'address'                      => "{$barangay}, {$city}, Cebu",
                'city_municipality'            => $city,
                'barangay'                     => $barangay,
                'latitude'                     => $lat,
                'longitude'                    => $lng,
                'verification_status'          => $verification,
                'rejection_reason'             => $reason,
                'publication_status'           => $publication,
            ]
        );

        if ($property->wasRecentlyCreated && $model) {
            foreach ($model->media as $m) {
                PropertyMedia::create(['property_id' => $property->property_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url]);
            }
            $property->amenities()->sync($model->amenities->pluck('amenity_id')->all());
        }

        $modelUnit = $model?->units->first(fn ($u) => $u->media->isNotEmpty());
        foreach ($unitLabels as $i => $label) {
            $unit = PropertyUnit::firstOrCreate(
                ['property_id' => $property->property_id, 'unit_label' => $label],
                [
                    'floor'               => ($i % 2 === 0) ? '1st floor' : '2nd floor',
                    'bedrooms'            => 1,
                    'bathrooms'           => 1,
                    'floor_area_sqm'      => 22,
                    'furnishing_status'   => 'Semi-furnished',
                    'description'         => 'Private unit with its own entrance, comfort room and kitchen counter. Quiet at night.',
                    'rental_fee'          => $rent,
                    'security_deposit'    => $deposit,
                    'occupancy_limit'     => 2,
                    'availability_status' => 'Available',
                    'verification_status' => 'Approved',
                ]
            );

            if ($unit->wasRecentlyCreated && $modelUnit) {
                foreach ($modelUnit->media as $m) {
                    UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url, 'source' => $m->source, 'caption' => $m->caption]);
                }
            }
        }

        return $property;
    }

    private function unit(string $prop, string $label): PropertyUnit
    {
        return PropertyUnit::where('property_id', $this->props[$prop]->property_id)->where('unit_label', $label)->firstOrFail();
    }

    private function policies(): void
    {
        $make = function (string $name, array $attrs): LeaseTemplate {
            $existing = LeaseTemplate::where('landlord_id', $this->maria->user_id)->where('name', $name)->first();
            if ($existing) {
                return $existing;
            }
            $template = new LeaseTemplate($attrs + ['name' => $name]);
            $template->landlord_id = $this->maria->user_id;
            $template->version = 1;
            $template->save();

            return $template;
        };

        $standardPlus = $make('Friendly: 5 days grace', [
            'welcome_message' => 'Welcome! Rent is due on the date in your agreement; there is a 5-day grace period before a late fee.',
            'rent_grace_days' => 5, 'late_fee_type' => 'fixed', 'late_fee_value' => 200, 'notice_days' => 30,
        ]);
        $strict = $make('Quiet building: 60 days notice', [
            'welcome_message' => 'This building keeps quiet hours from 10 PM. Please read the rules below.',
            'rent_grace_days' => 0, 'late_fee_type' => 'percent', 'late_fee_value' => 5, 'notice_days' => 60,
        ]);

        $this->props['apartment']->forceFill(['lease_template_id' => $standardPlus->lease_template_id])->save();
        $this->unit('apartment', 'Unit 3')->forceFill(['lease_template_id' => $strict->lease_template_id])->save();
    }

    /** Maria's GCash payout account, already approved by the admin, so online payouts work from the first minute. */
    private function payoutAccount(): void
    {
        UserPayoutAccount::updateOrCreate(['user_id' => $this->maria->user_id], [
            'gcash_number'       => '09171234567',
            'gcash_account_name' => 'MARIA SANTOS',
            'accepts_online'     => true,
            'payout_approved_at' => now()->subDays(6),
            'payout_approved_by' => $this->admin->user_id,
        ]);
    }

    /** Axcee has her emergency contact and ID on file, so signing never stops to ask. */
    private function details(User $axcee): void
    {
        TenantDetail::saveFor($axcee, [
            'emergency_contact_name'         => 'Emman Bas',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_number'       => '09181234000',
            'id_type'                        => array_values(TenantDetail::ID_TYPES)[0],
            'id_number'                      => '1234-5678-9012-3456',
        ]);
    }

    // ─── Axcee ───────────────────────────────────────────────────────────────────────────────────────────────────

    /** Moved in three months ago, paid this month, skipped the two months before it: ₱19,000 overdue, always two months. */
    private function axceeOverdueRental(User $axcee): void
    {
        $unit = $this->unit('apartment', 'Unit 1');
        // Due on today's day of the month (at most the 28th): the two earlier months are past due, this month is paid.
        $t = $this->story($axcee->email, 3, min(28, now()->day));
        $reservation = $this->tenancy($axcee, $unit, $t, [1, 2]);

        if (DepositCharge::where('reservation_id', $reservation->reservation_id)->exists()) {
            return;
        }

        DepositCharge::create([
            'reservation_id' => $reservation->reservation_id,
            'amount'         => 500,
            'category'       => 'Cleaning',
            'description'    => 'Deep cleaning of the bathroom after the contractor visit.',
            'charged_at'     => now()->subDays(9),
            'charged_by'     => $this->maria->user_id,
        ]);

        $thread = $reservation->fresh()->tenancyThread;
        if (! $thread) {
            return;
        }

        $this->say($thread, $this->maria->user_id, 'Good morning Axcee, a reminder that September and October rent are still open.', 60 * 30, read: false);
        $concern = TenantConcern::create([
            'conversation_id' => $thread->conversation_id,
            'reservation_id'  => $reservation->reservation_id,
            'tenant_id'       => $axcee->user_id,
            'category'        => 'Electrical',
            'description'     => 'The outlet beside the bed stopped working. The other outlets in the room are fine.',
            'status'          => 'Open',
        ]);
        $this->say($thread, $axcee->user_id, $concern->description, 60 * 3, kind: 'concern', read: false, concernId: $concern->concern_id);
    }

    /** Signed yesterday, direct payments, no QR shared: the move-in payment is the next step. Two unread messages. */
    private function axceeSignedAgreement(User $axcee): void
    {
        $reservation = $this->signedAgreement($axcee, $this->unit('studios', 'Studio 1'), [
            ['tenant', 'Hi po! Is the studio still available for the 1st of next month?', 60 * 24 * 3],
            ['landlord', 'Hello Axcee, yes. I will send the agreement now.', 60 * 24 * 3 - 10],
            ['tenant', 'Thank you po, I will sign tonight.', 60 * 24 * 2],
        ]);

        if (! $reservation || Message::where('conversation_id', $reservation->conversation_id)->where('is_read', false)->exists()) {
            return;
        }

        $thread = $reservation->conversation;
        $this->say($thread, $this->maria->user_id, 'Thank you for signing, Axcee. Your move-in payment is ₱9,500 rent plus the ₱7,500 deposit.', 45, read: false);
        $this->say($thread, $this->maria->user_id, 'You can pay by GCash QR or cash. Message me if you need my QR.', 40, read: false);
    }

    /** Paid online, the admin upheld the move-in dispute and refunded it: the tenant is told the refund is on its way. */
    private function axceeCancelledWithRefund(User $axcee): void
    {
        $unit = $this->unit('casa', 'Room 1');
        if (Reservation::where('tenant_id', $axcee->user_id)->where('unit_id', $unit->unit_id)->exists()) {
            return;
        }

        $conversation = $this->inquiryThread($axcee, $unit);
        $moveIn = now()->subDays(6)->startOfDay();
        $reservation = Reservation::create([
            'tenant_id'            => $axcee->user_id,
            'property_id'          => $unit->property_id,
            'unit_id'              => $unit->unit_id,
            'conversation_id'      => $conversation->conversation_id,
            'reservation_date'     => now()->subDays(12),
            'rental_status'        => 'Rental Agreement Signed',
            'target_move_in_date'  => $moveIn,
            'target_move_out_date' => $moveIn->copy()->addMonths(12),
            'agreed_monthly_rent'  => (float) $unit->rental_fee,
            'rent_due_day'         => 5,
            'occupants_count'      => 1,
            'agreed_at'            => now()->subDays(10),
            'agreed_ip'            => '127.0.0.1',
            'move_in_disputed_at'  => now()->subDays(4),
            'move_in_dispute_reason' => 'The room was not as pictured: the window does not close and there is no bed.',
        ]);

        $breakdown = MoveInPaymentBreakdown::make((float) $unit->rental_fee + (float) $unit->security_deposit, (float) $unit->rental_fee, (float) $unit->security_deposit, $moveIn);
        foreach ($breakdown->rows() as $row) {
            Payment::create($row + [
                'reservation_id'      => $reservation->reservation_id,
                'payment_method'      => 'GCash',
                'status'              => 'Refunded',
                'paid_at'             => now()->subDays(11),
                'refunded_at'         => now()->subHour(),
                'refunded_by'         => $this->admin->user_id,
                'refund_reason'       => 'Move-in dispute upheld: the room was not as listed.',
                'refund_status'       => 'pending',
                'paymongo_payment_id' => 'pay_defense_'.substr(md5($reservation->reservation_id.'casa'), 0, 12),
            ]);
        }

        $this->say($conversation, $axcee->user_id, 'Hello, I would like to take Room 1 starting this week.', 60 * 24 * 12, read: true);
        $this->say($conversation, null, 'An administrator has reviewed this reservation and refunded the held payment to the tenant. The reservation has been cancelled.', 70, kind: 'system');
        $reservation->update(['rental_status' => 'Cancelled', 'move_in_disputed_at' => null, 'move_in_dispute_reason' => null]);
    }

    private function favorites(User $axcee): void
    {
        Property::where('landlord_id', '!=', $this->maria->user_id)->where('verification_status', 'Approved')->where('publication_status', 'Published')
            ->orderBy('property_id')->take(3)->get()
            ->each(fn (Property $p) => Favorite::firstOrCreate(['tenant_id' => $axcee->user_id, 'property_id' => $p->property_id], ['created_at' => now()->subDays(2)]));
    }

    // ─── Other tenants ───────────────────────────────────────────────────────────────────────────────────────────

    /** Kyle signed and asked Maria for her QR 10 minutes ago: she has the notification, the chat line and the marked button. */
    private function kyleQrRequest(): void
    {
        $kyle = $this->person('kyle.bautista@abangananhub.com', 'Kyle', 'Bautista', '09175550301');
        TenantDetail::saveFor($kyle, ['emergency_contact_name' => 'Rina Bautista', 'emergency_contact_relationship' => 'Parent', 'emergency_contact_number' => '09175550302', 'id_type' => array_values(TenantDetail::ID_TYPES)[0], 'id_number' => 'N02-45-778811']);

        $reservation = $this->signedAgreement($kyle, $this->unit('apartment', 'Unit 3'), [
            ['tenant', 'Good day po, I would like Unit 3 from the 1st.', 60 * 24 * 4],
            ['landlord', 'Sure Kyle. I sent the agreement.', 60 * 24 * 4 - 15],
        ]);

        if (! $reservation || $reservation->qr_requested_at !== null) {
            return;
        }

        $reservation->forceFill(['qr_requested_at' => now()->subMinutes(10)])->save();
        $reservation->conversation->postSystemMessage('Kyle asked Maria to share a payment QR.');
        Notification::notify(
            $this->maria->user_id, 'payment', 'Kyle asks for your payment QR',
            'Share it in your chat so they can pay you directly for '.self::APARTMENT.' · Unit 3.',
            route('conversations.index', ['active' => $reservation->conversation_id]),
            $reservation->conversation_id, $reservation,
        );
    }

    /** A year-long rental that ends in 30 days, inside the renewal window. */
    private function liamEndingSoon(): void
    {
        $liam = $this->person('liam.dizon@abangananhub.com', 'Liam', 'Dizon', '09175550311', 'Line cook. Pays on the 5th, never late.');
        $reservation = $this->tenancy($liam, $this->unit('apartment', 'Unit 4'), $this->story($liam->email, 11, 5, [0, -1, 0, 1, 0]));
        $reservation->update(['target_move_out_date' => now()->addDays(30)->toDateString()]);
    }

    /** A rental that ended last month: deposit charge for damage, part of the deposit returned. */
    private function anaCompleted(): void
    {
        $ana = $this->person('ana.rivera@abangananhub.com', 'Ana', 'Rivera', '09175550321', 'Moved to Manila for work. Great tenant.');
        $unit = $this->unit('apartment', 'Unit 5');
        $reservation = $this->tenancy($ana, $unit, $this->story($ana->email, 6, 1, [0, 0, 1, 0, 0, 0]));

        if ($reservation->rental_status === 'Completed') {
            return;
        }

        $reservation->update([
            'rental_status'          => 'Completed',
            'target_move_out_date'   => now()->subDays(20)->toDateString(),
            'deposit_returned_amount' => 1500,
            'deposit_returned_at'    => now()->subDays(18),
        ]);
        $unit->update(['availability_status' => 'Available', 'vacated_at' => now()->subDays(20)]);
        DepositCharge::create([
            'reservation_id' => $reservation->reservation_id,
            'amount'         => 500,
            'category'       => 'Damage',
            'description'    => 'Broken cabinet door in the kitchen.',
            'charged_at'     => now()->subDays(21),
            'charged_by'     => $this->maria->user_id,
        ]);
    }

    /** This month's rent is open; Noemi paid by GCash QR and reported it, so Maria has Confirm and Reject waiting. */
    private function noemiPaymentReport(): void
    {
        $noemi = $this->person('noemi.gutierrez@abangananhub.com', 'Noemi', 'Gutierrez', '09175550331', 'Pharmacy assistant on the day shift.');
        $reservation = $this->tenancy($noemi, $this->unit('apartment', 'Unit 6'), $this->story($noemi->email, 1, 1), [1]);

        $thread = $reservation->fresh()->tenancyThread;
        if ($thread && ! PaymentQrShare::where('conversation_id', $thread->conversation_id)->exists()) {
            $share = PaymentQrShare::create([
                'conversation_id' => $thread->conversation_id,
                'shared_by'       => $this->maria->user_id,
                'payload'         => QrPh::make('MARIA SANTOS'),
                'account_name'    => 'MARIA SANTOS',
                'note'            => 'Rent is due on the 1st. Please tap "I paid" with the reference number after you pay.',
            ]);
            $message = new Message(['conversation_id' => $thread->conversation_id, 'sender_id' => $this->maria->user_id, 'message' => 'Shared a payment QR (MARIA SANTOS)', 'kind' => 'payment_qr', 'payment_qr_share_id' => $share->payment_qr_share_id, 'is_read' => true]);
            $message->sent_at = now()->subHours(5);
            $message->save();
        }

        if ($reservation->paymentClaims()->exists()) {
            return;
        }

        PaymentClaims::file($reservation->fresh(), $noemi, [
            'claim_type'     => 'Monthly',
            'billing_period' => now()->startOfMonth()->toDateString(),
            'amount'         => (float) $reservation->agreed_monthly_rent ?: (float) $reservation->unit->rental_fee,
            'payment_method' => 'GCash',
            'reference_no'   => '5023189774210',
            'paid_on'        => now()->toDateString(),
            'note'           => 'Paid through the QR you shared.',
        ]);
    }

    /** A tenant with no account: moved in two months ago, hands cash to Maria every month. */
    private function walkInCash(): void
    {
        if (User::where('is_walk_in', true)->where('created_by_landlord_id', $this->maria->user_id)->exists()) {
            return;
        }

        $unit = $this->unit('apartment', 'Unit 7');
        $moveIn = now()->subMonths(2)->startOfMonth();
        $tenant = User::create([
            'first_name' => 'Noel', 'last_name' => 'Abarquez', 'password' => Hash::make(Str::random(40)), 'contact_number' => '09175550341',
            'account_status' => 'inactive', 'is_walk_in' => true, 'created_by_landlord_id' => $this->maria->user_id,
        ]);
        $tenant->assignRole('Tenant');

        $reservation = Reservation::create([
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'tenant_id' => $tenant->user_id,
            'reservation_date' => $moveIn->copy()->subDays(5), 'target_move_in_date' => $moveIn->toDateString(),
            'agreed_monthly_rent' => $unit->rental_fee, 'rent_due_day' => 5, 'occupants_count' => 1,
            'rental_status' => 'Occupied', 'remarks' => 'Agreed in person.',
        ]);
        $reservation->update(['lease_snapshot' => LeaseTerms::snapshot($reservation)]);
        $unit->update(['availability_status' => 'Occupied']);

        MoveInPaymentRecorder::record($reservation, $unit, [
            'initial_amount' => (float) $unit->rental_fee + (float) $unit->security_deposit, 'agreed_monthly_rent' => (float) $unit->rental_fee,
            'move_in_date' => $moveIn->toDateString(), 'payment_method' => 'Cash', 'payment_date' => $moveIn,
        ], $this->maria->user_id);

        $story = ['email' => 'noel.abarquez', 'due_day' => 5, 'pay_offsets' => [0, 1], 'cash_every' => 1, 'methods' => ['Cash']];
        for ($m = 1; $m <= 2; $m++) {
            $this->monthlyPayment($reservation, $story, $moveIn->copy()->addMonths($m), $m, (float) $unit->rental_fee, $this->maria->user_id);
        }
    }

    /** The three payment stories the base seed may not have room for: half paid, two months ahead, cash every month. */
    private function paymentStories(): void
    {
        $rico = $this->person('rico.salazar@abangananhub.com', 'Rico', 'Salazar', '09175550361', 'Delivery rider. Pays half when the week is slow (partial until the 28th, then overdue for the rest).');
        $this->tenancy($rico, $this->unit('apartment', 'Unit 9'), $this->story($rico->email, 4, 28, [0, 2, 1, 0]), [], [4]);

        $selena = $this->person('selena.tan@abangananhub.com', 'Selena', 'Tan', '09175550371', 'Remote editor who pays months ahead: this month and the next two are already settled.');
        $this->tenancy($selena, $this->unit('apartment', 'Unit 10'), ['advance' => 4] + $this->story($selena->email, 2, 1, [-1, 0]));

        $jun = $this->person('jun.pascual@abangananhub.com', 'Jun', 'Pascual', '09175550381', 'Hands the rent to Maria in cash on the 5th.');
        $this->tenancy($jun, $this->unit('apartment', 'Unit 11'), ['cash_every' => 1, 'methods' => ['Cash']] + $this->story($jun->email, 3, 5, [0, 1, 0]));
    }

    /** A brand-new inquiry and a viewing request from the same tenant: both wait for Maria. */
    private function biancaInquiryAndViewing(): void
    {
        $bianca = $this->person('bianca.uy@abangananhub.com', 'Bianca', 'Uy', '09175550351');
        $unit = $this->unit('apartment', 'Unit 8');

        if (Reservation::where('tenant_id', $bianca->user_id)->where('unit_id', $unit->unit_id)->exists()) {
            return;
        }

        $conversation = $this->inquiryThread($bianca, $unit);
        $reservation = Reservation::create([
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'tenant_id' => $bianca->user_id,
            'conversation_id' => $conversation->conversation_id, 'reservation_date' => now()->subHours(5), 'rental_status' => 'Inquiry',
            'target_move_in_date' => now()->addDays(20)->toDateString(), 'target_move_out_date' => now()->addDays(20)->addMonths(12)->toDateString(),
        ]);

        $this->say($conversation, $bianca->user_id, '', 60 * 5, kind: 'inquiry_summary', read: false);
        $this->say($conversation, $bianca->user_id, 'Hi po! I work in Consolacion and need a unit near the town centre. May I see Unit 8 this week?', 60 * 4, read: false);

        ViewingRequest::create([
            'source' => 'Online', 'reservation_id' => $reservation->reservation_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'tenant_id' => $bianca->user_id, 'landlord_id' => $this->maria->user_id, 'scheduled_at' => now()->addDays(3)->setTime(15, 0),
            'status' => 'Pending', 'proposed_by' => $bianca->user_id,
        ]);
    }

    /** Three tenants reviewed the apartments and Maria has not answered any of them. */
    private function reviews(): void
    {
        $property = $this->props['apartment'];
        foreach ([
            ['ana.rivera@abangananhub.com', 5, 'Clean, quiet and the landlord answers fast. I stayed six months and would rent here again.'],
            ['liam.dizon@abangananhub.com', 4, 'Good value near the market. Water pressure is weak on the second floor.'],
            ['noemi.gutierrez@abangananhub.com', 3, 'Fine for the price. I wish the Wi-Fi were faster in the evening.'],
        ] as [$email, $rating, $comment]) {
            $tenant = User::where('email', $email)->first();
            if ($tenant) {
                Review::firstOrCreate(
                    ['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id],
                    ['landlord_id' => $this->maria->user_id, 'rating' => $rating, 'review_comment' => $comment]
                );
            }
        }
    }

    private function cleanTenant(): void
    {
        $this->person('tenant.new@abangananhub.com', 'New', 'Tenant', '09175550399');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────────────────────────────────

    private function person(string $email, string $first, string $last, string $contact, ?string $bio = null): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'first_name' => $first, 'last_name' => $last, 'password' => Hash::make('password'),
            'contact_number' => $contact, 'bio' => $bio, 'profile_visibility' => 'public', 'account_status' => 'active',
        ]);
        UserRole::firstOrCreate(['user_id' => $user->user_id, 'role' => 'Tenant']);

        return $user;
    }

    /** The shape TenantSeeder's payment helpers read. */
    private function story(string $email, int $monthsIn, int $dueDay, array $offsets = [0]): array
    {
        return [
            'email' => $email, 'months_in' => $monthsIn, 'occupants' => 1, 'due_day' => $dueDay,
            'pay_offsets' => $offsets, 'cash_every' => 0, 'methods' => ['GCash'], 'advance' => 0, 'current' => 'paid',
        ];
    }

    /**
     * A running tenancy, written the way TenantSeeder::rent() writes it, except that the months listed in $unpaid
     * (1 = the month after move-in) are skipped and the ones in $half are paid half. Found again, not repeated, when the tenant already has this unit.
     */
    private function tenancy(User $tenant, PropertyUnit $unit, array $t, array $unpaid = [], array $half = []): Reservation
    {
        $existing = Reservation::where('tenant_id', $tenant->user_id)->where('unit_id', $unit->unit_id)
            ->whereIn('rental_status', ['Occupied', 'Completed'])->first();
        if ($existing) {
            return $existing;
        }

        $moveIn = now()->subMonths($t['months_in'])->startOfMonth();
        $rent = (float) $unit->rental_fee;
        $deposit = (float) ($unit->security_deposit ?? $rent);

        $reservation = Reservation::create([
            'tenant_id'                   => $tenant->user_id,
            'property_id'                 => $unit->property_id,
            'unit_id'                     => $unit->unit_id,
            'rental_status'               => 'Occupied',
            'reservation_date'            => $moveIn->copy()->subDays(10),
            'target_move_in_date'         => $moveIn,
            'target_move_out_date'        => $moveIn->copy()->addMonths(12),
            'agreed_monthly_rent'         => $rent,
            'rent_due_day'                => $t['due_day'],
            'occupants_count'             => 1,
            'agreement_terms_notes'       => 'Rent due on day '.$t['due_day'].' of each month.',
            'agreed_at'                   => $moveIn->copy()->subDays(8),
            'agreed_ip'                   => '127.0.0.1',
            'landlord_tc_accepted_at'     => $moveIn->copy()->subDays(8),
            'tenant_tc_accepted_at'       => $moveIn->copy()->subDays(8),
            'keys_turned_over_at'         => $moveIn,
            'tenant_confirmed_move_in_at' => $moveIn,
            'handover_at'                 => $moveIn,
            'handover_confirmed_at'       => $moveIn,
        ]);
        $unit->update(['availability_status' => 'Occupied']);

        $covered = $this->moveInPayment($reservation, $t, $moveIn, $rent, $deposit);
        for ($m = 1; $m <= $t['months_in']; $m++) {
            $period = $moveIn->copy()->addMonths($m);
            if (in_array($period->format('Y-m'), $covered, true) || in_array($m, $unpaid, true)) {
                continue;
            }
            $this->monthlyPayment($reservation, $t, $period, $m, in_array($m, $half, true) ? round($rent / 2, 2) : $rent, $this->maria->user_id);
        }

        return $reservation;
    }

    /** Inquiry, negotiation, agreement sent and signed yesterday, ending where the move-in payment is the next step. */
    private function signedAgreement(User $tenant, PropertyUnit $unit, array $chat): ?Reservation
    {
        $existing = Reservation::where('tenant_id', $tenant->user_id)->where('unit_id', $unit->unit_id)->first();
        if ($existing) {
            return $existing->load('conversation');
        }

        $conversation = $this->inquiryThread($tenant, $unit);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id, 'reservation_date' => now()->subDays(5), 'rental_status' => 'Inquiry',
            'target_move_in_date' => now()->addDays(4)->toDateString(), 'target_move_out_date' => now()->addDays(4)->addMonths(12)->toDateString(),
            'occupants_count' => 1,
        ]);

        $this->say($conversation, $tenant->user_id, '', max(array_column($chat, 2)) + 60, kind: 'inquiry_summary');
        foreach ($chat as [$from, $text, $minutesAgo]) {
            $this->say($conversation, $from === 'tenant' ? $tenant->user_id : $this->maria->user_id, $text, $minutesAgo);
        }

        $reservation->advanceToNegotiation();
        $reservation->advanceToPendingAgreement([
            'agreement_terms_notes' => 'Quiet hours from 10 PM. No overnight guests without notice.',
            'agreed_monthly_rent'   => (float) $unit->rental_fee,
            'rent_due_day'          => 5,
            'security_deposit'      => (float) $unit->security_deposit,
            'advance_months'        => 0,
        ]);
        $reservation->refresh()->signAgreement('127.0.0.1');
        // Signed yesterday: about two days are left to pay before the reservation closes by itself.
        $reservation->forceFill([
            'agreed_at' => now()->subDay(), 'tenant_tc_accepted_at' => now()->subDay(), 'landlord_tc_accepted_at' => now()->subDays(2),
        ])->save();
        $this->say($conversation, null, $tenant->name.' signed the rental agreement.', 60 * 24, kind: 'system');

        return $reservation->fresh()->load('conversation');
    }

    private function inquiryThread(User $tenant, PropertyUnit $unit): Conversation
    {
        return Conversation::firstOrCreate(
            ['tenant_id' => $tenant->user_id, 'landlord_id' => $this->maria->user_id, 'property_id' => $unit->property_id, 'type' => Conversation::TYPE_INQUIRY],
            ['unit_id' => $unit->unit_id]
        );
    }

    /** One chat line, dated minutes ago. Unread by the other side when $read is false. */
    private function say(Conversation $conversation, ?int $senderId, string $text, int $minutesAgo, string $kind = 'user', bool $read = true, ?int $concernId = null): void
    {
        $message = new Message([
            'conversation_id' => $conversation->conversation_id,
            'sender_id'       => $senderId,
            'message'         => $text,
            'kind'            => $kind,
            'concern_id'      => $concernId,
            'is_read'         => $senderId === null ? true : $read,
        ]);
        $message->sent_at = Carbon::now()->subMinutes($minutesAgo);
        $message->save();
    }
}
