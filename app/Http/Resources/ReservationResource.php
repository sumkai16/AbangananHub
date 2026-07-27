<?php

namespace App\Http\Resources;

class ReservationResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'reservation_id'              => $this->attr('reservation_id'),
            'property_id'                 => $this->attr('property_id'),
            'unit_id'                     => $this->attr('unit_id'),
            'tenant_id'                   => $this->attr('tenant_id'),
            'conversation_id'             => $this->attr('conversation_id'),
            'reservation_date'            => $this->attr('reservation_date'),
            'target_move_in_date'         => $this->attr('target_move_in_date'),
            'target_move_out_date'        => $this->attr('target_move_out_date'),
            'duration_of_stay'            => $this->attr('duration_of_stay'),
            'agreed_monthly_rent'         => $this->attr('agreed_monthly_rent'),
            'rent_due_day'                => $this->attr('rent_due_day'),
            'occupants_count'             => $this->attr('occupants_count'),
            'rental_status'               => $this->attr('rental_status'),
            'agreement_terms_notes'       => $this->attr('agreement_terms_notes'),
            'agreed_at'                   => $this->attr('agreed_at'),
            'agreed_ip'                   => $this->attr('agreed_ip'),
            'remarks'                     => $this->attr('remarks'),
            'rejection_reason'            => $this->attr('rejection_reason'),
            'landlord_tc_accepted_at'     => $this->attr('landlord_tc_accepted_at'),
            'tenant_tc_accepted_at'       => $this->attr('tenant_tc_accepted_at'),
            'tenant_confirmed_move_in_at' => $this->attr('tenant_confirmed_move_in_at'),
            'keys_turned_over_at'         => $this->attr('keys_turned_over_at'),
            'move_in_deadline_at'         => $this->attr('move_in_deadline_at'),
            'move_in_disputed_at'         => $this->attr('move_in_disputed_at'),
            'move_in_dispute_reason'      => $this->attr('move_in_dispute_reason'),
            'move_in_last_reminder_on'    => $this->attr('move_in_last_reminder_on'),
            'handover_at'                 => $this->attr('handover_at'),
            'handover_proposed_by'        => $this->attr('handover_proposed_by'),
            'handover_proposed_at'        => $this->attr('handover_proposed_at'),
            'handover_confirmed_at'       => $this->attr('handover_confirmed_at'),
            'created_at'                  => $this->attr('created_at'),
            'updated_at'                  => $this->attr('updated_at'),

            // Which escrow clock is running, resolved server-side. The raw
            // timestamps above are not enough to decide it (see
            // Reservation::moveInClockState()), and a client re-deriving it
            // could disagree with what the web view shows the counterparty.
            // Null when no clock is running.
            'move_in_clock'               => $this->resource->moveInClockState(),

            'property'     => new PropertyResource($this->whenLoaded('property')),
            'unit'         => new PropertyUnitResource($this->whenLoaded('unit')),
            'tenant'       => new UserResource($this->whenLoaded('tenant')),
            'conversation' => new ConversationResource($this->whenLoaded('conversation')),
            'payments'     => PaymentResource::collection($this->whenLoaded('payments')),
            'tenantRating' => $this->whenLoaded('tenantRating'),
        ];
    }
}
