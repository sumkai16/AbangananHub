<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fills the new history with what the database already knows, so a property that existed before the Activity Log
 * does not open on an empty page: when it and its units were created, the admin's decisions on them (from
 * audit_logs), past unit status changes (from occupancy_activities) and the reviews tenants posted.
 *
 * Earlier edits, photos and payments were never recorded anywhere, so they cannot be recovered: the page says
 * its history before November 2026 is partial.
 *
 * Safe to run twice and on empty tables: every row it writes is marked `"backfilled": true`, and a line already
 * written (same property, action, unit and moment) is skipped. down() removes exactly those rows.
 */
return new class extends Migration
{
    /** @var array<string, true> lines already there, "property|action|unit|created_at" */
    private array $seen = [];

    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, bool> */
    private array $admins = [];

    public function up(): void
    {
        DB::table('property_activities')
            ->where('metadata', 'like', '%"backfilled":true%')
            ->get(['property_id', 'action', 'unit_id', 'created_at'])
            ->each(fn ($row) => $this->seen[$this->key($row->property_id, $row->action, $row->unit_id, $row->created_at)] = true);

        $this->properties();
        $this->units();
        $this->adminDecisions();
        $this->unitStatusChanges();
        $this->reviews();
    }

    public function down(): void
    {
        DB::table('property_activities')->where('metadata', 'like', '%"backfilled":true%')->delete();
    }

    private function properties(): void
    {
        DB::table('properties')->orderBy('property_id')->chunkById(200, function ($rows) {
            foreach ($rows as $property) {
                $this->add($property->property_id, null, 'landlord', $property->landlord_id, 'property.created',
                    'Created the listing "'.mb_strimwidth((string) $property->title, 0, 80, '…').'"', $property->created_at);
            }
        }, 'property_id');
    }

    private function units(): void
    {
        // Units of a draft are not history yet (the live code stays quiet about drafts too).
        DB::table('property_units')
            ->join('properties', 'properties.property_id', '=', 'property_units.property_id')
            ->where('properties.publication_status', '!=', 'Draft')
            ->select('property_units.unit_id', 'property_units.property_id', 'property_units.unit_label', 'property_units.created_at', 'properties.landlord_id')
            ->orderBy('property_units.unit_id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $unit) {
                    $this->add($unit->property_id, $unit->unit_id, 'landlord', $unit->landlord_id, 'unit.added',
                        'Added unit "'.mb_strimwidth((string) $unit->unit_label, 0, 60, '…').'"', $unit->created_at);
                }
            });
    }

    private function adminDecisions(): void
    {
        $actions = [
            'listing.approve' => ['property.approved', 'Listing approved'],
            'listing.reject'  => ['property.rejected', 'Listing rejected'],
            'unit.approve'    => ['unit.approved', 'approved'],
            'unit.reject'     => ['unit.rejected', 'rejected'],
        ];

        DB::table('audit_logs')->whereIn('action', array_keys($actions))->orderBy('log_id')->chunkById(200, function ($logs) use ($actions) {
            foreach ($logs as $log) {
                [$action, $text] = $actions[$log->action];
                $reason = trim((string) $log->reason);

                if (str_starts_with($log->action, 'listing.')) {
                    $propertyId = $log->auditable_id;
                    $unitId = null;
                    $summary = $text.($log->action === 'listing.reject' && $reason !== '' ? ': '.mb_strimwidth($reason, 0, 160, '…') : '');
                } else {
                    $unit = DB::table('property_units')->where('unit_id', $log->auditable_id)->first(['unit_id', 'property_id', 'unit_label']);
                    if (! $unit) {
                        continue; // the unit is gone, so is the place to show it
                    }
                    $propertyId = $unit->property_id;
                    $unitId = $unit->unit_id;
                    $summary = 'Unit "'.mb_strimwidth((string) $unit->unit_label, 0, 60, '…').'" '.$text
                        .($log->action === 'unit.reject' && $reason !== '' ? ': '.mb_strimwidth($reason, 0, 160, '…') : '');
                }

                if (! $propertyId || ! DB::table('properties')->where('property_id', $propertyId)->exists()) {
                    continue;
                }

                $this->add($propertyId, $unitId, 'admin', $log->actor_id, $action, $summary, $log->created_at, $log->actor_name);
            }
        }, 'log_id');
    }

    private function unitStatusChanges(): void
    {
        DB::table('occupancy_activities')->orderBy('activity_id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $unit = DB::table('property_units')->where('unit_id', $row->unit_id)->first(['unit_label']);
                if (! $unit) {
                    continue;
                }

                $landlordId = DB::table('properties')->where('property_id', $row->property_id)->value('landlord_id');
                $role = match (true) {
                    $row->actor_id === null                => 'system',
                    (int) $row->actor_id === (int) $landlordId => 'landlord',
                    $this->isAdmin((int) $row->actor_id)   => 'admin',
                    default                                => 'tenant',
                };

                $this->add($row->property_id, $row->unit_id, $role, $row->actor_id, 'unit.status_changed',
                    'Unit "'.mb_strimwidth((string) $unit->unit_label, 0, 60, '…').'" went from '.$row->from_status.' to '.$row->to_status, $row->created_at);
            }
        }, 'activity_id');
    }

    private function reviews(): void
    {
        DB::table('reviews')->where('is_hidden', false)->orderBy('review_id')->chunkById(200, function ($rows) {
            foreach ($rows as $review) {
                $this->add($review->property_id, null, 'tenant', $review->tenant_id, 'review.posted',
                    $this->name($review->tenant_id, 'A tenant').' left a '.(int) $review->rating.'-star review', $review->created_at);
            }
        }, 'review_id');
    }

    /** Write one line unless it is already there. $role 'system' means no account behind it. */
    private function add(int $propertyId, ?int $unitId, string $role, ?int $actorId, string $action, string $summary, ?string $at, ?string $nameOverride = null): void
    {
        $key = $this->key($propertyId, $action, $unitId, $at);
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;

        DB::table('property_activities')->insert([
            'property_id' => $propertyId,
            'unit_id'     => $unitId,
            'actor_id'    => $role === 'system' ? null : $this->existingUser($actorId),
            'actor_role'  => $role,
            'actor_name'  => $role === 'system' ? 'System' : ($nameOverride ?: $this->name($actorId, 'Someone')),
            'action'      => $action,
            'summary'     => mb_strimwidth($summary, 0, 255, '…'),
            'metadata'    => json_encode(['backfilled' => true]),
            'created_at'  => $at,
        ]);
    }

    private function key(int $propertyId, string $action, ?int $unitId, $at): string
    {
        return $propertyId.'|'.$action.'|'.($unitId ?? '').'|'.$at;
    }

    /** The id only when the account still exists (the foreign key would refuse a dangling one). */
    private function existingUser(?int $userId): ?int
    {
        return $userId && DB::table('users')->where('user_id', $userId)->exists() ? $userId : null;
    }

    private function name(?int $userId, string $fallback): string
    {
        if (! $userId) {
            return $fallback;
        }

        return $this->names[$userId] ??= trim(
            (string) DB::table('users')->where('user_id', $userId)->selectRaw("concat_ws(' ', first_name, last_name) as n")->value('n')
        ) ?: $fallback;
    }

    private function isAdmin(int $userId): bool
    {
        return $this->admins[$userId] ??= DB::table('user_roles')->where('user_id', $userId)->where('role', 'Admin')->exists();
    }
};
