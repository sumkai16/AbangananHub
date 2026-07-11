<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['roles', 'verificationApplication', 'rentalBusiness', 'properties', 'reservations']);

        return view('admin.users.show', compact('user'));
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

        $user->update([
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'email'          => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'account_status' => $data['account_status'],
        ]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        // Sync roles: remove old, assign new
        $user->roles()->delete();
        foreach ($data['roles'] as $role) {
            $user->assignRole($role);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);

        $user->update(['account_status' => $request->status]);

        return back()->with('success', 'User status updated to ' . ucfirst($request->status) . '.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->user_id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
