<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private const STATUSES = ['All', 'Held', 'Released', 'Pending'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'Held');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Held';
        }

        $payments = Payment::with(['reservation.tenant', 'reservation.property', 'reservation.unit'])
            ->when($status !== 'All', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'Held' => Payment::where('status', 'Held')->count(),
            'Released' => Payment::where('status', 'Released')->count(),
            'Pending' => Payment::where('status', 'Pending')->count(),
        ];
        $counts['All'] = Payment::count();

        $sums = [
            'Held' => Payment::where('status', 'Held')->sum('amount'),
            'Released' => Payment::where('status', 'Released')->sum('amount'),
        ];

        return view('admin.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'counts' => $counts,
            'sums' => $sums,
        ]);
    }

    public function release(Payment $payment)
    {
        abort_unless($payment->isHeld(), 409, 'Only held payments can be released.');

        $payment->update([
            'status' => 'Released',
            'released_at' => now(),
            'released_by' => auth()->id(),
        ]);

        $payment->load('reservation.property.landlord');

        $landlord = $payment->reservation?->property?->landlord;
        $landlordName = $landlord
            ? trim($landlord->first_name.' '.$landlord->last_name)
            : 'the landlord';

        $payment->reservation?->postSystemMessage(
            "AbangananHub has released the payment to {$landlordName}."
        );

        return back()->with('success', "Payment released to {$landlordName}.");
    }
}
