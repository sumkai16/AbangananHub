<?php

namespace App\Observers;

use App\Models\Property;
use App\Models\PropertyActivity;
use Illuminate\Support\Str;

/**
 * Writes the listing's own lines in its Activity Log: created, edited (which parts), submitted, published,
 * hidden, suspended, approved or rejected.
 *
 * It sits on the model, so the landlord's edit form, the property wizard, an admin decision and the mobile API
 * all leave the same trail. While a property is still an unsubmitted draft only its creation is recorded: the
 * wizard saves it step by step and none of that is history. A property that is deleted takes its history with it.
 */
class PropertyObserver
{
    /** Column => the words the line uses. Columns that share a label are listed once. Anything else is ignored. */
    private const FIELD_LABELS = [
        'title'                        => 'title',
        'description'                  => 'description',
        'property_type'                => 'type',
        'living_arrangement'           => 'living arrangement',
        'occupancy_preference'         => 'who can rent',
        'house_rules'                  => 'house rules',
        'water_included'               => 'what rent includes',
        'electricity_included'         => 'what rent includes',
        'internet_included'            => 'what rent includes',
        'association_fees_included'    => 'what rent includes',
        'utilities_separately_metered' => 'what rent includes',
        'address'                      => 'address',
        'city_municipality'            => 'address',
        'barangay'                     => 'address',
        'latitude'                     => 'map pin',
        'longitude'                    => 'map pin',
        'default_lease_terms'          => 'rental terms',
        'rent_grace_days'              => 'rent rules',
        'late_fee_type'                => 'rent rules',
        'late_fee_value'               => 'rent rules',
        'lease_template_id'            => 'rental policy',
    ];

    public function created(Property $property): void
    {
        PropertyActivity::safely(fn () => PropertyActivity::record(
            $property->property_id,
            'property.created',
            'Created the listing "'.Str::limit((string) $property->title, 80).'"',
        ));
    }

    public function updated(Property $property): void
    {
        PropertyActivity::safely(function () use ($property) {
            $wasDraft = $property->getOriginal('publication_status') === 'Draft';

            // Leaving draft is the one status change that matters while drafts are quiet: the wizard's submit.
            if ($wasDraft && $property->wasChanged('publication_status')) {
                PropertyActivity::record($property->property_id, 'property.submitted', 'Submitted the listing for admin review');

                return;
            }

            if ($property->isDraft()) {
                return;
            }

            $this->recordStatus($property);
            $this->recordEdit($property);
        });
    }

    private function recordStatus(Property $property): void
    {
        if ($property->wasChanged('publication_status')) {
            $from = $property->getOriginal('publication_status');
            [$action, $summary] = match ($property->publication_status) {
                'Published'   => $from === 'Suspended'
                    ? ['property.reinstated', 'Listing reinstated']
                    : ['property.published', 'Published the listing'],
                'Unpublished' => ['property.unpublished', 'Hid the listing from tenants'],
                'Suspended'   => ['property.suspended', 'Listing suspended'],
                default       => [null, null],
            };

            if ($action) {
                PropertyActivity::record($property->property_id, $action, $summary);
            }
        }

        if (! $property->wasChanged('verification_status')) {
            return;
        }

        $reason = trim((string) $property->rejection_reason);

        match ($property->verification_status) {
            'Approved' => PropertyActivity::record($property->property_id, 'property.approved', 'Listing approved'),
            'Rejected' => PropertyActivity::record(
                $property->property_id,
                'property.rejected',
                'Listing rejected'.($reason !== '' ? ': '.Str::limit($reason, 160) : ''),
                ['metadata' => array_filter(['reason' => $reason])],
            ),
            default => null, // Pending is told by the edit line (or the requeue line below)
        };
    }

    private function recordEdit(Property $property): void
    {
        $fields = collect(array_keys($property->getChanges()))
            ->map(fn ($column) => self::FIELD_LABELS[$column] ?? null)
            ->filter()->unique()->values()->all();

        $requeued = $property->wasChanged('verification_status') && $property->verification_status === 'Pending';

        if ($fields === [] && ! $requeued) {
            return;
        }

        if ($fields === []) {
            PropertyActivity::record($property->property_id, 'property.requeued', 'Sent the listing back for admin review');

            return;
        }

        PropertyActivity::record(
            $property->property_id,
            'property.updated',
            'Edited the listing ('.implode(', ', $fields).')'.($requeued ? '. It went back for admin review.' : ''),
            ['metadata' => ['fields' => $fields, 'requeued' => $requeued]],
        );
    }
}
