<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Support\CsvExport;
use App\Support\UserAgentSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only list of account and security events (sign-ins, failures, lockouts, access denied). Like the audit
 * log, there is deliberately no action here that edits or clears rows.
 */
class SecurityEventController extends Controller
{
    private const ROLES = ['Admin', 'Landlord', 'Tenant'];

    public function index(Request $request)
    {
        ['search' => $search, 'role' => $role, 'event' => $event, 'from' => $from, 'to' => $to] = $this->filters($request);

        $base = $this->filtered($request);

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

    /**
     * CSV of exactly what the page shows for the same filters (it uses the same filtered() query). The export
     * is written to the audit log first. Only the listed columns go out; the metadata blob stays in the database.
     */
    public function export(Request $request)
    {
        $cap   = (int) config('security.export_row_cap', 50000);
        $query = $this->filtered($request)->with('user');
        $count = min($cap, (clone $query)->count());

        DB::transaction(fn () => AuditLog::record(
            'activity.export',
            "Exported {$count} rows of the account & security log.",
            metadata: ['filters' => $request->only(['search', 'role', 'event', 'from', 'to']), 'rows' => $count],
        ));

        $tz   = config('rentals.timezone');
        $rows = (function () use ($query, $cap, $tz) {
            foreach (CsvExport::capped($query, $cap) as $event) {
                yield [
                    $event->created_at?->timezone($tz)->format('Y-m-d H:i:s'),
                    $event->user ? trim($event->user->first_name . ' ' . $event->user->last_name) : $event->email,
                    $event->email,
                    $event->label(),
                    $event->channel,
                    UserAgentSummary::from($event->user_agent),
                    $event->ip_address,
                ];
            }
        })();

        return CsvExport::stream(
            'account-security-' . now($tz)->format('Y-m-d') . '.csv',
            ['When', 'Person', 'Email', 'Event', 'Channel', 'Device', 'IP'],
            $rows,
        );
    }

    /** The filter values as the page and the export both read them: unknown roles/events fall back to "all". */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'role'   => in_array($request->input('role'), self::ROLES, true) ? $request->input('role') : '',
            'event'  => array_key_exists((string) $request->input('event'), AuthEvent::EVENTS) ? $request->input('event') : '',
            'from'   => $request->input('from'),
            'to'     => $request->input('to'),
        ];
    }

    /** The one query behind both the page and the CSV, so an export can never disagree with what is on screen. */
    private function filtered(Request $request): Builder
    {
        ['search' => $search, 'role' => $role, 'event' => $event, 'from' => $from, 'to' => $to] = $this->filters($request);

        return AuthEvent::query()
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
    }
}
