{{-- The tenant view modal body (fetched by _view-modal from TenantController::show). --}}
@php
    $detail = $tenant->tenantDetail;
    $name = trim($tenant->first_name.' '.$tenant->last_name);
    $pill = [
        'Active' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'Pending' => 'bg-[#FBBF24]/[0.12] text-[#92400E] border-[#FBBF24]/35',
        'Former' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ][$status];
    $leasePill = [
        'ok' => 'text-[#15803D]', 'warn' => 'text-[#92400E]', 'bad' => 'text-[#B91C1C]', 'muted' => 'text-[#5B6A8E]',
    ];
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
    $label = 'text-[12.5px] text-[#5B6A8E]';
    $value = 'mt-0.5 text-[15px] text-[#060D26] break-words';
    $link = 'underline-offset-2 hover:underline focus-visible:underline';
    $ghostBtn = 'inline-flex items-center justify-center h-11 px-5 rounded-full border border-[#5B6A8E]/30 text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer';
    $ctaBtn = 'inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#FF8A66] text-[14px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer';
    $hasEmergency = (bool) $detail?->emergency_contact_name;
@endphp

{{-- Who: the name leads. ID and status sit beside it, in plain words. --}}
<div class="px-6 pt-6 pb-4 pr-16">
    <h2 class="text-[21px] font-semibold leading-tight text-[#060D26]">{{ $name }}</h2>
    <div class="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $pill }}">{{ $status }}</span>
        <span class="text-[13px] text-[#5B6A8E] tabular-nums">{{ $row->code() }}</span>
    </div>
    <p class="mt-2 text-[13px] text-[#5B6A8E]">
        @if(! $tenant->is_walk_in)
            Signs in with their own AbangananHub account.
        @elseif($tenant->email)
            You added them. They haven't accepted the invite yet.
        @else
            You added them. Add an email to invite them to AbangananHub.
        @endif
    </p>
</div>

<div class="px-6 pb-2 grid gap-5">
    {{-- Missing emergency contact: said plainly, once, where it matters. --}}
    @unless($hasEmergency)
        <div class="rounded-xl bg-[#FBBF24]/[0.14] px-4 py-3 text-[13.5px] leading-snug text-[#7A3E00]" role="note">
            <p class="font-semibold">Emergency contact needed</p>
            <p class="mt-0.5">Please add one for {{ $tenant->first_name }}@if($canEdit ?? false). Use the pencil button on the Tenants list.@else.@endif</p>
        </div>
    @endunless

    <section aria-labelledby="tenant-contact">
        <h3 id="tenant-contact" class="text-[15px] font-semibold text-[#060D26]">Contact details</h3>
        <dl class="mt-2 divide-y divide-[#E2E4EC]">
            <div class="py-2.5">
                <dt class="{{ $label }}">Mobile</dt>
                <dd class="{{ $value }}">
                    @if($tenant->contact_number)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="{{ $link }}">{{ $tenant->contact_number }}</a>
                    @else
                        <span class="text-[#5B6A8E]">Not added</span>
                    @endif
                </dd>
            </div>
            <div class="py-2.5">
                <dt class="{{ $label }}">Email</dt>
                <dd class="{{ $value }}">
                    @if($tenant->email)
                        <a href="mailto:{{ $tenant->email }}" class="{{ $link }}">{{ $tenant->email }}</a>
                    @else
                        <span class="text-[#5B6A8E]">Not added</span>
                    @endif
                </dd>
            </div>
            @if($hasEmergency)
                <div class="py-2.5">
                    <dt class="{{ $label }}">Emergency contact</dt>
                    <dd class="{{ $value }}">
                        {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) <span class="text-[#5B6A8E]">({{ $detail->emergency_contact_relationship }})</span>@endif
                        @if($detail->emergency_contact_number)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $detail->emergency_contact_number) }}" class="block {{ $link }}">{{ $detail->emergency_contact_number }}</a>
                        @endif
                    </dd>
                </div>
            @endif
            <div class="py-2.5">
                <dt class="{{ $label }}">ID</dt>
                <dd class="{{ $value }}">
                    @if($detail?->id_type)
                        {{ $detail->id_type }} <span class="tabular-nums text-[#5B6A8E]">{{ $detail->maskedIdNumber() }}</span>
                    @else
                        <span class="text-[#5B6A8E]">Not added</span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="tenant-contracts">
        <h3 id="tenant-contracts" class="text-[15px] font-semibold text-[#060D26]">Leases</h3>
        @if($reservations->isEmpty())
            <p class="mt-2 text-[14px] text-[#5B6A8E]">No lease yet. Add one when {{ $tenant->first_name }} is ready to move in.</p>
        @else
            <ul class="mt-2 divide-y divide-[#E2E4EC]">
                @foreach($reservations as $reservation)
                    @php
                        $isLease = in_array($reservation->rental_status, \App\Http\Controllers\Landlord\ContractController::CONTRACT_STATUSES, true);
                        $cs = $isLease ? $reservation->contractStatus() : null;
                    @endphp
                    <li class="py-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 text-[15px] text-[#060D26]">{{ $reservation->property->title }}<span class="text-[#5B6A8E]"> · {{ $reservation->unit->unit_label ?? 'No unit' }}</span></p>
                            <span class="shrink-0 text-[13px] font-semibold {{ $cs ? $leasePill[$cs['tone']] : 'text-[#5B6A8E]' }}">{{ $cs['label'] ?? $reservation->rental_status }}</span>
                        </div>
                        <p class="mt-0.5 text-[13px] text-[#5B6A8E] tabular-nums">{{ $fmt($reservation->target_move_in_date) }} to {{ $fmt($reservation->target_move_out_date) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

@if($canInvite || $status !== 'Active')
    <div class="px-6 pt-3 pb-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
        @if($canInvite)
            <form method="POST" action="{{ route('landlord.tenants.invite', $row) }}" class="contents">
                @csrf
                <button type="submit" class="{{ $ghostBtn }} w-full sm:w-auto">Resend invite</button>
            </form>
        @endif
        @if($status !== 'Active')
            <a href="{{ route('landlord.contracts.index', ['new' => 1, 'tenant' => $row->landlord_tenant_id]) }}" class="{{ $ctaBtn }} w-full sm:w-auto">Add lease</a>
        @endif
    </div>
@else
    <div class="pb-4"></div>
@endif
