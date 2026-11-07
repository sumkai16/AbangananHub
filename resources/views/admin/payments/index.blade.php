@extends('layouts.admin')

@section('page-title', 'Payments')

@section('content')
<div class="admin-compact admin-fill"
    x-data="{
        view: (() => { try { return localStorage.getItem('adminPaymentsView') || 'table'; } catch (e) { return 'table'; } })(),
        setView(v) { this.view = v; try { localStorage.setItem('adminPaymentsView', v); } catch (e) {} }
    }">

    <x-admin.header title="Payments" subtitle="Online money held until move-in, and payments landlords recorded." />

    {{-- A tab already names the status, so the column only shows on All. --}}
    @php $showStatus = $status === 'All'; @endphp

    @php
        $statusLabels = ['All' => 'All statuses', 'Held' => 'Held', 'Released' => 'Released', 'Paid' => 'Recorded', 'Pending' => 'Processing', 'Voided' => 'Voided', 'Refunded' => 'Refunded'];
        $statusOptions = collect($statusLabels)->map(fn ($l, $k) => $k === 'All' ? $l.' ('.$counts['All'].')' : $l.' ('.$counts[$k].')')->all();
        $sortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'amount_desc' => 'Highest amount', 'amount_asc' => 'Lowest amount'];
        $cellHref = fn (string $st) => route('admin.payments.index', array_filter(['search' => $search, 'sort' => $sort !== 'newest' ? $sort : null, 'status' => $st === $status ? null : $st]));
    @endphp

    {{-- Pesos only: the tabs below carry the counts. --}}
    <x-stat-strip class="mb-3" :cells="[
        ['label' => 'Held', 'value' => '₱'.number_format($sums['Held'], 2), 'note' => 'Waiting for move-in', 'dot' => '#FBBF24', 'href' => $cellHref('Held'), 'active' => $status === 'Held'],
        ['label' => 'Released', 'value' => '₱'.number_format($sums['Released'], 2), 'note' => 'Sent on to landlords', 'dot' => '#22C55E', 'href' => $cellHref('Released'), 'active' => $status === 'Released'],
        ['label' => 'Recorded', 'value' => '₱'.number_format($sums['Paid'], 2), 'note' => 'Offline, reported by landlords', 'dot' => '#94A3B8', 'href' => $cellHref('Paid'), 'active' => $status === 'Paid'],
    ]" />

    {{-- Search + filters, same bar as Reservations --}}
    <form data-live method="GET" action="{{ route('admin.payments.index') }}" class="mb-3">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search tenant, email, property or landlord…" aria-label="Search"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="status" :options="$statusOptions" :selected="$status"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="sort" :options="$sortOptions" :selected="$sort"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                @if($search || $sort !== 'newest' || $status !== 'All')
                    <a href="{{ route('admin.payments.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                @endif

                {{-- View toggle: the choice is kept in the browser, like Reservations --}}
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

    @if ($payments->isEmpty())
        @php
            $empty = [
                'Held' => ['Nothing is being held', 'Online payments wait here until the tenant moves in.'],
                'Released' => ['No payments released yet', 'A held payment shows here once it is sent on to the landlord.'],
                'Paid' => ['No recorded payments', 'Payments a landlord records as paid offline show here.'],
                'Pending' => ['Nothing is processing', 'Payments that are still going through show here.'],
                'Voided' => ['No voided payments', 'Payments that were cancelled before settling show here.'],
                'Refunded' => ['No refunds', 'Payments you refund to a tenant show here.'],
                'All' => ['No payments yet', 'Online and recorded payments show here once tenants start paying.'],
            ][$status] ?? ['No payments here', null];
        @endphp
        <x-card flush fill>
            <x-empty-state flat fill symbol="wallet" :title="$empty[0]" :note="$empty[1]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.payments.index') }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all payments</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <x-card flush fill>
            <div x-show="view === 'table'" class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[1040px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th class="w-[28%]">Property / Unit</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th align="right">Amount</x-admin.th>
                            <x-admin.th>Paid by</x-admin.th>
                            <x-admin.th>Date</x-admin.th>
                            @if($showStatus)<x-admin.th>Status</x-admin.th>@endif
                            <x-admin.th align="right">Action</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($payments as $payment)
                            @php
                                $tenant = $payment->reservation?->tenant;
                                $payLandlord = $payment->reservation?->property?->landlord;
                            @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if ($payment->reservation)
                                            <a href="{{ route('admin.reservations.show', $payment->reservation) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</a>
                                        @else
                                            <p class="text-[13px] font-semibold text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                        @endif
                                        @if ($tenant?->is_walk_in)
                                            <span class="inline-flex items-center h-5 px-1.5 rounded bg-[#FBBF24]/[0.16] text-[#B45309] text-[11px] font-semibold"
                                                title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                        @endif
                                    </div>
                                    <p class="max-w-[240px] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $tenant?->email }}">{{ $tenant?->email ?: '—' }}</p>
                                </td>
                                <td class="max-w-0 px-4">
                                    <p class="truncate text-[13px] leading-5 text-[#060D26]" title="{{ $payment->reservation?->property?->title }}">{{ $payment->reservation?->property?->title ?? '—' }}</p>
                                    <p class="truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $payment->reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#060D26]">{{ $payLandlord ? trim($payLandlord->first_name.' '.$payLandlord->last_name) : '—' }}</td>
                                <td class="px-4 text-right">
                                    <p class="text-[13px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $payment->payment_type }}</p>
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    <p class="text-[13px] text-[#060D26]">{{ $payment->payment_method ?: '—' }}</p>
                                    @if ($payment->reference_no)<p class="max-w-[10rem] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $payment->reference_no }}">{{ $payment->reference_no }}</p>@endif
                                </td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#5B6A8E]">
                                    {{ ($payment->paid_at ?? $payment->created_at)?->format('M j, Y') ?? '—' }}
                                </td>
                                @if($showStatus)
                                    <td class="px-4">
                                        <x-admin.status :status="$payment->status" :label="['Paid' => 'Recorded', 'Pending' => 'Processing'][$payment->status] ?? $payment->status" />
                                    </td>
                                @endif
                                <td class="px-4 text-right">
                                    @include('admin.payments.partials._actions', ['payment' => $payment, 'heldGroups' => $heldGroups])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Grid view: one card per payment, the same figures and actions as the table row. --}}
            <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 gap-3 bg-[#F7F8FC] p-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($payments as $payment)
                    @php
                        $tenant = $payment->reservation?->tenant;
                        $payLandlord = $payment->reservation?->property?->landlord;
                    @endphp
                    <article data-payment-card class="flex min-w-0 flex-col rounded-xl border border-[#E2E4EC] bg-white p-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    @if ($payment->reservation)
                                        <a href="{{ route('admin.reservations.show', $payment->reservation) }}" class="truncate text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</a>
                                    @else
                                        <p class="truncate text-[13px] font-semibold text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                    @endif
                                    @if ($tenant?->is_walk_in)
                                        <span class="inline-flex h-5 shrink-0 items-center rounded bg-[#FBBF24]/[0.16] px-1.5 text-[11px] font-semibold text-[#B45309]" title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                    @endif
                                </div>
                                <p class="truncate text-[12px] text-[#5B6A8E]" title="{{ $tenant?->email }}">{{ $tenant?->email ?: '—' }}</p>
                            </div>
                            @if ($showStatus)
                                <span data-payment-status class="shrink-0"><x-admin.status :status="$payment->status" :label="['Paid' => 'Recorded', 'Pending' => 'Processing'][$payment->status] ?? $payment->status" /></span>
                            @endif
                        </div>

                        <p class="mt-3 text-[20px] font-semibold tabular-nums leading-6 text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</p>
                        <p class="text-[12px] text-[#5B6A8E]">{{ $payment->payment_type }}</p>

                        <dl class="mt-3 space-y-1.5 text-[12.5px]">
                            <div class="min-w-0">
                                <dt class="sr-only">Property</dt>
                                <dd class="truncate text-[#060D26]" title="{{ $payment->reservation?->property?->title }}">{{ $payment->reservation?->property?->title ?? '—' }}</dd>
                                <dd class="truncate text-[12px] text-[#5B6A8E]">{{ $payment->reservation?->unit?->unit_label ?? '' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Landlord</dt><dd class="truncate text-right text-[#060D26]">{{ $payLandlord ? trim($payLandlord->first_name.' '.$payLandlord->last_name) : '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Paid by</dt><dd class="min-w-0 truncate text-right text-[#060D26]" title="{{ $payment->reference_no }}">{{ $payment->payment_method ?: '—' }}@if ($payment->reference_no) <span class="text-[#5B6A8E]">· {{ $payment->reference_no }}</span>@endif</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Date</dt><dd class="text-right tabular-nums text-[#060D26]">{{ ($payment->paid_at ?? $payment->created_at)?->format('M j, Y') ?? '—' }}</dd></div>
                        </dl>

                        <div class="mt-auto pt-3">
                            <div class="border-t border-[#E2E4EC] pt-3">
                                @include('admin.payments.partials._actions', ['payment' => $payment, 'heldGroups' => $heldGroups, 'align' => 'start'])
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5">
                @if ($payments->hasPages())
                    <div class="w-full">{{ $payments->links() }}</div>
                @else
                    <p class="text-[13px] text-[#5B6A8E]">Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $payments->firstItem() }}–{{ $payments->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($payments->total()) }}</span></p>
                @endif
            </div>
        </x-card>
    @endif

    @include('admin.payments.partials.refund-modal')

</div>
@endsection
