@extends('layouts.admin')

@section('page-title', 'Dashboard')

@section('content')
    <div class="max-w-7xl space-y-6">

        {{-- ── Page Header ─────────────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Dashboard</h1>
                <p class="text-[13.5px] text-gray-400 mt-0.5">Welcome back! Here's what's happening on AbangananHub.</p>
            </div>
            <div
                class="hidden sm:flex items-center gap-2 bg-white border border-gray-100 rounded-2xl px-4 py-2.5 shadow-sm text-[13px] text-gray-500">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                {{ now()->subDays(6)->format('M j') }} – {{ now()->format('M j, Y') }}
            </div>
        </div>

        {{-- ── Stat Cards ───────────────────────────────────────────── --}}

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">

            @php
                $statCards = [
                    ['label' => 'Total Users', 'value' => $totalUsers, 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z', 'color' => 'blue', 'sub' => 'Registered accounts'],
                    ['label' => 'Verified Landlords', 'value' => $verifiedLandlords, 'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'color' => 'emerald', 'sub' => 'Approved verifications'],
                    ['label' => 'Total Properties', 'value' => $totalProperties, 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25', 'color' => 'blue', 'sub' => 'Listed properties'],
                    ['label' => 'Total Units', 'value' => $totalUnits, 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z', 'color' => 'purple', 'sub' => 'Rental units'],
                    ['label' => 'Active Reservations', 'value' => $activeReservations, 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5', 'color' => 'amber', 'sub' => 'Approved bookings'],
                    ['label' => 'Pending Items', 'value' => $pendingItems, 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z', 'color' => 'red', 'sub' => 'Requires your action'],
                ];
                $colorMap = [
                    'blue' => ['icon_bg' => 'bg-blue-50', 'icon_text' => 'text-[#286CD2]', 'val_text' => 'text-[#286CD2]'],
                    'emerald' => ['icon_bg' => 'bg-emerald-50', 'icon_text' => 'text-emerald-600', 'val_text' => 'text-emerald-600'],
                    'purple' => ['icon_bg' => 'bg-purple-50', 'icon_text' => 'text-purple-600', 'val_text' => 'text-purple-600'],
                    'amber' => ['icon_bg' => 'bg-amber-50', 'icon_text' => 'text-amber-600', 'val_text' => 'text-amber-600'],
                    'red' => ['icon_bg' => 'bg-red-50', 'icon_text' => 'text-red-500', 'val_text' => 'text-red-500'],
                ];
            @endphp

            @foreach($statCards as $card)
                @php $c = $colorMap[$card['color']]; @endphp
                <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[11.5px] font-bold text-gray-400 uppercase tracking-wider leading-tight">
                            {{ $card['label'] }}</p>
                        <div class="w-8 h-8 rounded-xl {{ $c['icon_bg'] }} flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 {{ $c['icon_text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-[28px] font-extrabold {{ $c['val_text'] }} leading-none">{{ number_format($card['value']) }}
                    </p>
                    <p class="text-[11.5px] text-gray-400 mt-1.5">{{ $card['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ── Charts Row ───────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- Platform Overview --}}
            <div class="lg:col-span-7 bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-[15px] font-bold text-[#1A1A2E]">Platform Overview</h2>
                        <p class="text-[12px] text-gray-400 mt-0.5">New activity over the last 7 days</p>
                    </div>
                    <div class="flex items-center gap-4 text-[12px] text-gray-400">
                        <span class="flex items-center gap-1.5"><span
                                class="w-3 h-0.5 rounded-full bg-[#286CD2] inline-block"></span>Users</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-3 h-0.5 rounded-full bg-emerald-500 inline-block"></span>Properties</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-3 h-0.5 rounded-full bg-amber-400 inline-block"></span>Reservations</span>
                    </div>
                </div>
                <div class="h-56">
                    <canvas id="platformChart"></canvas>
                </div>
            </div>

            {{-- User Distribution --}}
            <div class="lg:col-span-5 bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-[15px] font-bold text-[#1A1A2E]">User Distribution</h2>
                    <p class="text-[12px] text-gray-400 mt-0.5">Breakdown by role</p>
                </div>
                <div class="flex items-center gap-6">
                    <div class="w-36 h-36 shrink-0">
                        <canvas id="distributionChart"></canvas>
                    </div>
                    <div class="flex-1 space-y-3">
                        @php
                            $distItems = [
                                ['label' => 'Tenants', 'value' => $totalTenants, 'dot' => 'bg-[#286CD2]'],
                                ['label' => 'Landlords', 'value' => $totalLandlords, 'dot' => 'bg-emerald-500'],
                                ['label' => 'Admins', 'value' => $totalAdmins, 'dot' => 'bg-purple-500'],
                                ['label' => 'Unverified Landlords', 'value' => $unverifiedLandlords, 'dot' => 'bg-amber-400'],
                            ];
                            $distTotal = max(1, $totalTenants + $totalLandlords + $totalAdmins + $unverifiedLandlords);
                        @endphp
                        @foreach($distItems as $item)
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $item['dot'] }}"></span>
                                    <span class="text-[12.5px] text-gray-500 truncate">{{ $item['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span
                                        class="text-[13px] font-bold text-[#1A1A2E]">{{ number_format($item['value']) }}</span>
                                    <span class="text-[11px] text-gray-400 w-10 text-right">
                                        {{ $distTotal > 0 ? round(($item['value'] / $distTotal) * 100, 1) : 0 }}%
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Pending Verifications + Recent Reservations ──────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- Pending Verifications --}}
            <div class="lg:col-span-8 bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden"
                x-data="{ tab: 'landlords' }">

                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between gap-4">
                    <h2 class="text-[15px] font-bold text-[#1A1A2E]">Pending Verifications</h2>
                    <div class="flex gap-1 bg-gray-50 rounded-xl p-1">
                        <button @click="tab = 'landlords'"
                            :class="tab === 'landlords' ? 'bg-white shadow-sm text-[#1A1A2E]' : 'text-gray-400'"
                            class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold transition-all">
                            Landlords
                            @if($pendingVerifications > 0)
                                <span
                                    class="ml-1 bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pendingVerifications }}</span>
                            @endif
                        </button>
                        <button @click="tab = 'properties'"
                            :class="tab === 'properties' ? 'bg-white shadow-sm text-[#1A1A2E]' : 'text-gray-400'"
                            class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold transition-all">
                            Properties
                            @if($pendingListings > 0)
                                <span
                                    class="ml-1 bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pendingListings }}</span>
                            @endif
                        </button>
                        <button @click="tab = 'units'"
                            :class="tab === 'units' ? 'bg-white shadow-sm text-[#1A1A2E]' : 'text-gray-400'"
                            class="px-3 py-1.5 rounded-lg text-[12.5px] font-semibold transition-all">
                            Units
                            @if($pendingUnits > 0)
                                <span
                                    class="ml-1 bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pendingUnits }}</span>
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Landlord verifications --}}
                <div x-show="tab === 'landlords'" x-cloak>
                    @if($pendingVerificationList->isEmpty())
                        <div class="py-10 text-center text-[13px] text-gray-400">No pending landlord verifications.</div>
                    @else
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-50">
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Name</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Business</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Date Submitted</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Status</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($pendingVerificationList as $v)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-3.5">
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E]">{{ $v->user->first_name }}
                                                {{ $v->user->last_name }}</p>
                                            <p class="text-[11.5px] text-gray-400">{{ $v->user->email }}</p>
                                        </td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-600">{{ $v->business_name ?? '—' }}</td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-400">
                                            {{ $v->submitted_at ? \Carbon\Carbon::parse($v->submitted_at)->format('M j, Y') : '—' }}
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <a href="{{ route('admin.verifications.show', $v) }}"
                                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                                Review →
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="px-6 py-3 border-t border-gray-50">
                            <a href="{{ route('admin.verifications.index', ['status' => 'Pending']) }}"
                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                View all pending verifications →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Property verifications --}}
                <div x-show="tab === 'properties'" x-cloak>
                    @if($pendingListingList->isEmpty())
                        <div class="py-10 text-center text-[13px] text-gray-400">No pending property verifications.</div>
                    @else
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-50">
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Property</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Type</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Landlord</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Date Submitted</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Status</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($pendingListingList as $p)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-3.5">
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E] truncate max-w-[180px]">
                                                {{ $p->title ?? 'Untitled' }}</p>
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span
                                                class="text-[11.5px] font-bold bg-[#286CD2]/10 text-[#286CD2] px-2 py-0.5 rounded-full">{{ $p->property_type ?? '—' }}</span>
                                        </td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-600">{{ $p->landlord?->first_name }}
                                            {{ $p->landlord?->last_name }}</td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-400">
                                            {{ $p->created_at ? $p->created_at->format('M j, Y') : '—' }}
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <a href="{{ route('admin.listings.approval') }}"
                                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                                Review →
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="px-6 py-3 border-t border-gray-50">
                            <a href="{{ route('admin.listings.approval') }}"
                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                View all pending properties →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Unit verifications --}}
                <div x-show="tab === 'units'" x-cloak>
                    @if($pendingUnitList->isEmpty())
                        <div class="py-10 text-center text-[13px] text-gray-400">No pending unit approvals.</div>
                    @else
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-50">
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Unit</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Property</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Date Submitted</th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                        Status</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($pendingUnitList as $u)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-3.5 text-[13.5px] font-semibold text-[#1A1A2E]">
                                            {{ $u->unit_label ?? '—' }}</td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-600 truncate max-w-[180px]">
                                            {{ $u->property?->title ?? '—' }}</td>
                                        <td class="px-6 py-3.5 text-[13px] text-gray-400">
                                            {{ $u->created_at ? $u->created_at->format('M j, Y') : '—' }}
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <a href="{{ route('admin.units.index') }}"
                                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                                Review →
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="px-6 py-3 border-t border-gray-50">
                            <a href="{{ route('admin.units.index') }}"
                                class="text-[12.5px] font-semibold text-[#286CD2] hover:text-[#1e5bb8] transition-colors">
                                View all pending units →
                            </a>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Recent Reservations --}}
            <div class="lg:col-span-4 bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h2 class="text-[15px] font-bold text-[#1A1A2E]">Recent Reservations</h2>
                    <span class="text-[12px] text-gray-400">Latest</span>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recentReservations as $res)
                        @php
                            $rStatusCls = match ($res->rental_status) {
                                'Occupied' => 'bg-emerald-50 text-emerald-700',
                                'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed' => 'bg-amber-50 text-amber-700',
                                'Cancelled' => 'bg-gray-100 text-gray-500',
                                'Rejected' => 'bg-red-50 text-red-600',
                                default => 'bg-gray-50 text-gray-500',
                            };
                        @endphp
                        <div class="px-5 py-3.5 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-[#286CD2]/10 flex items-center justify-center shrink-0">
                                <span class="text-[#286CD2] text-[11px] font-bold">
                                    {{ strtoupper(substr($res->tenant?->first_name ?? '?', 0, 1)) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold text-[#1A1A2E] truncate">
                                    {{ $res->tenant?->first_name }} {{ $res->tenant?->last_name }}
                                </p>
                                <p class="text-[11.5px] text-gray-400 truncate">{{ $res->property?->title ?? '—' }}</p>
                            </div>
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full shrink-0 {{ $rStatusCls }}">
                                {{ $res->rental_status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-10 text-center text-[13px] text-gray-400">No recent reservations.</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ── Bottom Row: Reservation Overview + Reports ───────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- Reservations Overview --}}
            <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[15px] font-bold text-[#1A1A2E]">Reservations Overview</h2>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @php
                        $resOverview = [
                            ['label' => 'In Progress', 'value' => ($reservationStats['Inquiry'] ?? 0) + ($reservationStats['Under Negotiation'] ?? 0) + ($reservationStats['Pending Rental Agreement'] ?? 0) + ($reservationStats['Rental Agreement Signed'] ?? 0), 'cls' => 'bg-amber-50 border-amber-100 text-amber-700'],
                            ['label' => 'Occupied', 'value' => $reservationStats['Occupied'] ?? 0, 'cls' => 'bg-emerald-50 border-emerald-100 text-emerald-700'],
                            ['label' => 'Cancelled', 'value' => $reservationStats['Cancelled'] ?? 0, 'cls' => 'bg-gray-50 border-gray-100 text-gray-500'],
                            ['label' => 'Rejected', 'value' => $reservationStats['Rejected'] ?? 0, 'cls' => 'bg-blue-50 border-blue-100 text-[#286CD2]'],
                        ];
                    @endphp
                    @foreach($resOverview as $item)
                        <div class="rounded-2xl border p-4 text-center {{ $item['cls'] }}">
                            <p class="text-[26px] font-extrabold leading-none">{{ number_format($item['value']) }}</p>
                            <p class="text-[11.5px] font-bold mt-1.5 uppercase tracking-wide opacity-80">{{ $item['label'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm">
                <h2 class="text-[15px] font-bold text-[#1A1A2E] mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-3">
                    @php
                        $quickActions = [
                            ['label' => 'Review Landlords', 'sub' => $pendingVerifications . ' pending', 'href' => route('admin.verifications.index', ['status' => 'Pending']), 'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'color' => 'bg-[#286CD2]'],
                            ['label' => 'Review Properties', 'sub' => $pendingListings . ' pending', 'href' => route('admin.listings.approval'), 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25', 'color' => 'bg-emerald-500'],
                            ['label' => 'Review Units', 'sub' => $pendingUnits . ' pending', 'href' => route('admin.units.index'), 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm9.75 0A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm-9.75 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75 0a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z', 'color' => 'bg-purple-500'],
                            ['label' => 'Manage Users', 'sub' => number_format($totalUsers) . ' total', 'href' => route('admin.users.index'), 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z', 'color' => 'bg-amber-500'],
                        ];
                    @endphp
                    @foreach($quickActions as $action)
                        <a href="{{ $action['href'] }}"
                            class="group flex items-center gap-3 p-4 rounded-2xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-[#286CD2]/20 hover:shadow-sm transition-all">
                            <div
                                class="w-9 h-9 rounded-xl {{ $action['color'] }} flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-4.5 h-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[13px] font-bold text-[#1A1A2E] truncate group-hover:text-[#286CD2] transition-colors">
                                    {{ $action['label'] }}</p>
                                <p class="text-[11.5px] text-gray-400">{{ $action['sub'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ── Platform Overview Line Chart ──────────────────────────────
            const platformCtx = document.getElementById('platformChart');
            if (platformCtx) {
                new Chart(platformCtx, {
                    type: 'line',
                    data: {
                        labels: {!! $chartLabels->toJson() !!},
                        datasets: [
                            {
                                label: 'Users',
                                data: {!! $chartUsers->toJson() !!},
                                borderColor: '#286CD2',
                                backgroundColor: 'rgba(40,108,210,0.08)',
                                borderWidth: 2.5,
                                pointBackgroundColor: '#286CD2',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.4,
                                fill: true,
                            },
                            {
                                label: 'Properties',
                                data: {!! $chartProperties->toJson() !!},
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,0.06)',
                                borderWidth: 2.5,
                                pointBackgroundColor: '#10b981',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.4,
                                fill: true,
                            },
                            {
                                label: 'Reservations',
                                data: {!! $chartReservations->toJson() !!},
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,0.06)',
                                borderWidth: 2.5,
                                pointBackgroundColor: '#f59e0b',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.4,
                                fill: true,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1A1A2E',
                                titleColor: '#fff',
                                bodyColor: 'rgba(255,255,255,0.7)',
                                padding: 10,
                                cornerRadius: 10,
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: '#9CA3AF', font: { size: 11 } },
                            },
                            y: {
                                grid: { color: 'rgba(0,0,0,0.04)', lineWidth: 1 },
                                border: { display: false, dash: [4, 4] },
                                ticks: { color: '#9CA3AF', font: { size: 11 }, precision: 0 },
                            },
                        },
                    },
                });
            }

            // ── User Distribution Donut ───────────────────────────────────
            const distributionCtx = document.getElementById('distributionChart');
            if (distributionCtx) {
                new Chart(distributionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Tenants', 'Landlords', 'Admins', 'Unverified Landlords'],
                        datasets: [{
                            data: [
                            {{ $totalTenants }},
                            {{ $totalLandlords }},
                            {{ $totalAdmins }},
                            {{ $unverifiedLandlords }},
                            ],
                            backgroundColor: ['#286CD2', '#10b981', '#a855f7', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1A1A2E',
                                titleColor: '#fff',
                                bodyColor: 'rgba(255,255,255,0.7)',
                                padding: 10,
                                cornerRadius: 10,
                            },
                        },
                    },
                });
            }
        });
    </script>
@endpush