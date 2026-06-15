<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserRole;

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

        // Landlord
        $landlord = User::create([
            'first_name'     => 'Maria',
            'last_name'      => 'Santos',
            'email'          => 'landlord@abangananhub.com',
            'password'       => Hash::make('password'),
            'contact_number' => '09171234567',
            'account_status' => 'Active',
        ]);
        UserRole::create(['user_id' => $landlord->user_id, 'role' => 'Landlord']);

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