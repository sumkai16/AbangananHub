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
@endphp

<div x-data="{ renewing: false, uploading: false }">

<div class="px-6 pt-6 pb-5 border-b border-[#E2E4EC] pr-14">
    <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $reference ?? 'Contract' }}</p>
    <h2 class="mt-1 text-[18px] font-normal text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill }}">{{ $status['label'] }}</span>
        @if($code)<span class="text-[12px] text-[#5B6A8E]">{{ $code }}</span>@endif
        @if($tenant->is_walk_in)<span class="text-[12px] text-[#5B6A8E]">· Added by you</span>@endif
    </div>
</div>

<div class="px-6 py-5 grid gap-6">
    <section aria-labelledby="contract-lease">
        <h3 id="contract-lease" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Lease</h3>
        <dl>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Unit</dt><dd class="{{ $dd }}">{{ $reservation->property->title }} · {{ $reservation->unit->unit_label ?? 'No unit' }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Move-in</dt><dd class="{{ $dd }}">{{ $fmt($reservation->target_move_in_date) }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Move-out</dt><dd class="{{ $dd }}">{{ $fmt($reservation->target_move_out_date) }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Monthly rent</dt><dd class="{{ $dd }} tabular-nums">₱{{ number_format($reservation->currentRent(), 2) }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Security deposit</dt><dd class="{{ $dd }} tabular-nums">{{ $deposit > 0 ? '₱'.number_format($deposit, 2) : 'None' }}</dd></div>
            <div class="{{ $row }}"><dt class="{{ $dt }}">Rent due</dt><dd class="{{ $dd }}">Day {{ $reservation->rentDueDay() }} of each month</dd></div>
            @if($reservation->occupants_count)
                <div class="{{ $row }}"><dt class="{{ $dt }}">Occupants</dt><dd class="{{ $dd }}">{{ $reservation->occupants_count }}</dd></div>
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

    {{-- The contract's documents: the lease to view or print, and the signed copy to open or upload. --}}
    @php($leaseStatus = $reservation->leaseStatus())
    <section aria-labelledby="contract-documents">
        <h3 id="contract-documents" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-2">Documents</h3>
        <div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4 grid gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @if($leaseStatus === 'missing')
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#FBBF24]/15 text-[#B45309]">Lease missing</span>
                @else
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#22C55E]/10 text-[#15803D]">Signed</span>
                @endif
                <p class="text-[13px] text-[#060D26] min-w-0 flex-1">
                    @if($leaseStatus === 'uploaded')
                        Signed copy uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}.
                    @elseif($leaseStatus === 'signed_online')
                        Signed online by the tenant {{ $reservation->agreed_at->format('M j, Y') }}.
                    @else
                        Print the lease, have both of you sign it, then upload the signed copy.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('landlord.leases.show', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }} bg-white">
                    {{ $leaseStatus === 'missing' ? 'View & print lease' : 'View lease' }}
                </a>
                @if($reservation->lease_file_path)
                    <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener" class="{{ $ghostBtn }} bg-white">Open signed copy</a>
                @endif
                <button type="button" @click="uploading = !uploading"
                    class="{{ $leaseStatus === 'missing' ? $ctaBtn : $ghostBtn.' bg-white' }}">
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
</div>

<div class="px-6 pb-6 flex flex-wrap items-center justify-end gap-2">
    @if(in_array($reservation->rental_status, ['Occupied', 'Completed', 'Reserved'], true))
        <a href="{{ route('landlord.payments.ledger', $reservation) }}" class="{{ $ghostBtn }}">Open ledger</a>
    @endif

    {{-- Rating the tenant lives with the contract now (it used to be a Reservations row action). --}}
    @if(in_array($reservation->rental_status, ['Occupied', 'Completed'], true))
        @if($reservation->tenantRating)
            <span class="inline-flex items-center gap-1 h-10 px-3 rounded-full bg-[#22C55E]/[0.07] text-[#15803D] text-[13px] font-semibold">Rated {{ $reservation->tenantRating->rating }}/5</span>
        @else
            <a href="{{ route('landlord.reservations.rateTenant', $reservation) }}" class="{{ $ghostBtn }}">Rate tenant</a>
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
    @elseif($reservation->rental_status === 'Rental Agreement Signed' && $reservation->move_in_paid_at)
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
            <button type="button" @click="renewing = true" x-show="!renewing" class="{{ $ctaBtn }}">Renew</button>
        @endif
    @endif
</div>

@if($canRenew)
    {{-- Renew: offered within rentals.renewal_window_days of the end date, or once it has passed. --}}
    <form method="POST" action="{{ route('landlord.contracts.renew', $reservation) }}" x-show="renewing" x-cloak
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
