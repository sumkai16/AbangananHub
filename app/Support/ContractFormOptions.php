<?php

namespace App\Support;

use App\Models\LandlordTenant;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/** What the Add contract modal offers: the landlord's tenants and the units a contract can still be added on. */
class ContractFormOptions
{
    /** Tenants on this landlord's list, for the Add contract picker. */
    public static function tenants(): Collection
    {
        return LandlordTenant::where('landlord_id', Auth::id())->visible()
            ->with('tenant:user_id,first_name,last_name')
            ->get()
            ->map(fn (LandlordTenant $row) => [
                'id' => $row->landlord_tenant_id,
                'label' => $row->code().' · '.trim($row->tenant?->first_name.' '.$row->tenant?->last_name),
            ])
            ->sortBy('label')
            ->values();
    }

    /**
     * Units a contract can be added on: approved, available, and with no live reservation (LeaseContractCreator's rule).
     * Each property carries its cover photo and each unit its first photo (small, for the pickers); a unit without
     * one gets null and the view draws a plain room icon.
     */
    public static function units(): Collection
    {
        // Images only, oldest first; the foreign key must be selected for the relation to match its parent.
        $images = fn ($q, string $fk) => $q->where('media_type', 'Image')->orderBy('media_id')->select('media_id', $fk, 'media_type', 'media_url');

        return Property::where('landlord_id', Auth::id())
            ->where('verification_status', 'Approved')
            ->with([
                'media' => fn ($q) => $images($q, 'property_id'),
                'units' => fn ($q) => $q
                    ->where('verification_status', 'Approved')
                    ->where('availability_status', 'Available')
                    ->whereDoesntHave('reservations', fn ($r) => $r->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES))
                    ->orderBy('unit_label'),
                'units.media' => fn ($q) => $images($q, 'unit_id'),
            ])
            ->orderBy('title')
            ->get()
            ->filter(fn (Property $p) => $p->units->isNotEmpty())
            ->map(fn (Property $p) => [
                'id' => $p->property_id,
                'title' => $p->title,
                'area' => trim(($p->barangay ? $p->barangay.', ' : '').$p->city_municipality, ', '),
                'photo' => Images::resize($p->media->first()?->media_url, 480),
                'terms' => $p->default_lease_terms,
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->unit_id,
                    'label' => $u->unit_label,
                    'rent' => (float) $u->rental_fee,
                    'deposit' => (float) ($u->security_deposit ?? 0),
                    'limit' => $u->occupancy_limit,
                    'floor' => $u->floor,
                    'photo' => Images::resize($u->media->first()?->media_url, 320),
                ])->values(),
            ])
            ->values();
    }
}
