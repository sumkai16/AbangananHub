@extends('layouts.admin')

@section('page-title', 'Reservations')

@section('content')
@php
    $allStatuses = [
        'Inquiry'                  => ['label' => 'Inquiry',           'dot' => 'bg-[#94A3B8]'],
        'Under Negotiation'        => ['label' => 'Negotiation',       'dot' => 'bg-[#C9A84C]'],
        'Pending Rental Agreement' => ['label' => 'Pending Agreement', 'dot' => 'bg-[#FBBF24]'],
        'Rental Agreement Signed'  => ['label' => 'Agreement Signed',  'dot' => 'bg-[#C9A84C]'],
        'Occupied'                 => ['label' => 'Occupied',          'dot' => 'bg-[#22C55E]'],
        'Cancelled'                => ['label' => 'Cancelled',         'dot' => 'bg-[#94A3B8]'],
        'Rejected'                 => ['label' => 'Rejected',          'dot' => 'bg-[#EF4444]'],
    ];

    $statusBadge = [
        'Inquiry'                  => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]',
        'Under Negotiation'        => 'bg-[#ECEEF6] text-[#060D26] border-[#C9A84C]/25',
        'Pending Rental Agreement' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Rental Agreement Signed'  => 'bg-[#ECEEF6] text-[#060D26] border-[#C9A84C]/25',
        'Occupied'                 => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Cancelled'                => 'bg-[#F7F8FC] text-[#94A3B8] border-[#E2E4EC]',
        'Rejected'                 => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
    ];
@endphp

<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <x-page-header title="Reservations" subtitle="System-wide view of all reservations across all properties.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <span class="text-[13px] font-semibold text-[#94A3B8]">{{ number_format($counts['all']) }} total</span>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat-card label="Total" :value="number_format($counts['all'])" sub="All time">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Occupied" :value="number_format($counts['Occupied'])" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)" sub="Units currently rented">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="In Progress" :value="number_format($counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed'])"
            value-color="#060D26" icon-bg="#ECEEF6" sub="Active pipeline">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                    <circle cx="12" cy="12" r="9" stroke="#060D26" stroke-width="2" />
                </svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Closed" :value="number_format($counts['Cancelled'] + $counts['Rejected'])" value-color="#DC2626" icon-bg="rgba(239,68,68,0.07)" sub="Cancelled + Rejected">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.reservations.index') }}"
        class="bg-white rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(6,13,38,0.06)] flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant name, email, or property…" aria-label="Search by tenant name, email, or property"
                x-on:input.debounce.400ms="$el.form.requestSubmit()"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/20 focus:border-[#C9A84C] transition-all">
        </div>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#060D26] text-[#F7F4ED] rounded-xl hover:brightness-95 transition-colors shadow-sm">
            Search
        </button>
        @if($search)
            <a href="{{ route('admin.reservations.index', ['status' => $status]) }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:text-[#060D26] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- Status tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E4EC] mb-5 overflow-x-auto">
        @php
            // "Needs review" is a separate filter dimension layered on top of the
            // status tabs, not a status itself — while it's active, none of the
            // status tabs should also read as active or two tabs highlight at once.
            $disputedActive = request('filter') === 'disputed';
        @endphp
        <a href="{{ route('admin.reservations.index', array_filter(['search' => $search])) }}"
            class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                {{ ! $disputedActive && $status === 'all' ? 'border-[#C9A84C] text-[#060D26]' : 'border-transparent text-[#94A3B8] hover:text-[#060D26]' }}">
            All
            <span class="ml-1 text-[11px] {{ ! $disputedActive && $status === 'all' ? 'text-[#060D26]' : 'text-[#94A3B8]' }}">{{ $counts['all'] }}</span>
        </a>
        @foreach($allStatuses as $key => $meta)
            <a href="{{ route('admin.reservations.index', array_filter(['status' => $key, 'search' => $search])) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ ! $disputedActive && $status === $key ? 'border-[#C9A84C] text-[#060D26]' : 'border-transparent text-[#94A3B8] hover:text-[#060D26]' }}">
                {{ $meta['label'] }}
                <span class="ml-1 text-[11px] {{ ! $disputedActive && $status === $key ? 'text-[#060D26]' : 'text-[#94A3B8]' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
        <a href="{{ route('admin.reservations.index', array_filter(['filter' => 'disputed', 'status' => $status, 'search' => $search])) }}"
            class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                {{ $disputedActive ? 'border-[#C9A84C] text-[#060D26]' : 'border-transparent text-[#94A3B8] hover:text-[#060D26]' }}">
            Needs review
            @if ($disputedCount > 0)
                <span class="ml-1 rounded-full bg-[#EF4444]/[0.10] px-2 py-0.5 text-xs text-[#DC2626]">{{ $disputedCount }}</span>
            @endif
        </a>
    </div>

    {{-- Table --}}
    @if($reservations->isEmpty())
        <div class="bg-white border border-[#E2E4EC] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
            <div class="w-14 h-14 rounded-2xl bg-[#F7F8FC] border border-[#E2E4EC] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#060D26]">No reservations found</p>
            <p class="text-[13px] text-[#94A3B8] mt-1">{{ $search ? 'Try adjusting your search.' : 'None with this status yet.' }}</p>
        </div>
    @else
        <x-card flush>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Property / Unit</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Move-In</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($reservations as $res)
                            @php $photo = $res->property?->media->firstWhere('media_type', 'Image'); @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors">

                                {{-- Tenant --}}
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                                        {{ trim(($res->tenant->first_name ?? '') . ' ' . ($res->tenant->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[12px] text-[#94A3B8]">{{ $res->tenant->email ?? '—' }}</p>
                                </td>

                                {{-- Property / Unit --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-[#ECEEF6] overflow-hidden shrink-0">
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
                                            <p class="text-[13.5px] font-semibold text-[#060D26] truncate max-w-[180px]">
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
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                                        {{ trim(($res->property->landlord->first_name ?? '') . ' ' . ($res->property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>

                                {{-- Move-in --}}
                                <td class="px-6 py-4 text-[13px] text-[#5B6A8E]">
                                    {{ $res->target_move_in_date?->format('M d, Y') ?? '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusBadge[$res->rental_status] ?? 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $allStatuses[$res->rental_status]['dot'] ?? 'bg-[#94A3B8]' }}"></span>
                                        {{ $res->rental_status }}
                                    </span>
                                    @if ($res->move_in_disputed_at)
                                        <p class="mt-1 text-xs text-[#DC2626]">
                                            Needs review — {{ $res->move_in_dispute_reason }}
                                            <span class="text-[#94A3B8]">({{ $res->move_in_disputed_at->diffForHumans() }})</span>
                                        </p>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.reservations.show', $res) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] text-[12px] font-semibold text-[#060D26] hover:bg-[#060D26] hover:text-[#F7F4ED] hover:border-[#060D26] transition-all">
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
                <div class="px-6 py-4 border-t border-[#E2E4EC]">
                    {{ $reservations->links() }}
                </div>
            @endif
        </x-card>
    @endif

</div>
@endsection
