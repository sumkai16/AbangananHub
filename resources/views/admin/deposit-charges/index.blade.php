@extends('layouts.admin')

@section('page-title', 'Deposit Charges')

@section('content')
<div class="max-w-[1600px] mx-auto"
    x-data="{ voidTarget: { id: null, label: '' }, openVoid(id, label) { this.voidTarget = { id, label }; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'void-deposit-charge' })); } }">

    {{-- Page header --}}
    <x-page-header icon-path="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" title="Deposit Charges" subtitle="Oversight of landlord claims against a tenant's held security deposit." />

    {{-- Tabs --}}
    <x-admin.tabs class="mb-4" :tabs="collect(['Active', 'Voided', 'All'])->map(fn ($tab) => [
        'label' => $tab,
        'count' => $counts[$tab] ?? null,
        'href' => route('admin.deposit-charges.index', ['status' => $tab]),
        'active' => $status === $tab,
    ])->all()" />

    @if ($charges->isEmpty())
        <x-card flush><x-admin.empty title="No deposit charges here" /></x-card>
    @else
        <x-card flush>
            <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center justify-between">
                <p class="text-[13px] font-semibold text-[#060D26]">
                    {{ $charges->total() }} {{ Str::plural('charge', $charges->total()) }}
                </p>
            </div>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th>Property / Unit</x-admin.th>
                            <x-admin.th>Category</x-admin.th>
                            <x-admin.th>Amount</x-admin.th>
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
                            <tr class="hover:bg-[#F7F8FC] transition-all duration-200">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-[13.5px] font-semibold text-[#060D26]">
                                                    {{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}
                                                </p>
                                                @if ($tenant?->is_walk_in)
                                                    <span class="inline-flex items-center h-5 px-2 rounded-md border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[10px] font-bold"
                                                        title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                                @endif
                                            </div>
                                            <p class="text-[12px] text-[#5B6A8E]">{{ $tenant?->email ?: '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] text-[#060D26] font-medium">{{ $reservation?->property?->title ?? '—' }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-[#060D26]">
                                    {{ $charge->category }}
                                    @if ($charge->description)
                                        <p class="text-[12px] text-[#5B6A8E] mt-0.5">{{ Str::limit($charge->description, 40) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] font-bold text-[#060D26]">₱{{ number_format((float) $charge->amount, 2) }}</p>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-[#5B6A8E]">
                                    {{ $charge->charged_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($charge->isVoided())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[#E2E4EC] text-[11.5px] font-bold text-[#5B6A8E]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#94A3B8]"></span>
                                            Voided
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[#22C55E]/15 text-[11.5px] font-bold text-[#15803D]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($charge->isVoided())
                                        <span class="text-[12px] text-[#5B6A8E]">
                                            {{ $charge->voidReasonLabel() }} · {{ $charge->voided_at?->format('M d, Y') }}
                                            @if ($charge->voider)
                                                <br>by {{ trim($charge->voider->first_name.' '.$charge->voider->last_name) }}
                                            @endif
                                        </span>
                                    @else
                                        <button type="button" x-on:click="openVoid({{ $charge->deposit_charge_id }}, @js($label))"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-[#E2E4EC] text-[12px] font-semibold text-[#5B6A8E] hover:border-[#EF4444]/40 hover:text-[#DC2626] transition-all duration-200 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Void
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($charges->hasPages())
                <div class="px-6 py-4 border-t border-[#E2E4EC]">
                    {{ $charges->links() }}
                </div>
            @endif
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
