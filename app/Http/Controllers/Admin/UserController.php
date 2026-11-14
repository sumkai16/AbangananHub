<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AccountFilePurger;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $role   = $request->input('role', 'All');
        $status = $request->input('status', 'all');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($role === 'No role', fn ($query) => $query->doesntHave('roles'))
            ->when(! in_array($role, ['All', 'No role'], true), function ($query) use ($role) {
                $query->whereHas('roles', fn ($q) => $q->where('role', $role));
            })
            ->when(in_array($status, ['active', 'suspended', 'inactive'], true), fn ($query) => $query->where('account_status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $roleTotals = UserRole::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');
        $roleCounts = [
            'Landlord' => $roleTotals['Landlord'] ?? 0,
            'Tenant'   => $roleTotals['Tenant'] ?? 0,
            'Admin'    => $roleTotals['Admin'] ?? 0,
        ];
        $roleCounts['No role'] = User::doesntHave('roles')->count();

        $statusTotals = User::selectRaw('account_status, COUNT(*) as total')->groupBy('account_status')->pluck('total', 'account_status');
        $statusCounts = [
            'active'    => $statusTotals['active'] ?? 0,
            'suspended' => $statusTotals['suspended'] ?? 0,
            'inactive'  => $statusTotals['inactive'] ?? 0,
        ];

        // What an administrator would otherwise have to hunt for.
        $attention = [
            'applications' => LandlordVerification::where('verification_status', 'Pending')->count(),
            'suspended'    => $statusCounts['suspended'],
            'unverified'   => User::whereNull('email_verified_at')->where('is_walk_in', false)->count(),
            'walk_ins'     => User::where('is_walk_in', true)->count(),
        ];

        // Sign-ups per day for the last 30 days.
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $byDay = User::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');
        $signupLabels = $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->values();
        $signupData = $days->map(fn ($d) => (int) ($byDay[$d] ?? 0))->values();

        return view('admin.users.index', compact(
            'users', 'search', 'role', 'status', 'roleCounts', 'statusCounts', 'attention', 'signupLabels', 'signupData'
        ));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'contact_number' => ['nullable', 'string', 'max:20', Phone::RULE],
            'account_status' => 'required|in:active,suspended,inactive',
            'roles'          => 'required|array|min:1',
            'roles.*'        => 'in:Admin,Landlord,Tenant',
        ]);

        $user = \DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'email'          => $data['email'],
                'password'       => Hash::make($data['password']),
                'contact_number' => $data['contact_number'] ?? null,
                'account_status' => $data['account_status'],
            ]);

            foreach ($data['roles'] as $role) {
                $user->assignRole($role);
            }

            AuditLog::record(
                'user.create',
                "Created account for {$data['email']}.",
                $user,
                null,
                ['roles' => implode(', ', $data['roles']), 'account_status' => $data['account_status']],
            );

            return $user;
        });

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['roles', 'verificationApplication', 'rentalBusiness', 'properties', 'reservations']);

        // Two independent scores, never blended: what tenants rated them as a
        // landlord, and what landlords rated them as a tenant.
        $landlordRating = $user->landlordRatingSummary();
        $tenantRating = $user->tenantRatingSummary();

        $recentReservations = $user->reservations()->with('property:property_id,title')->latest('created_at')->limit(5)->get();
        $recentProperties   = $user->properties()->latest('created_at')->limit(5)->get();

        // What admins did to this account, newest first.
        $adminActivity = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->user_id)
            ->latest('created_at')->limit(6)->get();

        // The last few sign-ins and security events on this account, for the Security card.
        $recentAuthEvents = AuthEvent::where('user_id', $user->user_id)->latest('created_at')->limit(5)->get();

        return view('admin.users.show', compact('user', 'landlordRating', 'tenantRating', 'recentReservations', 'recentProperties', 'adminActivity', 'recentAuthEvents'));
    }

    public function edit(User $user)
    {
        $user->load('roles');

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => ['required', 'email', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'contact_number' => ['nullable', 'string', 'max:20', Phone::RULE],
            'account_status' => 'required|in:active,suspended,inactive',
            'roles'          => 'required|array|min:1',
            'roles.*'        => 'in:Admin,Landlord,Tenant',
        ]);

        if ($user->user_id === auth()->id() && !in_array('Admin', $data['roles'], true)) {
            return back()->withInput()->with('error', 'You cannot remove your own Admin role.');
        }

        if ($user->user_id === auth()->id() && $data['account_status'] !== 'active') {
            return back()->withInput()->with('error', 'You cannot change your own account status.');
        }

        $before = [
            'email'          => $user->email,
            'account_status' => $user->account_status,
            'roles'          => $user->roles()->pluck('role')->sort()->implode(', '),
        ];

        $user->update([
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'email'          => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'account_status' => $data['account_status'],
        ]);

        // Sync roles: remove old, assign new
        \DB::transaction(function () use ($user, $data, $before) {
            $user->roles()->delete();
            $user->unsetRelation('roles');
            foreach ($data['roles'] as $role) {
                $user->assignRole($role);
            }

            $after = [
                'email'          => $data['email'],
                'account_status' => $data['account_status'],
                'roles'          => collect($data['roles'])->sort()->implode(', '),
            ];

            // Only record what actually moved, so the log reads as a diff
            // rather than a dump of every field on the form.
            $changes = [];
            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = $before[$field] . ' → ' . $value;
                }
            }
            AuditLog::record(
                'user.update',
                "Updated account {$user->email}." . ($changes ? '' : ' No tracked fields changed.'),
                $user,
                null,
                $changes,
            );
        });

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Email the user a password reset link. Admins never set or see a password; the
     * user chooses their own through the normal reset flow.
     */
    public function sendPasswordReset(User $user)
    {
        if (! $user->email) {
            return back()->with('error', 'This account has no email address to send a reset link to.');
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        AuditLog::record('user.password_reset_link', "Sent a password reset link to {$user->email}.", $user);

        return back()->with('success', "Password reset link sent to {$user->email}.");
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);

        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot change your own account status.');
        }

        $previous = $user->account_status;
        $user->update(['account_status' => $request->status]);

        AuditLog::record(
            'user.status_change',
            "Changed {$user->email} status to " . ucfirst($request->status) . '.',
            $user,
            null,
            ['from' => $previous, 'to' => $request->status],
        );

        if ($previous !== $request->status) {
            $copy = match ($request->status) {
                'suspended' => ['Account suspended', 'Your account has been suspended. Contact support if you believe this is a mistake.'],
                'active'    => ['Account reinstated', 'Your account is active again. Welcome back.'],
                default     => ['Account deactivated', 'Your account has been set to inactive.'],
            };

            Notification::notify($user->user_id, 'account', $copy[0], $copy[1], route('notifications.index'));
        }

        return back()->with('success', 'User status updated to ' . ucfirst($request->status) . '.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Hard-deleting a user cascades to every property, reservation, payment,
        // review, conversation, and report tied to them (see FK onDelete('cascade')
        // in the migrations). Block it once there's real activity to lose and
        // point the admin at suspension instead, which is reversible.
        $hasActivity = $user->properties()->exists()
            || $user->reservations()->exists()
            || $user->reviews()->exists();

        if ($hasActivity) {
            return back()->with('error', 'This user has properties, reservations, or reviews on record and cannot be deleted — suspend the account instead to preserve that history.');
        }

        // The audit row and the delete must commit together: the target is gone
        // afterwards, so a lost log entry leaves no other trace of the action.
        // auditable is null by design — the referenced row no longer exists, so
        // the identifying detail is snapshotted into metadata instead.
        // The verification and business rows cascade away with the user, so list their files first.
        $files = AccountFilePurger::snapshot($user);

        \DB::transaction(function () use ($user) {
            AuditLog::record(
                'user.delete',
                "Deleted account {$user->email}.",
                null,
                null,
                [
                    'user_id' => $user->user_id,
                    'email'   => $user->email,
                    'name'    => trim($user->first_name . ' ' . $user->last_name),
                    'roles'   => $user->roles()->pluck('role')->implode(', '),
                ],
            );

            $user->delete();
        });

        AccountFilePurger::purge($files);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
