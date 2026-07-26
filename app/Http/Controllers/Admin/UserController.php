<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $role   = $request->input('role', 'All');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($role !== 'All', function ($query) use ($role) {
                $query->whereHas('roles', fn($q) => $q->where('role', $role));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $roleCounts = [
            'Landlord' => UserRole::where('role', 'Landlord')->count(),
            'Tenant'   => UserRole::where('role', 'Tenant')->count(),
            'Admin'    => UserRole::where('role', 'Admin')->count(),
        ];
        $roleCounts['No role'] = User::doesntHave('roles')->count();

        $statusCounts = [
            'active'    => User::where('account_status', 'active')->count(),
            'suspended' => User::where('account_status', 'suspended')->count(),
            'inactive'  => User::where('account_status', 'inactive')->count(),
        ];

        return view('admin.users.index', compact('users', 'search', 'role', 'roleCounts', 'statusCounts'));
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
            'contact_number' => 'nullable|string|max:20',
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

        return view('admin.users.show', compact('user', 'landlordRating', 'tenantRating'));
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
            'password'       => 'nullable|string|min:8|confirmed',
            'contact_number' => 'nullable|string|max:20',
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

        $passwordReset = ! empty($data['password']);
        if ($passwordReset) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        // Sync roles: remove old, assign new
        \DB::transaction(function () use ($user, $data, $before, $passwordReset) {
            $user->roles()->delete();
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
            if ($passwordReset) {
                $changes['password'] = 'reset by admin';
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

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
