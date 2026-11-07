@extends('layouts.admin')

@section('page-title', 'Deposit Charges')

@section('content')
<div class="admin-compact admin-fill"
    x-data="{ voidTarget: { id: null, label: '' }, openVoid(id, label) { this.voidTarget = { id, label }; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'void-deposit-charge' })); } }">

    <x-admin.header title="Deposit Charges" subtitle="Oversight of landlord claims against a tenant's held security deposit." />

    @php
        $statusOptions = ['Active' => 'Active ('.$counts['Active'].')', 'Voided' => 'Voided ('.$counts['Voided'].')', 'All' => 'All statuses ('.$counts['All'].')'];
        $sortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'amount_desc' => 'Highest amount', 'amount_asc' => 'Lowest amount'];
        $cellHref = fn (string $st) => route('admin.deposit-charges.index', array_filter(['search' => $search, 'sort' => $sort !== 'newest' ? $sort : null, 'status' => $st === $status ? null : $st]));
    @endphp

    <x-stat-strip class="mb-3" :cells="[
        ['label' => 'All charges', 'value' => $counts['All'], 'note' => 'Claims on held deposits', 'href' => $cellHref('All'), 'active' => $status === 'All'],
        ['label' => 'Active', 'value' => $counts['Active'], 'note' => 'Still standing', 'dot' => '#22C55E', 'href' => $cellHref('Active'), 'active' => $status === 'Active'],
        ['label' => 'Voided', 'value' => $counts['Voided'], 'note' => 'Overturned, with a reason', 'dot' => '#94A3B8', 'href' => $cellHref('Voided'), 'active' => $status === 'Voided'],
        ['label' => 'Active amount', 'value' => '₱'.number_format($activeAmount, 2), 'note' => 'Charged against deposits'],
    ]" />

    {{-- Search + filters, same bar as Reservations --}}
    <form method="GET" action="{{ route('admin.deposit-charges.index') }}" class="mb-3">
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
                @if($search || $sort !== 'newest' || $status !== 'Active')
                    <a href="{{ route('admin.deposit-charges.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if ($charges->isEmpty())
        @php
            $empty = [
                'Active' => ['No active deposit charges', "A landlord's claim against a tenant's deposit shows here."],
                'Voided' => ['No voided charges', 'Charges you void show here, with the reason.'],
                'All' => ['No deposit charges yet', "Claims landlords make against a tenant's deposit show here."],
            ][$status] ?? ['No deposit charges here', null];
        @endphp
        <x-card flush fill>
            <x-empty-state flat fill symbol="banknotes" :title="$empty[0]" :note="$empty[1]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.deposit-charges.index', ['status' => 'All']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all charges</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <x-card flush fill>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[960px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th class="w-[30%]">Property / Unit</x-admin.th>
                            <x-admin.th>Category</x-admin.th>
                            <x-admin.th align="right">Amount</x-admin.th>
                            <x-admin.th>Charged</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th align="right">Action</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($charges as $charge)
                            @php
                                $reservation = $charge->reservation;
                                $tenant = $reservation?->tenant;
                                $label = ($tenant ? trim($tenant->first_name.' '.$tenant->last_name) : 'this tenant').' — ₱'.number_format((float) $charge->amount, 2);
                            @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[13px] font-semibold text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                        @if ($tenant?->is_walk_in)
                                            <span class="inline-flex items-center h-5 px-1.5 rounded bg-[#FBBF24]/[0.16] text-[#B45309] text-[11px] font-semibold"
                                                title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                        @endif
                                    </div>
                                    <p class="max-w-[240px] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $tenant?->email }}">{{ $tenant?->email ?: '—' }}</p>
                                </td>
                                <td class="max-w-0 px-4">
                                    <p class="truncate text-[13px] leading-5 text-[#060D26]" title="{{ $reservation?->property?->title }}">{{ $reservation?->property?->title ?? '—' }}</p>
                                    <p class="truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-4">
                                    <p class="text-[13px] whitespace-nowrap text-[#060D26]">{{ $charge->category }}</p>
                                    @if ($charge->description)
                                        <p class="max-w-[220px] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $charge->description }}">{{ $charge->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 text-right text-[13px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">₱{{ number_format((float) $charge->amount, 2) }}</td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#5B6A8E]">{{ $charge->charged_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4">
                                    <x-admin.status :status="$charge->isVoided() ? 'Voided' : 'Active'" />
                                </td>
                                <td class="px-4 text-right">
                                    @if ($charge->isVoided())
                                        <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $charge->voidReasonLabel() }} · {{ $charge->voided_at?->format('M j, Y') }}</p>
                                        @if ($charge->voider)
                                            <p class="text-[12px] leading-4 text-[#5B6A8E]">by {{ trim($charge->voider->first_name.' '.$charge->voider->last_name) }}</p>
                                        @endif
                                    @else
                                        <button type="button" x-on:click="openVoid({{ $charge->deposit_charge_id }}, @js($label))"
                                            class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:border-[#EF4444]/40 hover:bg-[#EF4444]/[0.06] hover:text-[#DC2626] cursor-pointer">
                                            Void
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5">
                @if ($charges->hasPages())
                    <div class="w-full">{{ $charges->links() }}</div>
                @else
                    <p class="text-[13px] text-[#5B6A8E]">Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $charges->firstItem() }}–{{ $charges->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($charges->total()) }}</span></p>
                @endif
            </div>
        </x-card>
    @endif

    {{-- Void reason modal — shared across every row instead of an inline
         per-row form. Admin overriding a landlord's charge needs a reason
         on record, same as listing rejection requires one. --}}
    <x-modal name="void-deposit-charge" focusable>
        <form method="POST" x-bind:action="`{{ url('admin/deposit-charges') }}/${voidTarget.id}/void`" class="p-6"
            x-data="{ voidReason: '' }">
            @csrf
            <h2 class="text-[15px] font-bold text-[#060D26]">Void deposit charge</h2>
            <p class="mt-1 text-[13px] text-[#5B6A8E]">
                <span x-text="voidTarget.label"></span> will be voided. The original entry stays on record and both the tenant and landlord are notified.
            </p>

            <label for="void_reason" class="block mt-4 text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">
                Reason
            </label>
            <select name="void_reason" id="void_reason" x-model="voidReason" required
                class="w-full h-10 rounded-lg border border-[#E2E4EC] px-3 text-[13px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
                <option value="" disabled selected>Select a reason</option>
                @foreach (\App\Models\DepositCharge::VOID_REASONS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('void_reason')
                <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror

            <div x-show="voidReason === 'other'" x-cloak>
                <label for="void_note" class="block mt-4 text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-1.5">
                    Details
                </label>
                <textarea name="void_note" id="void_note" rows="3"
                    class="w-full rounded-lg border border-[#E2E4EC] px-3 py-2.5 text-[13px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all resize-none"
                    placeholder="Explain why this charge is being voided."></textarea>
            </div>
            @error('void_note')
                <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" x-on:click="show = false"
                    class="h-10 px-4 rounded-lg border border-[#E2E4EC] text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="h-10 px-4 rounded-lg bg-[#EF4444] hover:brightness-95 text-white text-[13px] font-bold transition-all">
                    Confirm void
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
