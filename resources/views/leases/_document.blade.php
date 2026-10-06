{{--
    The lease text, rendered from App\Support\LeaseTerms. Shared by the
    tenant's agreement page (e-sign) and the landlord's printable lease
    (paper signing for walk-ins). Signatures are the caller's: e-sign records
    and blank signature lines are different things.

    Plain language on purpose — most tenants read this on a phone.
    Standard clauses are a template, not legal advice; see
    plans/formal-lease-agreement.md.

    @param array $terms  LeaseTerms::for($reservation)
--}}
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $date = fn (?string $d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('F j, Y') : null;
    $ordinal = fn (int $n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    $landlordName = $terms['landlord']['name'] ?: 'the Landlord';
    $tenantName = $terms['tenant']['name'] ?: 'the Tenant';
    $utilities = $terms['utilities'] ?? ['included' => [], 'not_included' => [], 'separately_metered' => null];
    $n = 0;
    $heading = 'text-[17px] font-semibold text-[#060D26] mt-8 mb-2 print:break-after-avoid';
    $body = 'text-[13.5px] leading-relaxed text-[#060D26]';
@endphp

<div class="text-[#060D26]">
    {{-- Parties — plain, not a card: this prints, and boxed UI chrome (rounded
         corners, fill) reads as an app screenshot, not a contract. Same
         bordered-column treatment as the Signatures block further down. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 pb-5 border-b border-[#E2E4EC]">
        @foreach (['Landlord' => $terms['landlord'], 'Tenant' => $terms['tenant']] as $role => $p)
            <div @if ($role === 'Tenant') class="sm:border-l sm:border-[#E2E4EC] sm:pl-4" @endif>
                <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wider mb-1">{{ $role }}</p>
                <p class="text-[14px] font-bold text-[#060D26]">{{ $p['name'] ?: '—' }}</p>
                @if ($p['email'])
                    <p class="text-[11.5px] text-[#5B6A8E] mt-0.5 break-words">{{ $p['email'] }}</p>
                @endif
                @if ($p['phone'])
                    <p class="text-[11.5px] text-[#5B6A8E]">{{ $p['phone'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <p class="{{ $body }}">
        This Lease Agreement is made between <strong>{{ $landlordName }}</strong> ("Landlord") and
        <strong>{{ $tenantName }}</strong> ("Tenant") for the rental of the premises described below.
    </p>

    {{-- Key terms at a glance --}}
    <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wider mt-6 mb-1.5">Summary of key terms</p>
    <dl class="divide-y divide-[#E2E4EC] border-y border-[#E2E4EC]">
        @foreach (array_filter([
            'Premises'         => trim(($terms['property']['title'] ?? '') . ($terms['unit'] ? ' · ' . $terms['unit'] : '')),
            'Address'          => $terms['property']['address'] ?? null,
            'Start date'       => $date($terms['start']),
            'End date'         => $terms['end'] ? $date($terms['end']) : 'Month-to-month',
            'Monthly rent'     => $peso($terms['monthly_rent']),
            'Rent due'         => 'Every ' . $ordinal((int) $terms['due_day']) . ' of the month',
            'Security deposit' => $terms['deposit'] !== null ? $peso($terms['deposit']) : 'None',
            'Occupants'        => $terms['occupants'] ? $terms['occupants'] . ' ' . \Illuminate\Support\Str::plural('person', $terms['occupants']) : null,
        ]) as $label => $value)
            <div class="flex items-baseline justify-between gap-4 py-2.5">
                <dt class="text-[13px] text-[#5B6A8E]">{{ $label }}</dt>
                <dd class="text-[13px] font-bold text-[#060D26] text-right tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <h3 class="{{ $heading }}">{{ ++$n }}. Premises and use</h3>
    <p class="{{ $body }}">
        The Landlord rents to the Tenant {{ $terms['unit'] ? 'the unit "' . $terms['unit'] . '" at' : '' }}
        {{ $terms['property']['title'] }}, {{ $terms['property']['address'] }}.
        The premises are for residential use only{{ $terms['occupants'] ? ', by no more than ' . $terms['occupants'] . ' ' . \Illuminate\Support\Str::plural('person', $terms['occupants']) : '' }}.
        The Tenant may not sublet or transfer the premises without the Landlord's written consent.
    </p>

    <h3 class="{{ $heading }}">{{ ++$n }}. Term</h3>
    <p class="{{ $body }}">
        @if ($terms['start'])
            This lease starts on {{ $date($terms['start']) }}
        @else
            This lease starts on the Tenant's move-in date
        @endif
        @if ($terms['end'])
            and ends on {{ $date($terms['end']) }}, unless ended earlier as set out below or renewed in writing.
        @else
            and continues month to month until either party ends it with notice as set out below.
        @endif
    </p>

    <h3 class="{{ $heading }}">{{ ++$n }}. Rent</h3>
    <p class="{{ $body }}">
        The Tenant pays {{ $peso($terms['monthly_rent']) }} per month, due on the {{ $ordinal((int) $terms['due_day']) }} day of each month.
        Payments made through AbangananHub or recorded there by the Landlord form the rent ledger for this tenancy.
    </p>

    <h3 class="{{ $heading }}">{{ ++$n }}. Security deposit</h3>
    <p class="{{ $body }}">
        @if ($terms['deposit'] !== null && $terms['deposit'] > 0)
            The Tenant pays a security deposit of {{ $peso($terms['deposit']) }}. It covers unpaid rent and damage beyond normal wear and tear.
            The Landlord returns it within 30 days after the Tenant moves out, less any deductions, which the Landlord lists in writing.
            The deposit may not be used as the last month's rent without the Landlord's written agreement.
        @else
            No security deposit is required under this lease.
        @endif
    </p>

    <h3 class="{{ $heading }}">{{ ++$n }}. Utilities</h3>
    <p class="{{ $body }}">
        @if ($utilities['included'])
            Included in the rent: {{ implode(', ', $utilities['included']) }}.
        @endif
        @if ($utilities['not_included'])
            Not included, paid by the Tenant: {{ implode(', ', $utilities['not_included']) }}.
        @endif
        @if ($utilities['separately_metered'])
            Utilities are separately metered, and the Tenant pays for their own consumption.
        @endif
        @if (! $utilities['included'] && ! $utilities['not_included'] && ! $utilities['separately_metered'])
            Utility charges are as agreed between the parties{{ $terms['extra_terms'] ? ' in the additional terms below' : '' }}.
        @endif
    </p>

    @if (! empty($terms['house_rules']))
        <h3 class="{{ $heading }}">{{ ++$n }}. House rules</h3>
        <p class="{{ $body }}">The Tenant, their household and guests follow these rules:</p>
        <ul class="mt-1.5 list-disc pl-5 space-y-0.5 {{ $body }}">
            @foreach ($terms['house_rules'] as $rule)
                <li>{{ $rule }}</li>
            @endforeach
        </ul>
    @endif

    <h3 class="{{ $heading }}">{{ ++$n }}. Care and repairs</h3>
    <p class="{{ $body }}">
        The Tenant keeps the premises clean and in good condition and reports any damage or needed repair promptly.
        The Landlord handles repairs from normal wear and tear and to the building's structure, plumbing and wiring.
        The Tenant pays for damage caused by the Tenant, their household or guests. The Tenant makes no alterations without the Landlord's written consent.
        The Landlord gives reasonable notice before entering the premises, except in an emergency.
    </p>

    <h3 class="{{ $heading }}">{{ ++$n }}. Ending the tenancy</h3>
    <p class="{{ $body }}">
        Either party may end this lease by giving at least {{ $terms['notice_days'] }} days' written notice, including by message on AbangananHub.
        The Landlord may end it sooner if the Tenant fails to pay rent or seriously breaks this lease, as allowed by law.
        On moving out, the Tenant returns the premises and all keys in the condition received, apart from normal wear and tear.
    </p>

    @if ($terms['extra_terms'])
        <h3 class="{{ $heading }}">{{ ++$n }}. Additional terms</h3>
        <p class="whitespace-pre-wrap {{ $body }}">{{ $terms['extra_terms'] }}</p>
    @endif

    <h3 class="{{ $heading }}">{{ ++$n }}. Whole agreement</h3>
    <p class="{{ $body }}">
        This document is the whole agreement between the parties. Changes are valid only if made in writing and accepted by both.
    </p>

    <p class="text-[11.5px] text-[#5B6A8E] leading-relaxed mt-6 pt-4 border-t border-[#E2E4EC]">
        AbangananHub provides this lease as a template and record-keeping tool between Landlord and Tenant. It is not a party to,
        nor liable for, the terms herein, and this template is not legal advice. Reference {{ $terms['reference'] }}
        · issued {{ \Illuminate\Support\Carbon::parse($terms['issued_at'])->format('F j, Y') }}.
    </p>
</div>
