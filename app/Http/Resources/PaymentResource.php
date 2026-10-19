<?php

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Support\Facades\DB;

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
            $this->mergeWhen($this->viewerSeesInternals(), fn () => [
                'paymongo_payment_intent_id'   => $this->attr('paymongo_payment_intent_id'),
                'paymongo_payment_id'          => $this->attr('paymongo_payment_id'),
                'paymongo_checkout_session_id' => $this->attr('paymongo_checkout_session_id'),
            ]),
            'status'                        => $this->attr('status'),
            'paid_at'                       => $this->attr('paid_at'),
            'released_at'                   => $this->attr('released_at'),
            $this->mergeWhen($this->viewerSeesInternals(), fn () => ['released_by' => $this->attr('released_by')]),
            'release_reason'                => $this->attr('release_reason'),
            // Payout state is derived (payout_owed_at / payout_id); the keys stay as the mobile app knows them.
            'payout_status'                 => $this->resource->payout_status,
            $this->mergeWhen($this->viewerSeesInternals(), fn () => [
                'payout_id'        => $this->attr('payout_id'),
                'paid_out_at'      => $this->payout()?->paid_out_at,
                'paid_out_by'      => $this->payout()?->paid_out_by,
                'payout_reference' => $this->payout()?->payout_reference,
            ]),
            'recorded_by'                   => $this->attr('recorded_by'),
            'reference_no'                  => $this->attr('reference_no'),
            'payment_notes'                 => $this->attr('payment_notes'),
            'created_at'                    => $this->attr('created_at'),
            'updated_at'                    => $this->attr('updated_at'),

            'reservation'                   => new ReservationResource($this->whenLoaded('reservation')),
            'recorder'                      => new UserResource($this->whenLoaded('recorder')),
        ];
    }

    /**
     * Gateway ids and payout bookkeeping are for the property's landlord and admins only. The tenant gets this
     * resource nested in ReservationResource, so it has to decide per viewer.
     */
    private function viewerSeesInternals(): bool
    {
        // auth(), not request()->user(): the request's user resolver is only wired during a real HTTP request.
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $reservationId = $this->resource->reservation_id;
        $cache = request()->attributes->get('payment_landlord_ids', []);

        if (! array_key_exists($reservationId, $cache)) {
            $cache[$reservationId] = DB::table('reservations')
                ->join('properties', 'properties.property_id', '=', 'reservations.property_id')
                ->where('reservations.reservation_id', $reservationId)
                ->value('properties.landlord_id');
            request()->attributes->set('payment_landlord_ids', $cache);
        }

        return $cache[$reservationId] !== null && (int) $cache[$reservationId] === (int) $user->getKey();
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
