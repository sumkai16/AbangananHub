<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoldMatchAlert;
use App\Models\TenantHold;
use App\Services\Holds\HoldMatcher;
use App\Services\Holds\HoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The admin side of unpaid-rent holds: confirm or reject what landlords report, lift holds, and review the
 * "this new account looks like a held tenant" alerts. All changes go through HoldService / HoldMatcher, which
 * write the audit rows. Confirm, release and put-on-hold sit behind `recent.password` (see routes).
 */
class TenantHoldController extends Controller
{
    private const TABS = ['pending', 'active', 'duplicates', 'history'];
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';

        $counts = [
            'pending'    => TenantHold::where('status', TenantHold::PENDING)->count(),
            'active'     => TenantHold::where('status', TenantHold::ACTIVE)->count(),
            'duplicates' => HoldMatchAlert::open()->count(),
            'history'    => TenantHold::whereIn('status', [TenantHold::RELEASED, TenantHold::REJECTED])->count(),
        ];

        $with = ['tenant', 'landlord', 'reservation.property', 'reservation.unit', 'sourceHold'];

        $rows = match ($tab) {
            'duplicates' => HoldMatchAlert::open()->with(['user', 'hold.tenant', 'hold.landlord'])->latest()->paginate(self::PER_PAGE),
            'history'    => TenantHold::with($with)->whereIn('status', [TenantHold::RELEASED, TenantHold::REJECTED])
                ->orderByDesc('updated_at')->paginate(self::PER_PAGE),
            'active'     => TenantHold::with($with)->where('status', TenantHold::ACTIVE)->orderByDesc('confirmed_at')->paginate(self::PER_PAGE),
            default      => TenantHold::with($with)->where('status', TenantHold::PENDING)->orderBy('flagged_at')->paginate(self::PER_PAGE),
        };

        return view('admin.tenant-holds.index', [
            'tab'    => $tab,
            'counts' => $counts,
            'rows'   => $rows->withQueryString(),
        ]);
    }

    public function confirm(Request $request, TenantHold $hold, HoldService $holds): RedirectResponse
    {
        return $this->run(fn () => $holds->confirm($hold, $request->user()), 'Hold confirmed. The tenant has been told.');
    }

    public function reject(Request $request, TenantHold $hold, HoldService $holds): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        return $this->run(fn () => $holds->reject($hold, $request->user(), $data['note']), 'Report rejected. The landlord has been told.');
    }

    public function release(Request $request, TenantHold $hold, HoldService $holds): RedirectResponse
    {
        return $this->run(fn () => $holds->release($hold, $request->user(), 'admin'), 'Hold released.');
    }

    public function dismissAlert(Request $request, HoldMatchAlert $alert, HoldMatcher $matcher): RedirectResponse
    {
        return $this->run(fn () => $matcher->dismiss($alert, $request->user()), 'Alert dismissed.');
    }

    public function holdAlert(Request $request, HoldMatchAlert $alert, HoldMatcher $matcher): RedirectResponse
    {
        return $this->run(fn () => $matcher->putOnHold($alert, $request->user()), 'The new account is now on hold.');
    }

    /** Runs one service call. A refused change (wrong state, missing note) shows as a message, never a 500. */
    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'That could not be done.');
        }

        return back()->with('success', $success);
    }
}
