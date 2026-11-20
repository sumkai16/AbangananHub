<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserActivity;
use App\Support\CsvExport;
use App\Support\UserAgentSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only list of everything signed-in people did (UserActivity). Like the audit log, there is deliberately no
 * action here that edits or clears rows.
 */
class UserActivityController extends Controller
{
    private const ROLES = ['Admin', 'Landlord', 'Tenant'];

    public function index(Request $request)
    {
        ['search' => $search, 'role' => $role, 'outcome' => $outcome, 'from' => $from, 'to' => $to] = $this->filters($request);

        $base = $this->filtered($request);
        $today = UserActivity::query()->whereDate('created_at', today());

        $stats = [
            'today'    => (clone $today)->count(),
            'people'   => (clone $today)->whereNotNull('user_id')->distinct()->count('user_id'),
            'failed'   => (clone $today)->where('outcome', '!=', 'ok')->count(),
            'matching' => (clone $base)->count(),
        ];

        $activities = $base->with('user.roles')->latest('created_at')->latest('activity_id')->paginate(25)->withQueryString();

        $roleOptions    = ['' => 'All roles'] + array_combine(self::ROLES, self::ROLES);
        $outcomeOptions = ['' => 'Any result'] + UserActivity::OUTCOMES;

        return view('admin.user-activity.index', compact('activities', 'stats', 'search', 'role', 'outcome', 'from', 'to', 'roleOptions', 'outcomeOptions'));
    }

    /**
     * CSV of exactly what the page shows for the same filters (it uses the same filtered() query). The export
     * is written to the audit log first.
     */
    public function export(Request $request)
    {
        $cap   = (int) config('security.export_row_cap', 50000);
        $query = $this->filtered($request);
        $count = min($cap, (clone $query)->count());

        DB::transaction(fn () => AuditLog::record(
            'activity.export',
            "Exported {$count} rows of the activity log.",
            metadata: ['filters' => $request->only(['search', 'role', 'outcome', 'from', 'to']), 'rows' => $count],
        ));

        $tz   = config('rentals.timezone');
        $rows = (function () use ($query, $cap, $tz) {
            foreach (CsvExport::capped($query, $cap) as $a) {
                yield [
                    $a->created_at?->timezone($tz)->format('Y-m-d H:i:s'),
                    $a->actor_name,
                    $a->actor_email,
                    $a->actor_role,
                    $a->summary,
                    $a->targetLabel(),
                    UserActivity::OUTCOMES[$a->outcome] ?? $a->outcome,
                    $a->status,
                    $a->method . ' ' . $a->path,
                    $a->channel,
                    UserAgentSummary::from($a->user_agent),
                    $a->ip_address,
                ];
            }
        })();

        return CsvExport::stream(
            'activity-log-' . now($tz)->format('Y-m-d') . '.csv',
            ['When', 'Person', 'Email', 'Role', 'Action', 'Record', 'Result', 'Status', 'Request', 'Channel', 'Device', 'IP'],
            $rows,
        );
    }

    /** The filter values as the page and the export both read them: unknown values fall back to "all". */
    private function filters(Request $request): array
    {
        return [
            'search'  => trim((string) $request->input('search', '')),
            'role'    => in_array($request->input('role'), self::ROLES, true) ? $request->input('role') : '',
            'outcome' => array_key_exists((string) $request->input('outcome'), UserActivity::OUTCOMES) ? $request->input('outcome') : '',
            'from'    => $request->input('from'),
            'to'      => $request->input('to'),
        ];
    }

    /** The one query behind both the page and the CSV, so an export can never disagree with what is on screen. */
    private function filtered(Request $request): Builder
    {
        ['search' => $search, 'role' => $role, 'outcome' => $outcome, 'from' => $from, 'to' => $to] = $this->filters($request);

        return UserActivity::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($q) => $q->where('actor_role', $role))
            ->when($outcome !== '', fn ($q) => $q->where('outcome', $outcome))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }
}
