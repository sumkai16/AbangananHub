<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthEvent;
use Illuminate\Http\Request;

/**
 * Read-only list of account and security events (sign-ins, failures, lockouts, access denied). Like the audit
 * log, there is deliberately no action here that edits or clears rows.
 */
class SecurityEventController extends Controller
{
    private const ROLES = ['Admin', 'Landlord', 'Tenant'];

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $role   = in_array($request->input('role'), self::ROLES, true) ? $request->input('role') : '';
        $event  = array_key_exists((string) $request->input('event'), AuthEvent::EVENTS) ? $request->input('event') : '';
        $from   = $request->input('from');
        $to     = $request->input('to');

        $base = AuthEvent::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('email', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($role !== '', fn ($q) => $q->whereHas('user.roles', fn ($r) => $r->where('role', $role)))
            ->when($event !== '', fn ($q) => $q->where('event', $event))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $stats = [
            'signins'  => (clone $base)->where('event', 'login')->whereDate('created_at', today())->count(),
            'failed'   => (clone $base)->where('event', 'login_failed')->whereDate('created_at', today())->count(),
            'lockouts' => (clone $base)->where('event', 'lockout')->count(),
            'denied'   => (clone $base)->where('event', 'access_denied')->count(),
        ];

        $events = $base->with('user.roles')->latest('created_at')->latest('event_id')->paginate(25)->withQueryString();

        $roleOptions  = ['' => 'All roles'] + array_combine(self::ROLES, self::ROLES);
        $eventOptions = ['' => 'All events'] + AuthEvent::EVENTS;

        return view('admin.security-events.index', compact('events', 'stats', 'search', 'role', 'event', 'from', 'to', 'roleOptions', 'eventOptions'));
    }
}
