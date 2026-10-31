{{-- The tenant view modal body (fetched by _view-modal from TenantController::show). --}}
@php
    $detail = $tenant->tenantDetail;
    $pill = [
        'Active' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'Pending' => 'bg-[#FBBF24]/[0.12] text-[#B45309] border-[#FBBF24]/35',
        'Former' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ][$status];
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
    $line = 'flex items-start justify-between gap-4 py-2 border-b border-[#E2E4EC] last:border-0';
    $dt = 'text-[12.5px] text-[#5B6A8E] shrink-0';
    $dd = 'text-[13px] font-semibold text-[#060D26] text-right';
    $ghostBtn = 'inline-flex items-center justify-center h-10 px-4 rounded-full border border-[#5B6A8E]/30 text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer';
    $ctaBtn = 'inline-flex items-center justify-center h-10 px-5 rounded-full bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer';
@endphp

<div class="px-6 pt-6 pb-5 border-b border-[#E2E4EC] pr-14">
    <p class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $row->code() }}</p>
    <h2 class="mt-1 text-[18px] font-normal text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</h2>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill }}">{{ $status }}</span>
        <span class="text-[12px] text-[#5B6A8E]">
            @if(! $tenant->is_walk_in)
                Has their own AbangananHub login
            @elseif($tenant->email)
                Added by you · invite not accepted yet
            @else
                Added by you · no email, so they cannot be invited
            @endif
        </span>
    </div>
</div>

<div class="px-6 py-5 grid gap-6">
    <section aria-labelledby="tenant-contact">
        <h3 id="tenant-contact" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Contact</h3>
        <dl>
            <div class="{{ $line }}"><dt class="{{ $dt }}">Mobile</dt><dd class="{{ $dd }}">{{ $tenant->contact_number ?: '—' }}</dd></div>
            <div class="{{ $line }}"><dt class="{{ $dt }}">Email</dt><dd class="{{ $dd }} break-all">{{ $tenant->email ?: '—' }}</dd></div>
            <div class="{{ $line }}"><dt class="{{ $dt }}">Emergency contact</dt>
                <dd class="{{ $dd }}">
                    @if($detail?->emergency_contact_name)
                        {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) ({{ $detail->emergency_contact_relationship }})@endif
                        <span class="block font-normal text-[#5B6A8E]">{{ $detail->emergency_contact_number }}</span>
                    @else
                        Not on file
                    @endif
                </dd>
            </div>
            <div class="{{ $line }}"><dt class="{{ $dt }}">ID</dt><dd class="{{ $dd }}">{{ $detail?->id_type ? $detail->id_type.' '.$detail->maskedIdNumber() : 'Not on file' }}</dd></div>
        </dl>
    </section>

    <section aria-labelledby="tenant-contracts">
        <h3 id="tenant-contracts" class="text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E] mb-1">Contracts</h3>
        @if($reservations->isEmpty())
            <p class="text-[13px] text-[#5B6A8E] py-2">No contracts yet.</p>
        @else
            <ul class="divide-y divide-[#E2E4EC]">
                @foreach($reservations as $reservation)
                    <li class="py-2 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold text-[#060D26] truncate">{{ $reservation->property->title }} · {{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                            <p class="text-[12px] text-[#5B6A8E] tabular-nums">{{ $fmt($reservation->target_move_in_date) }} → {{ $fmt($reservation->target_move_out_date) }}</p>
                        </div>
                        <span class="shrink-0 text-[12px] text-[#5B6A8E]">{{ in_array($reservation->rental_status, \App\Http\Controllers\Landlord\ContractController::CONTRACT_STATUSES, true) ? $reservation->contractStatus()['label'] : $reservation->rental_status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="px-6 pb-6 flex flex-wrap items-center justify-end gap-2">
    @if($canInvite)
        <form method="POST" action="{{ route('landlord.tenants.invite', $row) }}">
            @csrf
            <button type="submit" class="{{ $ghostBtn }}">Resend invite</button>
        </form>
    @endif
    @if($status !== 'Active')
        <a href="{{ route('landlord.contracts.index', ['new' => 1, 'tenant' => $row->landlord_tenant_id]) }}" class="{{ $ctaBtn }}">Add contract</a>
    @endif
</div>
