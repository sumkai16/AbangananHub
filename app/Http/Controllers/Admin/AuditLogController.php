<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Read-only. Audit logs are append-only (see App\Models\AuditLog) — there is
 * deliberately no store, update, destroy, or "clear logs" action here.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $action = $request->input('action', '');
        $from   = $request->input('from');
        $to     = $request->input('to');

        $base = AuditLog::with('actor')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('summary', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($action, AuditLog::ACTION_LABELS), fn($q) => $q->where('action', $action))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to));

        $stats = [
            'total'       => (clone $base)->count(),
            'today'       => (clone $base)->whereDate('created_at', today())->count(),
            'actors'      => (clone $base)->distinct()->count('actor_id'),
            'destructive' => (clone $base)->whereIn('action', AuditLog::DESTRUCTIVE_ACTIONS)->count(),
        ];

        $logs = $base->latest('created_at')->paginate(25)->withQueryString();

        $actionOptions = ['' => 'All actions'] + AuditLog::ACTION_LABELS;

        return view('admin.audit-logs.index', compact('logs', 'stats', 'search', 'action', 'from', 'to', 'actionOptions'));
    }
}
