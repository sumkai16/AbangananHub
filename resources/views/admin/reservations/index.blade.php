@extends('layouts.admin')

@section('page-title', 'Reservations')

@section('content')
@php
    $allStatuses = [
        'Inquiry'                  => ['label' => 'Inquiry',           'dot' => 'bg-[#94A3B8]'],
        'Under Negotiation'        => ['label' => 'Negotiation',       'dot' => 'bg-[#2AA7A1]'],
        'Pending Rental Agreement' => ['label' => 'Pending Agreement', 'dot' => 'bg-[#FBBF24]'],
        'Rental Agreement Signed'  => ['label' => 'Agreement Signed',  'dot' => 'bg-[#2AA7A1]'],
        'Occupied'                 => ['label' => 'Occupied',          'dot' => 'bg-[#22C55E]'],
        'Cancelled'                => ['label' => 'Cancelled',         'dot' => 'bg-[#94A3B8]'],
        'Rejected'                 => ['label' => 'Rejected',          'dot' => 'bg-[#EF4444]'],
    ];

    $statusBadge = [
        'Inquiry'                  => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
        'Under Negotiation'        => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Pending Rental Agreement' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Rental Agreement Signed'  => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Occupied'                 => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Cancelled'                => 'bg-[#F7FCFC] text-[#94A3B8] border-[#E2E8F0]',
        'Rejected'                 => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
    ];
@endphp

<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">Reservations</h1>
            <p class="text-[13.5px] text-[#64748B] mt-1">System-wide view of all reservations across all properties.</p>
        </div>
        <span class="text-[13px] font-semibold text-[#94A3B8]">{{ number_format($counts['all']) }} total</span>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-4 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
            <p class="text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1">Total</p>
            <p class="text-[28px] font-extrabold text-[#1F2937] leading-none">{{ number_format($counts['all']) }}</p>
            <p class="text-[11px] text-[#94A3B8] mt-1">All time</p>
        </div>
        <div class="bg-[#22C55E]/[0.07] border border-[#22C55E]/20 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-[#15803D] mb-1">Occupied</p>
            <p class="text-[28px] font-extrabold text-[#15803D] leading-none">{{ number_format($counts['Occupied']) }}</p>
            <p class="text-[11px] text-[#15803D] mt-1">Units currently rented</p>
        </div>
        <div class="bg-[#EEF8F8] border border-[#2AA7A1]/20 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-[#156F8C] mb-1">In Progress</p>
            <p class="text-[28px] font-extrabold text-[#156F8C] leading-none">
                {{ number_format($counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed']) }}
            </p>
            <p class="text-[11px] text-[#156F8C] mt-1">Active pipeline</p>
        </div>
        <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-[#DC2626] mb-1">Closed</p>
            <p class="text-[28px] font-extrabold text-[#DC2626] leading-none">{{ number_format($counts['Cancelled'] + $counts['Rejected']) }}</p>
            <p class="text-[11px] text-[#DC2626] mt-1">Cancelled + Rejected</p>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.reservations.index') }}"
        class="bg-white border border-[#E2E8F0] rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)] flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant name, email, or property…" aria-label="Search by tenant name, email, or property"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
        </div>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-colors shadow-sm">
            Search
        </button>
        @if($search)
            <a href="{{ route('admin.reservations.index', ['status' => $status]) }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- Status tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-5 overflow-x-auto">
        <a href="{{ route('admin.reservations.index', array_filter(['search' => $search])) }}"
            class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                {{ $status === 'all' ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
            All
            <span class="ml-1 text-[11px] {{ $status === 'all' ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts['all'] }}</span>
        </a>
        @foreach($allStatuses as $key => $meta)
            <a href="{{ route('admin.reservations.index', array_filter(['status' => $key, 'search' => $search])) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ $status === $key ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                {{ $meta['label'] }}
                <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    @if($reservations->isEmpty())
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
            <div class="w-14 h-14 rounded-2xl bg-[#F7FCFC] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No reservations found</p>
            <p class="text-[13px] text-[#94A3B8] mt-1">{{ $search ? 'Try adjusting your search.' : 'None with this status yet.' }}</p>
        </div>
    @else
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Property / Unit</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Move-In</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach($reservations as $res)
                            @php $photo = $res->property?->media->first(); @endphp
                            <tr class="hover:bg-[#F7FCFC] transition-colors">

                                {{-- Tenant --}}
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                        {{ trim(($res->tenant->first_name ?? '') . ' ' . ($res->tenant->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[12px] text-[#94A3B8]">{{ $res->tenant->email ?? '—' }}</p>
                                </td>

                                {{-- Property / Unit --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($photo)
                                                <img src="{{ $photo->media_url }}" alt="{{ $reservation->unit->unit_label ?? 'Property' }}" loading="lazy" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-semibold text-[#1F2937] truncate max-w-[180px]">
                                                {{ $res->property->title ?? '—' }}
                                            </p>
                                            <p class="text-[12px] text-[#94A3B8]">
                                                {{ $res->unit->unit_label ?? 'No unit' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Landlord --}}
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                        {{ trim(($res->property->landlord->first_name ?? '') . ' ' . ($res->property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>

                                {{-- Move-in --}}
                                <td class="px-6 py-4 text-[13px] text-[#64748B]">
                                    {{ $res->target_move_in_date?->format('M d, Y') ?? '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusBadge[$res->rental_status] ?? 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $allStatuses[$res->rental_status]['dot'] ?? 'bg-[#94A3B8]' }}"></span>
                                        {{ $res->rental_status }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.reservations.show', $res) }}"
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
            @if($reservations->hasPages())
                <div class="px-6 py-4 border-t border-[#E2E8F0]">
                    {{ $reservations->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
