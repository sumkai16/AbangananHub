<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Mobile equivalent of Landlord\PaymentController's index/store. CSV export
 * and the printable receipt are web/desktop-only concerns (a receipt is a
 * document to print or attach, not a phone screen) and are deliberately not
 * ported — see plans/mobile-app.md.
 */
class PaymentController extends Controller
{
    private const METHODS = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'];
    private const TYPES = ['Monthly', 'Deposit', 'Initial', 'Utility', 'Other'];

    public function index(Request $request): JsonResponse
    {
        $landlordId = auth()->id();

        $properties = Property::where('landlord_id', $landlordId)
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $statusFilter = $request->query('status', 'all');
        $propertyId = $request->integer('property') ?: null;

        $query = Reservation::whereIn('rental_status', ['Occupied', 'Completed'])
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments']);

        if ($propertyId && $properties->contains('property_id', $propertyId)) {
            $query->where('property_id', $propertyId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()
            ->map(function (Reservation $reservation) {
                $summary = RentLedger::for($reservation)->summary();

                return [
                    'reservation' => new ReservationResource($reservation),
                    'summary'     => $summary,
                    'standing'    => $this->standingFor($summary),
                ];
            })
            ->when($statusFilter !== 'all', fn ($rows) => $rows->where('standing', $statusFilter))
            ->sortByDesc(fn ($row) => $row['summary']['overdueAmount'])
            ->values();

        $totals = [
            'collected'   => round($rows->sum(fn ($r) => $r['summary']['collected']), 2),
            'outstanding' => round($rows->sum(fn ($r) => $r['summary']['outstanding']), 2),
            'overdue'     => round($rows->sum(fn ($r) => $r['summary']['overdueAmount']), 2),
            'behind'      => $rows->where('standing', 'overdue')->count(),
        ];

        return response()->json([
            'data'         => $rows,
            'properties'   => $properties,
            'totals'       => $totals,
            'statusFilter' => $statusFilter,
            'propertyId'   => $propertyId,
        ]);
    }

    public function store(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('recordPayment', $reservation);

        $data = $request->validate([
            'payment_type'   => ['required', Rule::in(self::TYPES)],
            'billing_period' => ['required_if:payment_type,Monthly', 'nullable', 'date'],
            'amount'         => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payment_method' => ['required', Rule::in(self::METHODS)],
            'paid_at'        => ['required', 'date', 'before_or_equal:today'],
            'reference_no'   => ['nullable', 'string', 'max:255'],
            'payment_notes'  => ['nullable', 'string', 'max:1000'],
        ], [
            'billing_period.required_if' => 'Choose which month this rent payment covers.',
            'paid_at.before_or_equal'    => 'A payment cannot be recorded for a future date.',
        ]);

        DB::transaction(function () use ($data, $reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->rental_status === 'Occupied',
                409,
                'This tenancy is no longer active, so payments cannot be added to it.'
            );

            Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type'   => $data['payment_type'],
                'billing_period' => $data['payment_type'] === 'Monthly'
                    ? Carbon::parse($data['billing_period'])->startOfMonth()
                    : null,
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'status'         => 'Paid',
                'paid_at'        => $data['paid_at'],
                'reference_no'   => $data['reference_no'] ?? null,
                'payment_notes'  => $data['payment_notes'] ?? null,
                'recorded_by'    => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Payment recorded.'], 201);
    }

    private function standingFor(array $summary): string
    {
        if ($summary['overdueCount'] > 0) {
            return 'overdue';
        }

        return $summary['outstanding'] > 0 ? 'due' : 'settled';
    }
}
