<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\DepositCharge;
use App\Models\HoldMatchAlert;
use App\Models\LandlordVerification;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Models\UnitMedia;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Models\UserRole;
use App\Support\LeaseTerms;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * The ADMIN side of the capstone-defense world: every sidebar item and queue of the admin panel has something real
 * to show. Runs after the base seeders (UserSeeder, LandlordSeeder, AmenitySeeder, PropertySeeder, TenantSeeder,
 * ReviewSeeder, ReportSeeder, DemoSeeder) and before DefenseRentalsSeeder.
 *
 * Re-runnable (everything is found by email, title or natural key before it is created), deterministic (no
 * randomness) and dated relative to now(), so "pending for 2 days" stays true on the day. New accounts are
 * `@abangananhub.com` with the password `password`. It never uses an image URL: new listings copy the media rows
 * of a seeded listing, and verification and document files are the two sample images in database/seeders/assets.
 *
 * Money rows are written without model events on purpose (no payout job queued, no notification storm, no
 * broadcast): the seeder describes a state, it does not replay the flows that lead to it.
 *
 * Landlords used (by the listing they own): Ramon Alcoseba (Maguikay Duplex Apartments, Liloan Poblacion Apartment),
 * Teresita Ouano (Gabi Rooms, Maribago Staff Rooms), Gerardo Tudtud (Tunghaan Rooms, Naga). Maria Santos is left to
 * DefenseRentalsSeeder. Five of the seven seeded GCash accounts are approved; Cristina Gonzaga's and Wilfredo
 * Lozada's are left waiting, which is the Payouts "to approve" list.
 */
class DefenseAdminSeeder extends Seeder
{
    /** Every scenario this seeder puts on screen, for the demo script (docs/defense-script.md). */
    public const SCENARIOS = [
        ['key' => 'verifications_pending', 'who' => 'admin@abangananhub.com', 'where' => '/admin/verifications?status=Pending', 'shows' => 'Three landlord applications waiting, two with viewable ID and selfie, name-read and face-match results (match, and a "review by eye" case).'],
        ['key' => 'verification_rejected', 'who' => 'admin@abangananhub.com', 'where' => '/admin/verifications?status=Rejected', 'shows' => 'A rejected application with the admin note about the blurry ID, for the resubmission story.'],
        ['key' => 'listings_pending', 'who' => 'admin@abangananhub.com', 'where' => '/admin/listings/approval?status=Pending', 'shows' => 'Listings waiting for approval: one complete (photos and three units), one thin (no photos, one unit).'],
        ['key' => 'documents_pending', 'who' => 'admin@abangananhub.com', 'where' => '/admin/documents?status=Pending', 'shows' => 'Three uploaded property documents (tax declaration, business permit, proof of ownership) to verify or reject.'],
        ['key' => 'documents_decided', 'who' => 'admin@abangananhub.com', 'where' => '/admin/documents?status=All', 'shows' => 'One verified proof of ownership (the listing earns the Verified badge), one rejected expired certificate, one document still requested.'],
        ['key' => 'units_pending', 'who' => 'admin@abangananhub.com', 'where' => '/admin/catalogue/units?needs=approval', 'shows' => 'Two new units added to already-approved listings, each needing its own approval.'],
        ['key' => 'reports_pending', 'who' => 'admin@abangananhub.com', 'where' => '/admin/reports?status=Pending', 'shows' => 'Nine pending reports against listings and users, with real reasons.'],
        ['key' => 'reports_resolved', 'who' => 'admin@abangananhub.com', 'where' => '/admin/reports?status=Resolved', 'shows' => 'Resolved reports with notes and the action taken (warn, delist, suspend, no action).'],
        ['key' => 'refund_dispute', 'who' => 'admin@abangananhub.com', 'where' => '/admin/refund-requests?type=disputes', 'shows' => 'A move-in dispute on rent and deposit held online: the tenant, the reason, and the Refund and Release buttons (Refund calls PayMongo, so it only works with real test keys; Release works offline).'],
        ['key' => 'refund_escalation', 'who' => 'admin@abangananhub.com', 'where' => '/admin/refund-requests?type=escalations', 'shows' => 'A "Refund not returned" escalation: a landlord kept ₱3,500 paid directly, the tenant sent it to admin, and the report says AbangananHub cannot refund it.'],
        ['key' => 'payouts_owed', 'who' => 'admin@abangananhub.com', 'where' => '/admin/payouts', 'shows' => 'A landlord owed money (released deposit and rent paid online) with a pay-out action.'],
        ['key' => 'payouts_to_approve', 'who' => 'admin@abangananhub.com', 'where' => '/admin/payouts', 'shows' => 'Two GCash payout accounts waiting for approval, each with the name check against the landlord\'s verified ID.'],
        ['key' => 'payouts_failed', 'who' => 'admin@abangananhub.com', 'where' => '/admin/payouts', 'shows' => 'A failed, unresolved payout (GCash declined the transfer) with Retry, plus an earlier payout that was sent.'],
        ['key' => 'tenant_holds', 'who' => 'admin@abangananhub.com', 'where' => '/admin/tenant-holds', 'shows' => 'A landlord\'s unpaid-rent report waiting for confirmation, an active hold, a released one, and a possible-duplicate-account alert.'],
        ['key' => 'deposit_charges', 'who' => 'admin@abangananhub.com', 'where' => '/admin/deposit-charges', 'shows' => 'Two active deposit charges (cleaning, damage) and one voided, on a completed tenancy.'],
        ['key' => 'payments_mix', 'who' => 'admin@abangananhub.com', 'where' => '/admin/payments?status=All', 'shows' => 'Payments in every state: Held, Released, Paid, Pending, Voided and Refunded.'],
        ['key' => 'audit_logs', 'who' => 'admin@abangananhub.com', 'where' => '/admin/audit-logs', 'shows' => 'An activity log across several days and action types (approvals, releases, refunds, holds, sign-ins), hash-chained.'],
        ['key' => 'users_suspended', 'who' => 'admin@abangananhub.com', 'where' => '/admin/users?status=Suspended', 'shows' => 'A suspended account in the users list.'],
        ['key' => 'ratings_analytics', 'who' => 'admin@abangananhub.com', 'where' => '/admin/ratings', 'shows' => 'Ratings and reports analytics with enough reviews to have shape (from ReviewSeeder).'],
        ['key' => 'admin_bell', 'who' => 'admin@abangananhub.com', 'where' => '/notifications', 'shows' => 'Unread admin notifications: payout accounts to approve, a refund escalation, a dispute, applications waiting.'],
    ];

    private const APPLICANTS = [
        [
            'first_name' => 'Nenita', 'last_name' => 'Pacaña', 'email' => 'nenita.pacana@abangananhub.com', 'contact' => '09175550301',
            'id_type' => 'PhilSys National ID', 'id_number' => '4412-8890-7731-2205', 'business' => 'Pacaña Rooms for Rent',
            'address' => 'Poblacion, Liloan, Cebu', 'description' => 'Six rooms for workers at the Liloan industrial area, with a shared kitchen and a caretaker on the ground floor.',
            'ocr_status' => 'pass', 'ocr_name' => 'NENITA PACAÑA', 'ocr_confidence' => 94, 'face_status' => 'match', 'face_score' => 91, 'days_ago' => 2,
        ],
        [
            'first_name' => 'Jerome', 'last_name' => 'Villaflor', 'email' => 'jerome.villaflor@abangananhub.com', 'contact' => '09175550302',
            'id_type' => 'Driver\'s License', 'id_number' => 'N07-14-203318', 'business' => 'Villaflor Apartments',
            'address' => 'Bulacao, Talisay City, Cebu', 'description' => 'Two apartments near the SRP road, mostly rented to call centre staff.',
            'ocr_status' => 'partial', 'ocr_name' => 'JEROME VILLAFLO', 'ocr_confidence' => 71, 'face_status' => 'review', 'face_score' => 68, 'days_ago' => 1,
        ],
    ];

    private User $admin;

    public function run(): void
    {
        $this->admin = User::where('email', 'admin@abangananhub.com')->firstOrFail();

        $this->applications();
        $this->pendingListings();
        $this->documents();
        $this->unitApprovals();
        $this->reports();
        $this->suspendedUser();
        $this->payoutAccounts();
        $this->refundDispute();
        $this->refundEscalation();
        $this->payouts();
        $this->holds();
        $this->depositCharges();
        $this->otherPayments();
        $this->auditLog();
        $this->adminBell();
    }

    // ─── 1. Landlord verifications ──────────────────────────────────────────────────────────────────────────────

    private function applications(): void
    {
        $id = file_get_contents(__DIR__.'/assets/sample-id.jpg');
        $selfie = file_get_contents(__DIR__.'/assets/sample-selfie.jpg');

        foreach (self::APPLICANTS as $a) {
            $user = $this->person($a['first_name'], $a['last_name'], $a['email'], $a['contact']);
            $slug = strstr($a['email'], '@', true);

            if (LandlordVerification::where('user_id', $user->user_id)->exists()) {
                continue;
            }

            Storage::disk('local')->put("verifications/defense-{$slug}-id.jpg", $id);
            Storage::disk('local')->put("verifications/defense-{$slug}-selfie.jpg", $selfie);

            LandlordVerification::create([
                'user_id' => $user->user_id,
                'government_id' => "verifications/defense-{$slug}-id.jpg",
                'selfie' => "verifications/defense-{$slug}-selfie.jpg",
                'liveness_passed' => true,
                'id_type' => $a['id_type'],
                'id_number' => $a['id_number'],
                'id_image_hash' => hash('sha256', 'defense-'.$a['email']),
                'ocr_name' => $a['ocr_name'],
                'ocr_confidence' => $a['ocr_confidence'],
                'ocr_status' => $a['ocr_status'],
                'face_match_status' => $a['face_status'],
                'face_match_score' => $a['face_score'],
                'business_name' => $a['business'],
                'description' => $a['description'],
                'contact_number' => $a['contact'],
                'business_address' => $a['address'],
                'verification_status' => 'Pending',
                'submitted_at' => now()->subDays($a['days_ago']),
            ]);
        }
    }

    // ─── 2. Listings waiting for approval ───────────────────────────────────────────────────────────────────────

    private function pendingListings(): void
    {
        // Complete: photos and three units, copied from a seeded listing of the same landlord.
        $this->pendingCopy('Tunghaan Rooms, Minglanilla', 'Tunghaan Annex Rooms, Minglanilla', 'Tunghaan, Minglanilla, Cebu', 3, true);
        // Thin: no photos, one unit. The admin can reject it for that.
        $this->pendingCopy('Aloguinsan Seaside Room', 'Aloguinsan Seaside Annex', 'Poblacion, Aloguinsan, Cebu', 1, false);
    }

    private function pendingCopy(string $modelTitle, string $title, string $address, int $unitCount, bool $withMedia): void
    {
        $model = Property::where('title', $modelTitle)->with(['media', 'units.media'])->firstOrFail();

        $property = Property::where('landlord_id', $model->landlord_id)->where('title', $title)->first();
        if ($property) {
            return;
        }

        $property = $model->replicate();
        $property->title = $title;
        $property->address = $address;
        $property->latitude = (float) $model->latitude + 0.0021;
        $property->longitude = (float) $model->longitude + 0.0017;
        $property->verification_status = 'Pending';
        $property->publication_status = 'Published';
        $property->rejection_reason = null;
        $property->save();

        if ($withMedia) {
            foreach ($model->media as $m) {
                PropertyMedia::create(['property_id' => $property->property_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url]);
            }
        }

        $sibling = $model->units->first();
        for ($i = 1; $i <= $unitCount; $i++) {
            $this->unitCopy($property, $sibling, 'Room '.$i, 'Pending');
        }
    }

    /** A new unit shaped like a sibling (same size, rent, deposit, photos). */
    private function unitCopy(Property $property, PropertyUnit $sibling, string $label, string $verification): PropertyUnit
    {
        $unit = $sibling->replicate();
        $unit->property_id = $property->property_id;
        $unit->unit_label = $label;
        $unit->availability_status = 'Available';
        $unit->verification_status = $verification;
        $unit->save();

        foreach ($sibling->media as $m) {
            UnitMedia::create([
                'unit_id' => $unit->unit_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url,
                'source' => $m->source, 'caption' => $m->caption,
            ]);
        }

        return $unit;
    }

    // ─── 3. Property documents ──────────────────────────────────────────────────────────────────────────────────

    private function documents(): void
    {
        $file = file_get_contents(__DIR__.'/assets/sample-id.jpg');

        $rows = [
            // [listing, type, number, status, extras]
            ['Maguikay Duplex Apartments', 'Tax Declaration', 'TD-2026-04417', 'Pending', ['created' => now()->subDays(3)]],
            ['Gabi Rooms, Cordova', 'Business Permit', 'BP-2026-1183', 'Pending', ['created' => now()->subDays(2)]],
            ['Tunghaan Rooms, Minglanilla', 'Proof of Ownership', 'TCT T-117-2024-009812', 'Pending', ['created' => now()->subDay()]],
            ['Compostela Family House', 'Proof of Ownership', 'TCT T-082-2019-004471', 'Verified', ['verified' => now()->subDays(6)]],
            ['Argao Poblacion Rooms', 'Fire Safety Certificate', 'FSIC-2025-0932', 'Rejected', [
                'reason' => 'The certificate expired in March 2026. Please upload the current one.', 'expiry' => now()->subMonths(8), 'verified' => now()->subDays(4),
            ]],
            // Asked for by an admin, not uploaded yet: it has no file, so it is not in the queue.
            ['Liloan Poblacion Apartment', 'Occupancy Permit', null, 'Pending', ['requested' => true]],
        ];

        foreach ($rows as [$title, $type, $number, $status, $extra]) {
            $property = Property::where('title', $title)->firstOrFail();
            $requested = $extra['requested'] ?? false;

            $document = PropertyDocument::firstOrNew(['property_id' => $property->property_id, 'document_type' => $type]);
            if ($document->exists) {
                continue;
            }

            $path = null;
            if (! $requested) {
                $path = "property_documents/{$property->property_id}/defense-".str($type)->slug().'.jpg';
                Storage::disk('local')->put($path, $file);
            }

            $document->fill([
                'file_path' => $path,
                'file_name' => $requested ? null : str($type)->slug().'-2026.jpg',
                'document_number' => $number,
                'status' => $status,
                'rejection_reason' => $extra['reason'] ?? null,
                'expiry_date' => $extra['expiry'] ?? null,
                'verified_by' => in_array($status, ['Verified', 'Rejected'], true) ? $this->admin->user_id : null,
                'verified_at' => $extra['verified'] ?? null,
                'requested_by' => $requested ? $this->admin->user_id : null,
            ]);
            if (isset($extra['created'])) {
                $document->created_at = $extra['created'];
            }
            $document->save();
        }
    }

    // ─── 4. Units that need their own approval ──────────────────────────────────────────────────────────────────

    private function unitApprovals(): void
    {
        foreach ([['Liloan Poblacion Apartment', 'Unit C (new)'], ['Sabang Rooms, Danao City', 'Room 6 (new)']] as [$title, $label]) {
            $property = Property::where('title', $title)->with('units.media')->firstOrFail();

            if ($property->units->contains('unit_label', $label)) {
                continue;
            }

            $this->unitCopy($property, $property->units->first(), $label, 'Pending');
        }
    }

    // ─── 5. Reports ─────────────────────────────────────────────────────────────────────────────────────────────

    private function reports(): void
    {
        $pending = [
            ['jomar@abangananhub.com', 'Lahug Dormitory near JY Square', null, 'The photos are from another dormitory. The room I was shown had no window and a shared bathroom for twelve people.', 2],
            ['angeline@abangananhub.com', null, 'wilfredo.lozada@abangananhub.com', 'Asked me to send the reservation money to a personal e-wallet before I could even view the unit.', 1],
            ['dennis@abangananhub.com', 'Compostela Family House', null, 'The listing says internet is included, but the landlord now wants an extra ₱800 a month for it.', 1],
        ];
        foreach ($pending as [$reporterEmail, $title, $userEmail, $reason, $daysAgo]) {
            $this->report($reporterEmail, $title, $userEmail, $reason, ['report_status' => 'Pending'], $daysAgo);
        }

        $resolved = [
            ['kristine@abangananhub.com', null, 'cristina.gonzaga@abangananhub.com', 'Kept my message unread for a week after accepting my inquiry, then said the room was taken.', 'Warned the landlord about response times. The inquiry was closed with an apology.', 'warn_user', 6],
            ['rhea@abangananhub.com', 'Lahug Dormitory near JY Square', null, 'The listing shows a rooftop deck that does not exist in the building.', 'Confirmed with the landlord, who removed the photo. Listing hidden until corrected.', 'delist_property', 5],
        ];
        foreach ($resolved as [$reporterEmail, $title, $userEmail, $reason, $notes, $action, $daysAgo]) {
            $this->report($reporterEmail, $title, $userEmail, $reason, [
                'report_status' => 'Resolved', 'admin_notes' => $notes, 'action_taken' => $action,
                'resolved_by' => $this->admin->user_id, 'resolved_at' => now()->subDays($daysAgo - 1),
            ], $daysAgo);
        }
    }

    private function report(string $reporterEmail, ?string $propertyTitle, ?string $userEmail, string $reason, array $attributes, int $daysAgo): Report
    {
        $reporter = User::where('email', $reporterEmail)->firstOrFail();

        $existing = Report::where('reporter_id', $reporter->user_id)->where('report_reason', $reason)->first();
        if ($existing) {
            return $existing;
        }

        $report = new Report([
            'reporter_id' => $reporter->user_id,
            'property_id' => $propertyTitle ? Property::where('title', $propertyTitle)->value('property_id') : null,
            'reported_user_id' => $userEmail ? User::where('email', $userEmail)->value('user_id') : null,
            'report_reason' => $reason,
        ] + $attributes);
        $report->created_at = now()->subDays($daysAgo);
        $report->save();

        return $report;
    }

    private function suspendedUser(): void
    {
        $user = $this->person('Rodel', 'Cabahug', 'rodel.cabahug@abangananhub.com', '09175550303');
        if ($user->account_status !== 'Suspended') {
            $user->forceFill(['account_status' => 'Suspended'])->save();
        }
    }

    // ─── 6. Payout accounts (approved, except two) ──────────────────────────────────────────────────────────────

    private function payoutAccounts(): void
    {
        $approve = ['ramon.alcoseba', 'teresita.ouano', 'gerardo.tudtud', 'lourdes.pepito', 'anselmo.yap'];

        foreach ($approve as $local) {
            $user = User::where('email', $local.'@abangananhub.com')->first();
            if (! $user) {
                continue;
            }
            UserPayoutAccount::where('user_id', $user->user_id)->whereNull('payout_approved_at')->update([
                'payout_approved_at' => now()->subDays(12), 'payout_approved_by' => $this->admin->user_id,
            ]);
        }
        // Cristina Gonzaga and Wilfredo Lozada stay unapproved: the Payouts "to approve" list.
    }

    // ─── 7. Refund requests ─────────────────────────────────────────────────────────────────────────────────────

    /** A move-in dispute on money AbangananHub holds: the tenant paid online, then reported the unit. */
    private function refundDispute(): void
    {
        $tenant = $this->person('Marites', 'Dacillo', 'marites.dacillo@abangananhub.com', '09175550311');

        [$reservation, $created, $conversation] = $this->tenancy($tenant, 'Maguikay Duplex Apartments', [
            'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => now()->subDays(6)->toDateString(),
            'target_move_in_date' => now()->addDays(2)->toDateString(),
            'agreed_at' => now()->subDays(5),
            'landlord_tc_accepted_at' => now()->subDays(5)->subMinutes(30),
            'tenant_tc_accepted_at' => now()->subDays(5),
            'move_in_disputed_at' => now()->subDays(2),
            'move_in_dispute_reason' => 'The unit still has no running water and the landlord has not answered for two days. I do not want to move in.',
        ], 'Reserved');

        if (! $created) {
            return;
        }

        $rent = (float) $reservation->agreed_monthly_rent;
        $this->payment($reservation, [
            'payment_type' => 'Deposit', 'amount' => $rent, 'status' => 'Held', 'payment_method' => 'GCash',
            'paid_at' => now()->subDays(5), 'paymongo_payment_id' => 'pay_demoDispute001', 'paymongo_checkout_session_id' => 'cs_demoDispute001',
        ]);
        $this->payment($reservation, [
            'payment_type' => 'Monthly', 'billing_period' => now()->addDays(2)->startOfMonth()->toDateString(), 'amount' => $rent, 'service_fee' => 112.50,
            'status' => 'Held', 'payment_method' => 'GCash', 'paid_at' => now()->subDays(5),
            'paymongo_payment_id' => 'pay_demoDispute001', 'paymongo_checkout_session_id' => 'cs_demoDispute001',
        ]);

        $name = trim($tenant->first_name.' '.$tenant->last_name);
        $this->chat($conversation, [
            [$tenant->user_id, 'Good morning po. Is the water supply fixed already? I am moving in this week.', 4300],
            [$conversation->landlord_id, 'Maayo. Hulaton ta ugma, gi-ayo pa ang tubo.', 4200],
            [$tenant->user_id, 'It has been two days po and the faucet is still dry.', 2900],
            [null, "{$name} signed the rental agreement.", 7200],
            [null, "{$name} completed the initial payment. Funds are held by AbangananHub.", 7190],
            [null, "{$name} reported an issue with the move-in. The deposit is on hold pending review by an administrator.", 2880],
        ]);
    }

    /** The landlord kept money paid straight to them, and the tenant sent it to AbangananHub. */
    private function refundEscalation(): void
    {
        $tenant = $this->person('Jaypee', 'Marapao', 'jaypee.marapao@abangananhub.com', '09175550312');

        [$reservation, $created, $conversation] = $this->tenancy($tenant, 'Gabi Rooms, Cordova', [
            'rental_status' => 'Cancelled',
            'reservation_date' => now()->subDays(24)->toDateString(),
            'target_move_in_date' => now()->subDays(10)->toDateString(),
            'agreed_at' => now()->subDays(20),
            'move_in_refund_requested_at' => now()->subDays(6),
            'move_in_refund_decision' => 'kept',
            'move_in_refund_amount' => 0,
            'move_in_refund_note' => 'Tenant cancelled after the room was cleaned and the first week was blocked for him.',
            'move_in_refund_at' => now()->subDays(5),
        ], 'Available');

        if (! $created) {
            return;
        }

        $this->payment($reservation, [
            'payment_type' => 'Deposit', 'amount' => 3500, 'status' => 'Paid', 'payment_method' => 'GCash', 'paid_at' => now()->subDays(19),
            'recorded_by' => $conversation->landlord_id, 'reference_no' => 'GC-40018822',
        ]);

        $report = new Report([
            'reporter_id' => $tenant->user_id,
            'property_id' => $reservation->property_id,
            'reported_user_id' => $conversation->landlord_id,
            'report_reason' => 'Refund not returned: tenant paid straight to the landlord and the landlord recorded keeping ₱3,500.00. Their reason: "'
                .$reservation->move_in_refund_note.'". Reservation #'.$reservation->reservation_id.'.',
            'report_status' => 'Pending',
        ]);
        $report->created_at = now()->subDays(3);
        $report->save();
        $reservation->forceFill(['refund_report_id' => $report->report_id])->saveQuietly();

        $name = trim($tenant->first_name.' '.$tenant->last_name);
        $this->chat($conversation, [
            [$tenant->user_id, 'Sir, I need to cancel, my company moved me to Davao. Can I get the deposit back?', 9000],
            [$conversation->landlord_id, 'Sorry, the deposit is not returned when you cancel after we cleaned the room.', 8900],
            [$tenant->user_id, 'But I never moved in po. I will ask AbangananHub to look at it.', 8800],
            [null, "{$name} cancelled the reservation.", 8700],
        ]);
    }

    // ─── 8. Payouts ─────────────────────────────────────────────────────────────────────────────────────────────

    private function payouts(): void
    {
        // Owed: released money (deposit after move-in) and rent paid online, not yet sent to the landlord (Ramon Alcoseba).
        $owedTenant = $this->person('Gina', 'Lumapas', 'gina.lumapas@abangananhub.com', '09175550313');
        [$owed, $created] = $this->tenancy($owedTenant, 'Liloan Poblacion Apartment', $this->occupied(40, 1), 'Occupied');
        if ($created) {
            $rent = (float) $owed->agreed_monthly_rent;
            $this->payment($owed, [
                'payment_type' => 'Deposit', 'amount' => $rent, 'status' => 'Released', 'payment_method' => 'GCash', 'paid_at' => now()->subDays(40),
                'released_at' => now()->subDays(38), 'release_reason' => 'tenant_confirmed',
                'payout_owed_at' => now()->subDays(38), 'paymongo_payment_id' => 'pay_demoOwed0001',
            ]);
            $this->payment($owed, [
                'payment_type' => 'Monthly', 'billing_period' => now()->subDays(40)->startOfMonth()->toDateString(), 'amount' => $rent, 'status' => 'Paid',
                'payment_method' => 'GCash', 'paid_at' => now()->subDays(2), 'payout_owed_at' => now()->subDays(2), 'paymongo_payment_id' => 'pay_demoOwed0002',
            ]);
        }

        // Sent, then failed (Teresita Ouano): one earlier payout went through, the latest one was declined by GCash.
        $tenant = $this->person('Ronnie', 'Quijano', 'ronnie.quijano@abangananhub.com', '09175550314');
        [$res, $created] = $this->tenancy($tenant, 'Maribago Staff Rooms', $this->occupied(75, 5), 'Occupied');
        if ($created) {
            $landlordId = $res->property->landlord_id;
            $account = UserPayoutAccount::find($landlordId);
            $rent = (float) $res->agreed_monthly_rent;

            $sent = Payout::create([
                'landlord_id' => $landlordId, 'amount' => $rent, 'status' => 'Sent', 'gcash_number' => $account?->gcash_number, 'gcash_account_name' => $account?->gcash_account_name,
                'payout_reference' => 'GCASH-7731204', 'paid_out_by' => $this->admin->user_id, 'paid_out_at' => now()->subDays(9),
            ]);
            $failed = Payout::create([
                'landlord_id' => $landlordId, 'amount' => $rent, 'status' => 'Failed', 'gcash_number' => $account?->gcash_number, 'gcash_account_name' => $account?->gcash_account_name,
                'failure_reason' => 'GCash declined the transfer: the account name on file does not match the GCash number.',
            ]);

            $this->payment($res, [
                'payment_type' => 'Monthly', 'billing_period' => now()->subDays(75)->startOfMonth()->toDateString(), 'amount' => $rent, 'status' => 'Paid',
                'payment_method' => 'GCash', 'paid_at' => now()->subDays(40), 'payout_owed_at' => now()->subDays(39), 'payout_id' => $sent->payout_id, 'paymongo_payment_id' => 'pay_demoSent0001',
            ]);
            $this->payment($res, [
                'payment_type' => 'Monthly', 'billing_period' => now()->subDays(40)->startOfMonth()->toDateString(), 'amount' => $rent, 'status' => 'Paid',
                'payment_method' => 'GCash', 'paid_at' => now()->subDays(6), 'payout_owed_at' => now()->subDays(5), 'payout_id' => $failed->payout_id, 'paymongo_payment_id' => 'pay_demoFailed001',
            ]);
        }
    }

    // ─── 9. Tenant holds and a possible duplicate ───────────────────────────────────────────────────────────────

    private function holds(): void
    {
        $landlordId = Property::where('title', 'Tunghaan Rooms, Minglanilla')->value('landlord_id');

        $elmer = $this->person('Elmer', 'Dumaluan', 'elmer.dumaluan@abangananhub.com', '09175550321');
        [$elmerRes, $created] = $this->tenancy($elmer, 'Tunghaan Rooms, Minglanilla', $this->occupied(95, 1), 'Occupied');
        TenantHold::firstOrCreate(['tenant_id' => $elmer->user_id, 'status' => TenantHold::PENDING], [
            'landlord_id' => $landlordId, 'reservation_id' => $elmerRes->reservation_id, 'amount_owed' => 2 * (float) $elmerRes->agreed_monthly_rent,
            'reason' => 'Two months of rent are unpaid and the tenant has stopped replying to messages.', 'flagged_at' => now()->subDays(2),
        ]);

        $cherry = $this->person('Cherry Mae', 'Patalinghug', 'cherrymae.patalinghug@abangananhub.com', '09175550322');
        [$cherryRes] = $this->tenancy($cherry, 'Naga Poblacion Apartment', $this->occupied(70, 1), 'Occupied', fallbackTitle: 'Poblacion Apartment, Naga City');
        $active = TenantHold::firstOrCreate(['tenant_id' => $cherry->user_id, 'status' => TenantHold::ACTIVE], [
            'landlord_id' => $cherryRes->property->landlord_id, 'reservation_id' => $cherryRes->reservation_id, 'amount_owed' => (float) $cherryRes->agreed_monthly_rent,
            'reason' => 'Rent for last month is unpaid after two reminders.', 'flagged_at' => now()->subDays(11),
            'confirmed_by' => $this->admin->user_id, 'confirmed_at' => now()->subDays(9),
        ]);

        // A hold that ended because the rent was paid (history tab).
        $bryan = $this->person('Bryan', 'Cañete', 'bryan.canete@abangananhub.com', '09175550323');
        TenantHold::firstOrCreate(['tenant_id' => $bryan->user_id, 'status' => TenantHold::RELEASED], [
            'landlord_id' => $landlordId, 'amount_owed' => 3800, 'reason' => 'One month unpaid.', 'flagged_at' => now()->subDays(30),
            'confirmed_by' => $this->admin->user_id, 'confirmed_at' => now()->subDays(28), 'released_at' => now()->subDays(21), 'release_reason' => 'paid',
        ]);

        // A new account that looks like the tenant on hold: same phone, same name written a little differently.
        $lookalike = $this->person('Cherry Mae', 'Patalinghug', 'cherrymae.patalinghug2@abangananhub.com', '09175550322');
        HoldMatchAlert::firstOrCreate(['hold_id' => $active->hold_id, 'user_id' => $lookalike->user_id], [
            'signals' => ['phone', 'name'], 'status' => HoldMatchAlert::OPEN,
        ]);
    }

    // ─── 10. Deposit charges ────────────────────────────────────────────────────────────────────────────────────

    private function depositCharges(): void
    {
        $tenant = $this->person('Kenneth', 'Alferez', 'kenneth.alferez@abangananhub.com', '09175550331');
        [$res, $created] = $this->tenancy($tenant, 'Carmen Poblacion Apartment', [
            'rental_status' => 'Completed',
            'reservation_date' => now()->subMonths(9)->toDateString(),
            'target_move_in_date' => now()->subMonths(8)->toDateString(),
            'target_move_out_date' => now()->subDays(10)->toDateString(),
            'agreed_at' => now()->subMonths(9),
            'move_in_paid_at' => now()->subMonths(8),
            'rent_due_day' => 1,
        ], 'Available');

        if (! $created) {
            return;
        }

        $landlordId = $res->property->landlord_id;
        $this->payment($res, [
            'payment_type' => 'Deposit', 'amount' => 5000, 'status' => 'Paid', 'payment_method' => 'Cash', 'paid_at' => now()->subMonths(8), 'recorded_by' => $landlordId,
        ]);

        foreach ([
            ['Cleaning', 800, 'Deep cleaning of the room and the shared kitchen after move-out.', 9, null],
            ['Damage', 1500, 'Cracked bathroom mirror and a hole in the room door.', 9, null],
            ['Other', 500, 'Late move-out fee entered by mistake.', 8, ['wrong_amount', 'The fee was not in the agreement.']],
        ] as [$category, $amount, $description, $daysAgo, $void]) {
            DepositCharge::create([
                'reservation_id' => $res->reservation_id, 'amount' => $amount, 'category' => $category, 'description' => $description,
                'charged_at' => now()->subDays($daysAgo), 'charged_by' => $landlordId,
                'voided_at' => $void ? now()->subDays(7) : null, 'voided_by' => $void ? $landlordId : null,
                'void_reason' => $void[0] ?? null, 'void_note' => $void[1] ?? null,
            ]);
        }
    }

    // ─── 11. The rest of the Payments page: Refunded, Pending, Voided ──────────────────────────────────────────

    private function otherPayments(): void
    {
        // Refunded: held money the admin gave back, and the reservation cancelled with it.
        $lorna = $this->person('Lorna', 'Gabutero', 'lorna.gabutero@abangananhub.com', '09175550341');
        [$refunded, $created] = $this->tenancy($lorna, 'Liloan Poblacion Apartment', [
            'rental_status' => 'Cancelled', 'reservation_date' => now()->subDays(20)->toDateString(), 'target_move_in_date' => now()->subDays(8)->toDateString(),
            'agreed_at' => now()->subDays(18), 'move_in_disputed_at' => null,
        ], 'Available');
        if ($created) {
            $rent = (float) $refunded->agreed_monthly_rent;
            foreach (['Deposit', 'Monthly'] as $type) {
                $this->payment($refunded, [
                    'payment_type' => $type, 'billing_period' => $type === 'Monthly' ? now()->subDays(8)->startOfMonth()->toDateString() : null, 'amount' => $rent,
                    'status' => 'Refunded', 'payment_method' => 'GCash', 'paid_at' => now()->subDays(17), 'refunded_at' => now()->subDays(12), 'refunded_by' => $this->admin->user_id,
                    'refund_reason' => 'Unit was not as listed (confirmed on the viewing).', 'paymongo_refund_id' => 'ref_demoRefund0001', 'refund_status' => 'succeeded',
                    'paymongo_payment_id' => 'pay_demoRefund0001', 'paymongo_checkout_session_id' => 'cs_demoRefund0001',
                ]);
            }
        }

        // Pending: a checkout opened a few minutes ago and not paid yet.
        $hannah = $this->person('Hannah', 'Cuyos', 'hannah.cuyos@abangananhub.com', '09175550342');
        [$pending, $created] = $this->tenancy($hannah, 'Liloan Poblacion Apartment', [
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => now()->subDays(2)->toDateString(), 'target_move_in_date' => now()->addDays(5)->toDateString(),
            'agreed_at' => now()->subDay(),
        ], 'Reserved');
        if ($created) {
            $this->payment($pending, [
                'payment_type' => 'Initial', 'amount' => (float) $pending->agreed_monthly_rent, 'status' => 'Pending', 'payment_method' => 'GCash', 'paid_at' => null,
                'paymongo_checkout_session_id' => 'cs_demoPending001', 'created_at' => now()->subMinutes(14),
            ]);
        }

        // Voided: a cash payment the landlord entered wrongly and struck out (on Gina's tenancy).
        $gina = User::where('email', 'gina.lumapas@abangananhub.com')->first();
        $ginaRes = $gina ? Reservation::where('tenant_id', $gina->user_id)->first() : null;
        if ($ginaRes && ! Payment::where('reservation_id', $ginaRes->reservation_id)->where('status', 'Voided')->exists()) {
            $this->payment($ginaRes, [
                'payment_type' => 'Monthly', 'billing_period' => now()->subDays(40)->startOfMonth()->toDateString(), 'amount' => 900, 'status' => 'Voided',
                'payment_method' => 'Cash', 'paid_at' => now()->subDays(20), 'recorded_by' => $ginaRes->property->landlord_id,
                'voided_at' => now()->subDays(19), 'voided_by' => $ginaRes->property->landlord_id, 'void_reason' => 'wrong_amount', 'void_note' => 'Typed 900 instead of 9,000.',
            ]);
        }
    }

    // ─── 12. Activity log ───────────────────────────────────────────────────────────────────────────────────────

    private function auditLog(): void
    {
        $ramon = User::where('email', 'ramon.alcoseba@abangananhub.com')->first();
        $maria = User::where('email', 'landlord@abangananhub.com')->first();
        $teresitaListing = Property::where('title', 'Gabi Rooms, Cordova')->first();
        $approvedVerification = LandlordVerification::where('verification_status', 'Approved')->orderBy('verification_id')->skip(1)->first();
        $dispute = Reservation::whereNotNull('move_in_disputed_at')->orderByDesc('reservation_id')->first();
        $firstUnit = PropertyUnit::orderBy('unit_id')->first();

        $entries = [
            [14, 9, 2, 'auth.login', 'Signed in', $this->admin, null, []],
            [14, 9, 3, 'auth.two_factor_passed', 'Passed two-factor', $this->admin, null, []],
            [13, 10, 15, 'verification.approve', 'Approved the landlord application of '.($approvedVerification?->user?->first_name ?? 'a landlord'), $approvedVerification, null, []],
            [12, 11, 40, 'listing.approve', 'Approved the listing "'.($teresitaListing?->title ?? 'a listing').'"', $teresitaListing, null, []],
            [11, 14, 5, 'unit.approve', 'Approved a unit', $firstUnit, null, []],
            [10, 15, 30, 'payout_account.approve', 'Approved the GCash account of '.($ramon?->first_name ?? 'a landlord'), $ramon, null, []],
            [9, 9, 20, 'user.status_change', 'Suspended the account of Rodel Cabahug', User::where('email', 'rodel.cabahug@abangananhub.com')->first(), 'Repeated harassing messages to landlords.', []],
            [8, 13, 10, 'report.resolve', 'Resolved a report about a listing', null, 'Landlord corrected the photos.', []],
            [7, 10, 45, 'hold.confirmed', 'Confirmed a tenant hold for unpaid rent', null, null, []],
            [6, 16, 25, 'payment.release', 'Released a held payment to the landlord', $dispute?->payments()->first(), 'Tenant confirmed the move-in.', []],
            [5, 11, 5, 'deposit_charge.create', 'Recorded a deposit charge (Cleaning)', null, null, []],
            [4, 15, 50, 'settings.update', 'Changed the online payment fee setting', null, null, []],
            [3, 9, 1, 'auth.login', 'Signed in', $this->admin, null, []],
            [2, 14, 35, 'payout.mark_paid_out', 'Recorded a GCash payout sent by hand', $ramon, null, []],
            [1, 9, 4, 'auth.logout', 'Signed out', $this->admin, null, []],
        ];

        foreach ($entries as [$daysAgo, $hour, $minute, $action, $summary, $auditable, $reason, $metadata]) {
            if (AuditLog::where('action', $action)->where('summary', $summary)->exists()) {
                continue;
            }

            Carbon::setTestNow(now()->subDays($daysAgo)->setTime($hour, $minute));
            try {
                AuditLog::record($action, $summary, $auditable, $reason, $metadata, false, $this->admin);
            } finally {
                Carbon::setTestNow();
            }
        }

        if ($maria) {
            // One landlord-side entry, because the log also carries those (voids, deposit charges).
            $summary = 'Voided a payment entered by mistake';
            if (! AuditLog::where('action', 'payment.void')->where('summary', $summary)->exists()) {
                Carbon::setTestNow(now()->subDays(3)->setTime(10, 20));
                try {
                    AuditLog::record('payment.void', $summary, null, 'wrong_amount', [], false, $maria);
                } finally {
                    Carbon::setTestNow();
                }
            }
        }
    }

    // ─── 13. The admin's bell ───────────────────────────────────────────────────────────────────────────────────

    private function adminBell(): void
    {
        $items = [
            ['payment', 'GCash account to approve', 'Wilfredo Lozada added a GCash account. Check the name against the verified ID before approving.', '/admin/payouts', 30],
            ['payment', 'GCash account to approve', 'Cristina Gonzaga added a GCash account. Check the name against the verified ID before approving.', '/admin/payouts', 45],
            ['payment', 'Refund not returned', 'Jaypee says his landlord kept ₱3,500 paid directly, and asked AbangananHub to look at it.', '/admin/refund-requests', 4300],
            ['payment', 'Move-in dispute', 'Marites Dacillo reported a problem with the move-in. ₱9,000 is on hold for your review.', '/admin/refund-requests', 2880],
            ['verification', 'New landlord application', 'Nenita Pacaña submitted an ID and selfie for review.', '/admin/verifications', 2900],
        ];

        foreach ($items as [$type, $title, $message, $link, $minutesAgo]) {
            if (Notification::where('user_id', $this->admin->user_id)->where('title', $title)->where('message', $message)->exists()) {
                continue;
            }
            $note = Notification::notify($this->admin->user_id, $type, $title, $message, $link);
            $note?->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
        }
    }

    // ─── helpers ────────────────────────────────────────────────────────────────────────────────────────────────

    private function person(string $first, string $last, string $email, string $contact): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'first_name' => $first, 'last_name' => $last, 'password' => Hash::make('password'),
            'contact_number' => $contact, 'account_status' => 'active',
        ]);
        UserRole::firstOrCreate(['user_id' => $user->user_id, 'role' => 'Tenant']);

        return $user;
    }

    /** An occupied tenancy that started $daysAgo days ago, with no rent paid (so the months since are owed). */
    private function occupied(int $daysAgo, int $dueDay): array
    {
        return [
            'rental_status' => 'Occupied',
            'reservation_date' => now()->subDays($daysAgo + 5)->toDateString(),
            'target_move_in_date' => now()->subDays($daysAgo)->toDateString(),
            'agreed_at' => now()->subDays($daysAgo + 4),
            'move_in_paid_at' => now()->subDays($daysAgo),
            'rent_due_day' => $dueDay,
        ];
    }

    /**
     * The tenant's reservation on a listing, created once (found by tenant and listing on a re-run). Also its chat.
     * `$unitState` is what the unit looks like while this tenancy exists.
     *
     * @return array{0: Reservation, 1: bool, 2: Conversation}
     */
    private function tenancy(User $tenant, string $title, array $attributes, string $unitState, ?string $fallbackTitle = null): array
    {
        $property = Property::where('title', $title)->first() ?? Property::where('title', $fallbackTitle)->firstOrFail();

        $reservation = Reservation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->first();
        if ($reservation) {
            return [$reservation->load('property'), false, $this->conversationFor($tenant, $reservation)];
        }

        $unit = PropertyUnit::where('property_id', $property->property_id)
            ->where('availability_status', 'Available')->where('verification_status', 'Approved')
            ->whereDoesntHave('reservations', fn ($q) => $q->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
            ->orderBy('unit_id')->first();

        if (! $unit) {
            $sibling = $property->units()->with('media')->orderBy('unit_id')->firstOrFail();
            $unit = $this->unitCopy($property, $sibling, 'Room '.(PropertyUnit::where('property_id', $property->property_id)->count() + 1), 'Approved');
        }

        $reservation = Reservation::withoutEvents(fn () => Reservation::create($attributes + [
            'property_id' => $property->property_id,
            'unit_id' => $unit->unit_id,
            'tenant_id' => $tenant->user_id,
            'agreed_monthly_rent' => $unit->rental_fee,
            'rent_due_day' => 1,
            'occupants_count' => 1,
            'duration_of_stay' => 'Open-ended',
        ]));

        $conversation = $this->conversationFor($tenant, $reservation);
        $reservation->forceFill(['conversation_id' => $conversation->conversation_id])->saveQuietly();

        if (in_array($reservation->rental_status, ['Rental Agreement Signed', 'Occupied', 'Completed', 'Cancelled'], true)) {
            $reservation->forceFill(['lease_snapshot' => LeaseTerms::snapshot($reservation->fresh())])->saveQuietly();
        }

        $unit->forceFill(['availability_status' => $unitState])->save();

        return [$reservation->load('property'), true, $conversation];
    }

    private function conversationFor(User $tenant, Reservation $reservation): Conversation
    {
        return Conversation::firstOrCreate([
            'tenant_id' => $tenant->user_id,
            'landlord_id' => $reservation->property()->value('landlord_id'),
            'property_id' => $reservation->property_id,
            'type' => Conversation::TYPE_INQUIRY,
        ], ['unit_id' => $reservation->unit_id]);
    }

    private function payment(Reservation $reservation, array $attributes): Payment
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        return Payment::withoutEvents(function () use ($reservation, $attributes, $createdAt) {
            $payment = new Payment(['reservation_id' => $reservation->reservation_id] + $attributes);
            if ($createdAt) {
                $payment->created_at = $createdAt;
            }
            $payment->save();

            return $payment;
        });
    }

    /** @param  list<array{0: ?int, 1: string, 2: int}>  $lines  sender (null = system), text, minutes ago */
    private function chat(Conversation $conversation, array $lines): void
    {
        if (Message::where('conversation_id', $conversation->conversation_id)->exists()) {
            return;
        }

        foreach ($lines as [$sender, $text, $minutesAgo]) {
            $message = new Message([
                'conversation_id' => $conversation->conversation_id,
                'sender_id' => $sender,
                'message' => $text,
                'kind' => $sender === null ? 'system' : 'user',
                'is_read' => true,
            ]);
            $message->sent_at = now()->subMinutes($minutesAgo);
            $message->save();
        }
    }
}
