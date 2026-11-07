@extends('layouts.admin')

@section('page-title', 'Reservations')

@section('content')
@php
    // "Needs review" is a separate filter layered on the status tabs, not a status; it comes first because it is the
    // one tab that asks for action. While it is active no status tab reads as active.
    $disputedActive = request('filter') === 'disputed';

    $typeOptions = \App\Enums\PropertyType::options('All Types');
    $sortOptions = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'move_in_soon' => 'Move-in soonest',
        'move_in_late' => 'Move-in latest',
    ];

    $statusLabels = [
        'Inquiry' => 'Inquiry',
        'Under Negotiation' => 'Negotiation',
        'Pending Rental Agreement' => 'Pending Agreement',
        'Rental Agreement Signed' => 'Agreement Signed',
        'Reserved' => 'Reserved',
        'Occupied' => 'Occupied',
        'Completed' => 'Completed',
        'Cancelled' => 'Cancelled',
        'Rejected' => 'Rejected',
    ];
    $statusOptions = ['all' => 'All statuses'] + collect($statusLabels)->map(fn ($l, $k) => $l.' ('.$counts[$k].')')->all();

    // Headline numbers above the tabs. Cells that map to one status link to its tab (click the active one again to clear);
    // "In progress" spans four statuses, so it is a plain number.
    $inProgress = $counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed'];
    $cellHref = fn (array $params) => route('admin.reservations.index', array_filter(array_merge(['search' => $search, 'property_type' => $type, 'sort' => $sort !== 'newest' ? $sort : null], $params)));
    $summary = [
        ['label' => 'Total reservations', 'value' => $counts['all'], 'note' => $counts['Completed'].' completed', 'href' => $cellHref([]), 'active' => ! $disputedActive && $status === 'all'],
        ['label' => 'In progress', 'value' => $inProgress, 'note' => 'Inquiry to agreement signed', 'dot' => '#FBBF24'],
        ['label' => 'Reserved', 'value' => $counts['Reserved'], 'note' => 'Waiting for move-in', 'dot' => '#3B82F6', 'href' => $status === 'Reserved' && ! $disputedActive ? $cellHref([]) : $cellHref(['status' => 'Reserved']), 'active' => ! $disputedActive && $status === 'Reserved'],
        ['label' => 'Occupied', 'value' => $counts['Occupied'], 'note' => 'Tenants living in', 'dot' => '#22C55E', 'href' => $status === 'Occupied' && ! $disputedActive ? $cellHref([]) : $cellHref(['status' => 'Occupied']), 'active' => ! $disputedActive && $status === 'Occupied'],
        ['label' => 'Needs review', 'value' => $disputedCount, 'note' => $disputedCount > 0 ? 'Move-in disputed' : 'Nothing disputed', 'dot' => '#EF4444', 'href' => $disputedActive ? $cellHref([]) : $cellHref(['filter' => 'disputed']), 'active' => $disputedActive],
    ];
@endphp

<div class="admin-compact max-w-[1600px] mx-auto"
    x-data="{
        view: (() => { try { return localStorage.getItem('adminReservationsView') || 'table'; } catch (e) { return 'table'; } })(),
        setView(v) { this.view = v; try { localStorage.setItem('adminReservationsView', v); } catch (e) {} }
    }">

    <x-admin.header title="Reservations" />

    <x-stat-strip :cells="$summary" class="mb-3" />

    {{-- Search + filters, same bar as Properties --}}
    <form data-live method="GET" action="{{ route('admin.reservations.index') }}" class="mb-3">
        @if(request('filter')) <input type="hidden" name="filter" value="{{ request('filter') }}"> @endif
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search tenant, email, property or landlord…" aria-label="Search reservations"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="status" :options="$statusOptions" :selected="$status"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="property_type" :options="$typeOptions" :selected="$type"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="sort" :options="$sortOptions" :selected="$sort"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                @if($search || $type || $sort !== 'newest' || $status !== 'all' || request('filter'))
                    <a href="{{ route('admin.reservations.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                @endif

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-10 p-1 rounded-xl border border-[#5B6A8E]/25 bg-white">
                    <button type="button" x-on:click="setView('grid')" aria-label="Grid view"
                        :class="view === 'grid' ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-8 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    </button>
                    <button type="button" x-on:click="setView('table')" aria-label="List view"
                        :class="view === 'table' ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-8 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Table --}}
    @if($reservations->isEmpty())
        <x-card flush><x-admin.empty title="No reservations found" :note="($search || $type) ? 'Try a different search or filter.' : null" /></x-card>
    @else
        <x-card flush x-show="view === 'table'">
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th>Property / Unit</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th>Move-In</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($reservations as $res)
                            @php $photo = $res->property?->media->firstWhere('media_type', 'Image'); @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4">
                                    <a href="{{ route('admin.reservations.show', $res) }}" class="block text-[13px] font-semibold leading-5 text-[#060D26] underline-offset-2 hover:underline">
                                        {{ trim(($res->tenant->first_name ?? '') . ' ' . ($res->tenant->last_name ?? '')) ?: '—' }}
                                    </a>
                                    <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $res->tenant->email ?? '—' }}</p>
                                </td>
                                <td class="px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-[#ECEEF6]">
                                            @if($photo)
                                                <img src="{{ \App\Support\Images::src($photo->media_url, 'card') }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                            @else
                                                <svg class="h-4 w-4 text-[#5B6A8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="max-w-[320px] truncate text-[13px] leading-5 text-[#060D26]" title="{{ $res->property->title ?? '' }}">{{ $res->property->title ?? '—' }}</p>
                                            <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $res->unit->unit_label ?? 'No unit' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 whitespace-nowrap text-[13px] text-[#060D26]">
                                    {{ trim(($res->property->landlord->first_name ?? '') . ' ' . ($res->property->landlord->last_name ?? '')) ?: '—' }}
                                </td>
                                <td class="px-4 whitespace-nowrap text-[13px] tabular-nums text-[#5B6A8E]">
                                    {{ $res->target_move_in_date?->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-4">
                                    <x-admin.status :status="$res->rental_status" />
                                    @if ($res->move_in_disputed_at)
                                        <p class="mt-0.5 max-w-[340px] truncate text-[12px] text-[#DC2626]" title="{{ $res->move_in_dispute_reason }}">Needs review: {{ $res->move_in_dispute_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-4 text-right">
                                    <a href="{{ route('admin.reservations.show', $res) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Grid view --}}
        <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach($reservations as $res)
                @php
                    $photo = $res->property?->media->firstWhere('media_type', 'Image');
                    $tenantName = trim(($res->tenant->first_name ?? '') . ' ' . ($res->tenant->last_name ?? '')) ?: '—';
                @endphp
                <a href="{{ route('admin.reservations.show', $res) }}" class="group block overflow-hidden rounded-xl border border-[#E2E4EC] bg-white transition-shadow duration-200 hover:shadow-md">
                    <div class="relative aspect-[16/10] bg-[#ECEEF6]">
                        @if($photo)
                            <img src="{{ \App\Support\Images::src($photo->media_url, 'card') }}" alt="" loading="lazy" class="h-full w-full object-cover">
                        @endif
                        <span class="absolute left-2 top-2 inline-flex rounded-md bg-white shadow-sm"><x-admin.status :status="$res->rental_status" /></span>
                    </div>
                    <div class="p-3">
                        <p class="truncate text-[13px] font-semibold text-[#060D26]">{{ $tenantName }}</p>
                        <p class="truncate text-[12px] text-[#5B6A8E]">{{ $res->tenant->email ?? '—' }}</p>
                        <p class="mt-2 truncate text-[13px] text-[#060D26]" title="{{ $res->property->title ?? '' }}">{{ $res->property->title ?? '—' }}</p>
                        <p class="truncate text-[12px] text-[#5B6A8E]">{{ $res->unit->unit_label ?? 'No unit' }} · {{ trim(($res->property->landlord->first_name ?? '') . ' ' . ($res->property->landlord->last_name ?? '')) ?: '—' }}</p>
                        <p class="mt-2 text-[12px] tabular-nums text-[#5B6A8E]">Move-in {{ $res->target_move_in_date?->format('M j, Y') ?? '—' }}</p>
                        @if ($res->move_in_disputed_at)
                            <p class="mt-1 truncate text-[12px] text-[#DC2626]" title="{{ $res->move_in_dispute_reason }}">Needs review: {{ $res->move_in_dispute_reason }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        @if($reservations->hasPages())
            <div class="mt-3">{{ $reservations->links() }}</div>
        @endif
    @endif

</div>
@endsection
