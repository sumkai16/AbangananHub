<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\LandlordVerification;
use App\Models\Message;
use App\Models\PaymentQrShare;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\TenantConcern;
use App\Models\UnitMedia;
use App\Models\User;
use App\Models\UserRole;
use App\Models\ViewingRequest;
use App\Services\MoveInPaymentRecorder;
use App\Support\LeaseTerms;
use App\Support\QrPh;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything docs/demo-flow needs on top of the base seed, so the landlord demo
 * (Maria Santos) has something to show in every act.
 *
 * Adds, all for landlord@abangananhub.com:
 *  - viewable sample ID / selfie images for the seeded verifications (the base
 *    seed only stores placeholder paths, so the admin review screen showed broken images)
 *  - five more platform tenancies with different rent stories
 *  - two walk-in tenants (one moved in with cash payments, one reserved)
 *  - three requests at different pipeline stages, with chat and viewings
 *  - one listing waiting in the admin approval queue
 *
 * Runs last. Re-runnable: tenants and the pending listing are found by email /
 * title, and a request, tenancy or walk-in is skipped when its person already has one.
 * Leaves KEEP_FREE units free so the walk-in form still has something to pick live.
 *
 * Must run after PropertySeeder and TenantSeeder. Extends TenantSeeder to reuse
 * the code that writes rent the way the app writes it.
 */
class DemoSeeder extends TenantSeeder
{
    private const KEEP_FREE = 3;

    /** Platform tenants who already moved in. Keys match TenantSeeder::TENANTS. */
    private const TENANCIES = [
        [
            'first_name' => 'Liza', 'last_name' => 'Montalbo', 'email' => 'liza@abangananhub.com',
            'contact' => '09171230021', 'months_in' => 6, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0, 0, -1, 0, 0, 0], 'cash_every' => 0, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'paid',
            'bio' => 'Nursing student. Pays on the 1st, every month.',
        ],
        [
            'first_name' => 'Carlo', 'last_name' => 'Bacalso', 'email' => 'carlo@abangananhub.com',
            'contact' => '09171230022', 'months_in' => 4, 'occupants' => 1, 'due_day' => 5,
            'pay_offsets' => [2, 6, 4, 9], 'cash_every' => 0, 'methods' => ['GCash', 'Maya'],
            'advance' => 0, 'current' => 'unpaid',
            'bio' => 'Delivery rider with irregular pay days. Usually a week late.',
        ],
        [
            'first_name' => 'Mylene', 'last_name' => 'Tampus', 'email' => 'mylene@abangananhub.com',
            'contact' => '09171230023', 'months_in' => 3, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [-2, 0, -1], 'cash_every' => 0, 'methods' => ['GCash'],
            'advance' => 2, 'current' => 'paid',
            'bio' => 'Works remotely and pays a few months ahead.',
        ],
        [
            'first_name' => 'Joel', 'last_name' => 'Cuizon', 'email' => 'joel@abangananhub.com',
            'contact' => '09171230024', 'months_in' => 5, 'occupants' => 1, 'due_day' => 28,
            'pay_offsets' => [0, 1, 3, 0, 2], 'cash_every' => 2, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'partial',
            'bio' => 'Hands over cash on some months and pays half when short.',
        ],
        [
            'first_name' => 'Jenny', 'last_name' => 'Ybañez', 'email' => 'jenny@abangananhub.com',
            'contact' => '09171230025', 'months_in' => 1, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0], 'cash_every' => 0, 'methods' => ['QRPh'],
            'advance' => 0, 'current' => 'paid',
            'bio' => 'Just moved in last month.',
        ],
    ];

    /** People who inquired but have not moved in, in pipeline order. */
    private const REQUESTS = [
        [
            'first_name' => 'Hannah', 'last_name' => 'Villaflor', 'email' => 'hannah@abangananhub.com', 'contact' => '09171230031',
            'stage' => 'Inquiry', 'move_in_in' => 21, 'viewing' => ['Pending', 2, 15],
            'chat' => [
                ['tenant', 'Hi po! Is this room still available? I am a USC student and I need a place starting next month.', 190],
                ['tenant', 'Can I visit this week?', 185],
            ],
        ],
        [
            'first_name' => 'Paolo', 'last_name' => 'Estrella', 'email' => 'paolo@abangananhub.com', 'contact' => '09171230032',
            'stage' => 'Under Negotiation', 'move_in_in' => 14, 'viewing' => ['Confirmed', 3, 11],
            'chat' => [
                ['tenant', 'Good day! I work near Colon. How much is the deposit and advance?', 2900],
                ['landlord', 'Hello Paolo. One month deposit and one month advance. Rent is due on the 5th.', 2850],
                ['tenant', 'Okay po. Can I move in on the 15th?', 2800],
                ['landlord', 'Yes, that works. Please come and see the room first.', 2790],
            ],
        ],
        [
            'first_name' => 'Gwen', 'last_name' => 'Lumapas', 'email' => 'gwen@abangananhub.com', 'contact' => '09171230033',
            'stage' => 'Pending Rental Agreement', 'move_in_in' => 9, 'viewing' => null,
            'chat' => [
                ['tenant', 'Hello! I saw the room and I would like to take it.', 5800],
                ['landlord', 'Great. I will send the rental agreement now. Please read it and sign.', 5700],
                ['tenant', 'Thank you po, I will sign tonight.', 5650],
            ],
        ],
    ];

    public function run(): void
    {
        $maria = User::where('email', 'landlord@abangananhub.com')->firstOrFail();

        $this->sampleVerificationImages();
        $this->requests($maria);
        $this->sharePaymentQr($maria);
        $this->walkIns($maria);
        $this->tenancies($maria);
        $this->tenancyChats($maria);
        $this->pendingListing($maria);
    }

    /** The base seed stores image paths that point at nothing. Put a sample picture at each. */
    private function sampleVerificationImages(): void
    {
        $id     = file_get_contents(__DIR__.'/assets/sample-id.jpg');
        $selfie = file_get_contents(__DIR__.'/assets/sample-selfie.jpg');

        LandlordVerification::where('government_id', 'like', 'verifications/seed-placeholder%')
            ->get()
            ->each(function (LandlordVerification $v) use ($id, $selfie) {
                Storage::disk('local')->put($v->government_id, $id);
                if ($v->selfie) {
                    Storage::disk('local')->put($v->selfie, $selfie);
                }
            });
    }

    private function requests(User $maria): void
    {
        $units = $this->freeUnits($maria)->unique('property_id')->values();

        foreach (self::REQUESTS as $i => $r) {
            $unit   = $units[$i] ?? null;
            $tenant = $this->tenant($r);

            if (! $unit || Reservation::where('tenant_id', $tenant->user_id)->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)->exists()) {
                continue;
            }

            $conversation = Conversation::create([
                'tenant_id'   => $tenant->user_id,
                'landlord_id' => $maria->user_id,
                'property_id' => $unit->property_id,
                'unit_id'     => $unit->unit_id,
            ]);

            $reservation = Reservation::create([
                'property_id'         => $unit->property_id,
                'unit_id'             => $unit->unit_id,
                'tenant_id'           => $tenant->user_id,
                'conversation_id'     => $conversation->conversation_id,
                'reservation_date'    => now()->subDays(3),
                'rental_status'       => 'Inquiry',
                'target_move_in_date' => now()->addDays($r['move_in_in'])->toDateString(),
                'target_move_out_date' => now()->addDays($r['move_in_in'])->addMonths(12)->toDateString(),
            ]);

            // The inquiry card is the first thing sent, so it is older than every chat line.
            $this->message($conversation, $tenant->user_id, '', max(array_column($r['chat'], 2)) + 60, summary: true);
            foreach ($r['chat'] as [$from, $text, $minutesAgo]) {
                $this->message($conversation, $from === 'tenant' ? $tenant->user_id : $maria->user_id, $text, $minutesAgo);
            }

            if (in_array($r['stage'], ['Under Negotiation', 'Pending Rental Agreement'], true)) {
                $reservation->advanceToNegotiation();
            }
            if ($r['stage'] === 'Pending Rental Agreement') {
                $reservation->advanceToPendingAgreement('Quiet hours from 10 PM. No visitors overnight.');
            }

            if ($r['viewing']) {
                [$status, $days, $hour] = $r['viewing'];
                ViewingRequest::create([
                    'source'         => 'Online',
                    'reservation_id' => $reservation->reservation_id,
                    'property_id'    => $unit->property_id,
                    'unit_id'        => $unit->unit_id,
                    'tenant_id'      => $tenant->user_id,
                    'landlord_id'    => $maria->user_id,
                    'scheduled_at'   => now()->addDays($days)->setTime($hour, 0),
                    'status'         => $status,
                    'proposed_by'    => $tenant->user_id,
                    'responded_at'   => $status === 'Confirmed' ? now()->subHour() : null,
                ]);
            }
        }

        // A visit arranged by phone, logged by the landlord. No tenant account behind it.
        $unit = $this->freeUnits($maria)->first();
        if ($unit && ! ViewingRequest::where('landlord_id', $maria->user_id)->where('source', 'Offline')->exists()) {
            ViewingRequest::create([
                'source'        => 'Offline',
                'property_id'   => $unit->property_id,
                'unit_id'       => $unit->unit_id,
                'visitor_name'  => 'Ramil Dagatan',
                'visitor_phone' => '09175550142',
                'landlord_id'   => $maria->user_id,
                'scheduled_at'  => now()->addDay()->setTime(10, 0),
                'status'        => 'Confirmed',
                'proposed_by'   => $maria->user_id,
                'note'          => 'Called to ask about the second-floor bed.',
            ]);
        }
    }

    /**
     * Maria has shared her payment QR with the tenant whose agreement is waiting to be signed, so the demo can go
     * straight from signing to "I paid" to confirming. The code is a made-up QR Ph payload (valid checksum, not a real account).
     */
    private function sharePaymentQr(User $maria): void
    {
        $reservation = Reservation::where('rental_status', 'Pending Rental Agreement')
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $maria->user_id))
            ->whereNotNull('conversation_id')
            ->first();

        if (! $reservation || PaymentQrShare::where('conversation_id', $reservation->conversation_id)->exists()) {
            return;
        }

        $share = PaymentQrShare::create([
            'conversation_id' => $reservation->conversation_id,
            'shared_by'       => $maria->user_id,
            'payload'         => QrPh::make('MARIA SANTOS'),
            'account_name'    => 'MARIA SANTOS',
            'note'            => 'Please pay on or before your move-in date, then tap "I paid" with the reference number.',
        ]);

        $message = new Message([
            'conversation_id'     => $reservation->conversation_id,
            'sender_id'           => $maria->user_id,
            'message'             => 'Shared a payment QR (MARIA SANTOS)',
            'kind'                => 'payment_qr',
            'payment_qr_share_id' => $share->payment_qr_share_id,
            'is_read'             => true,
        ]);
        $message->sent_at = now()->subMinutes(20);
        $message->save();
    }

    private function walkIns(User $maria): void
    {
        $units = $this->freeUnits($maria);

        // Moved in three months ago and pays cash every month.
        $moveIn = now()->subMonths(3)->startOfMonth();
        $story  = ['email' => 'tomas.rubio', 'due_day' => 5, 'pay_offsets' => [0, 1, 2], 'cash_every' => 1, 'methods' => ['Cash']];

        $this->walkIn($maria, $units->shift(), 'Tomas', 'Rubio', '09175550177', $moveIn, 'Occupied', function (Reservation $r, PropertyUnit $unit) use ($maria, $moveIn, $story) {
            MoveInPaymentRecorder::record($r, $unit, [
                'initial_amount'      => (float) $unit->rental_fee + (float) $unit->security_deposit,
                'agreed_monthly_rent' => (float) $unit->rental_fee,
                'move_in_date'        => $moveIn->toDateString(),
                'payment_method'      => 'Cash',
                'payment_date'        => $moveIn,
            ], $maria->user_id);

            for ($m = 1; $m <= 3; $m++) {
                $this->monthlyPayment($r, $story, $moveIn->copy()->addMonths($m), $m, (float) $unit->rental_fee, $maria->user_id);
            }
        });

        // Agreed offline, moves in next month. Shows the Reserved state.
        $this->walkIn($maria, $units->shift(), 'Nena', 'Gabutan', '09175550188', now()->addMonth()->startOfMonth(), 'Reserved', fn () => null);
    }

    private function walkIn(User $maria, ?PropertyUnit $unit, string $first, string $last, string $phone, Carbon $moveIn, string $status, callable $payments): void
    {
        $existing = User::where('is_walk_in', true)->where('created_by_landlord_id', $maria->user_id)
            ->where('first_name', $first)->where('last_name', $last)->first();

        if (! $unit || $existing) {
            return;
        }

        $tenant = User::create([
            'first_name'             => $first,
            'last_name'              => $last,
            'password'               => Hash::make(Str::random(40)),
            'contact_number'         => $phone,
            'account_status'         => 'inactive',
            'is_walk_in'             => true,
            'created_by_landlord_id' => $maria->user_id,
        ]);
        $tenant->assignRole('Tenant');

        $reservation = Reservation::create([
            'property_id'          => $unit->property_id,
            'unit_id'              => $unit->unit_id,
            'tenant_id'            => $tenant->user_id,
            'reservation_date'     => $moveIn->copy()->subDays(5),
            'target_move_in_date'  => $moveIn->toDateString(),
            'agreed_monthly_rent'  => $unit->rental_fee,
            'rent_due_day'         => 5,
            'occupants_count'      => 1,
            'rental_status'        => $status,
            'remarks'              => 'Agreed in person.',
        ]);
        $reservation->update(['lease_snapshot' => LeaseTerms::snapshot($reservation)]);
        $unit->update(['availability_status' => $status]);

        $payments($reservation, $unit);
    }

    private function tenancies(User $maria): void
    {
        foreach (self::TENANCIES as $t) {
            $unit = $this->freeUnits($maria)->skip(self::KEEP_FREE)->first();
            if (! $unit) {
                return;
            }

            $tenant = $this->tenant($t);
            if (Reservation::where('tenant_id', $tenant->user_id)->where('rental_status', 'Occupied')->exists()) {
                continue;
            }

            $this->rent($tenant, $unit, $t);
        }
    }

    /**
     * Some life in the post-move-in chat: two tenants talking to Maria, one with an open
     * plumbing concern and a fixed electrical one. The threads themselves already exist,
     * opened by ReservationObserver when each tenancy was created as Occupied.
     */
    private function tenancyChats(User $maria): void
    {
        $scenes = [
            'mylene@abangananhub.com' => [
                'chat' => [
                    ['tenant', 'Good morning po! Just confirming I will pay for 2 more months in advance this week.', 4300],
                    ['landlord', 'Thank you Mylene. I will send the receipt once it arrives.', 4280],
                ],
                'concerns' => [
                    ['Electrical', 'The outlet beside my bed stopped working. Other outlets are fine.', 'Fixed', 3000],
                    ['Plumbing', 'The bathroom faucet has been dripping since yesterday, even when fully closed.', 'Open', 90],
                ],
            ],
            'joel@abangananhub.com' => [
                'chat' => [
                    ['tenant', 'Hi po, can I pay half today and the rest on Friday?', 600],
                ],
                'concerns' => [],
            ],
        ];

        foreach ($scenes as $email => $scene) {
            $tenant = User::where('email', $email)->first();
            $thread = $tenant
                ? Conversation::tenancies()->where('tenant_id', $tenant->user_id)->where('landlord_id', $maria->user_id)->first()
                : null;
            if (! $thread || $thread->messages()->exists()) {
                continue;
            }

            foreach ($scene['chat'] as [$from, $text, $minutesAgo]) {
                $this->message($thread, $from === 'tenant' ? $tenant->user_id : $maria->user_id, $text, $minutesAgo);
            }

            foreach ($scene['concerns'] as [$category, $description, $status, $minutesAgo]) {
                $concern = TenantConcern::create([
                    'conversation_id' => $thread->conversation_id,
                    'reservation_id'  => $thread->reservation_id,
                    'tenant_id'       => $tenant->user_id,
                    'category'        => $category,
                    'description'     => $description,
                    'status'          => $status,
                    'fixed_at'        => $status === 'Fixed' ? now()->subMinutes($minutesAgo - 600) : null,
                ]);
                $this->message($thread, $tenant->user_id, $description, $minutesAgo, concernId: $concern->concern_id);

                if ($status === 'Fixed') {
                    $this->systemLine($thread, "{$maria->first_name} marked the \"{$category}\" concern as fixed.", $concern->concern_id, $minutesAgo - 600);
                }
            }
        }
    }

    /** A listing the admin has not approved yet, so /admin/listings/approval has something in it. */
    private function pendingListing(User $maria): void
    {
        $model = Property::where('landlord_id', $maria->user_id)->where('property_type', 'Boarding House')
            ->where('verification_status', 'Approved')->with(['media', 'units.media'])->first();
        if (! $model) {
            return;
        }

        $property = Property::firstOrCreate(
            ['landlord_id' => $maria->user_id, 'title' => 'Guadalupe Student Rooms'],
            [
                'description'                  => 'New rooms two streets from USC Main Campus. Wi-Fi included, shared kitchen, quiet at night.',
                'house_rules'                  => $model->house_rules,
                'property_type'                => 'Boarding House',
                'living_arrangement'           => 'Private',
                'occupancy_preference'         => 'No Preference',
                'water_included'               => true,
                'electricity_included'         => false,
                'internet_included'            => true,
                'association_fees_included'    => false,
                'utilities_separately_metered' => true,
                'address'                      => 'Guadalupe, Cebu City, Cebu',
                'city_municipality'            => 'Cebu City',
                'barangay'                     => 'Guadalupe',
                'latitude'                     => 10.3270,
                'longitude'                    => 123.8920,
                'verification_status'          => 'Pending',
                'publication_status'           => 'Published',
            ]
        );

        if (! $property->wasRecentlyCreated) {
            return;
        }

        foreach ($model->media as $m) {
            PropertyMedia::create(['property_id' => $property->property_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url]);
        }

        $modelUnit = $model->units->first();
        foreach ([['Room 1', 4200], ['Room 2', 4500]] as [$label, $fee]) {
            $unit = $property->units()->create([
                'unit_label'          => $label,
                'floor'               => '2nd floor',
                'bedrooms'            => 1,
                'bathrooms'           => 1,
                'floor_area_sqm'      => 14,
                'furnishing_status'   => 'Semi-furnished',
                'description'         => 'Private room with its own door and lock. Bed frame and cabinet provided.',
                'rental_fee'          => $fee,
                'security_deposit'    => $fee,
                'occupancy_limit'     => 1,
                'availability_status' => 'Available',
                'verification_status' => 'Pending',
            ]);

            foreach ($modelUnit?->media ?? [] as $m) {
                UnitMedia::create([
                    'unit_id'    => $unit->unit_id,
                    'media_type' => $m->media_type,
                    'media_url'  => $m->media_url,
                    'source'     => $m->source,
                    'caption'    => $m->caption,
                ]);
            }
        }
    }

    /** Maria's units a tenant could be placed in right now, in listing order. */
    private function freeUnits(User $maria)
    {
        return PropertyUnit::with('property')
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $maria->user_id)->where('verification_status', 'Approved'))
            ->whereDoesntHave('reservations', fn ($q) => $q->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
            ->orderBy('property_id')->orderBy('unit_id')
            ->get();
    }

    private function tenant(array $t): User
    {
        $tenant = User::firstOrCreate(
            ['email' => $t['email']],
            [
                'first_name'         => $t['first_name'],
                'last_name'          => $t['last_name'],
                'password'           => Hash::make('password'),
                'contact_number'     => $t['contact'],
                'bio'                => $t['bio'] ?? null,
                'profile_visibility' => 'public',
                'account_status'     => 'active',
            ]
        );
        UserRole::firstOrCreate(['user_id' => $tenant->user_id, 'role' => 'Tenant']);

        return $tenant;
    }

    private function message(Conversation $conversation, int $senderId, string $text, int $minutesAgo, bool $summary = false, ?int $concernId = null): void
    {
        $message = new Message([
            'conversation_id'    => $conversation->conversation_id,
            'sender_id'          => $senderId,
            'message'            => $text,
            'kind'               => $summary ? 'inquiry_summary' : ($concernId ? 'concern' : 'user'),
            'concern_id'         => $concernId,
            // The landlord has not read what the tenant sent, so the unread badge shows in the demo.
            'is_read'            => $senderId === $conversation->landlord_id,
        ]);
        $message->sent_at = now()->subMinutes($minutesAgo);
        $message->save();
    }

    private function systemLine(Conversation $conversation, string $text, ?int $concernId, int $minutesAgo): void
    {
        $message = new Message([
            'conversation_id' => $conversation->conversation_id,
            'sender_id'       => null,
            'message'         => $text,
            'kind'            => 'system',
            'is_read'         => true,
            'concern_id'      => $concernId,
        ]);
        $message->sent_at = now()->subMinutes($minutesAgo);
        $message->save();
    }
}
