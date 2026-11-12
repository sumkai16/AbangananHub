{{-- The lease modal body (fetched by _view-modal from ContractController::show or TenantController::show). --}}
@php
    $tenant = $reservation->tenant;
    $detail = $tenant->tenantDetail;
    $status = $reservation->contractStatus();
    $pill = [
        'ok' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'warn' => 'bg-[#FBBF24]/[0.12] text-[#B45309] border-[#FBBF24]/35',
        'bad' => 'bg-[#EF4444]/[0.08] text-[#B91C1C] border-[#EF4444]/25',
        'muted' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ][$status['tone']];
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
    $deposit = $reservation->agreedDeposit();
    $reference = $reservation->lease_snapshot['reference'] ?? null;
    $label = 'text-[12.5px] text-[#5B6A8E]';
    $value = 'mt-0.5 text-[15px] text-[#060D26] break-words';
    $link = 'underline-offset-2 hover:underline focus-visible:underline';
    $h3 = 'text-[15px] font-semibold text-[#060D26]';
    $ghostBtn = 'inline-flex items-center justify-center h-11 px-5 rounded-full border border-[#5B6A8E]/30 text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200';
    $ctaBtn = 'inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer';

    // Renew is offered on a running contract near or past its end (the same window as the "Ends in N days" pill).
    $canRenew = $reservation->rental_status === 'Occupied' && in_array($status['tone'], ['warn', 'bad'], true);
    $renewMonths = collect(range(0, 12))->mapWithKeys(fn ($i) => [
        now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m') => now()->startOfMonth()->addMonthsNoOverflow($i)->format('F Y'),
    ])->all();
    // Default: the month after the current end, or this month when the end has already passed.
    $afterEnd = $reservation->target_move_out_date?->copy()->addDay()->startOfMonth();
    $renewDefaultMonth = ($afterEnd && $afterEnd->gte(now()->startOfMonth()) ? $afterEnd : now())->format('Y-m');
    $canRate = in_array($reservation->rental_status, ['Occupied', 'Completed'], true) && ! $reservation->tenantRating;
    $dueDay = $reservation->rentDueDay();
    $dueOrdinal = $dueDay.(in_array($dueDay % 100, [11, 12, 13], true) ? 'th' : [1 => 'st', 2 => 'nd', 3 => 'rd'][$dueDay % 10] ?? 'th');
    $tile = 'rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 min-w-0';
    $hasEmergency = (bool) $detail?->emergency_contact_name;
    $leaseStatus = $reservation->leaseStatus();
@endphp

<div x-data="{ agreementOpen: false, agreementLoaded: false, agreementReady: false, ending: false, renewing: @js($canRenew && ($startRenewing ?? false)), uploading: false, rating: @js($canRate && ($startRating ?? false)), stars: 0, hovered: 0, count: 0,
        rate(on) { this.renewing = false; this.rating = on; this.$nextTick(() => this.$root.closest('.overflow-y-auto')?.scrollTo(0, 0)); } }">

{{-- The agreement, over the lease modal: the printable page (leases.show?embed=1) in a frame, printed from here.
     The frame loads on first open only, and Print waits for it. --}}
<template x-teleport="body">
    <div x-show="agreementOpen" x-cloak @keydown.escape.window="agreementOpen = false"
        style="z-index: 60;"
        class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-4"
        role="dialog" aria-modal="true" aria-labelledby="agreement-title-{{ $reservation->reservation_id }}">
        <div x-show="agreementOpen" x-transition.opacity @click="agreementOpen = false" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

        <div x-show="agreementOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 translate-y-6 motion-reduce:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="max-width: 860px; height: min(90vh, 1100px);"
            class="relative w-full flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)] overflow-hidden">

            <div class="shrink-0 flex items-center justify-between gap-3 px-5 sm:px-6 py-3.5 border-b border-[#E2E4EC]">
                <div class="min-w-0">
                    <h2 id="agreement-title-{{ $reservation->reservation_id }}" class="text-[16px] font-semibold text-[#060D26] leading-tight">Lease agreement</h2>
                    <p class="text-[12.5px] text-[#5B6A8E] truncate">{{ $tenant->first_name }} {{ $tenant->last_name }} · {{ $reservation->unit?->unit_label }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" :disabled="!agreementReady" @click="$refs.agreementFrame.contentWindow.print()"
                        class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] disabled:opacity-50 disabled:cursor-wait cursor-pointer transition-colors duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                        Print
                    </button>
                    <button type="button" @click="agreementOpen = false" aria-label="Close"
                        class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            <div class="relative flex-1 min-h-0 bg-white">
                <p x-show="!agreementReady" class="absolute inset-0 flex items-center justify-center text-[13px] text-[#5B6A8E]">Loading the lease agreement...</p>
                <template x-if="agreementLoaded">
                    <iframe x-ref="agreementFrame" src="{{ route('landlord.leases.show', ['reservation' => $reservation, 'embed' => 1]) }}"
                        title="Lease agreement" class="w-full h-full border-0" @load="agreementReady = true"></iframe>
                </template>
            </div>
        </div>
    </div>
</template>

<div x-show="!rating">

{{-- Two panes: who and what they pay on the left (always in view), the lease's details and what needs doing on the right. --}}
<div class="sm:grid sm:grid-cols-3">

    <aside class="bg-[#F7F8FC] px-6 pt-6 pb-6 sm:rounded-l-2xl sm:col-span-1">
        <div class="h-[110px] sm:h-[104px] w-full overflow-hidden rounded-xl bg-[#E4E7F2]">
            @if($photo ?? null)
                <img src="{{ $photo }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
            @else
                <span class="flex h-full w-full items-center justify-center text-[#8A94B3]">
                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18v-6.75A2.25 2.25 0 0 1 4.5 9h15a2.25 2.25 0 0 1 2.25 2.25V18M2.25 18h19.5M2.25 18v2.25m19.5-2.25v2.25M6 9V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75V9" /></svg>
                </span>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
            <h2 class="text-[21px] font-semibold leading-tight text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $pill }}">{{ $status['label'] }}</span>
        </div>
        <p class="mt-1.5 text-[14px] leading-snug text-[#5B6A8E]">{{ $reservation->unit->unit_label ?? 'No unit' }} · {{ $reservation->property->title }}</p>

        <div class="mt-5">
            <p class="text-[12.5px] text-[#5B6A8E]">Monthly rent</p>
            <p class="mt-0.5 text-[30px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">₱{{ number_format($reservation->currentRent(), 2) }}</p>
            <p class="mt-1.5 text-[13px] text-[#5B6A8E]">Due every {{ $dueOrdinal }} of the month</p>
        </div>

        <dl class="mt-5 divide-y divide-[#E2E4EC] border-t border-[#E2E4EC]">
            <div class="flex items-center justify-between gap-3 py-2.5"><dt class="text-[13.5px] text-[#5B6A8E]">Security deposit</dt><dd class="text-[14px] font-semibold text-[#060D26] tabular-nums">{{ $deposit > 0 ? '₱'.number_format($deposit, 2) : 'None' }}</dd></div>
            <div class="flex items-center justify-between gap-3 py-2.5"><dt class="text-[13.5px] text-[#5B6A8E]">Occupants</dt><dd class="text-[14px] font-semibold text-[#060D26]">{{ $reservation->occupants_count ?: 1 }}</dd></div>
            @if($code)<div class="flex items-center justify-between gap-3 py-2.5"><dt class="text-[13.5px] text-[#5B6A8E]">Tenant no.</dt><dd class="text-[14px] font-semibold text-[#060D26] tabular-nums">{{ $code }}</dd></div>@endif
            @if($reference)<div class="flex items-center justify-between gap-3 py-2.5"><dt class="text-[13.5px] text-[#5B6A8E]">Lease no.</dt><dd class="text-[14px] font-semibold text-[#060D26] tabular-nums">{{ $reference }}</dd></div>@endif
        </dl>
    </aside>

    <div class="min-w-0 flex flex-col sm:col-span-2">
        <div class="px-6 pt-6 pb-3 pr-16">
            <h2 class="text-[16px] font-semibold text-[#060D26]">Lease details</h2>
        </div>

        <div class="grid gap-4 px-6 pb-5">
            {{-- What needs doing comes first: the lease agreement, then the emergency contact. --}}
            @if($leaseStatus === 'missing')
                <div class="rounded-xl border border-[#FBBF24]/45 bg-[#FFF6E5] px-4 py-3.5">
                    <p class="text-[15px] font-semibold text-[#060D26]">No lease agreement yet</p>
                    <p class="mt-0.5 text-[13.5px] leading-snug text-[#7A5A1F]">Print it, have both of you sign it, then upload the signed copy.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="uploading = !uploading" class="{{ $ctaBtn }}">Upload agreement</button>
                        <button type="button" @click="agreementOpen = true; agreementLoaded = true" class="{{ $ghostBtn }} bg-white cursor-pointer">View &amp; print</button>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-[#E2E4EC] px-4 py-3.5">
                    <p class="text-[15px] font-semibold text-[#060D26]">Lease agreement <span class="text-[#15803D]">· Signed</span></p>
                    <p class="mt-0.5 text-[13.5px] leading-snug text-[#5B6A8E]">
                        @if($leaseStatus === 'uploaded')
                            Lease agreement uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}.
                        @else
                            {{ $tenant->first_name }} signed online on {{ $reservation->agreed_at->format('M j, Y') }}.
                        @endif
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="agreementOpen = true; agreementLoaded = true" class="{{ $ghostBtn }} cursor-pointer">View agreement</button>
                        @if($reservation->lease_file_path)
                            <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }}">Open agreement</a>
                        @endif
                        {{-- Signed online is the signed agreement: only a paper lease needs the signed copy uploaded. --}}
                        @if($leaseStatus !== 'signed_online')
                            <button type="button" @click="uploading = !uploading" class="{{ $ghostBtn }}">Replace agreement</button>
                        @endif
                    </div>
                </div>
            @endif

            <form x-show="uploading" x-cloak method="POST" action="{{ route('landlord.leases.upload', $reservation) }}" enctype="multipart/form-data"
                class="grid gap-2 rounded-xl bg-[#F7F8FC] p-4">
                @csrf
                {{-- Tells LeaseController::upload to come back to this modal, not the page underneath. --}}
                <input type="hidden" name="from" value="contracts">
                <label for="contract_lease_file_{{ $reservation->reservation_id }}" class="text-[13px] text-[#5B6A8E]">PDF, JPG or PNG, up to 10 MB</label>
                <input type="file" id="contract_lease_file_{{ $reservation->reservation_id }}" name="lease_file" required
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
                    class="block w-full text-[14px] text-[#060D26] file:mr-3 file:h-11 file:px-4 file:rounded-full file:border-0 file:bg-white file:text-[#060D26] file:font-semibold file:cursor-pointer">
                <div class="flex justify-end">
                    <button type="submit" class="{{ $ctaBtn }}">Upload</button>
                </div>
            </form>

            @if($showTenantDetails && ! $hasEmergency)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#FBBF24]/45 bg-[#FFF6E5] px-4 py-3.5">
                    <div class="min-w-0">
                        <p class="text-[15px] font-semibold text-[#060D26]">Emergency contact needed</p>
                        <p class="mt-0.5 text-[13.5px] leading-snug text-[#7A5A1F]">Add one for {{ $tenant->first_name }}.</p>
                    </div>
                    @if($landlordTenantId ?? null)
                        <a href="{{ route('landlord.tenants.index', ['edit' => $landlordTenantId]) }}" class="{{ $ghostBtn }} bg-white">Add contact</a>
                    @endif
                </div>
            @endif

            <section aria-labelledby="contract-lease">
                <h3 id="contract-lease" class="{{ $h3 }}">The stay</h3>
                <div class="mt-2 grid grid-cols-2 gap-2.5">
                    <div class="{{ $tile }}"><p class="{{ $label }}">Move-in</p><p class="mt-0.5 text-[15px] font-semibold text-[#060D26]">{{ $reservation->target_move_in_date ? $fmt($reservation->target_move_in_date) : 'Not set' }}</p></div>
                    <div class="{{ $tile }}"><p class="{{ $label }}">Move-out</p><p class="mt-0.5 text-[15px] font-semibold text-[#060D26]">{{ $reservation->target_move_out_date ? $fmt($reservation->target_move_out_date) : 'Not set' }}</p></div>
                </div>
                @if($reservation->target_move_in_date && $reservation->target_move_out_date)
                    <p class="mt-2 text-[13px] text-[#5B6A8E]">{{ $reservation->target_move_in_date->diffForHumans($reservation->target_move_out_date, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'join' => ', ']) }} in total</p>
                @endif
                @if($reservation->renewals->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach($reservation->renewals as $renewal)
                            <li class="text-[13px] text-[#5B6A8E]">Renewed to {{ $fmt($renewal->new_move_out_date) }} · ₱{{ number_format($renewal->new_monthly_rent, 2) }}/mo from {{ $renewal->rent_effective_from->format('M Y') }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="contract-tenant">
                <h3 id="contract-tenant" class="{{ $h3 }}">Tenant contact</h3>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <div class="{{ $tile }}">
                        <p class="{{ $label }}">Mobile</p>
                        <p class="mt-0.5 text-[15px] font-semibold text-[#060D26] break-words">
                            @if($tenant->contact_number)<a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="{{ $link }}">{{ $tenant->contact_number }}</a>@else<span class="font-normal text-[#5B6A8E]">Not added</span>@endif
                        </p>
                    </div>
                    <div class="{{ $tile }}">
                        <p class="{{ $label }}">Email</p>
                        <p class="mt-0.5 text-[15px] font-semibold text-[#060D26] break-all">
                            @if($tenant->email)<a href="mailto:{{ $tenant->email }}" class="{{ $link }}">{{ $tenant->email }}</a>@else<span class="font-normal text-[#5B6A8E]">Not added</span>@endif
                        </p>
                    </div>
                    @if($showTenantDetails)
                        <div class="{{ $tile }}">
                            <p class="{{ $label }}">ID</p>
                            <p class="mt-0.5 text-[15px] font-semibold text-[#060D26] break-words">
                                @if($detail?->id_type){{ $detail->id_type }} <span class="font-normal tabular-nums text-[#5B6A8E]">{{ $detail->maskedIdNumber() }}</span>@else<span class="font-normal text-[#5B6A8E]">Not added</span>@endif
                            </p>
                        </div>
                    @endif
                    @if($showTenantDetails && $hasEmergency)
                        <div class="{{ $tile }} sm:col-span-3">
                            <p class="{{ $label }}">Emergency contact</p>
                            <p class="mt-0.5 text-[15px] font-semibold text-[#060D26]">
                                {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) <span class="font-normal text-[#5B6A8E]">({{ $detail->emergency_contact_relationship }})</span>@endif
                                @if($detail->emergency_contact_number)
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $detail->emergency_contact_number) }}" class="ml-2 font-normal {{ $link }}">{{ $detail->emergency_contact_number }}</a>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </section>

            @if(($others ?? collect())->isNotEmpty())
                <section aria-labelledby="contract-others">
                    <h3 id="contract-others" class="{{ $h3 }}">Other leases with you</h3>
                    <ul class="mt-1 divide-y divide-[#E2E4EC]">
                        @foreach($others as $other)
                            <li>
                                <button type="button" @click="$dispatch('open-contract', { url: @js(route('landlord.contracts.show', $other)) })"
                                    class="w-full min-h-[44px] py-2.5 flex items-start justify-between gap-3 text-left hover:bg-[#F7F8FC] cursor-pointer">
                                    <span class="min-w-0">
                                        <span class="block text-[15px] text-[#060D26] truncate">{{ $other->property->title }}<span class="text-[#5B6A8E]"> · {{ $other->unit->unit_label ?? 'No unit' }}</span></span>
                                        <span class="block text-[13px] text-[#5B6A8E] tabular-nums">{{ $fmt($other->target_move_in_date) }} to {{ $fmt($other->target_move_out_date) }}</span>
                                    </span>
                                    <span class="shrink-0 text-[13px] text-[#5B6A8E]">{{ $other->contractStatus()['label'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>


{{-- Paid online through PayMongo and still held until move-in (see Reservation::moveInSettled()). One-line @php: this file already uses that form, and Blade cannot mix it with a block. --}}
@php($held = $reservation->rental_status === 'Rental Agreement Signed' ? $reservation->payments->firstWhere('status', 'Held') : null)

@if($held)
    <div class="mx-6 mb-4 rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
        <p class="text-[13px] font-semibold text-[#060D26]">₱{{ number_format((float) $held->amount, 2) }} paid online · held by AbangananHub</p>
        <p class="mt-1 text-[12.5px] text-[#5B6A8E]">
            @if($reservation->keys_turned_over_at)
                Keys handed over {{ $reservation->keys_turned_over_at->format('M j') }}. It is released to you when {{ $tenant->first_name }} confirms move-in{{ $reservation->move_in_deadline_at ? ', or automatically on '.$reservation->move_in_deadline_at->format('M j') : '' }}.
            @else
                Released to you when {{ $tenant->first_name }} confirms move-in. Hand over the keys, then mark them turned over so the confirmation clock starts.
            @endif
        </p>
    </div>
@endif

@if($reservation->rental_status === 'Occupied')
    @php($unpaid = (float) ($ledger['outstanding'] ?? 0))
    @php($depositLeft = (float) ($ledger['depositRemaining'] ?? 0))
    @php($unitName = $reservation->unit?->unit_label ?? 'The unit')
    {{-- End lease, in place (TenancyController::endTenancy). Unpaid rent is shown, never a reason to refuse. --}}
    <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}" x-ref="endForm" x-show="ending" x-cloak
        class="mx-6 mb-6 grid gap-4 rounded-xl bg-[#FEF2F2] p-4"
        data-confirm="End this lease?"
        data-confirm-message="{{ $unitName }} becomes available again and rent stops being billed for new months. This cannot be undone."
        data-confirm-button="End lease" data-confirm-type="warning">
        @csrf
        <input type="hidden" name="from" value="contracts">
        <div>
            <p class="text-[15px] font-semibold text-[#060D26]">End this lease</p>
            <p class="mt-1 text-[14px] leading-snug text-[#5B6A8E]">{{ $unitName }} goes back to your available units and no new months are billed. If rent is still owed you can keep collecting it, or stop collecting it, from Payments.</p>
        </div>

        <div class="sm:max-w-[260px]">
            <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5" for="end_date_{{ $reservation->reservation_id }}">Move-out date</label>
            <x-date-picker name="move_out_date" id="end_date_{{ $reservation->reservation_id }}"
                value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" />
        </div>

        @if($unpaid > 0)
            <p class="rounded-lg bg-[#FBBF24]/[0.16] px-3.5 py-2.5 text-[14px] leading-snug text-[#7A3E00]" role="note">
                <span class="font-semibold">₱{{ number_format($unpaid, 2) }} in rent is still unpaid.</span> You can still end the lease. The ledger keeps the balance so you can follow it up.
            </p>
        @endif

        @if($depositLeft > 0)
            <div x-data="{ returning: false }" class="grid gap-2 border-t border-[#EF4444]/15 pt-4">
                <p class="text-[14px] text-[#060D26]">
                    <span class="font-semibold">Security deposit:</span> ₱{{ number_format($depositLeft, 2) }} to return to {{ $tenant->first_name }}
                    @if(($ledger['depositCharged'] ?? 0) > 0)<span class="text-[#5B6A8E]">(after ₱{{ number_format($ledger['depositCharged'], 2) }} in charges)</span>@endif.
                </p>
                <label class="flex items-start gap-2.5 min-h-[44px] cursor-pointer">
                    <input type="checkbox" x-model="returning" class="mt-1 h-5 w-5 rounded border-[#5B6A8E]/40 text-[#FF8A66] focus:ring-[#FF8A66]/40">
                    <span class="text-[14px] text-[#060D26]">I have already returned the deposit<span class="block text-[13px] text-[#5B6A8E]">Leave this off to record it later from this lease.</span></span>
                </label>
                <div x-show="returning" x-cloak class="sm:max-w-[260px]">
                    <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5" for="dep_amt_{{ $reservation->reservation_id }}">Amount returned (₱)</label>
                    <input id="dep_amt_{{ $reservation->reservation_id }}" type="number" step="0.01" min="0" max="{{ $depositLeft }}" value="{{ $depositLeft }}"
                        x-bind:name="returning ? 'deposit_returned_amount' : null" x-bind:disabled="!returning"
                        class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 bg-white px-3.5 text-[15px] text-[#060D26] tabular-nums focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30">
                </div>
            </div>
        @endif

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button type="button" @click="ending = false" class="{{ $ghostBtn }} bg-white cursor-pointer">Keep lease</button>
            <button type="submit" class="inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#DC2626] text-[14px] font-semibold text-white hover:bg-[#B91C1C] transition-colors duration-200 cursor-pointer">End lease</button>
        </div>
    </form>
@endif

{{-- After the lease has ended: where the security deposit stands. The landlord's own record; no money moves here. --}}
@if($reservation->rental_status === 'Completed' && $deposit > 0)
    @php($depLeft = (float) ($ledger['depositRemaining'] ?? 0))
    <section class="mx-6 mb-6" aria-labelledby="deposit-return-title">
        <h3 id="deposit-return-title" class="text-[15px] font-semibold text-[#060D26]">Security deposit</h3>
        @if($reservation->deposit_returned_at)
            <p class="mt-1.5 rounded-xl bg-[#22C55E]/[0.08] px-4 py-3 text-[14px] text-[#14532D]">
                <span class="font-semibold">Returned.</span> ₱{{ number_format((float) $reservation->deposit_returned_amount, 2) }} went back to {{ $tenant->first_name }} on {{ $reservation->deposit_returned_at->format('M j, Y') }}.
            </p>
        @elseif($depLeft > 0)
            <form method="POST" action="{{ route('landlord.tenancies.depositReturn', $reservation) }}" class="mt-1.5 grid gap-3 rounded-xl bg-[#F7F8FC] p-4"
                data-confirm="Mark the deposit as returned?" data-confirm-message="This records that you gave ₱{{ number_format($depLeft, 2) }} back to {{ $tenant->first_name }}. It does not move any money."
                data-confirm-button="Mark as returned">
                @csrf
                <p class="text-[14px] text-[#060D26]">₱{{ number_format($depLeft, 2) }} still to return to {{ $tenant->first_name }}@if(($ledger['depositCharged'] ?? 0) > 0) <span class="text-[#5B6A8E]">(after ₱{{ number_format($ledger['depositCharged'], 2) }} in charges)</span>@endif.</p>
                <div class="grid gap-3 sm:grid-cols-2 sm:items-end">
                    <div>
                        <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5" for="dep_done_{{ $reservation->reservation_id }}">Amount returned (₱)</label>
                        <input id="dep_done_{{ $reservation->reservation_id }}" type="number" name="deposit_returned_amount" step="0.01" min="0" max="{{ $depLeft }}" value="{{ $depLeft }}" required
                            class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 bg-white px-3.5 text-[15px] text-[#060D26] tabular-nums focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30">
                    </div>
                    <button type="submit" class="{{ $ctaBtn }}">Mark as returned</button>
                </div>
            </form>
        @else
            <p class="mt-1.5 text-[14px] text-[#5B6A8E]">Nothing left to return. Charges used the whole deposit.</p>
        @endif
    </section>
@endif

@if($canRenew)
    {{-- Renew: offered within rentals.renewal_window_days of the end date, or once it has passed. --}}
    <form method="POST" action="{{ route('landlord.contracts.renew', $reservation) }}" x-ref="renewForm" x-show="renewing" x-cloak
        class="mx-6 mb-6 grid gap-3 rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
        @csrf
        <p class="text-[13px] font-semibold text-[#060D26]">Renew this lease</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5" for="renew_end_{{ $reservation->reservation_id }}">New end date</label>
                <x-date-picker name="new_move_out_date" id="renew_end_{{ $reservation->reservation_id }}"
                    :value="$reservation->target_move_out_date?->copy()->addYear()->toDateString()"
                    :min="($reservation->target_move_out_date ?? now())->copy()->addDay()->toDateString()" />
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5" for="renew_rent_{{ $reservation->reservation_id }}">Monthly rent (₱)</label>
                <input id="renew_rent_{{ $reservation->reservation_id }}" type="number" name="new_monthly_rent" min="0" step="0.01" required
                    value="{{ $reservation->currentRent() }}"
                    class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 bg-white px-3.5 text-[13.5px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">New rent starts</label>
                <x-styled-select name="rent_effective_from" :options="$renewMonths" :selected="$renewDefaultMonth"
                    class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 bg-white px-3.5 text-[13.5px] text-[#060D26] text-left" />
                <p class="mt-1 text-[12px] text-[#5B6A8E]">Months before this keep ₱{{ number_format($reservation->currentRent(), 2) }}.</p>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" @click="renewing = false" class="{{ $ghostBtn }}">Cancel</button>
            <button type="submit" class="{{ $ctaBtn }}">Save renewal</button>
        </div>
    </form>
@endif

        {{-- Actions. The risky one (End lease, Cancel lease) sits on the left as plain text; the rest on the right. --}}
        <div class="sticky bottom-0 z-10 mt-auto flex flex-wrap items-center gap-2 border-t border-[#E2E4EC] bg-white px-6 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            @if($reservation->rental_status === 'Occupied')
                <button type="button" x-show="!ending"
                    @click="ending = true; renewing = false; $nextTick(() => $refs.endForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
                    class="h-11 px-3 -ml-3 rounded-full text-[14px] font-semibold text-[#B91C1C] hover:bg-[#EF4444]/[0.07] transition-colors duration-200 cursor-pointer">End lease</button>
            @elseif($reservation->rental_status === 'Reserved')
                <form method="POST" action="{{ route('landlord.reservations.cancel', $reservation) }}"
                    data-confirm="Cancel this lease?" data-confirm-type="warning"
                    data-confirm-message="{{ $tenant->first_name }} has not moved in yet. The unit goes back to Available." data-confirm-button="Cancel lease" data-confirm-cancel="Keep it">
                    @csrf @method('PATCH')
                    <button type="submit" class="h-11 px-3 -ml-3 rounded-full text-[14px] font-semibold text-[#B91C1C] hover:bg-[#EF4444]/[0.07] transition-colors duration-200 cursor-pointer">Cancel lease</button>
                </form>
            @endif
            @if($canInvite ?? false)
                <form method="POST" action="{{ $inviteUrl }}">
                    @csrf
                    <button type="submit" class="{{ $ghostBtn }} cursor-pointer">Resend invite</button>
                </form>
            @endif

            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                @if($reservation->rental_status === 'Rental Agreement Signed' && ! $held && ! $reservation->moveInSettled())
                    <p class="text-[12.5px] text-[#5B6A8E] max-w-[26ch]">Waiting for {{ $tenant->first_name }}'s move-in payment. Confirm it from the chat once it reaches your account.</p>
                @endif

                {{-- Rating the tenant lives with the lease (it used to be a Reservations row action). --}}
                @if(in_array($reservation->rental_status, ['Occupied', 'Completed'], true))
                    @if($reservation->tenantRating)
                        <span class="inline-flex items-center gap-1 h-11 px-3 rounded-full bg-[#22C55E]/[0.07] text-[#15803D] text-[13px] font-semibold">Rated {{ $reservation->tenantRating->rating }}/5</span>
                    @else
                        <button type="button" @click="rate(true)" class="{{ $ghostBtn }} cursor-pointer">Rate tenant</button>
                    @endif
                @endif

                @if(in_array($reservation->rental_status, ['Occupied', 'Completed', 'Reserved'], true)
                    || ($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled()))
                    <a href="{{ route('landlord.payments.ledger', $reservation) }}" class="{{ $ghostBtn }}">Open ledger</a>
                @endif

                @if($reservation->rental_status === 'Reserved')
                    <form method="POST" action="{{ route('landlord.reservations.confirmWalkInMoveIn', $reservation) }}"
                        data-confirm="Confirm move-in?" data-confirm-message="The unit will be marked Occupied and rent starts." data-confirm-button="Confirm move-in">
                        @csrf
                        <button type="submit" class="{{ $ctaBtn }}">Confirm move-in</button>
                    </form>
                @elseif($held)
                    {{-- Escrow: the tenant's confirmation (or the deadline job, or an admin) releases the money, not this modal. --}}
                    @if($reservation->conversation_id)
                        <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ghostBtn }}">Open chat</a>
                    @endif
                    @if(! $reservation->keys_turned_over_at && ! $reservation->move_in_disputed_at)
                        <form method="POST" action="{{ route('landlord.reservations.markTurnedOver', $reservation) }}"
                            data-confirm="Mark the keys as turned over?"
                            data-confirm-message="{{ $tenant->first_name }} gets {{ config('rentals.move_in_confirmation_days') }} days to confirm move-in. After that the payment is released to you automatically."
                            data-confirm-button="Keys turned over">
                            @csrf
                            <button type="submit" class="{{ $ctaBtn }}">Mark keys turned over</button>
                        </form>
                    @endif
                @elseif($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled())
                    <form method="POST" action="{{ route('landlord.reservations.confirmDirectMoveIn', $reservation) }}"
                        data-confirm="Confirm move-in?" data-confirm-message="The unit will be marked Occupied and rent starts." data-confirm-button="Confirm move-in">
                        @csrf
                        <button type="submit" class="{{ $ctaBtn }}">Confirm move-in</button>
                    </form>
                @elseif($reservation->rental_status === 'Rental Agreement Signed')
                    @if($reservation->conversation_id)
                        <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ctaBtn }}">Open chat</a>
                    @endif
                @elseif($reservation->rental_status === 'Occupied' && $canRenew)
                    <button type="button" @click="renewing = true; ending = false; $nextTick(() => $refs.renewForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" x-show="!renewing" class="{{ $ctaBtn }}">Renew</button>
                @endif
            </div>
        </div>
    </div>
</div>

</div>

@if($canRate)
    {{-- Rate tenant: replaces the contract view inside the same modal; Back returns to it. --}}
    <form x-show="rating" x-cloak method="POST" action="{{ route('landlord.reservations.rateTenant.store', $reservation) }}">
        @csrf
        <div class="px-6 pt-6 pb-5 border-b border-[#E2E4EC] pr-14">
            <button type="button" @click="rate(false)"
                class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back
            </button>
            <h2 class="mt-2 text-[18px] font-normal text-[#060D26]">Rate {{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
            <p class="mt-1 text-[12.5px] text-[#5B6A8E]">{{ $reservation->property->title }} · {{ $reservation->unit->unit_label ?? 'No unit' }}. Other landlords see this when {{ $tenant->first_name }} applies.</p>
        </div>

        <div class="px-6 py-5 grid gap-5">
            <fieldset>
                <legend class="text-[12px] font-semibold text-[#060D26] mb-2">Rating</legend>
                <div class="flex items-center gap-1" @mouseleave="hovered = 0">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" @click="stars = {{ $i }}" @mouseenter="hovered = {{ $i }}"
                            :aria-pressed="stars === {{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                            class="w-11 h-11 -ml-1.5 first:ml-0 flex items-center justify-center rounded-lg cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40">
                            <svg width="30" height="30" viewBox="0 0 24 24" stroke-width="1.5"
                                :fill="(hovered || stars) >= {{ $i }} ? '#FBBF24' : 'none'"
                                :stroke="(hovered || stars) >= {{ $i }} ? '#FBBF24' : '#C5CADB'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-[13px] font-semibold text-[#060D26] tabular-nums" x-show="stars > 0" x-text="stars + '/5'"></span>
                </div>
                <input type="hidden" name="rating" :value="stars">
            </fieldset>

            <div>
                <label for="rate_comment_{{ $reservation->reservation_id }}" class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                    Comment <span class="font-normal text-[#5B6A8E]">(optional)</span>
                </label>
                <textarea id="rate_comment_{{ $reservation->reservation_id }}" name="comment" rows="4" maxlength="1000"
                    @input="count = $el.value.length" placeholder="How was it renting to this tenant?"
                    class="w-full rounded-xl border border-[#5B6A8E]/30 bg-white px-3.5 py-3 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E]/60 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 resize-none"></textarea>
                <p class="mt-1 text-right text-[11px] text-[#5B6A8E] tabular-nums"><span x-text="count"></span>/1000</p>
            </div>
        </div>

        <div class="px-6 pb-6 flex items-center justify-end gap-2">
            <button type="button" @click="rate(false)" class="{{ $ghostBtn }} cursor-pointer">Cancel</button>
            <button type="submit" :disabled="stars === 0" class="{{ $ctaBtn }} disabled:opacity-40 disabled:cursor-not-allowed">Submit rating</button>
        </div>
    </form>
@endif

</div>
