@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-[#2A2523]">System Users</h1>
            <p class="mt-1 text-sm text-[#9B9F98]">View and manage all registered accounts in the system.</p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#D7E8F3] border border-[#61B2F0] px-4 py-3 text-sm text-[#2A2523]">
                {{ session('status') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-[#9B9F98]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email, or phone…"
                    class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-[#9B9F98]/30 bg-white focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2]" />
            </div>
            <select name="role"
                class="text-sm rounded-xl border border-[#9B9F98]/30 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/20 focus:border-[#286CD2]">
                @foreach (['All', 'Admin', 'Landlord', 'Tenant'] as $r)
                    <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
            <button type="submit"
                class="px-5 py-2 text-sm font-semibold bg-[#286CD2] text-white rounded-xl hover:bg-[#1d57b0] transition-colors">
                Filter
            </button>
            @if($search || $role !== 'All')
                <a href="{{ route('admin.users.index') }}"
                    class="px-5 py-2 text-sm font-semibold border border-[#9B9F98]/40 text-[#9B9F98] rounded-xl hover:text-[#2A2523] hover:border-[#2A2523]/30 transition-colors flex items-center">
                    Clear
                </a>
            @endif
        </form>

        {{-- Results count --}}
        <p class="text-xs text-[#9B9F98] mb-4">
            Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
        </p>

        @if ($users->isEmpty())
            <div class="rounded-xl border border-[#9B9F98]/20 bg-[#F0EDE8] px-6 py-16 text-center">
                <svg class="mx-auto mb-3 text-[#9B9F98]" width="36" height="36" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="text-sm font-medium text-[#2A2523]">No users found</p>
                <p class="mt-1 text-sm text-[#9B9F98]">Try adjusting your search or filter.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-[#9B9F98]/20">
                <table class="min-w-full divide-y divide-[#9B9F98]/20">
                    <thead class="bg-[#F0EDE8]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Role(s)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#9B9F98]/10 bg-white">
                        @foreach ($users as $user)
                            <tr class="hover:bg-[#F7F8FA] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($user->profile_picture)
                                            <img src="{{ asset('storage/' . $user->profile_picture) }}"
                                                alt="{{ $user->first_name }}"
                                                class="w-9 h-9 rounded-full object-cover border border-[#9B9F98]/20 flex-shrink-0" />
                                        @else
                                            <div
                                                class="w-9 h-9 rounded-full bg-[#286CD2]/10 flex items-center justify-center flex-shrink-0">
                                                <span class="text-[#286CD2] text-xs font-bold">
                                                    {{ strtoupper(substr($user->first_name ?? $user->email, 0, 1)) }}{{ strtoupper(substr($user->last_name ?? '', 0, 1)) }}
                                                </span>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="text-sm font-semibold text-[#2A2523]">
                                                {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—' }}
                                            </div>
                                            <div class="text-xs text-[#9B9F98]">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#2A2523]">
                                    {{ $user->contact_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($user->roles as $userRole)
                                            @php
                                                $roleColors = [
                                                    'Admin'    => 'bg-purple-50 text-purple-700 border-purple-200',
                                                    'Landlord' => 'bg-blue-50 text-[#286CD2] border-blue-200',
                                                    'Tenant'   => 'bg-green-50 text-green-700 border-green-200',
                                                ];
                                                $cls = $roleColors[$userRole->role] ?? 'bg-gray-50 text-gray-600 border-gray-200';
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $cls }}">
                                                {{ $userRole->role }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-[#9B9F98]">No role</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $status = $user->account_status ?? 'active';
                                        $statusCls = match(strtolower($status)) {
                                            'active'    => 'bg-green-50 text-green-700 border-green-200',
                                            'suspended' => 'bg-red-50 text-red-700 border-red-200',
                                            'inactive'  => 'bg-gray-50 text-gray-600 border-gray-200',
                                            default     => 'bg-gray-50 text-gray-600 border-gray-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $statusCls }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#9B9F98]">
                                    {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif

    </div>
@endsection
