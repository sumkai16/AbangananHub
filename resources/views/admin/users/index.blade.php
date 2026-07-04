@extends('layouts.admin')

@section('page-title', 'Users')

@section('content')
<div class="max-w-[1400px]">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">System Users</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">View and manage all registered accounts.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[13px] font-semibold text-gray-500">{{ number_format($users->total()) }} total</span>
            <a href="{{ route('admin.users.create') }}"
                class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-bold bg-[#286CD2] text-white rounded-xl hover:bg-[#1e5bb8] transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create User
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.users.index') }}"
        class="bg-white border border-gray-100 rounded-2xl p-4 mb-5 shadow-sm flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by name, email, or phone…"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2] transition-all" />
        </div>
        <select name="role"
            class="h-10 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 px-3 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2] transition-all">
            @foreach (['All', 'Admin', 'Landlord', 'Tenant'] as $r)
                <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>{{ $r }}</option>
            @endforeach
        </select>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#286CD2] text-white rounded-xl hover:bg-[#1e5bb8] transition-colors shadow-sm">
            Filter
        </button>
        @if($search || $role !== 'All')
            <a href="{{ route('admin.users.index') }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:text-[#1A1A2E] hover:border-gray-300 transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    @if ($users->isEmpty())
        <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1A1A2E]">No users found</p>
            <p class="text-[13px] text-gray-400 mt-1">Try adjusting your search or filter.</p>
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50/60 border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">User</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Contact</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Role(s)</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($users as $user)
                            @php
                                $status = $user->account_status ?? 'active';
                                $statusCls = match(strtolower($status)) {
                                    'active'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'suspended' => 'bg-red-50 text-red-600 border-red-200',
                                    default     => 'bg-gray-50 text-gray-500 border-gray-200',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($user->profile_picture)
                                            <img src="{{ asset('storage/' . $user->profile_picture) }}"
                                                alt="{{ $user->first_name }}"
                                                class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0" />
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-[#286CD2]/10 flex items-center justify-center shrink-0">
                                                <span class="text-[#286CD2] text-[12px] font-bold">
                                                    {{ strtoupper(substr($user->first_name ?? $user->email, 0, 1)) }}{{ strtoupper(substr($user->last_name ?? '', 0, 1)) }}
                                                </span>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                                {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—' }}
                                            </p>
                                            <p class="text-[12px] text-gray-400">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-[13.5px] text-gray-500">
                                    {{ $user->contact_number ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($user->roles as $userRole)
                                            @php
                                                $roleColors = [
                                                    'Admin'    => 'bg-purple-50 text-purple-700 border-purple-200',
                                                    'Landlord' => 'bg-blue-50 text-[#286CD2] border-blue-200',
                                                    'Tenant'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                ];
                                                $cls = $roleColors[$userRole->role] ?? 'bg-gray-50 text-gray-600 border-gray-200';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $cls }}">
                                                {{ $userRole->role }}
                                            </span>
                                        @empty
                                            <span class="text-[12px] text-gray-400 italic">No role</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusCls }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-[13px] text-gray-400">
                                    {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.users.show', $user->user_id) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-100 text-[12px] font-semibold text-[#1A1A2E] hover:bg-[#286CD2] hover:text-white hover:border-[#286CD2] transition-all">
                                        View
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="px-6 py-4 border-t border-gray-50">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
