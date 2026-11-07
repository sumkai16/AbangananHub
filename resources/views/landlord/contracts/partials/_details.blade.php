{{-- The contract modal body (fetched by _view-modal from ContractController::show or TenantController::show). --}}
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

<div x-data="{ renewing: @js($canRenew && ($startRenewing ?? false)), uploading: false, rating: @js($canRate && ($startRating ?? false)), stars: 0, hovered: 0, count: 0,
        rate(on) { this.renewing = false; this.rating = on; this.$nextTick(() => this.$root.closest('.overflow-y-auto')?.scrollTo(0, 0)); } }">

<div x-show="!rating">

<div class="flex items-start gap-4 px-6 pt-6 pb-5 border-b border-[#E2E4EC] pr-14">
    <div class="h-[68px] w-[88px] shrink-0 overflow-hidden rounded-xl bg-[#ECEEF6]">
        @if($photo ?? null)
            <img src="{{ $photo }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <span class="flex h-full w-full items-center justify-center text-[#94A3B8]">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18v-6.75A2.25 2.25 0 0 1 4.5 9h15a2.25 2.25 0 0 1 2.25 2.25V18M2.25 18h19.5M2.25 18v2.25m19.5-2.25v2.25M6 9V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75V9" /></svg>
            </span>
        @endif
    </div>
    <div class="min-w-0">
        <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $reference ?? 'Contract' }}</p>
        <h2 class="mt-0.5 text-[19px] font-semibold leading-tight text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
        <p class="mt-0.5 truncate text-[13px] text-[#5B6A8E]">{{ $reservation->unit->unit_label ?? 'No unit' }} · {{ $reservation->property->title }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill }}">{{ $status['label'] }}</span>
            @if($code)<span class="text-[12px] font-semibold text-[#5B6A8E]">{{ $code }}</span>@endif
            @if($tenant->is_walk_in)<span class="text-[12px] text-[#5B6A8E]">· Added by you</span>@endif
        </div>
    </div>
</div>

{{-- The four figures a landlord looks for first. --}}
<dl class="grid grid-cols-2 gap-2.5 px-6 pt-5 sm:grid-cols-4">
    @foreach([
        ['Monthly rent', '₱'.number_format($reservation->currentRent(), 2), true],
        ['Security deposit', $deposit > 0 ? '₱'.number_format($deposit, 2) : 'None', true],
        ['Rent due', 'Day '.$reservation->rentDueDay().' monthly', false],
        ['Occupants', (string) ($reservation->occupants_count ?: 1), false],
    ] as [$term, $value, $money])
        <div class="rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3.5 py-2.5">
            <dt class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $term }}</dt>
            <dd class="mt-0.5 text-[14.5px] font-extrabold text-[#060D26] {{ $money ? 'tabular-nums' : '' }}">{{ $value }}</dd>
        </div>
    @endforeach
</dl>

<div class="grid gap-6 px-6 py-5 md:grid-cols-2 md:gap-x-8">
    <div class="grid content-start gap-6">
        <section aria-labelledby="contract-lease">
            <h3 id="contract-lease" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Stay</h3>
            <dl>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Move-in</dt><dd class="{{ $dd }}">{{ $fmt($reservation->target_move_in_date) }}</dd></div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">Move-out</dt><dd class="{{ $dd }}">{{ $fmt($reservation->target_move_out_date) }}</dd></div>
                @if($reservation->target_move_in_date && $reservation->target_move_out_date)
                    <div class="{{ $row }}"><dt class="{{ $dt }}">Length</dt><dd class="{{ $dd }}">{{ $reservation->target_move_in_date->diffForHumans($reservation->target_move_out_date, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'join' => ', ']) }}</dd></div>
                @endif
            </dl>
            @if($reservation->renewals->isNotEmpty())
                <ul class="mt-3 space-y-1">
                    @foreach($reservation->renewals as $renewal)
                        <li class="text-[12px] text-[#5B6A8E]">Renewed to {{ $fmt($renewal->new_move_out_date) }} · ₱{{ number_format($renewal->new_monthly_rent, 2) }}/mo from {{ $renewal->rent_effective_from->format('M Y') }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="grid content-start gap-6">
    <section aria-labelledby="contract-tenant">
        <h3 id="contract-tenant" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Tenant</h3>
        <dl>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Mobile</dt><dd class="{{ $dd }}">{{ $tenant->contact_number ?: '—' }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Email</dt><dd class="{{ $dd }} break-all">{{ $tenant->email ?: '—' }}</dd></div>
            @if($showTenantDetails)
                <div class="{{ $row }}"><dt class="{{ $dt }}">Emergency contact</dt>
                    <dd class="{{ $dd }}">
                        @if($detail?->emergency_contact_name)
                            {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) ({{ $detail->emergency_contact_relationship }})@endif
                            <span class="block font-normal text-[#5B6A8E]">{{ $detail->emergency_contact_number }}</span>
                        @else
                            Not on file
                        @endif
                    </dd>
                </div>
                <div class="{{ $row }}"><dt class="{{ $dt }}">ID</dt><dd class="{{ $dd }}">{{ $detail?->id_type ? $detail->id_type.' '.$detail->maskedIdNumber() : 'Not on file' }}</dd></div>
            @endif
        </dl>
    </section>

    @if(($others ?? collect())->isNotEmpty())
        <section aria-labelledby="contract-others">
            <h3 id="contract-others" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Other contracts with you</h3>
            <ul class="divide-y divide-[#E2E4EC]">
                @foreach($others as $other)
                    <li>
                        <button type="button" @click="$dispatch('open-contract', { url: @js(route('landlord.contracts.show', $other)) })"
                            class="w-full py-2.5 flex items-start justify-between gap-3 text-left hover:bg-[#F7F8FC] cursor-pointer">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-[#060D26] truncate">{{ $other->property->title }} · {{ $other->unit->unit_label ?? 'No unit' }}</span>
                                <span class="block text-[12px] text-[#5B6A8E] tabular-nums">{{ $fmt($other->target_move_in_date) }} → {{ $fmt($other->target_move_out_date) }}</span>
                            </span>
                            <span class="shrink-0 text-[12px] text-[#5B6A8E]">{{ $other->contractStatus()['label'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    </div>

    {{-- The contract's documents: the lease to view or print, and the signed copy to open or upload. --}}
    @php($leaseStatus = $reservation->leaseStatus())
    <section aria-labelledby="contract-documents" class="md:col-span-2">
        <h3 id="contract-documents" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-2">Documents</h3>
        <div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4 grid gap-3">
          <div class="grid gap-3 md:flex md:items-center md:justify-between md:gap-6">
            <div class="flex flex-wrap items-center gap-2 md:flex-1">
                @if($leaseStatus === 'missing')
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#FBBF24]/15 text-[#B45309]">Agreement missing</span>
                @else
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#22C55E]/10 text-[#15803D]">Signed</span>
                @endif
                <p class="text-[13px] text-[#060D26] min-w-0 flex-1">
                    @if($leaseStatus === 'uploaded')
                        Signed copy uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}.
                    @elseif($leaseStatus === 'signed_online')
                        Signed online by the tenant {{ $reservation->agreed_at->format('M j, Y') }}.
                    @else
                        Print the agreement, have both of you sign it, then upload the signed copy.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('landlord.leases.show', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }} bg-white">
                    {{ $leaseStatus === 'missing' ? 'View & print agreement' : 'View agreement' }}
                </a>
                @if($reservation->lease_file_path)
                    <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }} bg-white">Open signed copy</a>
                @endif
                {{-- Signed online is the signed agreement: only a paper lease needs the signed copy uploaded. --}}
                @if($leaseStatus !== 'signed_online')
                    <button type="button" @click="uploading = !uploading"
                        class="{{ $leaseStatus === 'missing' ? $ctaBtn : $ghostBtn.' bg-white' }}">
                        {{ $reservation->lease_file_path ? 'Replace signed copy' : 'Upload signed copy' }}
                    </button>
                @endif
            </div>

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

<div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-2 border-t border-[#E2E4EC] bg-white px-6 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
    @if($canInvite ?? false)
        <form method="POST" action="{{ $inviteUrl }}" class="mr-auto">
            @csrf
            <button type="submit" class="{{ $ghostBtn }} cursor-pointer">Resend invite</button>
        </form>
    @endif
    @if(in_array($reservation->rental_status, ['Occupied', 'Completed', 'Reserved'], true)
        || ($reservation->rental_status === 'Rental Agreement Signed' && $reservation->moveInSettled()))
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

    @if($reservation->rental_status === 'Reserved')
        <form method="POST" action="{{ route('landlord.reservations.cancel', $reservation) }}"
            data-confirm="Cancel this contract?" data-confirm-type="warning"
            data-confirm-message="{{ $tenant->first_name }} has not moved in yet. The unit goes back to Available." data-confirm-button="Cancel contract" data-confirm-cancel="Keep it">
            @csrf @method('PATCH')
            <button type="submit" class="{{ $ghostBtn }} text-[#B91C1C] cursor-pointer">Cancel contract</button>
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
        <p class="mr-auto text-[12.5px] text-[#5B6A8E]">Waiting for {{ $tenant->first_name }}'s move-in payment. Confirm it from the chat once it reaches your account.</p>
        @if($reservation->conversation_id)
            <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}" class="{{ $ctaBtn }}">Open chat</a>
        @endif
    @elseif($reservation->rental_status === 'Occupied')
        <a href="{{ route('landlord.tenancies.show', $reservation) }}#end-tenancy" class="{{ $ghostBtn }} text-[#B91C1C]">End contract</a>
        @if($canRenew)
            <button type="button" @click="renewing = true; $nextTick(() => $refs.renewForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" x-show="!renewing" class="{{ $ctaBtn }}">Renew</button>
        @endif
    @endif
</div>

@if($canRenew)
    {{-- Renew: offered within rentals.renewal_window_days of the end date, or once it has passed. --}}
    <form method="POST" action="{{ route('landlord.contracts.renew', $reservation) }}" x-ref="renewForm" x-show="renewing" x-cloak
        class="mx-6 mb-6 grid gap-3 rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
        @csrf
        <p class="text-[13px] font-semibold text-[#060D26]">Renew this contract</p>
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
