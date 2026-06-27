<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;
use App\Models\LandlordVerification;

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
            'account_status' => 'Active',
        ]);
        UserRole::create(['user_id' => $admin->user_id, 'role' => 'Admin']);

        // Landlord #1 — Approved
        $approvedLandlord = User::create([
            'first_name'     => 'Maria',
            'last_name'      => 'Santos',
            'email'          => 'landlord@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09171234567',
            'account_status' => 'Active',
        ]);
        UserRole::create(['user_id' => $approvedLandlord->user_id, 'role' => 'Landlord']);
        LandlordVerification::create([
            'user_id'             => $approvedLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-approved.jpg',
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
            'account_status' => 'Active',
        ]);
        LandlordVerification::create([
            'user_id'             => $pendingLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-pending.jpg',
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
            'account_status' => 'Active',
        ]);
        LandlordVerification::create([
            'user_id'             => $rejectedLandlord->user_id,
            'government_id'       => 'verifications/seed-placeholder-rejected.jpg',
            'verification_status' => 'Rejected',
            'admin_notes'         => 'Government ID image is blurry and the name does not match the account holder. Please resubmit a clearer photo of a valid government-issued ID.',
            'reviewed_by'         => $admin->user_id,
            'reviewed_at'         => now()->subDays(2),
            'submitted_at'        => now()->subDays(4),
        ]);

        // Tenant
        $tenant = User::create([
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'email'          => 'tenant@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09181234567',
            'account_status' => 'Active',
        ]);
        UserRole::create(['user_id' => $tenant->user_id, 'role' => 'Tenant']);
    }
}