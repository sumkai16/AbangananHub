@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <x-page-header title="Reports" subtitle="Platform analytics and data export">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
        </x-slot:icon>
    </x-page-header>

    {{-- Top bar: dropdown + export --}}
    <div class="flex items-center justify-between mb-5">
        <form method="GET" action="{{ route('admin.report-analytics.index') }}" id="sectionForm">
            <x-styled-select name="section" :options="['properties' => 'Properties and units', 'reservations' => 'Reservations', 'users' => 'Users']"
                :selected="$section" :autosubmit="true"
                class="text-sm font-medium pl-3 pr-8 py-2 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
        </form>

       <a href="{{ route('admin.report-analytics.export', array_merge(request()->query(), ['section' => $section])) }}"
           class="inline-flex items-center gap-1.5 text-xs font-medium text-[#156F8C] border border-[#E2E8F0] rounded-lg px-3 py-2 hover:bg-[#EEF8F8] transition-colors">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export
        </a>
    </div>

    {{-- ===== PROPERTIES & UNITS ===== --}}
    @if($section === 'properties')

        {{-- Stat cards --}}
        <div class="grid grid-cols-3 gap-2.5 mb-4">
            <x-stat-card label="Total properties" :value="$totalProperties">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Total units" :value="$totalUnits">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Occupancy rate" :value="$occupancyRate.'%'" value-color="#156F8C" icon-bg="#EEF8F8">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        {{-- Donut + detail cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            {{-- Donut chart --}}
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <h2 class="text-sm font-semibold text-[#1F2937] mb-4">Unit status distribution</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="unitStatusChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#64748B]">
                    @php $totalAll = $availableAll + $reservedAll + $occupiedAll; @endphp
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#22C55E]"></span> Available ({{ $totalAll > 0 ? round(($availableAll / $totalAll) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#FBBF24]"></span> Reserved ({{ $totalAll > 0 ? round(($reservedAll / $totalAll) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#EF4444]"></span> Occupied ({{ $totalAll > 0 ? round(($occupiedAll / $totalAll) * 100, 1) : 0 }}%)</span>
                </div>
            </div>

            {{-- Detail cards by type --}}
            <div class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                @foreach($typeBreakdown as $tb)
                    <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-3.5">
                        <div class="mb-2">
                            <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full
                                @if($tb['type'] === 'Bedspace') bg-[#EEF8F8] text-[#156F8C]
                                @elseif($tb['type'] === 'Room') bg-[#22C55E]/[0.07] text-[#15803D]
                                @elseif($tb['type'] === 'Apartment') bg-[#FBBF24]/[0.10] text-[#B45309]
                                @else bg-[#EF4444]/[0.07] text-[#DC2626]
                                @endif">{{ $tb['type'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-[#64748B]">Properties</span>
                            <span class="font-medium text-[#1F2937]">{{ $tb['property_count'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-[#64748B]">Units</span>
                            <span class="font-medium text-[#1F2937]">{{ $tb['unit_count'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-[#64748B]">Occupied</span>
                            <span class="font-medium text-[#1F2937]">{{ $tb['occupied_count'] }}</span>
                        </div>
                        <div class="mt-2 h-1 bg-[#EEF8F8] rounded-full overflow-hidden">
                            <div class="h-full bg-[#2AA7A1] rounded-full" style="width: {{ $tb['rate'] }}%"></div>
                        </div>
                        <p class="text-[10px] text-[#64748B] mt-1">{{ $tb['rate'] }}% occupancy</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Filters + table --}}
        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="propFilterForm">
                <input type="hidden" name="section" value="properties">
                @php
                    $propTypeOptions = ['' => 'All types'] + array_combine(['Bedspace', 'Room', 'Apartment', 'House'], ['Bedspace', 'Room', 'Apartment', 'House']);
                    $propStatusOptions = ['' => 'All statuses'] + array_combine(['Approved', 'Pending', 'Rejected'], ['Approved', 'Pending', 'Rejected']);
                @endphp
                <x-styled-select name="type" :options="$propTypeOptions" :selected="$typeFilter" :autosubmit="true"
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
                <x-styled-select name="status" :options="$propStatusOptions" :selected="$statusFilter" :autosubmit="true"
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $properties->total() }} properties</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-[#E2E8F0]">
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Property</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Units</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Available</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Occupied</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($properties as $property)
                        @php
                            $pTotal = $property->units_count;
                            $pOccupied = $property->occupied_units_count;
                            $pRate = $pTotal > 0 ? round(($pOccupied / $pTotal) * 100, 1) : 0;
                        @endphp
                        <tr class="hover:bg-[#F7FCFC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#1F2937]">{{ $property->title }}</td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $property->property_type }}</td>
                            <td class="px-4 py-2.5">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full
                                    @if($property->verification_status === 'Approved') bg-[#22C55E]/[0.07] text-[#15803D]
                                    @elseif($property->verification_status === 'Pending') bg-[#FBBF24]/[0.10] text-[#B45309]
                                    @else bg-[#EF4444]/[0.07] text-[#DC2626]
                                    @endif">{{ $property->verification_status }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-[#1F2937]">{{ $pTotal }}</td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $property->available_units_count }}</td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $pOccupied }}</td>
                            <td class="px-4 py-2.5 font-medium text-[#156F8C]">{{ $pRate }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-[#64748B]">No properties found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($properties->hasPages())
            <div class="mt-4">{{ $properties->links() }}</div>
        @endif

    {{-- ===== RESERVATIONS ===== --}}
    @elseif($section === 'reservations')

        <div class="grid grid-cols-3 gap-2.5 mb-4">
            <x-stat-card label="Total reservations" :value="$allReservations">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Occupied units" :value="$occupiedUnits">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Approval rate" :value="$approvalRate.'%'" value-color="#22C55E" icon-bg="rgba(34,197,94,0.07)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22C55E" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <h2 class="text-sm font-semibold text-[#1F2937] mb-4">Reservation status distribution</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="reservationStatusChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#64748B]">
                    @php $rTotal = $approvedCount + $pendingCount + $rejectedCount + $cancelledCount; @endphp
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#22C55E]"></span> Approved ({{ $rTotal > 0 ? round(($approvedCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#FBBF24]"></span> Pending ({{ $rTotal > 0 ? round(($pendingCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#EF4444]"></span> Rejected ({{ $rTotal > 0 ? round(($rejectedCount / $rTotal) * 100, 1) : 0 }}%)</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#94A3B8]"></span> Cancelled ({{ $rTotal > 0 ? round(($cancelledCount / $rTotal) * 100, 1) : 0 }}%)</span>
                </div>
            </div>

            <div class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                @foreach($typeBreakdown as $tb)
                    <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-3.5">
                        <div class="mb-2">
                            <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full
                                @if($tb['type'] === 'Bedspace') bg-[#EEF8F8] text-[#156F8C]
                                @elseif($tb['type'] === 'Room') bg-[#22C55E]/[0.07] text-[#15803D]
                                @elseif($tb['type'] === 'Apartment') bg-[#FBBF24]/[0.10] text-[#B45309]
                                @else bg-[#EF4444]/[0.07] text-[#DC2626]
                                @endif">{{ $tb['type'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs mb-0.5">
                            <span class="text-[#64748B]">Reservations</span>
                            <span class="font-medium text-[#1F2937]">{{ $tb['total'] }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-[#64748B]">Approved</span>
                            <span class="font-medium text-[#1F2937]">{{ $tb['approved'] }}</span>
                        </div>
                        <div class="mt-2 h-1 bg-[#EEF8F8] rounded-full overflow-hidden">
                            <div class="h-full bg-[#156F8C] rounded-full" style="width: {{ $tb['pct'] }}%"></div>
                        </div>
                        <p class="text-[10px] text-[#64748B] mt-1">{{ $tb['pct'] }}% of total</p>
                    </div>
                @endforeach
            </div>
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
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
                <x-styled-select name="time" :options="$resTimeOptions" :selected="$timeFilter" :autosubmit="true"
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $reservations->total() }} reservations</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-[#E2E8F0]">
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Tenant</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Property</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Unit</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($reservations as $reservation)
                        <tr class="hover:bg-[#F7FCFC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#1F2937]">{{ $reservation->tenant->first_name ?? '' }} {{ $reservation->tenant->last_name ?? '' }}</td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $reservation->property->title ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-[#1F2937]">{{ $reservation->unit->unit_label ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $rStatus = $reservation->rental_status;
                                    $rClass = match($rStatus) {
                                        'Occupied', 'Rental Agreement Signed', 'Under Negotiation', 'Pending Rental Agreement' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                                        'Inquiry' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                        'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
                                        'Cancelled' => 'bg-[#EEF8F8] text-[#64748B]',
                                        default => 'bg-[#EEF8F8] text-[#64748B]',
                                    };
                                @endphp
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full {{ $rClass }}">{{ $rStatus }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $reservation->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-[#64748B]">No reservations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reservations->hasPages())
            <div class="mt-4">{{ $reservations->links() }}</div>
        @endif

    {{-- ===== USERS ===== --}}
    @elseif($section === 'users')

        <div class="grid grid-cols-3 gap-2.5 mb-4">
            <x-stat-card label="Registered users" :value="$totalUsers">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Verified landlords" :value="$verifiedCount" value-color="#22C55E" icon-bg="rgba(34,197,94,0.07)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22C55E" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Suspended" :value="$suspendedCount">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <h2 class="text-sm font-semibold text-[#1F2937] mb-4">Users by role</h2>
                <div class="flex justify-center mb-3">
                    <canvas id="userRoleChart" width="160" height="160"></canvas>
                </div>
                <div class="flex flex-wrap justify-center gap-3 text-xs text-[#64748B]">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#156F8C]"></span> Admin ({{ $adminCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#2AA7A1]"></span> Landlord ({{ $landlordCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#69D2C6]"></span> Tenant ({{ $tenantCount }})</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#94A3B8]"></span> No role ({{ $noRoleCount }})</span>
                </div>
            </div>

            <div class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Verification pipeline</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Total applications</span><span class="font-medium text-[#1F2937]">{{ $verifiedCount + $pendingVerif + $rejectedVerif }}</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Approved</span><span class="font-medium text-[#22C55E]">{{ $verifiedCount }}</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Pending</span><span class="font-medium text-[#D97706]">{{ $pendingVerif }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Rejected</span><span class="font-medium text-[#EF4444]">{{ $rejectedVerif }}</span></div>
                </div>
                <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Account status</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Active</span><span class="font-medium text-[#22C55E]">{{ $totalUsers - $suspendedCount }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Suspended</span><span class="font-medium text-[#1F2937]">{{ $suspendedCount }}</span></div>
                </div>
                <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Registration trend</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">This week</span><span class="font-medium text-[#1F2937]">{{ $thisWeek }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Last 30 days</span><span class="font-medium text-[#1F2937]">{{ $last30Days }}</span></div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="userFilterForm">
                <input type="hidden" name="section" value="users">
                @php
                    $userRoleOptions = ['' => 'All roles'] + array_combine(['Admin', 'Landlord', 'Tenant'], ['Admin', 'Landlord', 'Tenant']);
                    $userStatusOptions = ['' => 'All statuses', 'active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'];
                @endphp
                <x-styled-select name="role" :options="$userRoleOptions" :selected="$roleFilter" :autosubmit="true"
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
                <x-styled-select name="status" :options="$userStatusOptions" :selected="$statusFilter" :autosubmit="true"
                    class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]" />
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $users->total() }} users</span>
        </div>

        <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-[#E2E8F0]">
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Name</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Email</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Role</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Account</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Verification</th>
                        <th class="text-left px-4 py-2.5 text-[10px] font-semibold text-[#64748B] uppercase tracking-wider">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($users as $user)
                        @php $userRole = $user->roles->first()?->role; @endphp
                        <tr class="hover:bg-[#F7FCFC] transition-colors">
                            <td class="px-4 py-2.5 font-medium text-[#1F2937]">{{ $user->first_name }} {{ $user->last_name }}</td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $user->email }}</td>
                            <td class="px-4 py-2.5">
                                @if($userRole)
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">{{ $userRole }}</span>
                                @else
                                    <span class="text-[10px] text-[#64748B]">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full
                                    {{ $user->account_status === 'active' ? 'bg-[#22C55E]/[0.07] text-[#15803D]' : 'bg-[#EF4444]/[0.07] text-[#DC2626]' }}">{{ ucfirst($user->account_status) }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                @if($user->verificationApplication)
                                    @php
                                        $vStatus = $user->verificationApplication->verification_status;
                                        $vClass = match($vStatus) {
                                            'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                                            'Pending' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                            'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
                                            default => 'bg-[#EEF8F8] text-[#64748B]',
                                        };
                                    @endphp
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full {{ $vClass }}">{{ $vStatus }}</span>
                                @else
                                    <span class="text-[10px] text-[#64748B]">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[#64748B]">{{ $user->created_at->format('M j') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-[#64748B]">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="mt-4">{{ $users->links() }}</div>
        @endif

    @endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                        backgroundColor: ['#156F8C', '#2AA7A1', '#69D2C6', '#94A3B8'],
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