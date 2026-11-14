<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only. Audit logs are append-only (see App\Models\AuditLog) — there is
 * deliberately no store, update, destroy, or "clear logs" action here.
 */
class AuditLogController extends Controller
{
    private const ROLES = ['Admin', 'Landlord', 'Tenant'];

    public function index(Request $request)
    {
        ['search' => $search, 'action' => $action, 'role' => $role, 'from' => $from, 'to' => $to] = $this->filters($request);

        $base = $this->filtered($request)->with('actor');

        $stats = [
            'total'       => (clone $base)->count(),
            'today'       => (clone $base)->whereDate('created_at', today())->count(),
            'actors'      => (clone $base)->distinct()->count('actor_id'),
            'destructive' => (clone $base)->whereIn('action', AuditLog::DESTRUCTIVE_ACTIONS)->count(),
        ];

        $logs = $base->latest('created_at')->paginate(25)->withQueryString();

        $actionOptions = ['' => 'All actions'] + AuditLog::ACTION_LABELS;
        $roleOptions   = ['' => 'All roles'] + array_combine(self::ROLES, self::ROLES);

        return view('admin.audit-logs.index', compact('logs', 'stats', 'search', 'action', 'role', 'from', 'to', 'actionOptions', 'roleOptions'));
    }

    /**
     * CSV of exactly what the page shows for the same filters (it uses the same filtered() query). The export
     * is itself written to the audit log before any data leaves, so pulling the log is never silent.
     */
    public function export(Request $request)
    {
        $cap   = (int) config('security.export_row_cap', 50000);
        $query = $this->filtered($request);
        $count = min($cap, (clone $query)->count());
        // Pin the newest row now so the "export" row recorded below is not part of its own file.
        $maxId = (clone $query)->max('log_id');

        DB::transaction(fn () => AuditLog::record(
            'activity.export',
            "Exported {$count} rows of the admin actions log.",
            metadata: ['filters' => $request->only(['search', 'role', 'action', 'from', 'to']), 'rows' => $count],
        ));

        $tz   = config('rentals.timezone');
        $rows = (function () use ($query, $maxId, $cap, $tz) {
            if ($maxId === null) {
                return;
            }

            foreach (CsvExport::capped($query->where('log_id', '<=', $maxId), $cap) as $log) {
                yield [
                    $log->created_at?->timezone($tz)->format('Y-m-d H:i:s'),
                    $log->actor_name,
                    $log->actor_email,
                    $log->actionLabel(),
                    $log->summary,
                    $log->reason,
                    $log->ip_address,
                ];
            }
        })();

        return CsvExport::stream(
            'admin-actions-' . now($tz)->format('Y-m-d') . '.csv',
            ['When', 'Actor', 'Actor email', 'Action', 'Summary', 'Reason', 'IP'],
            $rows,
        );
    }

    /** The filter values as the page and the export both read them: unknown roles/actions fall back to "all". */
    private function filters(Request $request): array
    {
        $action = (string) $request->input('action', '');

        return [
            'search' => $request->input('search'),
            'action' => $action,
            'role'   => in_array($request->input('role'), self::ROLES, true) ? $request->input('role') : '',
            'from'   => $request->input('from'),
            'to'     => $request->input('to'),
        ];
    }

    /** The one query behind both the page and the CSV, so an export can never disagree with what is on screen. */
    private function filtered(Request $request): Builder
    {
        ['search' => $search, 'action' => $action, 'role' => $role, 'from' => $from, 'to' => $to] = $this->filters($request);

        return AuditLog::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('summary', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($action, AuditLog::ACTION_LABELS), fn ($q) => $q->where('action', $action))
            ->when($role !== '', fn ($q) => $q->whereHas('actor.roles', fn ($r) => $r->where('role', $role)))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }
}
