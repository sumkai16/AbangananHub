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

<div class="admin-compact max-w-[1600px] mx-auto">

    <x-admin.header title="Reservations">
        <x-slot:actions>
            <x-admin.search :action="route('admin.reservations.index')" :value="$search" placeholder="Search tenant, email or property"
                :clear="route('admin.reservations.index', array_filter(['status' => $status === 'all' ? null : $status, 'filter' => request('filter')]))">
                <input type="hidden" name="status" value="{{ $status }}">
            </x-admin.search>
        </x-slot:actions>
    </x-admin.header>

    <x-admin.tabs class="mb-3" :tabs="$tabs" />

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
            @if($reservations->hasPages())
                <div class="px-4 py-2 border-t border-[#E2E4EC]">{{ $reservations->links() }}</div>
            @endif
        </x-card>
    @endif

</div>
@endsection
