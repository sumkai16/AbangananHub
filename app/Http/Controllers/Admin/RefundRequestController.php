<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\AdminQueues;
use App\Services\PaymentRefunds;
use App\Support\Paging;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Every refund a tenant is waiting on AbangananHub for, in one list (queue rules in App\Services\AdminQueues, so the
 * sidebar number and the dashboard row match this page):
 *
 *  - move-in disputes: AbangananHub holds the tenant's online payment; the admin refunds it or releases it
 *    (the existing Payments actions, reused here, not rewritten);
 *  - refunds not returned: the landlord kept money paid straight to them and the tenant escalated. AbangananHub
 *    never held that money, so the admin reviews the report and acts on the landlord's account or listing.
 */
class RefundRequestController extends Controller
{
    private const TYPES = ['all', 'disputes', 'escalations'];

    public function index(Request $request)
    {
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';
        $search = trim((string) $request->query('search', ''));

        $with = ['tenant', 'property.landlord', 'unit', 'payments.reservation.tenant', 'payments.reservation.property'];

        $disputes = AdminQueues::moveInDisputes()->with($with)->get()->map(fn (Reservation $r) => $this->disputeRow($r));
        $escalations = AdminQueues::escalations()->select('reservations.*')->with(array_merge($with, ["refundReport"]))->get()
            ->map(fn (Reservation $r) => $this->escalationRow($r));

        $counts = ['disputes' => $disputes->count(), 'escalations' => $escalations->count()];
        $counts['all'] = $counts['disputes'] + $counts['escalations'];

        // Longest waiting first: a queue is worked from the top.
        $rows = match ($type) {
            'disputes' => $disputes,
            'escalations' => $escalations,
            default => $disputes->concat($escalations),
        };
        $rows = $rows
            ->when($search !== '', fn (Collection $c) => $c->filter(fn (array $row) => str_contains(
                mb_strtolower(implode(' ', [$row['tenant'], $row['landlord'], $row['property'], $row['unit'], $row['reason']])),
                mb_strtolower($search),
            )))
            ->sortBy(fn (array $row) => $row['raised_at'])
            ->values();

        $paged = Paging::collection($rows, 15);

        // What "Refund tenant" would send for each held row on this page, from one query (the Payments page does the same).
        $heldGroups = PaymentRefunds::heldGroups(
            $paged->getCollection()->pluck('payment')->filter()->values()
        );

        return view('admin.refund-requests.index', [
            'rows' => $paged,
            'counts' => $counts,
            'type' => $type,
            'search' => $search,
            'heldGroups' => $heldGroups,
            'oldest' => $disputes->concat($escalations)->min('raised_at'),
        ]);
    }

    /** @return array<string, mixed> */
    private function disputeRow(Reservation $r): array
    {
        $held = $r->payments->where('status', 'Held');

        return $this->row($r, 'disputes', (float) $held->sum('amount'), $r->move_in_dispute_reason ?: 'No reason given', $r->move_in_disputed_at, $held->first());
    }

    /** @return array<string, mixed> */
    private function escalationRow(Reservation $r): array
    {
        $note = $r->move_in_refund_note ?: 'no reason given';

        return $this->row($r, 'escalations', $r->keptDirectAmount(), 'Landlord kept it: '.$note, $r->refundReport?->created_at, null);
    }

    /** @return array<string, mixed> */
    private function row(Reservation $r, string $kind, float $amount, string $reason, $raisedAt, ?Payment $payment): array
    {
        $name = fn ($u) => $u ? trim($u->first_name.' '.$u->last_name) : '—';

        return [
            'kind' => $kind,
            'reservation' => $r,
            'tenant' => $name($r->tenant),
            'tenantEmail' => $r->tenant?->email,
            'landlord' => $name($r->property?->landlord),
            'property' => $r->property?->title ?? '—',
            'unit' => $r->unit?->unit_label ?? '',
            'amount' => $amount,
            'reason' => $reason,
            'raised_at' => $raisedAt ?? $r->updated_at,
            'payment' => $payment,
            'report' => $kind === 'escalations' ? $r->refundReport : null,
        ];
    }
}
