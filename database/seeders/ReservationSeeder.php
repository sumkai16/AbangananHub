<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Property;
use App\Models\Reservation;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = User::where('email', 'tenant@abangananhub.com')->first();

        // Grab 3 different available+approved units from different properties
        $units = \App\Models\PropertyUnit::where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->take(3)
            ->get();

        foreach ($units as $unit) {
            Reservation::create([
                'tenant_id'          => $tenant->user_id,
                'property_id'        => $unit->property_id,
                'unit_id'            => $unit->unit_id,
                'rental_status'      => 'Inquiry',
                'duration_of_stay' => '6 Months',
                'occupants_count'    => 1,
            ]);
        }
    }
}