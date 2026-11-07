@extends('layouts.admin')

@section('page-title', 'Analytics')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" title="Analytics" subtitle="Platform numbers and data export." />

    {{-- Top bar: dropdown + export --}}
    <div class="flex items-center justify-between mb-5">
        <form method="GET" action="{{ route('admin.report-analytics.index') }}" id="sectionForm">
            <x-styled-select name="section" :options="['properties' => 'Properties and units', 'reservations' => 'Reservations', 'users' => 'Users']"
                :selected="$section" :autosubmit="true"
                class="h-10 text-[13.5px] font-semibold pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
        </form>

       <a href="{{ route('admin.report-analytics.export', array_merge(request()->query(), ['section' => $section])) }}"
           class="inline-flex h-10 items-center gap-1.5 text-[13.5px] font-semibold text-[#060D26] border border-[#E2E4EC] bg-white rounded-xl px-4 hover:bg-[#ECEEF6] transition-colors">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export
        </a>
    </div>

    {{-- ===== PROPERTIES & UNITS ===== --}}
    @if($section === 'properties')

        <x-stat-strip class="mb-4" :cells="[
            ['label' => 'Properties', 'value' => number_format($totalProperties)],
            ['label' => 'Units', 'value' => number_format($totalUnits)],
            ['label' => 'Occupancy', 'value' => $occupancyRate.'%'],
        ]" />

        {{-- Donut + detail cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            {{-- Donut chart --}}
            <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                <h2 class="text-[15px] font-semibold text-[#060D26] mb-4">Unit status distribution</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="unitStatusChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#5B6A8E]">
                    @php $totalAll = $availableAll + $reservedAll + $occupiedAll; @endphp
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#22C55E]"></span> Available ({{ $totalAll > 0 ? round(($availableAll / $totalAll) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#FBBF24]"></span> Reserved ({{ $totalAll > 0 ? round(($reservedAll / $totalAll) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#EF4444]"></span> Occupied ({{ $totalAll > 0 ? round(($occupiedAll / $totalAll) * 100, 1) : 0 }}%)</span>
                </div>
            </div>

            <x-card flush>
                <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Type</x-admin.th>
                            <x-admin.th align="right">Properties</x-admin.th>
                            <x-admin.th align="right">Units</x-admin.th>
                            <x-admin.th align="right">Occupied</x-admin.th>
                            <x-admin.th align="right">Occupancy</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($typeBreakdown as $tb)
                            <tr>
                                <td class="px-4 py-2.5 text-[13px] font-semibold text-[#060D26]">{{ $tb['type'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['property_count'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['unit_count'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['occupied_count'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </x-card>
        </div>

        {{-- Filters + table --}}
        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="propFilterForm">
                <input type="hidden" name="section" value="properties">
                @php
                    $propTypeOptions = \App\Enums\PropertyType::options('All types');
                    $propStatusOptions = ['' => 'All statuses'] + array_combine(['Approved', 'Pending', 'Rejected'], ['Approved', 'Pending', 'Rejected']);
                @endphp
                <x-styled-select name="type" :options="$propTypeOptions" :selected="$typeFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
                <x-styled-select name="status" :options="$propStatusOptions" :selected="$statusFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
            </form>
            <span class="text-[13px] text-[#5B6A8E]">Showing {{ $properties->total() }} properties</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th>Property</x-admin.th>
                        <x-admin.th>Type</x-admin.th>
                        <x-admin.th>Status</x-admin.th>
                        <x-admin.th align="right">Units</x-admin.th>
                        <x-admin.th align="right">Available</x-admin.th>
                        <x-admin.th align="right">Occupied</x-admin.th>
                        <x-admin.th align="right">Rate</x-admin.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @forelse($properties as $property)
                        @php
                            $pTotal = $property->units_count;
                            $pOccupied = $property->occupied_units_count;
                            $pRate = $pTotal > 0 ? round(($pOccupied / $pTotal) * 100, 1) : 0;
                        @endphp
                        <tr class="hover:bg-[#F7F8FC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#060D26]">{{ $property->title }}</td>
                            <td class="px-4 py-2.5 text-[#5B6A8E]">{{ $property->property_type }}</td>
                            <td class="px-4 py-2.5">
                                <x-admin.status :status="$property->verification_status" />
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[#060D26]">{{ $pTotal }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[#5B6A8E]">{{ $property->available_units_count }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[#5B6A8E]">{{ $pOccupied }}</td>
                            <td class="px-4 py-2.5 text-right font-medium tabular-nums text-[#060D26]">{{ $pRate }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-[#5B6A8E]">No properties found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        @if($properties->hasPages())
            <div class="mt-4">{{ $properties->links() }}</div>
        @endif

    {{-- ===== RESERVATIONS ===== --}}
    @elseif($section === 'reservations')

        <x-stat-strip class="mb-4" :cells="[
            ['label' => 'Reservations', 'value' => number_format($allReservations)],
            ['label' => 'Occupied units', 'value' => number_format($occupiedUnits)],
            ['label' => 'Approval rate', 'value' => $approvalRate.'%'],
        ]" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                <h2 class="text-[15px] font-semibold text-[#060D26] mb-4">Reservation status distribution</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="reservationStatusChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#5B6A8E]">
                    @php $rTotal = $approvedCount + $pendingCount + $rejectedCount + $cancelledCount; @endphp
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#22C55E]"></span> Approved ({{ $rTotal > 0 ? round(($approvedCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#FBBF24]"></span> Pending ({{ $rTotal > 0 ? round(($pendingCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#EF4444]"></span> Rejected ({{ $rTotal > 0 ? round(($rejectedCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#94A3B8]"></span> Cancelled ({{ $rTotal > 0 ? round(($cancelledCount / $rTotal) * 100, 1) : 0 }}%)</span>
                </div>
            </div>

            <x-card flush>
                <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Type</x-admin.th>
                            <x-admin.th align="right">Reservations</x-admin.th>
                            <x-admin.th align="right">Approved</x-admin.th>
                            <x-admin.th align="right">Share</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($typeBreakdown as $tb)
                            <tr>
                                <td class="px-4 py-2.5 text-[13px] font-semibold text-[#060D26]">{{ $tb['type'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['total'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['approved'] }}</td>
                                <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#060D26]">{{ $tb['pct'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </x-card>
        </div>

        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="resFilterForm">
                <input type="hidden" name="section" value="reservations">
                @php
                    $resStatusList = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied', 'Rejected', 'Cancelled'];
                    $resStatusOptions = ['' => 'All statuses'] + array_combine($resStatusList, $resStatusList);
                    $resTimeOptions = ['' => 'All time', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
                @endphp
                <x-styled-select name="status" :options="$resStatusOptions" :selected="$statusFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
                <x-styled-select name="time" :options="$resTimeOptions" :selected="$timeFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
            </form>
            <span class="text-[13px] text-[#5B6A8E]">Showing {{ $reservations->total() }} reservations</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th>Tenant</x-admin.th>
                        <x-admin.th>Property</x-admin.th>
                        <x-admin.th>Unit</x-admin.th>
                        <x-admin.th>Status</x-admin.th>
                        <x-admin.th>Date</x-admin.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @forelse($reservations as $reservation)
                        <tr class="hover:bg-[#F7F8FC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#060D26]">{{ $reservation->tenant->first_name ?? '' }} {{ $reservation->tenant->last_name ?? '' }}</td>
                            <td class="px-4 py-2.5 text-[#5B6A8E]">{{ $reservation->property->title ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-[#060D26]">{{ $reservation->unit->unit_label ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <x-admin.status :status="$reservation->rental_status" />
                            </td>
                            <td class="px-4 py-2.5 text-[#5B6A8E]">{{ $reservation->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-[#5B6A8E]">No reservations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        @if($reservations->hasPages())
            <div class="mt-4">{{ $reservations->links() }}</div>
        @endif

    {{-- ===== USERS ===== --}}
    @elseif($section === 'users')

        <x-stat-strip class="mb-4" :cells="[
            ['label' => 'Users', 'value' => number_format($totalUsers)],
            ['label' => 'Verified landlords', 'value' => number_format($verifiedCount)],
            ['label' => 'Suspended', 'value' => number_format($suspendedCount)],
        ]" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                <h2 class="text-[15px] font-semibold text-[#060D26] mb-4">Users by role</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="userRoleChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#5B6A8E]">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#060D26]"></span> Admin ({{ $adminCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#FF8A66]"></span> Landlord ({{ $landlordCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#B35A3D]"></span> Tenant ({{ $tenantCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#94A3B8]"></span> No role ({{ $noRoleCount }})</span>
                </div>
            </div>

            <x-card flush>
                <dl class="divide-y divide-[#E2E4EC]">
                    <div class="bg-[#F7F8FC] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Verification</div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Applications</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $verifiedCount + $pendingVerif + $rejectedVerif }}</dd></div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Approved</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $verifiedCount }}</dd></div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Pending</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $pendingVerif }}</dd></div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Rejected</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $rejectedVerif }}</dd></div>
                    <div class="bg-[#F7F8FC] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Accounts</div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Active</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $totalUsers - $suspendedCount }}</dd></div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Suspended</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $suspendedCount }}</dd></div>
                    <div class="bg-[#F7F8FC] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">New sign-ups</div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">This week</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $thisWeek }}</dd></div>
                    <div class="flex items-center justify-between px-4 py-2"><dt class="text-[13px] text-[#5B6A8E]">Last 30 days</dt><dd class="text-[13px] font-semibold tabular-nums text-[#060D26]">{{ $last30Days }}</dd></div>
                </dl>
            </x-card>
        </div>

        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="userFilterForm">
                <input type="hidden" name="section" value="users">
                @php
                    $userRoleOptions = ['' => 'All roles'] + array_combine(['Admin', 'Landlord', 'Tenant'], ['Admin', 'Landlord', 'Tenant']);
                    $userStatusOptions = ['' => 'All statuses', 'active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'];
                @endphp
                <x-styled-select name="role" :options="$userRoleOptions" :selected="$roleFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
                <x-styled-select name="status" :options="$userStatusOptions" :selected="$statusFilter" :autosubmit="true"
                    class="h-10 text-[13.5px] pl-3 pr-8 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26]" />
            </form>
            <span class="text-[13px] text-[#5B6A8E]">Showing {{ $users->total() }} users</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th>Name</x-admin.th>
                        <x-admin.th>Email</x-admin.th>
                        <x-admin.th>Role</x-admin.th>
                        <x-admin.th>Account</x-admin.th>
                        <x-admin.th>Verification</x-admin.th>
                        <x-admin.th>Joined</x-admin.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @forelse($users as $user)
                        @php $userRole = $user->roles->first()?->role; @endphp
                        <tr class="hover:bg-[#F7F8FC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#060D26]">{{ $user->first_name }} {{ $user->last_name }}</td>
                            <td class="px-4 py-2.5 text-[#5B6A8E]">{{ $user->email }}</td>
                            <td class="px-4 py-2.5">
                                <span class="text-[13px] text-[#060D26]">{{ $userRole ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <x-admin.status :status="ucfirst($user->account_status)" />
                            </td>
                            <td class="px-4 py-2.5">
                                @if($user->verificationApplication)
                                    <x-admin.status :status="$user->verificationApplication->verification_status" />
                                @else
                                    <span class="text-[13px] text-[#5B6A8E]">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[#5B6A8E]">{{ $user->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-[#5B6A8E]">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        @if($users->hasPages())
            <div class="mt-4">{{ $users->links() }}</div>
        @endif

    @endif

</div>
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const donutOpts = {
        responsive: false,
        cutout: '70%',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
        },
    };

    @if($section === 'properties')
        const unitCtx = document.getElementById('unitStatusChart');
        if (unitCtx) {
            new Chart(unitCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Reserved', 'Occupied'],
                    datasets: [{
                        data: [{{ $availableAll }}, {{ $reservedAll }}, {{ $occupiedAll }}],
                        backgroundColor: ['#22C55E', '#FBBF24', '#EF4444'],
                        borderWidth: 0,
                    }],
                },
                options: donutOpts,
            });
        }
    @elseif($section === 'reservations')
        const resCtx = document.getElementById('reservationStatusChart');
        if (resCtx) {
            new Chart(resCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected', 'Cancelled'],
                    datasets: [{
                        data: [{{ $approvedCount }}, {{ $pendingCount }}, {{ $rejectedCount }}, {{ $cancelledCount }}],
                        backgroundColor: ['#22C55E', '#FBBF24', '#EF4444', '#94A3B8'],
                        borderWidth: 0,
                    }],
                },
                options: donutOpts,
            });
        }
    @elseif($section === 'users')
        const userCtx = document.getElementById('userRoleChart');
        if (userCtx) {
            new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Admin', 'Landlord', 'Tenant', 'No role'],
                    datasets: [{
                        data: [{{ $adminCount }}, {{ $landlordCount }}, {{ $tenantCount }}, {{ $noRoleCount }}],
                        backgroundColor: ['#060D26', '#FF8A66', '#B35A3D', '#94A3B8'],
                        borderWidth: 0,
                    }],
                },
                options: donutOpts,
            });
        }
    @endif
});
</script>
@endpush