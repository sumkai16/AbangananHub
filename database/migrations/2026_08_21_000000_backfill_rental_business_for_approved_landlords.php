<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Admin\VerificationController::approve() has always created the landlord's
     * rental_businesses row in the same transaction as the role grant — but at
     * least one landlord in this dataset holds an Approved verification and the
     * Landlord role with no matching row, predating that pairing. Every
     * "Verified Host" surface (browse page's Verified filter, the property show
     * page badge, landlord/properties/show) reads landlord.rentalBusiness as the
     * verified signal, so a landlord missing this row is invisible to all of
     * them even though admin genuinely approved them.
     *
     * Backfills one row per Approved verification lacking one, copying whatever
     * business fields that application captured (often blank, same as a fresh
     * approval — business_name is nullable for exactly this reason, see
     * SCHEMA.md). Blank strings are normalized to NULL to match the
     * ConvertEmptyStringsToNull path a real form submission already goes
     * through, not left as '' the way this stale data has it.
     */
    public function up(): void
    {
        $blank = fn ($value) => $value === '' ? null : $value;

        $verifications = DB::table('landlord_verifications')
            ->where('verification_status', 'Approved')
            ->orderByDesc('reviewed_at')
            ->get(['user_id', 'business_name', 'description', 'logo_url', 'contact_number', 'business_address']);

        $seen = [];
        foreach ($verifications as $verification) {
            if (isset($seen[$verification->user_id])) {
                continue; // keep only the most recent approval per landlord
            }
            $seen[$verification->user_id] = true;

            $exists = DB::table('rental_businesses')->where('landlord_id', $verification->user_id)->exists();
            if ($exists) {
                continue;
            }

            DB::table('rental_businesses')->insert([
                'landlord_id'      => $verification->user_id,
                'business_name'    => $blank($verification->business_name),
                'description'      => $blank($verification->description),
                'logo_url'         => $blank($verification->logo_url),
                'contact_number'   => $blank($verification->contact_number),
                'business_address' => $blank($verification->business_address),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    /**
     * Irreversible by design, same rationale as the security_deposit backfill —
     * a landlord may have since filled in real business details on top of the
     * backfilled row, and there is no record of which rows this migration
     * created versus already existed.
     */
    public function down(): void
    {
        //
    }
};
