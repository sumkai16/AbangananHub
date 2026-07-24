@extends('layouts.admin')

@section('page-title', 'Users')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Page header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">System Users</h1>
            <p class="text-[13.5px] text-[#64748B] mt-1">View and manage all registered accounts.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[13px] font-semibold text-[#64748B]">{{ number_format($users->total()) }} total</span>
            <a href="{{ route('admin.users.create') }}"
                class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create User
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6 items-start">

        {{-- LEFT: filters + table --}}
        <div class="min-w-0">
            {{-- Filters --}}
            <form method="GET" action="{{ route('admin.users.index') }}"
                class="bg-white border border-[#E2E8F0] rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)] flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ $search }}"
                        placeholder="Search by name, email, or phone…" aria-label="Search by name, email, or phone"
                        class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all" />
                </div>
                <select name="role"
                    class="h-10 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] px-3 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                    @foreach (['All', 'Admin', 'Landlord', 'Tenant'] as $r)
                        <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
                <button type="submit"
                    class="h-10 px-5 text-[13.5px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-colors shadow-sm">
                    Filter
                </button>
                @if($search || $role !== 'All')
                    <a href="{{ route('admin.users.index') }}"
                        class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] hover:border-[#E2E8F0] transition-colors flex items-center">
                        Clear
                    </a>
                @endif
            </form>

            @if ($users->isEmpty())
                <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                    <div class="w-14 h-14 rounded-2xl bg-[#F7FCFC] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" />
                        </svg>
                    </div>
                    <p class="text-[15px] font-bold text-[#1F2937]">No users found</p>
                    <p class="text-[13px] text-[#94A3B8] mt-1">Try adjusting your search or filter.</p>
                </div>
            @else
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                    <div class="overflow-x-auto scrollbar-thin-light">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">User</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Contact</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Role(s)</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                                    <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Joined</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach ($users as $user)
                                    @php
                                        $status = $user->account_status ?? 'active';
                                        $statusCls = match(strtolower($status)) {
                                            'active'    => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                                            'suspended' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
                                            default     => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
                                        };
                                    @endphp
                                    <tr class="hover:bg-[#F7FCFC] transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                @if ($user->profile_picture)
                                                    <img src="{{ $user->profile_picture }}"
                                                        alt="{{ $user->first_name }}"
                                                        class="w-9 h-9 rounded-full object-cover border border-[#E2E8F0] shrink-0" />
                                                @else
                                                    <div class="w-9 h-9 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                                                        <span class="text-[#156F8C] text-[12px] font-bold">
                                                            {{ strtoupper(substr($user->first_name ?? $user->email, 0, 1)) }}{{ strtoupper(substr($user->last_name ?? '', 0, 1)) }}
                                                        </span>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                                            {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—' }}
                                                        </p>
                                                        @if ($user->is_walk_in)
                                                            {{-- A landlord-entered tenant, not a self-registered
                                                                 account — so its blank email is expected, not broken. --}}
                                                            <span class="inline-flex items-center h-5 px-2 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[10px] font-bold"
                                                                title="Walk-in tenant added by a landlord — identity not verified">Walk-in</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-[12px] text-[#94A3B8]">{{ $user->email ?: '—' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-[13.5px] text-[#64748B]">
                                            {{ $user->contact_number ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($user->roles as $userRole)
                                                    @php
                                                        $roleColors = [
                                                            'Admin'    => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
                                                            'Landlord' => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
                                                            'Tenant'   => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                                                        ];
                                                        $cls = $roleColors[$userRole->role] ?? 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]';
                                                    @endphp
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $cls }}">
                                                        {{ $userRole->role }}
                                                    </span>
                                                @empty
                                                    <span class="text-[12px] text-[#94A3B8] italic">No role</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusCls }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-[13px] text-[#94A3B8]">
                                            {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('admin.users.show', $user->user_id) }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] text-[12px] font-semibold text-[#1F2937] hover:bg-[#2AA7A1] hover:text-white hover:border-[#2AA7A1] transition-all">
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
                        <div class="px-6 py-4 border-t border-[#E2E8F0]">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- RIGHT: breakdown panel --}}
        <div class="space-y-5">

            {{-- Role breakdown donut --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <h2 class="text-[13.5px] font-bold text-[#1F2937] mb-4">User Breakdown</h2>
                <div class="relative h-40 flex items-center justify-center mb-4">
                    <canvas id="roleBreakdownChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <p class="text-[24px] font-extrabold text-[#1F2937] leading-none">{{ number_format($users->total()) }}</p>
                        <p class="text-[10px] font-semibold text-[#94A3B8] uppercase tracking-wider mt-0.5">Users</p>
                    </div>
                </div>
                <div class="space-y-2">
                    @php
                        $legendColors = [
                            'Landlord' => '#156F8C',
                            'Tenant'   => '#2AA7A1',
                            'Admin'    => '#69D2C6',
                            'No role'  => '#E2E8F0',
                        ];
                    @endphp
                    @foreach ($roleCounts as $label => $count)
                        <div class="flex items-center justify-between text-[12.5px]">
                            <span class="flex items-center gap-2 text-[#64748B]">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $legendColors[$label] }}"></span>
                                {{ $label }}
                            </span>
                            <span class="font-bold text-[#1F2937]">{{ number_format($count) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Account status --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
                <h2 class="text-[13.5px] font-bold text-[#1F2937] mb-4">Account Status</h2>
                <div class="space-y-3">
                    @php
                        $statusMeta = [
                            'active'    => ['label' => 'Active',    'color' => 'bg-[#22C55E]'],
                            'suspended' => ['label' => 'Suspended', 'color' => 'bg-[#EF4444]'],
                            'inactive'  => ['label' => 'Inactive',  'color' => 'bg-[#94A3B8]'],
                        ];
                        $statusTotal = max(array_sum($statusCounts), 1);
                    @endphp
                    @foreach ($statusCounts as $key => $count)
                        <div>
                            <div class="flex items-center justify-between text-[12.5px] mb-1">
                                <span class="text-[#64748B]">{{ $statusMeta[$key]['label'] }}</span>
                                <span class="font-bold text-[#1F2937]">{{ number_format($count) }}</span>
                            </div>
                            <div class="h-1.5 w-full bg-[#EEF8F8] rounded-full overflow-hidden">
                                <div class="h-full {{ $statusMeta[$key]['color'] }} rounded-full" style="width: {{ round($count / $statusTotal * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const ctx = document.getElementById('roleBreakdownChart');
            if (!ctx || typeof Chart === 'undefined') return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: @json(array_keys($roleCounts)),
                    datasets: [{
                        data: @json(array_values($roleCounts)),
                        backgroundColor: ['#156F8C', '#2AA7A1', '#69D2C6', '#E2E8F0'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true },
                    },
                },
            });
        })();
    </script>
@endpush
@endsection
