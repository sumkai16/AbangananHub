<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\LandlordVerification;
use App\Models\Property;
use App\Models\Report;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'first_name'     => 'Admin',
            'last_name'      => 'AbangananHub',
            'email'          => 'admin@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09000000001',
            'account_status' => 'active',
        ]);
        UserRole::create(['user_id' => $admin->user_id, 'role' => 'Admin']);

        // Landlord #1 — Approved
        $approvedLandlord = User::create([
            'first_name'          => 'Maria',
            'last_name'           => 'Santos',
            'email'               => 'landlord@abangananhub.com',
            'password'            => Hash::make('password'),
            'contact_number'      => '09171234567',
            'account_status'      => 'active',
            'profile_visibility'  => 'public',
        ]);
        UserRole::create(['user_id' => $approvedLandlord->user_id, 'role' => 'Landlord']);
        LandlordVerification::create([
            'user_id'             => $approvedLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-approved.jpg',
            'id_type'             => 'PhilSys National ID',
            'selfie'              => 'verifications/seed-placeholder-selfie-approved.jpg',
            'id_image_hash'       => hash('sha256', 'seed-placeholder-approved'),
            'verification_status' => 'Approved',
            'admin_notes'         => null,
            'reviewed_by'         => $admin->user_id,
            'reviewed_at'         => now()->subDays(5),
            'submitted_at'        => now()->subDays(7),
        ]);

        // Landlord #2 — Pending (no Landlord role yet — role is granted on approval, not application)
        $pendingLandlord = User::create([
            'first_name'     => 'Roberto',
            'last_name'      => 'Reyes',
            'email'          => 'landlord.pending@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09181112222',
            'account_status' => 'active',
        ]);
        LandlordVerification::create([
            'user_id'             => $pendingLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-pending.jpg',
            'id_type'             => 'Driver\'s License',
            'selfie'              => 'verifications/seed-placeholder-selfie-pending.jpg',
            'id_image_hash'       => hash('sha256', 'seed-placeholder-pending'),
            'verification_status' => 'Pending',
            'admin_notes'         => null,
            'reviewed_by'         => null,
            'reviewed_at'         => null,
            'submitted_at'        => now()->subDays(1),
        ]);

        // Landlord #3 — Rejected (with admin_notes, to test resubmission flow; no Landlord role — never approved)
        $rejectedLandlord = User::create([
            'first_name'     => 'Carmela',
            'last_name'      => 'Villanueva',
            'email'          => 'landlord.rejected@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09191223344',
            'account_status' => 'active',
        ]);
        LandlordVerification::create([
            'user_id'             => $rejectedLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-rejected.jpg',
            'id_type'             => 'UMID',
            'selfie'              => 'verifications/seed-placeholder-selfie-rejected.jpg',
            'id_image_hash'       => hash('sha256', 'seed-placeholder-rejected'),
            'verification_status' => 'Rejected',
            'admin_notes'         => 'Government ID image is blurry and the name does not match the account holder. Please resubmit a clearer photo of a valid government-issued ID.',
            'reviewed_by'         => $admin->user_id,
            'reviewed_at'         => now()->subDays(2),
            'submitted_at'        => now()->subDays(4),
        ]);

        // Tenant
        $tenant = User::create([
            'first_name'     => 'Axcee',
            'last_name'      => 'Cabusas',
            'email'          => 'axcee@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09181234567',
            'account_status' => 'active',
        ]);
        UserRole::create(['user_id' => $tenant->user_id, 'role' => 'Tenant']);

        // ─── Reports ─────────────────────────────────────────
        // Seeded after PropertySeeder runs (called order in DatabaseSeeder),
        // so we grab the first property for property-targeted reports.
        $firstProperty = Property::first();

        // 3 Pending reports
        Report::create([
            'reporter_id'     => $tenant->user_id,
            'property_id'     => $firstProperty?->property_id,
            'reported_user_id' => null,
            'report_reason'   => 'The listing photos do not match the actual unit. The room shown appears to be a completely different property.',
            'report_status'   => 'Pending',
        ]);

        Report::create([
            'reporter_id'      => $tenant->user_id,
            'property_id'      => null,
            'reported_user_id' => $approvedLandlord->user_id,
            'report_reason'    => 'Landlord is unresponsive after accepting my reservation. Cannot reach them through chat or phone.',
            'report_status'    => 'Pending',
        ]);

        Report::create([
            'reporter_id'      => $pendingLandlord->user_id,
            'property_id'      => $firstProperty?->property_id,
            'reported_user_id' => null,
            'report_reason'    => 'This listing appears to be a duplicate of another property posted under a different title.',
            'report_status'    => 'Pending',
        ]);

        // 3 Resolved reports
        Report::create([
            'reporter_id'      => $tenant->user_id,
            'property_id'      => $firstProperty?->property_id,
            'reported_user_id' => null,
            'report_reason'    => 'Listing contains misleading rental fee — advertised as 2,500 but landlord demands 4,000 upon inquiry.',
            'report_status'    => 'Resolved',
            'admin_notes'      => 'Confirmed the discrepancy after reviewing chat history. Property has been delisted pending correction by the landlord.',
            'action_taken'     => 'delist_property',
            'resolved_by'      => $admin->user_id,
            'resolved_at'      => now()->subDays(1),
        ]);

        Report::create([
            'reporter_id'      => $tenant->user_id,
            'property_id'      => null,
            'reported_user_id' => $rejectedLandlord->user_id,
            'report_reason'    => 'This user sent harassing messages after I declined to proceed with a reservation.',
            'report_status'    => 'Resolved',
            'admin_notes'      => 'Reviewed message history and confirmed inappropriate behavior. Account has been suspended.',
            'action_taken'     => 'suspend_user',
            'resolved_by'      => $admin->user_id,
            'resolved_at'      => now()->subDays(3),
        ]);

        Report::create([
            'reporter_id'      => $pendingLandlord->user_id,
            'property_id'      => null,
            'reported_user_id' => $approvedLandlord->user_id,
            'report_reason'    => 'I believe this landlord is operating without proper business permits.',
            'report_status'    => 'Resolved',
            'admin_notes'      => 'Investigated and found no evidence of violation. Business permits are outside the scope of platform verification. No action taken.',
            'action_taken'     => 'no_action',
            'resolved_by'      => $admin->user_id,
            'resolved_at'      => now()->subDays(2),
        ]);
    }
}