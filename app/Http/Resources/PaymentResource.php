<?php

namespace App\Http\Resources;

use App\Models\Payout;

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
            // The online fee the tenant paid on top of `amount` (0 when none), and what this one row charged them.
            // A move-in checkout is two rows (rent + deposit); the fee sits on the rent row.
            'service_fee'                   => $this->attr('service_fee'),
            'charged_total'                 => $this->resource->amount !== null
                ? number_format((float) $this->resource->amount + (float) $this->resource->service_fee, 2, '.', '')
                : null,
            'payment_method'                => $this->attr('payment_method'),
            'paymongo_payment_intent_id'    => $this->attr('paymongo_payment_intent_id'),
            'paymongo_payment_id'           => $this->attr('paymongo_payment_id'),
            'paymongo_checkout_session_id'  => $this->attr('paymongo_checkout_session_id'),
            'status'                        => $this->attr('status'),
            'paid_at'                       => $this->attr('paid_at'),
            'released_at'                   => $this->attr('released_at'),
            'released_by'                   => $this->attr('released_by'),
            'release_reason'                => $this->attr('release_reason'),
            // Payout state is derived (payout_owed_at / payout_id); the keys stay as the mobile app knows them.
            'payout_id'                     => $this->attr('payout_id'),
            'payout_status'                 => $this->resource->payout_status,
            'paid_out_at'                   => $this->payout()?->paid_out_at,
            'paid_out_by'                   => $this->payout()?->paid_out_by,
            'payout_reference'              => $this->payout()?->payout_reference,
            'recorded_by'                   => $this->attr('recorded_by'),
            'reference_no'                  => $this->attr('reference_no'),
            'payment_notes'                 => $this->attr('payment_notes'),
            'created_at'                    => $this->attr('created_at'),
            'updated_at'                    => $this->attr('updated_at'),

            'reservation'                   => new ReservationResource($this->whenLoaded('reservation')),
            'recorder'                      => new UserResource($this->whenLoaded('recorder')),
        ];
    }

    /** The settling payout, loaded on demand and only when there is one (no query for unpaid rows). */
    private function payout(): ?Payout
    {
        if ($this->resource->payout_id === null) {
            return null;
        }

        return $this->resource->loadMissing('payout')->payout;
    }
}
