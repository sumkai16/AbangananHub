<?php

namespace App\Http\Resources;

class PaymentResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'payment_id'                    => $this->attr('payment_id'),
            'reservation_id'                => $this->attr('reservation_id'),
            'payment_type'                  => $this->attr('payment_type'),
            'billing_period'                => $this->attr('billing_period'),
            'amount'                        => $this->attr('amount'),
            'payment_method'                => $this->attr('payment_method'),
            'paymongo_payment_intent_id'    => $this->attr('paymongo_payment_intent_id'),
            'paymongo_payment_id'           => $this->attr('paymongo_payment_id'),
            'paymongo_checkout_session_id'  => $this->attr('paymongo_checkout_session_id'),
            'status'                        => $this->attr('status'),
            'paid_at'                       => $this->attr('paid_at'),
            'released_at'                   => $this->attr('released_at'),
            'released_by'                   => $this->attr('released_by'),
            'release_reason'                => $this->attr('release_reason'),
            'payout_status'                 => $this->attr('payout_status'),
            'paid_out_at'                   => $this->attr('paid_out_at'),
            'paid_out_by'                   => $this->attr('paid_out_by'),
            'payout_reference'              => $this->attr('payout_reference'),
            'recorded_by'                   => $this->attr('recorded_by'),
            'reference_no'                  => $this->attr('reference_no'),
            'payment_notes'                 => $this->attr('payment_notes'),
            'created_at'                    => $this->attr('created_at'),
            'updated_at'                    => $this->attr('updated_at'),

            'reservation'                   => new ReservationResource($this->whenLoaded('reservation')),
            'recorder'                      => new UserResource($this->whenLoaded('recorder')),
        ];
    }
}
