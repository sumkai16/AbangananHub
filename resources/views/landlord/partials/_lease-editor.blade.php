{{--
    The "Edit policy" control on the send-agreement forms: opens the full-screen wording editor for one reservation.
    Needs $reservation. Posts `lease_body` only when the landlord edited it; otherwise the unit's policy
    (else the property's, else the standard one) is sent. See App\Support\LeaseTemplates.
--}}
@php
    $leaseTemplate = \App\Support\LeaseTemplates::forReservation($reservation);
    $tenantFirst = $reservation->tenant?->first_name ?? 'this tenant';
@endphp
<x-lease-editor
    :title="($leaseTemplate?->name ?? 'Standard policy').' · for '.$tenantFirst"
    :initial="\App\Support\LeaseBody::templateFor($reservation->property, $reservation->unit)"
    :starter="\App\Support\LeaseBody::starter()"
    :preview-url="route('landlord.reservations.leasePreview', $reservation)"
    button="Edit policy"
    :using-label="$leaseTemplate ? 'Sending: '.$leaseTemplate->name : 'Sending the standard policy'"
    edited-label="Edited for this tenant" />
