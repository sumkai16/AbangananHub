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
    $ghostBtn = 'inline-flex items-center justify-center h-11 px-5 rounded-full border border-[#060D26]/35 bg-white text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200';
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
    // Footer buttons: square, 40px, firm border (one-line @php forms below cannot share a block with this).
    $ftBase = 'inline-flex items-center justify-center h-10 px-4 rounded-full text-[13.5px] font-semibold whitespace-nowrap transition-colors duration-200 cursor-pointer';
    $ftGhost = $ftBase.' border border-[#060D26]/35 bg-white text-[#060D26] hover:bg-[#ECEEF6]';
    $ftCta = $ftBase.' bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F]';
    $ftNavy = $ftBase.' bg-[#060D26] text-white hover:bg-[#1A2347]';
    $ftDanger = $ftBase.' border border-[#DC2626]/50 bg-white text-[#B91C1C] hover:bg-[#FEF2F2]';
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
                <p x-show="!agreementReady" class="absolute inset-0 flex items-center justify-center text-[13px] text-[#5B6A8E]">Loading the rental agreement...</p>
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
    $rw = 'flex items-start justify-between gap-4 py-2.5 border-b border-[#E2E4EC] last:border-b-0';
    $dt = 'text-[13px] text-[#5B6A8E] shrink-0';
    $dd = 'text-[13.5px] font-semibold text-[#060D26] text-right min-w-0 break-words';
    $none = 'font-normal text-[#5B6A8E]';
    $inset = 'mt-2 rounded-xl bg-[#F7F8FC] px-4 py-1';
    $head = 'text-[13.5px] font-bold text-[#060D26]';
    $banner = 'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#F59E0B]/50 bg-[#FFF6E5] px-3.5 py-2.5 text-[13px] text-[#7A3E00]';
@endphp
{{-- One column, like a form sheet: title and status, a hairline, then label-left / value-right rows. The close button belongs to the modal shell. --}}
<div>
<div class="min-w-0 flex flex-col">
    <div class="px-6 pt-5 pb-4 pr-16 border-b border-[#E2E4EC]">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <h2 class="text-[18px] font-bold leading-tight text-[#060D26]">Rental details</h2>
            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $pill }}">{{ $status['label'] }}</span>
        </div>
        <p class="mt-1 text-[13px] text-[#5B6A8E]">{{ $tenant->first_name }} {{ $tenant->last_name }} · {{ $reservation->unit->unit_label ?? 'No unit' }} · {{ $reservation->property->title }}</p>
    </div>

    <div class="grid gap-5 px-6 py-4">
        {{-- What needs doing comes first, as a plain banner. --}}
        @if($leaseStatus === 'missing')
            <div class="{{ $banner }}" role="note">
                <span><span class="font-semibold">No rental agreement yet.</span> Print it, sign it together, then upload the signed copy.</span>
            </div>
        @endif
        @if($showTenantDetails && ! $hasEmergency)
            <div class="{{ $banner }}" role="note">
                <span><span class="font-semibold">Emergency contact needed.</span> Add one for {{ $tenant->first_name }}.</span>
                @if($landlordTenantId ?? null)
                    <a href="{{ route('landlord.tenants.index', ['edit' => $landlordTenantId]) }}" class="font-semibold underline underline-offset-2">Add contact</a>
                @endif
            </div>
        @endif

        <section aria-labelledby="contract-lease">
            <h3 id="contract-lease" class="{{ $head }}">The rental</h3>
            <dl class="mt-1">
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Monthly rent</dt><dd class="{{ $dd }} tabular-nums">₱{{ number_format($reservation->currentRent(), 2) }}</dd></div>
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Due</dt><dd class="{{ $dd }}">Every {{ $dueOrdinal }} of the month</dd></div>
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Security deposit</dt><dd class="{{ $dd }} tabular-nums">{{ $deposit > 0 ? '₱'.number_format($deposit, 2) : 'None' }}</dd></div>
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Move-in</dt><dd class="{{ $dd }}">{{ $reservation->target_move_in_date ? $fmt($reservation->target_move_in_date) : 'Not set' }}</dd></div>
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Move-out</dt><dd class="{{ $dd }}">{{ $reservation->target_move_out_date ? $fmt($reservation->target_move_out_date) : 'Not set' }}</dd></div>
                @if($reservation->target_move_in_date && $reservation->target_move_out_date)
                    <div class="{{ $rw }}"><dt class="{{ $dt }}">Length</dt><dd class="{{ $dd }}">{{ $reservation->target_move_in_date->diffForHumans($reservation->target_move_out_date, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'join' => ', ']) }}</dd></div>
                @endif
                <div class="{{ $rw }}"><dt class="{{ $dt }}">Occupants</dt><dd class="{{ $dd }}">{{ $reservation->occupants_count ?: 1 }}</dd></div>
                @if($code)<div class="{{ $rw }}"><dt class="{{ $dt }}">Tenant no.</dt><dd class="{{ $dd }} tabular-nums">{{ $code }}</dd></div>@endif
                @if($reference)<div class="{{ $rw }}"><dt class="{{ $dt }}">Rental no.</dt><dd class="{{ $dd }} tabular-nums">{{ $reference }}</dd></div>@endif
            </dl>
            @if($reservation->renewals->isNotEmpty())
                <div class="{{ $inset }} py-2.5">
                    @foreach($reservation->renewals as $renewal)
                        <p class="text-[13px] text-[#5B6A8E]">Renewed to {{ $fmt($renewal->new_move_out_date) }} · ₱{{ number_format($renewal->new_monthly_rent, 2) }}/mo from {{ $renewal->rent_effective_from->format('M Y') }}</p>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="contract-agreement">
            <h3 id="contract-agreement" class="{{ $head }}">Rental agreement</h3>
            {{-- One compact row: the status on the left, the buttons on the right (they wrap under it on a narrow phone). --}}
            @php
                $sqGhost = 'inline-flex items-center justify-center h-9 px-3.5 rounded-full border border-[#060D26]/35 bg-white text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer whitespace-nowrap';
                $sqCta = 'inline-flex items-center justify-center h-9 px-3.5 rounded-full bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer whitespace-nowrap';
            @endphp
            <div class="{{ $inset }} flex flex-wrap items-center justify-between gap-x-4 gap-y-2.5 py-3">
                <div class="min-w-0">
                    <p class="{{ $dt }}">Status</p>
                    <p class="mt-0.5 text-[13.5px] font-semibold">
                        @if($leaseStatus === 'missing')<span class="text-[#B45309]">Missing</span>
                        @elseif($leaseStatus === 'uploaded')<span class="text-[#15803D]">Uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}</span>
                        @else<span class="text-[#15803D]">Signed online {{ $reservation->agreed_at->format('M j, Y') }}</span>@endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 ml-auto">
                    @if($leaseStatus === 'missing')
                        <button type="button" @click="agreementOpen = true; agreementLoaded = true" class="{{ $sqGhost }}">View &amp; print</button>
                        <button type="button" @click="uploading = !uploading" class="{{ $sqCta }}">Upload agreement</button>
                    @else
                        <button type="button" @click="agreementOpen = true; agreementLoaded = true" class="{{ $sqGhost }}">View agreement</button>
                        @if($reservation->lease_file_path)
                            <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener" class="{{ $sqGhost }}">Open agreement</a>
                        @endif
                        {{-- Signed online is the signed agreement: only a paper rental needs the signed copy uploaded. --}}
                        @if($leaseStatus !== 'signed_online')
                            <button type="button" @click="uploading = !uploading" class="{{ $sqGhost }}">Replace agreement</button>
                        @endif
                    @endif
                </div>
            </div>

            <form x-show="uploading" x-cloak method="POST" action="{{ route('landlord.leases.upload', $reservation) }}" enctype="multipart/form-data"
                class="mt-2 grid gap-2 rounded-xl border border-[#E2E4EC] p-4">
                @csrf
                {{-- Tells LeaseController::upload to come back to this modal, not the page underneath. --}}
                <input type="hidden" name="from" value="contracts">
                <label for="contract_lease_file_{{ $reservation->reservation_id }}" class="text-[13px] text-[#5B6A8E]">PDF, JPG or PNG, up to 10 MB</label>
                <input type="file" id="contract_lease_file_{{ $reservation->reservation_id }}" name="lease_file" required
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
                    class="block w-full text-[14px] text-[#060D26] file:mr-3 file:h-11 file:px-4 file:rounded-full file:border-0 file:bg-[#ECEEF6] file:text-[#060D26] file:font-semibold file:cursor-pointer">
                <div class="flex justify-end">
                    <button type="submit" class="{{ $sqCta }}">Upload</button>
                </div>
            </form>
        </section>

        <section aria-labelledby="contract-tenant">
            <h3 id="contract-tenant" class="{{ $head }}">Tenant</h3>
            <div class="{{ $inset }}">
                <dl>
                    <div class="{{ $rw }}"><dt class="{{ $dt }}">Name</dt><dd class="{{ $dd }}">{{ $tenant->first_name }} {{ $tenant->last_name }}</dd></div>
                    <div class="{{ $rw }}"><dt class="{{ $dt }}">Mobile</dt><dd class="{{ $dd }}">@if($tenant->contact_number)<a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="{{ $link }}">{{ $tenant->contact_number }}</a>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                    <div class="{{ $rw }}"><dt class="{{ $dt }}">Email</dt><dd class="{{ $dd }} break-all">@if($tenant->email)<a href="mailto:{{ $tenant->email }}" class="{{ $link }}">{{ $tenant->email }}</a>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                    @if($showTenantDetails)
                        <div class="{{ $rw }}"><dt class="{{ $dt }}">ID</dt><dd class="{{ $dd }}">@if($detail?->id_type){{ $detail->id_type }} <span class="{{ $none }} tabular-nums">{{ $detail->maskedIdNumber() }}</span>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                    @endif
                    @if($showTenantDetails && $hasEmergency)
                        <div class="{{ $rw }}">
                            <dt class="{{ $dt }}">Emergency contact</dt>
                            <dd class="{{ $dd }}">
                                {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) <span class="{{ $none }}">({{ $detail->emergency_contact_relationship }})</span>@endif
                                @if($detail->emergency_contact_number)<a href="tel:{{ preg_replace('/[^\d+]/', '', $detail->emergency_contact_number) }}" class="block {{ $none }} {{ $link }}">{{ $detail->emergency_contact_number }}</a>@endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        @if(($others ?? collect())->isNotEmpty())
            <section aria-labelledby="contract-others">
                <h3 id="contract-others" class="{{ $head }}">Other rentals with you</h3>
                <ul class="{{ $inset }} divide-y divide-[#E2E4EC]">
                    @foreach($others as $other)
                        <li>
                            <button type="button" @click="$dispatch('open-contract', { url: @js(route('landlord.contracts.show', $other)) })"
                                class="w-full min-h-[44px] py-2.5 flex items-start justify-between gap-3 text-left cursor-pointer">
                                <span class="min-w-0">
                                    <span class="block text-[13.5px] font-semibold text-[#060D26] truncate">{{ $other->property->title }}<span class="font-normal text-[#5B6A8E]"> · {{ $other->unit->unit_label ?? 'No unit' }}</span></span>
                                    <span class="block text-[12.5px] text-[#5B6A8E] tabular-nums">{{ $fmt($other->target_move_in_date) }} to {{ $fmt($other->target_move_out_date) }}</span>
                                </span>
                                <span class="shrink-0 text-[12.5px] text-[#5B6A8E]">{{ $other->contractStatus()['label'] }}</span>
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
    {{-- End rental, in place (TenancyController::endTenancy). Unpaid rent is shown, never a reason to refuse. --}}
    <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}" x-ref="endForm" x-show="ending" x-cloak
        class="mx-6 mb-6 grid gap-4 rounded-xl bg-[#FEF2F2] p-4"
        data-confirm="End this rental?"
        data-confirm-message="{{ $unitName }} becomes available again and rent stops being billed for new months. This cannot be undone."
        data-confirm-button="End rental" data-confirm-type="warning">
        @csrf
        <input type="hidden" name="from" value="contracts">
        <div>
            <p class="text-[15px] font-semibold text-[#060D26]">End this rental</p>
            <p class="mt-1 text-[14px] leading-snug text-[#5B6A8E]">{{ $unitName }} goes back to your available units and no new months are billed. If rent is still owed you can keep collecting it, or stop collecting it, from Payments.</p>
        </div>

        <div class="sm:max-w-[260px]">
            <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5" for="end_date_{{ $reservation->reservation_id }}">Move-out date</label>
            <x-date-picker name="move_out_date" id="end_date_{{ $reservation->reservation_id }}"
                value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" />
        </div>

        @if($unpaid > 0)
            <p class="rounded-lg bg-[#FBBF24]/[0.16] px-3.5 py-2.5 text-[14px] leading-snug text-[#7A3E00]" role="note">
                <span class="font-semibold">₱{{ number_format($unpaid, 2) }} in rent is still unpaid.</span> You can still end the rental. The ledger keeps the balance so you can follow it up.
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
                    <span class="text-[14px] text-[#060D26]">I have already returned the deposit<span class="block text-[13px] text-[#5B6A8E]">Leave this off to record it later from this rental.</span></span>
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
            <button type="button" @click="ending = false" class="{{ $ghostBtn }} bg-white cursor-pointer">Keep rental</button>
            <button type="submit" class="inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#DC2626] text-[14px] font-semibold text-white hover:bg-[#B91C1C] transition-colors duration-200 cursor-pointer">End rental</button>
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
        <p class="text-[13px] font-semibold text-[#060D26]">Renew this rental</p>
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
        <div class="sticky bottom-0 z-10 mt-auto flex flex-wrap items-center gap-2 border-t border-[#C9CEDD] bg-[#F7F8FC] px-6 py-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))]">
            @if($reservation->rental_status === 'Occupied')
                <button type="button" x-show="!ending"
                    @click="ending = true; renewing = false; $nextTick(() => $refs.endForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
                    class="{{ $ftDanger }}">End rental</button>
            @elseif($reservation->rental_status === 'Reserved')
                <form method="POST" action="{{ route('landlord.reservations.cancel', $reservation) }}"
                    data-confirm="Cancel this rental?" data-confirm-type="warning"
                    data-confirm-message="{{ $tenant->first_name }} has not moved in yet. The unit goes back to Available." data-confirm-button="Cancel rental" data-confirm-cancel="Keep it">
                    @csrf @method('PATCH')
                    <button type="submit" class="{{ $ftDanger }}">Cancel rental</button>
                </form>
            @endif
            @if($canInvite ?? false)
                <form method="POST" action="{{ $inviteUrl }}">
                    @csrf
                    <button type="submit" class="{{ $ftGhost }}">Resend invite</button>
                </form>
            @endif

            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                @if($reservation->rental_status === 'Rental Agreement Signed' && ! $held && ! $reservation->moveInSettled())
                    <p class="text-[12.5px] text-[#5B6A8E] max-w-[26ch]">Waiting for {{ $tenant->first_name }}'s move-in payment. Confirm it from the chat once it reaches your account.</p>
                @endif

                {{-- Rating the tenant lives with the lease (it used to be a Reservations row action). --}}
                @if(in_array($reservation->rental_status, ['Occupied', 'Completed'], true))
                    @if($reservation->tenantRating)
                        <span class="inline-flex items-center gap-1 h-10 px-3 rounded-full bg-[#22C55E]/[0.10] text-[#15803D] text-[13px] font-semibold">Rated {{ $reservation->tenantRating->rating }}/5</span>
                    @else
                        <button type="button" @click="rate(true)" class="{{ $ftGhost }}">Rate tenant</button>
                    @endif
                @endif

                @if(in_array($reservation->rental_status, ['Occupied', 'Completed', 'Reserved'], true)
                    || ($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled()))
                    <a href="{{ route('landlord.payments.ledger', $reservation) }}" class="{{ $ftNavy }}">Open ledger</a>
                @endif

                @if($reservation->rental_status === 'Reserved')
                    <form method="POST" action="{{ route('landlord.reservations.confirmWalkInMoveIn', $reservation) }}"
                        data-confirm="Confirm move-in?" data-confirm-message="The unit will be marked Occupied and rent starts." data-confirm-button="Confirm move-in">
                        @csrf
                        <button type="submit" class="{{ $ftCta }}">Confirm move-in</button>
                    </form>
                @elseif($held)
                    {{-- Escrow: the tenant's confirmation (or the deadline job, or an admin) releases the money, not this modal. --}}
                    @if($reservation->conversation_id)
                        <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ftGhost }}">Open chat</a>
                    @endif
                    @if(! $reservation->keys_turned_over_at && ! $reservation->move_in_disputed_at)
                        <form method="POST" action="{{ route('landlord.reservations.markTurnedOver', $reservation) }}"
                            data-confirm="Mark the keys as turned over?"
                            data-confirm-message="{{ $tenant->first_name }} gets {{ config('rentals.move_in_confirmation_days') }} days to confirm move-in. After that the payment is released to you automatically."
                            data-confirm-button="Keys turned over">
                            @csrf
                            <button type="submit" class="{{ $ftCta }}">Mark keys turned over</button>
                        </form>
                    @endif
                @elseif($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled())
                    <form method="POST" action="{{ route('landlord.reservations.confirmDirectMoveIn', $reservation) }}"
                        data-confirm="Confirm move-in?" data-confirm-message="The unit will be marked Occupied and rent starts." data-confirm-button="Confirm move-in">
                        @csrf
                        <button type="submit" class="{{ $ftCta }}">Confirm move-in</button>
                    </form>
                @elseif($reservation->rental_status === 'Rental Agreement Signed')
                    @if($reservation->conversation_id)
                        <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ftCta }}">Open chat</a>
                    @endif
                @elseif($reservation->rental_status === 'Occupied' && $canRenew)
                    <button type="button" @click="renewing = true; ending = false; $nextTick(() => $refs.renewForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" x-show="!renewing" class="{{ $ftCta }}">Renew</button>
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
