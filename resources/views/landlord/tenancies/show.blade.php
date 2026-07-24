@extends('layouts.landlord')

@section('page-title', 'Tenancy')

@section('content')
    @php
        $tenant = $reservation->tenant;
        $unit = $reservation->unit;
        $isWalkIn = (bool) $tenant?->is_walk_in;
        $isActive = $reservation->rental_status === 'Occupied';
        $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1));

        // Period pill styling, one map so the table and the tiles can't drift.
        $periodStyles = [
            'paid'    => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'partial' => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'due'     => ['pill' => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]', 'label' => 'Due'],
        ];

        $statusPill = match ($reservation->rental_status) {
            'Occupied'  => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
            'Completed' => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
            default     => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        };

        $inputClass = 'h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#1F2937] mb-1.5';

        $thumb = $unit?->media->firstWhere('media_type', 'Image');
    @endphp

    {{-- x-data only so the "Record payment" button has an Alpine scope to
         $dispatch from; the modal listens for the event on the window. --}}
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16" x-data>

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-3">
            <a href="{{ route('landlord.tenants.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">My Tenants</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">{{ $tenant->name ?? 'Tenancy' }}</span>
        </div>

        {{-- Tenant header --}}
        <x-card class="mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="flex items-center gap-4 min-w-0 flex-1">
                    <div class="w-14 h-14 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[18px] font-bold text-[#156F8C] shrink-0">
                        {{ $initials ?: '?' }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-[#1F2937] truncate">
                                {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown tenant' }}
                            </h1>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $statusPill }}">
                                {{ $reservation->rental_status }}
                            </span>
                            @if($isWalkIn)
                                {{-- Not platform-verified. The landlord asserted this person exists. --}}
                                <span class="inline-flex items-center gap-1 h-6 px-2.5 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[11px] font-bold"
                                    title="Added by you — identity not verified by AbangananHub">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                    Walk-in
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-[12.5px] text-[#64748B]">
                            <span class="flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                                {{ $tenant->contact_number ?: '—' }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                {{ $tenant->email ?: '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if($reservation->conversation)
                        <a href="{{ route('conversations.show', $reservation->conversation) }}"
                            class="h-11 px-4 inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-white text-[#1F2937] text-sm font-semibold hover:bg-[#F7FCFC] transition-all duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Message
                        </a>
                    @endif

                    @if($isActive)
                        <button type="button" @click="$dispatch('open-record-payment')"
                            class="h-11 px-5 inline-flex items-center gap-2 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Record payment
                        </button>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            {{-- ── Ledger column ──────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Summary tiles --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @php
                        $tiles = [
                            ['label' => 'Collected',   'value' => $summary['collected'],     'tone' => 'text-[#15803D]', 'sub' => 'Rent + deposits recorded'],
                            ['label' => 'Outstanding', 'value' => $summary['outstanding'],   'tone' => 'text-[#1F2937]', 'sub' => 'Unpaid rent to date'],
                            ['label' => 'Overdue',     'value' => $summary['overdueAmount'], 'tone' => $summary['overdueCount'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]', 'sub' => $summary['overdueCount'] . ' ' . \Illuminate\Support\Str::plural('month', $summary['overdueCount']) . ' behind'],
                        ];
                    @endphp
                    @foreach($tiles as $tile)
                        <x-card class="!p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2">{{ $tile['label'] }}</p>
                            <p class="text-2xl font-extrabold {{ $tile['tone'] }}">
                                ₱{{ number_format($tile['value'], 2) }}
                            </p>
                            <p class="text-[11px] text-[#64748B] mt-1">{{ $tile['sub'] }}</p>
                        </x-card>
                    @endforeach
                </div>

                {{-- Rent ledger --}}
                <x-card flush>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-[#E2E8F0]">
                        <div>
                            <h2 class="text-[15px] font-bold text-[#1F2937]">Rent ledger</h2>
                            <p class="text-[12px] text-[#64748B] mt-0.5">
                                ₱{{ number_format($summary['monthlyRent'], 2) }} per month, due on day {{ $summary['dueDay'] }}.
                            </p>
                        </div>
                    </div>

                    @if($periods->isEmpty())
                        <div class="flex flex-col items-center justify-center py-10 px-6 text-center">
                            <p class="text-[14px] font-semibold text-[#1F2937]">No billing periods yet</p>
                            <p class="text-[13px] text-[#64748B] mt-1">
                                Rent starts accruing from the move-in date.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Period</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Due on</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Expected</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Paid</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Balance</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E8F0]">
                                    @foreach($periods as $period)
                                        @php $style = $periodStyles[$period['status']] ?? $periodStyles['due']; @endphp
                                        <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $period['label'] }}</p>
                                                @if($period['payments']->isNotEmpty())
                                                    <p class="text-[11px] text-[#64748B] mt-0.5">
                                                        {{ $period['payments']->pluck('payment_method')->unique()->join(', ') }}
                                                    </p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B] whitespace-nowrap">
                                                {{ $period['due_on']->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['expected'], 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1F2937] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['paid'], 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-right whitespace-nowrap {{ $period['balance'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#64748B]' }}">
                                                ₱{{ number_format(max(0, $period['balance']), 2) }}
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">
                                                    {{ $style['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                {{-- Other charges --}}
                @if($otherCharges->isNotEmpty())
                    <x-card flush>
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E8F0]">
                            <h2 class="text-[15px] font-bold text-[#1F2937]">Deposits &amp; other payments</h2>
                            <p class="text-[12px] text-[#64748B] mt-0.5">Money outside the monthly rent cycle.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px]">
                                <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Method</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Amount</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Source</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E8F0]">
                                    @foreach($otherCharges as $charge)
                                        <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                            <td class="px-5 sm:px-6 py-3.5 text-[13px] text-[#1F2937] whitespace-nowrap">
                                                {{ optional($charge->paid_at)->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B]">{{ $charge->payment_type }}</td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B]">{{ $charge->payment_method }}</td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1F2937] text-right whitespace-nowrap">
                                                ₱{{ number_format((float) $charge->amount, 2) }}
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                @if($charge->isManuallyRecorded())
                                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B] text-[11px] font-bold">
                                                        Recorded by you
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#2AA7A1]/25 bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold">
                                                        Paid online
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>

            {{-- ── Side column ────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- Unit --}}
                <x-card>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-4">Unit</p>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-14 h-14 rounded-xl bg-[#F7FCFC] overflow-hidden shrink-0 ring-1 ring-[#64748B]/10">
                            @if($thumb)
                                <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-bold text-[#1F2937] truncate">{{ $unit->unit_label ?? 'No unit' }}</p>
                            <p class="text-[12px] text-[#64748B] truncate">{{ $reservation->property->title ?? '' }}</p>
                        </div>
                    </div>

                    <dl class="space-y-2.5 text-[13px]">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">Monthly rent</dt>
                            <dd class="font-bold text-[#2AA7A1]">₱{{ number_format($summary['monthlyRent'], 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">Moved in</dt>
                            <dd class="font-semibold text-[#1F2937]">
                                {{ optional($reservation->target_move_in_date)->format('M d, Y') ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">{{ $isActive ? 'Move-out' : 'Moved out' }}</dt>
                            <dd class="font-semibold text-[#1F2937]">
                                {{ optional($reservation->target_move_out_date)->format('M d, Y') ?? 'Open-ended' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">Occupants</dt>
                            <dd class="font-semibold text-[#1F2937]">{{ $reservation->occupants_count ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if($reservation->remarks)
                        <div class="mt-4 pt-4 border-t border-[#E2E8F0]">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1.5">Notes</p>
                            <p class="text-[12.5px] text-[#64748B] leading-relaxed">{{ $reservation->remarks }}</p>
                        </div>
                    @endif
                </x-card>

                {{-- End tenancy --}}
                @if($isActive)
                    <x-card>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">End of tenancy</p>
                        <p class="text-[12.5px] text-[#64748B] leading-relaxed mb-4">
                            Marks this tenancy complete and returns
                            <strong class="text-[#1F2937]">{{ $unit->unit_label ?? 'the unit' }}</strong>
                            to your available units. The ledger stays readable but stops accepting new payments.
                        </p>

                        <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}"
                            data-confirm="End this tenancy?"
                            data-confirm-message="The unit becomes available again and no further payments can be recorded against this ledger. This cannot be undone."
                            data-confirm-button="End tenancy"
                            data-confirm-type="warning">
                            @csrf
                            <label for="move_out_date" class="{{ $labelClass }}">Move-out date</label>
                            <input type="date" id="move_out_date" name="move_out_date" value="{{ now()->toDateString() }}"
                                max="{{ now()->toDateString() }}" class="{{ $inputClass }} cursor-pointer mb-3">

                            <button type="submit"
                                class="w-full h-11 rounded-full border border-[#EF4444]/30 text-[#DC2626] text-sm font-semibold hover:bg-[#EF4444]/[0.06] transition-all duration-200 cursor-pointer">
                                End tenancy
                            </button>
                        </form>
                    </x-card>
                @else
                    <x-card class="bg-[#F7FCFC]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Tenancy closed</p>
                        <p class="text-[12.5px] text-[#64748B] leading-relaxed">
                            This tenancy ended on
                            <strong class="text-[#1F2937]">{{ optional($reservation->target_move_out_date)->format('M d, Y') ?? 'an unrecorded date' }}</strong>.
                            The ledger is kept as a record and no longer accepts payments.
                        </p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>

    {{-- Record payment modal — teleported so no ancestor's transform or
         overflow can clip it (RULES.md → Modals & Overlays). --}}
    @if($isActive)
        @include('landlord.tenancies.partials.record-payment-modal')
    @endif
@endsection
