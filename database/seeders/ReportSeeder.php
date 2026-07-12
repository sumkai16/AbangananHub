<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Property;
use App\Models\Report;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@abangananhub.com')->first();
        $tenant = User::where('email', 'axcee@abangananhub.com')->first();
        $approvedLandlord = User::where('email', 'landlord@abangananhub.com')->first();
        $pendingLandlord = User::where('email', 'landlord.pending@abangananhub.com')->first();
        $rejectedLandlord = User::where('email', 'landlord.rejected@abangananhub.com')->first();
        $firstProperty = Property::first();

        // 3 Pending reports
        Report::create([
            'reporter_id'      => $tenant->user_id,
            'property_id'      => $firstProperty->property_id,
            'reported_user_id' => null,
            'report_reason'    => 'The listing photos do not match the actual unit. The room shown appears to be a completely different property.',
            'report_status'    => 'Pending',
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
            'property_id'      => $firstProperty->property_id,
            'reported_user_id' => null,
            'report_reason'    => 'This listing appears to be a duplicate of another property posted under a different title.',
            'report_status'    => 'Pending',
        ]);

        // 3 Resolved reports
        Report::create([
            'reporter_id'      => $tenant->user_id,
            'property_id'      => $firstProperty->property_id,
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