@extends('layouts.admin')

@section('page-title', 'Reports')

@section('content')
<div class="max-w-[1200px] mx-auto">

    {{-- Header --}}
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[#1F2937]">Reports</h1>
        <p class="text-sm text-[#64748B] mt-0.5">Platform analytics and data export</p>
    </div>

    {{-- Top bar: dropdown + export --}}
    <div class="flex items-center justify-between mb-5">
        <form method="GET" action="{{ route('admin.report-analytics.index') }}" id="sectionForm">
            <select name="section"
                    onchange="document.getElementById('sectionForm').submit()"
                    class="text-sm font-medium px-3 py-2 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937] focus:outline-none focus:ring-1 focus:ring-[#156F8C] focus:border-[#156F8C]">
                <option value="properties" {{ $section === 'properties' ? 'selected' : '' }}>Properties and units</option>
                <option value="reservations" {{ $section === 'reservations' ? 'selected' : '' }}>Reservations</option>
                <option value="users" {{ $section === 'users' ? 'selected' : '' }}>Users</option>
            </select>
        </form>

        <a href="{{ route('admin.report-analytics.index', array_merge(request()->query(), ['export' => 'excel'])) }}"
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
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" /></svg>
                    Total properties
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $totalProperties }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z" /></svg>
                    Total units
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $totalUnits }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>
                    Occupancy rate
                </div>
                <p class="text-2xl font-bold text-[#156F8C]">{{ $occupancyRate }}%</p>
            </div>
        </div>

        {{-- Donut + detail cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            {{-- Donut chart --}}
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
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
                    <div class="bg-white rounded-xl border border-[#E2E8F0] p-3.5">
                        <div class="mb-2">
                            <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full
                                @if($tb['type'] === 'Bedspace') bg-blue-50 text-blue-700
                                @elseif($tb['type'] === 'Room') bg-green-50 text-green-700
                                @elseif($tb['type'] === 'Apartment') bg-amber-50 text-amber-700
                                @else bg-red-50 text-red-700
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
                        <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden">
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
                <select name="type" onchange="document.getElementById('propFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All types</option>
                    @foreach(['Bedspace', 'Room', 'Apartment', 'House'] as $type)
                        <option value="{{ $type }}" {{ $typeFilter === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="document.getElementById('propFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All statuses</option>
                    @foreach(['Approved', 'Pending', 'Rejected'] as $status)
                        <option value="{{ $status }}" {{ $statusFilter === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $properties->total() }} properties</span>
        </div>

        <div class="bg-white rounded-xl border border-[#E2E8F0] overflow-hidden">
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
                                    @if($property->verification_status === 'Approved') bg-green-50 text-green-700
                                    @elseif($property->verification_status === 'Pending') bg-amber-50 text-amber-700
                                    @else bg-red-50 text-red-700
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
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                    Total reservations
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $allReservations }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                    Occupied units
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $occupiedUnits }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Approval rate
                </div>
                <p class="text-2xl font-bold text-[#22C55E]">{{ $approvalRate }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
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
                    <div class="bg-white rounded-xl border border-[#E2E8F0] p-3.5">
                        <div class="mb-2">
                            <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full
                                @if($tb['type'] === 'Bedspace') bg-blue-50 text-blue-700
                                @elseif($tb['type'] === 'Room') bg-green-50 text-green-700
                                @elseif($tb['type'] === 'Apartment') bg-amber-50 text-amber-700
                                @else bg-red-50 text-red-700
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
                        <div class="mt-2 h-1 bg-gray-100 rounded-full overflow-hidden">
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
                <select name="status" onchange="document.getElementById('resFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All statuses</option>
                    @foreach(['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied', 'Rejected', 'Cancelled'] as $s)
                        <option value="{{ $s }}" {{ $statusFilter === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
                <select name="time" onchange="document.getElementById('resFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All time</option>
                    <option value="7" {{ $timeFilter === '7' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ $timeFilter === '30' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="90" {{ $timeFilter === '90' ? 'selected' : '' }}>Last 90 days</option>
                </select>
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $reservations->total() }} reservations</span>
        </div>

        <div class="bg-white rounded-xl border border-[#E2E8F0] overflow-hidden">
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
                                        'Occupied', 'Rental Agreement Signed', 'Under Negotiation', 'Pending Rental Agreement' => 'bg-green-50 text-green-700',
                                        'Inquiry' => 'bg-amber-50 text-amber-700',
                                        'Rejected' => 'bg-red-50 text-red-700',
                                        'Cancelled' => 'bg-gray-100 text-[#64748B]',
                                        default => 'bg-gray-100 text-[#64748B]',
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
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" /></svg>
                    Registered users
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $totalUsers }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    Verified landlords
                </div>
                <p class="text-2xl font-bold text-[#22C55E]">{{ $verifiedCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
                <div class="flex items-center gap-1.5 text-xs text-[#64748B] mb-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    Suspended
                </div>
                <p class="text-2xl font-bold text-[#1F2937]">{{ $suspendedCount }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4">
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
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Verification pipeline</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Total applications</span><span class="font-medium text-[#1F2937]">{{ $verifiedCount + $pendingVerif + $rejectedVerif }}</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Approved</span><span class="font-medium text-[#22C55E]">{{ $verifiedCount }}</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Pending</span><span class="font-medium text-[#D97706]">{{ $pendingVerif }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Rejected</span><span class="font-medium text-[#EF4444]">{{ $rejectedVerif }}</span></div>
                </div>
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Account status</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">Active</span><span class="font-medium text-[#22C55E]">{{ $totalUsers - $suspendedCount }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Suspended</span><span class="font-medium text-[#1F2937]">{{ $suspendedCount }}</span></div>
                </div>
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-3.5">
                    <div class="mb-2"><span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">Registration trend</span></div>
                    <div class="flex justify-between text-xs mb-0.5"><span class="text-[#64748B]">This week</span><span class="font-medium text-[#1F2937]">{{ $thisWeek }}</span></div>
                    <div class="flex justify-between text-xs"><span class="text-[#64748B]">Last 30 days</span><span class="font-medium text-[#1F2937]">{{ $last30Days }}</span></div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-2.5">
            <form method="GET" action="{{ route('admin.report-analytics.index') }}" class="flex items-center gap-2" id="userFilterForm">
                <input type="hidden" name="section" value="users">
                <select name="role" onchange="document.getElementById('userFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All roles</option>
                    @foreach(['Admin', 'Landlord', 'Tenant'] as $role)
                        <option value="{{ $role }}" {{ $roleFilter === $role ? 'selected' : '' }}>{{ $role }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="document.getElementById('userFilterForm').submit()"
                        class="text-xs px-2.5 py-1.5 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]">
                    <option value="">All statuses</option>
                    @foreach(['Active', 'Suspended'] as $acctStatus)
                        <option value="{{ $acctStatus }}" {{ $statusFilter === $acctStatus ? 'selected' : '' }}>{{ $acctStatus }}</option>
                    @endforeach
                </select>
            </form>
            <span class="text-xs text-[#64748B]">Showing {{ $users->total() }} users</span>
        </div>

        <div class="bg-white rounded-xl border border-[#E2E8F0] overflow-hidden">
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
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{{ $userRole }}</span>
                                @else
                                    <span class="text-[10px] text-[#64748B]">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full
                                    {{ $user->account_status === 'Active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $user->account_status }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                @if($user->verificationApplication)
                                    @php
                                        $vStatus = $user->verificationApplication->verification_status;
                                        $vClass = match($vStatus) {
                                            'Approved' => 'bg-green-50 text-green-700',
                                            'Pending' => 'bg-amber-50 text-amber-700',
                                            'Rejected' => 'bg-red-50 text-red-700',
                                            default => 'bg-gray-100 text-[#64748B]',
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