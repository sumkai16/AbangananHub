@extends('layouts.admin')

@section('page-title', 'Refund requests')

@section('content')
    <div class="max-w-[1600px] mx-auto">

        <x-page-header title="Refund requests" subtitle="Tenants waiting on AbangananHub for their money: held payments with a move-in problem, and money a landlord kept." />

        @php
            $cellHref = fn (array $params) => route('admin.refund-requests.index', array_filter(array_merge(['search' => $search], $params)));
            $toggle = fn (string $key) => $type === $key ? $cellHref([]) : $cellHref(['type' => $key]);
            $summary = [
                ['label' => 'Open requests', 'value' => number_format($counts['all']),
                    'note' => $counts['all'] > 0 ? 'Waiting since '.$oldest->format('M j') : 'Nothing waiting',
                    'dot' => '#EF4444', 'href' => $cellHref([]), 'active' => $type === 'all'],
                ['label' => 'Move-in disputes', 'value' => number_format($counts['disputes']), 'note' => 'We hold the money: refund or release', 'dot' => '#FBBF24',
                    'href' => $toggle('disputes'), 'active' => $type === 'disputes'],
                ['label' => 'Refunds not returned', 'value' => number_format($counts['escalations']), 'note' => 'Paid to the landlord: act on the landlord', 'dot' => '#94A3B8',
                    'href' => $toggle('escalations'), 'active' => $type === 'escalations'],
            ];
            $typeOptions = [
                'all' => 'All requests ('.$counts['all'].')',
                'disputes' => 'Move-in disputes ('.$counts['disputes'].')',
                'escalations' => 'Refunds not returned ('.$counts['escalations'].')',
            ];
            $peso = fn (float $n) => '₱'.number_format($n, 2);
        @endphp
        <x-stat-strip data-swap-region="cards" :cells="$summary" class="mb-4" />

        <form data-live method="GET" action="{{ route('admin.refund-requests.index') }}" class="mb-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search tenant, landlord, property or reason…" aria-label="Search refund requests"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-styled-select autosubmit name="type" :options="$typeOptions" :selected="$type"
                        class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                    @if($search || $type !== 'all')
                        <a href="{{ route('admin.refund-requests.index') }}"
                            class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div data-swap-region="results">
        <x-card flush>
            @if ($rows->isEmpty())
                <x-admin.empty title="No refund requests" :note="$search ? 'Try a different search.' : 'When a tenant reports a move-in problem, or says a landlord kept their money, it shows here.'" />
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                <x-admin.th>Request</x-admin.th>
                                <x-admin.th>Tenant</x-admin.th>
                                <x-admin.th>Landlord</x-admin.th>
                                <x-admin.th>Rental</x-admin.th>
                                <x-admin.th>Amount</x-admin.th>
                                <x-admin.th>Reason</x-admin.th>
                                <x-admin.th>Raised</x-admin.th>
                                <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach ($rows as $row)
                                @php $res = $row['reservation']; $isDispute = $row['kind'] === 'disputes'; @endphp
                                <tr class="align-top hover:bg-[#F7F8FC] transition-colors duration-200">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.status :status="$isDispute ? 'Under review' : 'Pending'" :label="$isDispute ? 'Move-in dispute' : 'Not returned'" />
                                        <p class="mt-1 text-[12px] text-[#5B6A8E]">{{ $isDispute ? 'Money held by us' : 'Paid to the landlord' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $row['tenant'] }}</p>
                                        <p class="text-[12.5px] text-[#5B6A8E]">{{ $row['tenantEmail'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ $row['landlord'] }}</td>
                                    <td class="px-4 py-3 max-w-[16rem]">
                                        <p class="truncate text-[13.5px] text-[#060D26]" title="{{ $row['property'] }}">{{ $row['property'] }}</p>
                                        <p class="text-[12.5px] text-[#5B6A8E]">{{ $row['unit'] }} · Reservation #{{ $res->reservation_id }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-[13.5px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">{{ $peso($row['amount']) }}</td>
                                    <td class="px-4 py-3 max-w-[22rem] text-[13px] text-[#060D26]" title="{{ $row['reason'] }}"><span class="line-clamp-3">{{ $row['reason'] }}</span></td>
                                    <td class="px-4 py-3 text-[13px] whitespace-nowrap text-[#5B6A8E]">{{ $row['raised_at']->format('M j, Y') }}<br><span class="text-[12px]">{{ $row['raised_at']->diffForHumans() }}</span></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col items-end gap-2">
                                            @if($isDispute && $row['payment'])
                                                @include('admin.payments.partials._actions', ['payment' => $row['payment'], 'heldGroups' => $heldGroups])
                                            @elseif($isDispute)
                                                <span class="text-[12px] text-[#5B6A8E]">No held payment left. Open the reservation.</span>
                                            @else
                                                <a href="{{ route('admin.reports.show', $row['report']) }}" class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] hover:border-[#FF8A66] hover:bg-[#FF8A66]/[0.08] transition-colors duration-200">Review report</a>
                                            @endif
                                            <div class="flex items-center gap-3 text-[12.5px] font-semibold text-[#060D26]">
                                                <a href="{{ route('admin.reservations.show', $res) }}" class="underline-offset-2 hover:underline">Reservation</a>
                                                @if($res->conversation_id)
                                                    <a href="{{ route('admin.conversations.show', $res->conversation_id) }}" class="underline-offset-2 hover:underline">Chat</a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($rows->hasPages())
                    <div class="border-t border-[#E2E4EC] px-4 py-3">{{ $rows->links() }}</div>
                @endif
            @endif
        </x-card>
        <p class="mt-3 text-[12.5px] text-[#5B6A8E]">Older "Needs review" list: <a href="{{ route('admin.reservations.index', ['filter' => 'disputed']) }}" class="font-semibold text-[#060D26] underline-offset-2 hover:underline">Reservations, move-in disputed</a>.</p>
        </div>

    </div>

    @include('admin.payments.partials.refund-modal')
@endsection
