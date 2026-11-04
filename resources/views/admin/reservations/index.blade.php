@extends('layouts.admin')

@section('page-title', 'Reservations')

@section('content')
@php
    // "Needs review" is a separate filter layered on the status tabs, not a status; it comes first because it is the
    // one tab that asks for action. While it is active no status tab reads as active.
    $disputedActive = request('filter') === 'disputed';

    $tabs = [[
        'label' => 'Needs review',
        'count' => $disputedCount,
        'href' => route('admin.reservations.index', array_filter(['filter' => 'disputed', 'status' => $status, 'search' => $search])),
        'active' => $disputedActive,
    ], [
        'label' => 'All',
        'count' => $counts['all'],
        'href' => route('admin.reservations.index', array_filter(['search' => $search])),
        'active' => ! $disputedActive && $status === 'all',
    ]];

    foreach ([
        'Inquiry' => 'Inquiry',
        'Under Negotiation' => 'Negotiation',
        'Pending Rental Agreement' => 'Pending Agreement',
        'Rental Agreement Signed' => 'Agreement Signed',
        'Reserved' => 'Reserved',
        'Occupied' => 'Occupied',
        'Completed' => 'Completed',
        'Cancelled' => 'Cancelled',
        'Rejected' => 'Rejected',
    ] as $key => $label) {
        $tabs[] = [
            'label' => $label,
            'count' => $counts[$key],
            'href' => route('admin.reservations.index', array_filter(['status' => $key, 'search' => $search])),
            'active' => ! $disputedActive && $status === $key,
        ];
    }
@endphp

<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" title="Reservations" />


    <form method="GET" action="{{ route('admin.reservations.index') }}" class="mb-4 flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant name, email, or property…" aria-label="Search by tenant name, email, or property"
                x-on:input.debounce.400ms="$el.form.requestSubmit()"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
        </div>
        <button type="submit" class="h-10 rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-white">Search</button>
        @if($search)
            <a href="{{ route('admin.reservations.index', ['status' => $status]) }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:text-[#060D26] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    <x-admin.tabs class="mb-4" :tabs="$tabs" />

    {{-- Table --}}
    @if($reservations->isEmpty())
        <x-card flush><x-admin.empty title="No reservations found" :note="$search ? 'Try a different search.' : null" /></x-card>
    @else
        <x-card flush>
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
                            <tr class="hover:bg-[#F7F8FC] transition-colors">

                                {{-- Tenant --}}
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                                        {{ trim(($res->tenant->first_name ?? '') . ' ' . ($res->tenant->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $res->tenant->email ?? '—' }}</p>
                                </td>

                                {{-- Property / Unit --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-[#ECEEF6] overflow-hidden shrink-0">
                                            @if($photo)
                                                <img src="{{ $photo->media_url }}" alt="{{ $res->unit->unit_label ?? 'Property' }}" loading="lazy" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-[#5B6A8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-semibold text-[#060D26] truncate max-w-[180px]">
                                                {{ $res->property->title ?? '—' }}
                                            </p>
                                            <p class="text-[12px] text-[#5B6A8E]">
                                                {{ $res->unit->unit_label ?? 'No unit' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Landlord --}}
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                                        {{ trim(($res->property->landlord->first_name ?? '') . ' ' . ($res->property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>

                                {{-- Move-in --}}
                                <td class="px-4 py-3 text-[13px] text-[#5B6A8E]">
                                    {{ $res->target_move_in_date?->format('M j, Y') ?? '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$res->rental_status" />
                                    @if ($res->move_in_disputed_at)
                                        <p class="mt-1 text-xs text-[#DC2626]">
                                            Needs review — {{ $res->move_in_dispute_reason }}
                                            <span class="text-[#5B6A8E]">({{ $res->move_in_disputed_at->diffForHumans() }})</span>
                                        </p>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.reservations.show', $res) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($reservations->hasPages())
                <div class="px-4 py-3 border-t border-[#E2E4EC]">{{ $reservations->links() }}</div>
            @endif
        </x-card>
    @endif

</div>
@endsection
