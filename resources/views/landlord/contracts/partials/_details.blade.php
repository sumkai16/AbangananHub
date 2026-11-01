{{-- The view-contract modal body (fetched by _view-modal from ContractController::show). --}}
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
    $row = 'flex items-start justify-between gap-4 py-2 border-b border-[#E2E4EC] last:border-0';
    $dt = 'text-[12.5px] text-[#5B6A8E] shrink-0';
    $dd = 'text-[13px] font-semibold text-[#060D26] text-right';
    $ghostBtn = 'inline-flex items-center justify-center h-10 px-4 rounded-full border border-[#5B6A8E]/30 text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200';
    $ctaBtn = 'inline-flex items-center justify-center h-10 px-5 rounded-full bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer';

    // Renew is offered on a running contract near or past its end (the same window as the "Ends in N days" pill).
    $canRenew = $reservation->rental_status === 'Occupied' && in_array($status['tone'], ['warn', 'bad'], true);
    $renewMonths = collect(range(0, 12))->mapWithKeys(fn ($i) => [
        now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m') => now()->startOfMonth()->addMonthsNoOverflow($i)->format('F Y'),
    ])->all();
    // Default: the month after the current end, or this month when the end has already passed.
    $afterEnd = $reservation->target_move_out_date?->copy()->addDay()->startOfMonth();
    $renewDefaultMonth = ($afterEnd && $afterEnd->gte(now()->startOfMonth()) ? $afterEnd : now())->format('Y-m');
    $canRate = in_array($reservation->rental_status, ['Occupied', 'Completed'], true) && ! $reservation->tenantRating;
@endphp

<div x-data="{ renewing: false, ending: false, uploading: false, agreementOpen: false, agreementLoaded: false, agreementReady: false, rating: @js($canRate && ($startRating ?? false)), stars: 0, hovered: 0, count: 0,
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
                    <h2 id="agreement-title-{{ $reservation->reservation_id }}" class="text-[16px] font-semibold text-[#060D26] leading-tight">Rental agreement</h2>
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
                <p x-show="!agreementReady" class="absolute inset-0 flex items-center justify-center text-[13px] text-[#5B6A8E]">Loading the agreement…</p>
                <template x-if="agreementLoaded">
                    <iframe x-ref="agreementFrame" src="{{ route('landlord.leases.show', ['reservation' => $reservation, 'embed' => 1]) }}"
                        title="Rental agreement" class="w-full h-full border-0" @load="agreementReady = true"></iframe>
                </template>
            </div>
        </div>
    </div>
</template>

<div x-show="!rating">

@php
    // How far through the lease term today is, for the progress bar (running leases with both dates only).
    $start = $reservation->target_move_in_date;
    $end = $reservation->target_move_out_date;
    $progress = $reservation->rental_status === 'Occupied' && $start && $end && $end->gt($start)
        ? (int) round(max(0, min(1, $start->diffInDays(now(), false) / max(1, $start->diffInDays($end)))) * 100)
        : null;
    $bar = ['ok' => 'bg-[#22C55E]', 'warn' => 'bg-[#F59E0B]', 'bad' => 'bg-[#EF4444]', 'muted' => 'bg-[#94A3B8]'][$status['tone']];
    $tile = 'rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-3';
    $tileLabel = 'text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]';
    $tileValue = 'mt-0.5 text-[15px] font-semibold text-[#060D26] tabular-nums';
    $label = 'text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]';
    $showLedger = in_array($reservation->rental_status, ['Occupied', 'Completed', 'Reserved'], true)
        || ($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled());
    $leaseStatus = $reservation->leaseStatus();
@endphp

{{-- Header: who, where, and how the lease stands --}}
<div class="px-5 sm:px-6 pt-6 pb-5 pr-14 border-b border-[#E2E4EC]">
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill }}">{{ $status['label'] }}</span>
        @if($code)<span class="inline-flex items-center h-6 px-2 rounded-md bg-[#ECEEF6] text-[11px] font-semibold text-[#5B6A8E] tabular-nums">{{ $code }}</span>@endif
        @if($reference)<span class="inline-flex items-center h-6 px-2 rounded-md bg-[#ECEEF6] text-[11px] font-semibold text-[#5B6A8E] tabular-nums">{{ $reference }}</span>@endif
        @if($tenant->is_walk_in)<span class="text-[11.5px] text-[#5B6A8E]">Added by you</span>@endif
    </div>
    <h2 class="mt-2.5 text-[20px] font-semibold leading-tight text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">
        <span class="font-semibold text-[#060D26]">{{ $reservation->unit->unit_label ?? 'No unit' }}</span> · {{ $reservation->property->title }}
    </p>

    {{-- Term: the dates on one line, how far through it today is --}}
    <div class="mt-4">
        <div class="flex items-baseline justify-between gap-3 text-[13px]">
            <span class="font-semibold text-[#060D26] tabular-nums">{{ $fmt($start) }} <span class="font-normal text-[#5B6A8E]">→</span> {{ $end ? $fmt($end) : 'Open-ended' }}</span>
            @if($progress !== null)<span class="text-[12px] text-[#5B6A8E] tabular-nums">{{ $progress }}%</span>@endif
        </div>
        @if($progress !== null)
            <div class="mt-2 h-1.5 rounded-full bg-[#ECEEF6] overflow-hidden" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Lease term elapsed">
                <div class="h-full rounded-full {{ $bar }}" style="width: {{ $progress }}%"></div>
            </div>
        @endif
    </div>
</div>

<div class="px-5 sm:px-6 py-5 grid gap-6">
    {{-- The money and the terms, at a glance --}}
    <section aria-label="Lease terms">
        <dl class="grid grid-cols-2 gap-2.5">
            <div class="{{ $tile }}"><dt class="{{ $tileLabel }}">Monthly rent</dt><dd class="{{ $tileValue }}">₱{{ number_format($reservation->currentRent(), 2) }}</dd></div>
            <div class="{{ $tile }}"><dt class="{{ $tileLabel }}">Deposit</dt><dd class="{{ $tileValue }}">{{ $deposit > 0 ? '₱'.number_format($deposit, 2) : 'None' }}</dd></div>
            <div class="{{ $tile }}"><dt class="{{ $tileLabel }}">Rent due</dt><dd class="{{ $tileValue }}">Day {{ $reservation->rentDueDay() }} <span class="text-[12px] font-normal text-[#5B6A8E]">monthly</span></dd></div>
            <div class="{{ $tile }}"><dt class="{{ $tileLabel }}">Occupants</dt><dd class="{{ $tileValue }}">{{ $reservation->occupants_count ?: '—' }}</dd></div>
        </dl>
        @if($reservation->renewals->isNotEmpty())
            <ul class="mt-3 space-y-1">
                @foreach($reservation->renewals as $renewal)
                    <li class="text-[12px] text-[#5B6A8E]">Renewed to {{ $fmt($renewal->new_move_out_date) }} · ₱{{ number_format($renewal->new_monthly_rent, 2) }}/mo from {{ $renewal->rent_effective_from->format('M Y') }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- The agreement: view or print it, and the signed copy to open or upload. --}}
    <section aria-labelledby="contract-documents">
        <h3 id="contract-documents" class="{{ $label }} mb-2">Agreement</h3>
        <div class="rounded-2xl border p-4 grid gap-3 {{ $leaseStatus === 'missing' ? 'border-[#FBBF24]/40 bg-[#FBBF24]/[0.06]' : 'border-[#E2E4EC] bg-[#F7F8FC]' }}">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 w-8 h-8 shrink-0 rounded-full flex items-center justify-center {{ $leaseStatus === 'missing' ? 'bg-[#FBBF24]/20 text-[#B45309]' : 'bg-[#22C55E]/12 text-[#15803D]' }}" aria-hidden="true">
                    @if($leaseStatus === 'missing')
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    @else
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                        {{ ['missing' => 'Agreement missing', 'uploaded' => 'Signed copy uploaded', 'signed_online' => 'Signed online'][$leaseStatus] }}
                    </p>
                    <p class="text-[12.5px] text-[#5B6A8E]">
                        @if($leaseStatus === 'uploaded')
                            Uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}.
                        @elseif($leaseStatus === 'signed_online')
                            {{ $tenant->first_name }} signed it {{ $reservation->agreed_at->format('M j, Y') }}.
                        @else
                            Print it, both of you sign it, then upload the signed copy.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="agreementOpen = true; agreementLoaded = true" class="{{ $ghostBtn }} bg-white cursor-pointer">
                    {{ $leaseStatus === 'missing' ? 'View & print agreement' : 'View agreement' }}
                </button>
                @if($reservation->lease_file_path)
                    <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }} bg-white">Open signed copy</a>
                @endif
                <button type="button" @click="uploading = !uploading"
                    class="{{ $leaseStatus === 'missing' ? $ctaBtn : $ghostBtn.' bg-white cursor-pointer' }}">
                    {{ $reservation->lease_file_path ? 'Replace signed copy' : 'Upload signed copy' }}
                </button>
            </div>

            <form x-show="uploading" x-cloak method="POST" action="{{ route('landlord.leases.upload', $reservation) }}" enctype="multipart/form-data"
                class="grid gap-2 pt-3 border-t border-[#E2E4EC]">
                @csrf
                {{-- Tells LeaseController::upload to come back to this modal, not the page underneath. --}}
                <input type="hidden" name="from" value="contracts">
                <label for="contract_lease_file_{{ $reservation->reservation_id }}" class="text-[12px] font-semibold text-[#5B6A8E]">PDF, JPG or PNG · up to 10 MB</label>
                <input type="file" id="contract_lease_file_{{ $reservation->reservation_id }}" name="lease_file" required
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
                    class="block w-full text-[13px] text-[#060D26] file:mr-3 file:h-9 file:px-3 file:rounded-lg file:border-0 file:bg-[#ECEEF6] file:text-[#060D26] file:font-semibold file:cursor-pointer">
                <div class="flex justify-end">
                    <button type="submit" class="{{ $ctaBtn }}">Upload</button>
                </div>
            </form>
        </div>
    </section>

    {{-- The tenant: tap to call or email --}}
    <section aria-labelledby="contract-tenant">
        <h3 id="contract-tenant" class="{{ $label }} mb-2">Tenant</h3>
        <div class="grid gap-2">
            @if($tenant->contact_number)
                <a href="tel:{{ preg_replace('/\s+/', '', $tenant->contact_number) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 -mx-3 hover:bg-[#F7F8FC] transition-colors duration-150">
                    <svg class="shrink-0 text-[#5B6A8E]" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                    <span class="text-[13.5px] font-semibold text-[#060D26] tabular-nums">{{ $tenant->contact_number }}</span>
                </a>
            @endif
            @if($tenant->email)
                <a href="mailto:{{ $tenant->email }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 -mx-3 hover:bg-[#F7F8FC] transition-colors duration-150 min-w-0">
                    <svg class="shrink-0 text-[#5B6A8E]" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                    <span class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $tenant->email }}</span>
                </a>
            @endif
            @if(! $tenant->contact_number && ! $tenant->email)
                <p class="text-[13px] text-[#5B6A8E]">No phone or email on file.</p>
            @endif

            @if($showTenantDetails)
                <dl class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div class="{{ $tile }}">
                        <dt class="{{ $tileLabel }}">Emergency contact</dt>
                        <dd class="mt-0.5 text-[13.5px] font-semibold text-[#060D26]">
                            @if($detail?->emergency_contact_name)
                                {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) <span class="font-normal text-[#5B6A8E]">({{ $detail->emergency_contact_relationship }})</span>@endif
                                <span class="block font-normal text-[12.5px] text-[#5B6A8E] tabular-nums">{{ $detail->emergency_contact_number }}</span>
                            @else
                                <span class="font-normal text-[#5B6A8E]">Not on file</span>
                            @endif
                        </dd>
                    </div>
                    <div class="{{ $tile }}">
                        <dt class="{{ $tileLabel }}">ID</dt>
                        <dd class="mt-0.5 text-[13.5px] font-semibold text-[#060D26] tabular-nums">
                            @if($detail?->id_type){{ $detail->id_type }} <span class="font-normal text-[#5B6A8E]">{{ $detail->maskedIdNumber() }}</span>@else<span class="font-normal text-[#5B6A8E]">Not on file</span>@endif
                        </dd>
                    </div>
                </dl>
            @endif
        </div>
    </section>

    {{-- Paid online through PayMongo and still held until move-in (see Reservation::moveInSettled()). One-line @php: this file already uses that form, and Blade cannot mix it with a block. --}}
    @php($held = $reservation->rental_status === 'Rental Agreement Signed' ? $reservation->payments->firstWhere('status', 'Held') : null)

    @if($held)
        <div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
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
        {{-- End lease: closes the tenancy and returns the unit to the available pool (TenancyController::endTenancy). --}}
        <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}" x-show="ending" x-cloak
            class="grid gap-3 rounded-2xl border border-[#EF4444]/25 bg-[#EF4444]/[0.04] p-4"
            data-confirm="End this lease?"
            data-confirm-message="{{ $reservation->unit?->unit_label ?? 'The unit' }} becomes available again and no further payments can be recorded against this ledger. This cannot be undone."
            data-confirm-button="End lease"
            data-confirm-type="warning">
            @csrf
            <p class="text-[13px] font-semibold text-[#060D26]">End this lease</p>
            <p class="-mt-1.5 text-[12.5px] leading-relaxed text-[#5B6A8E]">
                Returns <strong class="text-[#060D26]">{{ $reservation->unit?->unit_label ?? 'the unit' }}</strong> to your available units.
                The ledger stays readable but stops accepting new payments.
            </p>
            <div class="sm:max-w-[260px]">
                <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5" for="end_date_{{ $reservation->reservation_id }}">Move-out date</label>
                <x-date-picker name="move_out_date" id="end_date_{{ $reservation->reservation_id }}"
                    value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" />
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="ending = false" class="{{ $ghostBtn }} cursor-pointer">Cancel</button>
                <button type="submit" class="inline-flex items-center justify-center h-10 px-5 rounded-full bg-[#DC2626] text-[13px] font-semibold text-white hover:bg-[#B91C1C] transition-colors duration-200 cursor-pointer">End lease</button>
            </div>
        </form>
    @endif

    @if($canRenew)
        {{-- Renew: offered within rentals.renewal_window_days of the end date, or once it has passed. --}}
        <form method="POST" action="{{ route('landlord.contracts.renew', $reservation) }}" x-show="renewing" x-cloak
            class="grid gap-3 rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
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
                <button type="button" @click="renewing = false" class="{{ $ghostBtn }} cursor-pointer">Cancel</button>
                <button type="submit" class="{{ $ctaBtn }}">Save renewal</button>
            </div>
        </form>
    @endif
</div>

{{-- Actions: stays at the bottom of the modal while the details scroll. Records on the left, the lease's next step on the right. --}}
<div class="sticky bottom-0 z-10 flex flex-wrap items-center gap-2 border-t border-[#E2E4EC] bg-white px-5 sm:px-6 py-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))]">
    @if($showLedger)
        <a href="{{ route('landlord.payments.ledger', $reservation) }}" class="{{ $ghostBtn }}">Open ledger</a>
    @endif

    {{-- Rating the tenant lives with the contract now (it used to be a Reservations row action). --}}
    @if(in_array($reservation->rental_status, ['Occupied', 'Completed'], true))
        @if($reservation->tenantRating)
            <span class="inline-flex items-center gap-1 h-10 px-3 rounded-full bg-[#22C55E]/[0.07] text-[#15803D] text-[13px] font-semibold">Rated {{ $reservation->tenantRating->rating }}/5</span>
        @else
            <button type="button" @click="rate(true)" class="{{ $ghostBtn }} cursor-pointer">Rate tenant</button>
        @endif
    @endif

    <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
        @if($reservation->rental_status === 'Reserved')
            <form method="POST" action="{{ route('landlord.reservations.cancel', $reservation) }}"
                data-confirm="Cancel this lease?" data-confirm-type="warning"
                data-confirm-message="{{ $tenant->first_name }} has not moved in yet. The unit goes back to Available." data-confirm-button="Cancel lease" data-confirm-cancel="Keep it">
                @csrf @method('PATCH')
                <button type="submit" class="inline-flex items-center justify-center h-10 px-3 rounded-full text-[13px] font-semibold text-[#B91C1C] hover:bg-[#EF4444]/[0.07] transition-colors duration-200 cursor-pointer">Cancel lease</button>
            </form>
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
            <p class="text-[12.5px] text-[#5B6A8E]">Waiting for {{ $tenant->first_name }}'s move-in payment.</p>
            @if($reservation->conversation_id)
                <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ctaBtn }}">Open chat</a>
            @endif
        @elseif($reservation->rental_status === 'Occupied')
            <button type="button" @click="ending = true; renewing = false; $nextTick(() => $root.closest('.overflow-y-auto')?.scrollTo({ top: 99999, behavior: 'smooth' }))" x-show="!ending"
                class="inline-flex items-center justify-center h-10 px-3 rounded-full text-[13px] font-semibold text-[#B91C1C] hover:bg-[#EF4444]/[0.07] transition-colors duration-200 cursor-pointer">End lease</button>
            @if($canRenew)
                <button type="button" @click="renewing = true; ending = false; $nextTick(() => $root.closest('.overflow-y-auto')?.scrollTo({ top: 99999, behavior: 'smooth' }))" x-show="!renewing" class="{{ $ctaBtn }}">Renew</button>
            @endif
        @endif
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
                Back to lease
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
