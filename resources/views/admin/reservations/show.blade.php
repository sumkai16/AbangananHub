@extends('layouts.admin')

@section('page-title', 'Reservation #' . $reservation->reservation_id)

@section('content')
@php
    $rs = $reservation->rental_status;
    $isTerminal = in_array($rs, ['Occupied', ...\App\Models\Reservation::TERMINAL_STATUSES], true);
    $fellThrough = in_array($rs, ['Cancelled', 'Rejected'], true);

    $pipeline = [
        'Inquiry' => 'Inquiry',
        'Under Negotiation' => 'Negotiation',
        'Pending Rental Agreement' => 'Agreement sent',
        'Rental Agreement Signed' => 'Agreement signed',
        'Occupied' => 'Occupied',
    ];
    $steps = array_keys($pipeline);
    // A completed tenancy ran the whole pipeline, so it reads as finished at the last step rather than falling through
    // to array_search()'s false and rendering every step grey. Reserved has no step of its own: it sits after signing, before move-in.
    $currentStep = match ($rs) {
        'Completed' => count($steps) - 1,
        'Reserved' => array_search('Rental Agreement Signed', $steps),
        default => array_search($rs, $steps),
    };

    $tenant = $reservation->tenant;
    $property = $reservation->property;
    $landlord = $property?->landlord;
    $unit = $reservation->unit;
    $photo = $property?->media->firstWhere('media_type', 'Image');

    $paid = $reservation->payments->whereIn('status', ['Held', 'Released', 'Paid'])->sum('amount');
    $held = $reservation->payments->where('status', 'Held')->sum('amount');
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
@endphp

<div class="max-w-[1600px] mx-auto" x-data="{ cancelOpen: false, rejectOpen: false }">

    <x-back-link href="{{ route('admin.reservations.index') }}" to="reservations" class="mb-4" />

    <x-page-header :title="'Reservation #'.$reservation->reservation_id"
        :subtitle="trim(($tenant->first_name ?? '').' '.($tenant->last_name ?? '')).' · '.($property->title ?? 'No property').' · created '.$reservation->created_at->format('M j, Y, g:i A')">
        <x-slot:badge><x-admin.status :status="$rs" /></x-slot:badge>
    </x-page-header>

    {{-- Where this reservation stands. --}}
    <x-card class="mb-5">
        @if($fellThrough)
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 class="text-[15px] font-semibold text-[#060D26]">This reservation {{ $rs === 'Rejected' ? 'was rejected' : 'was cancelled' }}</h2>
                @if($reservation->rejection_reason)
                    <p class="text-[13.5px] text-[#DC2626]">{{ $reservation->rejection_reason }}</p>
                @endif
            </div>
        @else
            <ol class="flex items-start overflow-x-auto scrollbar-thin-light" aria-label="Rental progress">
                @foreach($pipeline as $step => $label)
                    @php
                        $done = $currentStep !== false && $loop->index < $currentStep;
                        $current = $currentStep !== false && $loop->index === $currentStep;
                    @endphp
                    <li class="flex min-w-[8rem] flex-1 flex-col" @if($current) aria-current="step" @endif>
                        <div class="flex items-center">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[13px] font-bold
                                {{ $done || $current ? 'bg-[#FF8A66] text-[#060D26]' : 'bg-[#ECEEF6] text-[#5B6A8E]' }}">
                                @if($done)
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>
                            @unless($loop->last)
                                <span class="mx-2 h-0.5 flex-1 rounded {{ $done ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]' }}"></span>
                            @endunless
                        </div>
                        <p class="mt-2 pr-3 text-[13px] {{ $current ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $label }}</p>
                    </li>
                @endforeach
            </ol>
            @if($rs === 'Completed')
                <p class="mt-3 text-[13px] text-[#5B6A8E]">The tenancy has ended.</p>
            @elseif($rs === 'Reserved')
                <p class="mt-3 text-[13px] text-[#5B6A8E]">The unit is reserved and waiting for move-in.</p>
            @endif
        @endif
    </x-card>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px] items-start">

        <div class="min-w-0 space-y-5">

            @if($reservation->move_in_disputed_at)
                <div class="rounded-2xl border border-[#EF4444]/25 bg-[#EF4444]/[0.07] p-5">
                    <h2 class="text-[15px] font-semibold text-[#DC2626]">Move-in needs review</h2>
                    <p class="mt-1 text-[13.5px] text-[#060D26]">{{ $reservation->move_in_dispute_reason ?: 'No reason given.' }}</p>
                    <p class="mt-1 text-[12.5px] text-[#5B6A8E]">Reported {{ $reservation->move_in_disputed_at->diffForHumans() }}</p>
                </div>
            @endif

            {{-- Money --}}
            <x-card flush>
                <div class="grid grid-cols-2 gap-px bg-[#E2E4EC] xl:grid-cols-4">
                    @foreach([
                        ['Monthly rent', $unit || $reservation->agreed_monthly_rent ? $peso($reservation->monthlyRent()) : '—', 'Agreed for this rental'],
                        ['Security deposit', $peso($reservation->agreedDeposit()), 'Agreed for this rental'],
                        ['Paid so far', $peso($paid), $reservation->payments->count().' '.\Illuminate\Support\Str::plural('payment', $reservation->payments->count())],
                        ['Held', $peso($held), 'Until move-in is confirmed'],
                    ] as [$label, $value, $note])
                        <div class="bg-white px-5 py-4">
                            <p class="text-[13px] font-semibold text-[#5B6A8E]">{{ $label }}</p>
                            <p class="mt-1 text-[22px] font-semibold leading-tight tabular-nums text-[#060D26]">{{ $value }}</p>
                            <p class="mt-1 text-[12.5px] text-[#5B6A8E]">{{ $note }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Details --}}
            <x-card>
                <h2 class="mb-4 text-[15px] font-semibold text-[#060D26]">Details</h2>
                <x-admin.facts :items="[
                    'Reservation date' => $reservation->reservation_date?->format('M j, Y'),
                    'Target move-in' => $reservation->target_move_in_date?->format('M j, Y'),
                    'Target move-out' => $reservation->target_move_out_date?->format('M j, Y'),
                    'Duration of stay' => $reservation->duration_of_stay,
                    'Occupants' => $reservation->occupants_count,
                    'Agreement signed' => $reservation->agreed_at?->format('M j, Y, g:i A'),
                    'Signed from' => $reservation->agreed_ip,
                ]" />
                @if($reservation->remarks)
                    <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                        <p class="text-[12.5px] text-[#5B6A8E]">Tenant's remarks</p>
                        <p class="mt-0.5 text-[14px] leading-relaxed text-[#060D26]">{{ $reservation->remarks }}</p>
                    </div>
                @endif
                @if($reservation->agreement_terms_notes)
                    <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                        <p class="text-[12.5px] text-[#5B6A8E]">Negotiated terms</p>
                        <p class="mt-0.5 whitespace-pre-wrap text-[14px] leading-relaxed text-[#060D26]">{{ $reservation->agreement_terms_notes }}</p>
                    </div>
                @endif
                @if($reservation->rejection_reason && ! $fellThrough)
                    <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                        <p class="text-[12.5px] text-[#5B6A8E]">Rejection reason</p>
                        <p class="mt-0.5 text-[14px] leading-relaxed text-[#DC2626]">{{ $reservation->rejection_reason }}</p>
                    </div>
                @endif
            </x-card>

            {{-- Payments --}}
            <x-card flush>
                <div class="border-b border-[#E2E4EC] px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Payments</h2>
                </div>
                @if($reservation->payments->isEmpty())
                    <x-admin.empty title="No payments yet" />
                @else
                    <div class="overflow-x-auto scrollbar-thin-light">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <x-admin.th>Type</x-admin.th>
                                    <x-admin.th>Method</x-admin.th>
                                    <x-admin.th>For</x-admin.th>
                                    <x-admin.th>Date</x-admin.th>
                                    <x-admin.th>Status</x-admin.th>
                                    <x-admin.th align="right">Amount</x-admin.th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E4EC]">
                                @foreach($reservation->payments as $payment)
                                    <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                        <td class="px-4 py-3 text-[13.5px] font-semibold text-[#060D26]">{{ $payment->payment_type }}</td>
                                        <td class="px-4 py-3 text-[13.5px] text-[#060D26]">{{ $payment->payment_method ?: '—' }}</td>
                                        <td class="px-4 py-3 text-[13.5px] whitespace-nowrap text-[#5B6A8E]">{{ $payment->billing_period?->format('M Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-[13.5px] whitespace-nowrap text-[#5B6A8E]">{{ ($payment->paid_at ?? $payment->created_at)?->format('M j, Y') ?? '—' }}</td>
                                        <td class="px-4 py-3"><x-admin.status :status="$payment->status" :label="['Paid' => 'Recorded', 'Pending' => 'Processing'][$payment->status] ?? $payment->status" /></td>
                                        <td class="px-4 py-3 text-right text-[13.5px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">{{ $peso($payment->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Rail --}}
        <div class="space-y-5 lg:sticky lg:top-6">

            @if(! $isTerminal)
                <x-card>
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Admin actions</h2>
                    <p class="mt-1 text-[13px] text-[#5B6A8E]">Use these only when the tenant and landlord cannot settle it themselves. Both are logged.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button type="button" @click="cancelOpen = true"
                            class="h-10 rounded-lg border border-[#E2E4EC] bg-white text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Force Cancel</button>
                        <button type="button" @click="rejectOpen = true"
                            class="h-10 rounded-lg border border-[#EF4444]/30 text-[13px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] cursor-pointer">Force Reject</button>
                    </div>
                </x-card>
            @endif

            <x-card flush>
                <div class="divide-y divide-[#E2E4EC]">
                    @foreach([['Tenant', $tenant], ['Landlord', $landlord]] as [$role, $person])
                        <div class="px-5 py-4">
                            <p class="text-[12.5px] text-[#5B6A8E]">{{ $role }}</p>
                            @if($person)
                                <a href="{{ route('admin.users.show', $person) }}" class="mt-0.5 block text-[14px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ trim($person->first_name.' '.$person->last_name) }}</a>
                                <p class="break-words text-[12.5px] text-[#5B6A8E]">{{ $person->email ?: '—' }}</p>
                                @if($person->contact_number)<p class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $person->contact_number }}</p>@endif
                            @else
                                <p class="mt-0.5 text-[14px] text-[#5B6A8E]">—</p>
                            @endif
                        </div>
                    @endforeach
                    @if($reservation->conversation)
                        <a href="{{ route('admin.conversations.show', $reservation->conversation) }}" class="block px-5 py-3 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Open their conversation</a>
                    @endif
                </div>
            </x-card>

            <x-card flush>
                @if($photo)
                    <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($photo->media_url, 'card') }}" alt="" class="h-36 w-full object-cover">
                @endif
                <div class="p-5">
                    @if($property)
                        <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="block text-[14px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $property->title }}</a>
                        <p class="text-[12.5px] text-[#5B6A8E]">{{ $property->address }}</p>
                    @else
                        <p class="text-[14px] text-[#5B6A8E]">No property</p>
                    @endif
                    @if($unit)
                        <dl class="mt-3 space-y-1.5 border-t border-[#E2E4EC] pt-3 text-[13px]">
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Unit</dt><dd class="font-medium text-[#060D26]">{{ $unit->unit_label }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Listed rent</dt><dd class="font-medium tabular-nums text-[#060D26]">{{ $peso($unit->rental_fee) }}</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="text-[#5B6A8E]">Unit status</dt><dd><x-admin.status :status="$unit->availability_status" kind="unit" /></dd></div>
                        </dl>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    {{-- Force Cancel Modal --}}
    <template x-teleport="body">
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="force-cancel-title" @keydown.escape.window="cancelOpen = false">
        <div class="absolute inset-0 bg-black/40" @click="cancelOpen = false"></div>
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 id="force-cancel-title" class="text-[16px] font-semibold text-[#060D26]">Force cancel this reservation?</h3>
            <p class="mt-1 mb-4 text-[13.5px] text-[#5B6A8E]">This will cancel the reservation and free the unit. The action will be logged.</p>
            <form action="{{ route('admin.reservations.forceCancel', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <label for="force-cancel-note" class="mb-1.5 block text-[13px] font-medium text-[#060D26]">Admin note <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                <textarea id="force-cancel-note" name="admin_note" rows="2" placeholder="Reason for cancellation"
                    class="w-full resize-none rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-[13.5px] transition-all focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20"></textarea>
                <div class="mt-4 flex gap-2">
                    <button type="button" @click="cancelOpen = false" class="h-10 flex-1 rounded-lg border border-[#E2E4EC] text-[13.5px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">Back</button>
                    <button type="submit" class="h-10 flex-1 rounded-lg bg-[#FBBF24] text-[13.5px] font-semibold text-[#060D26] transition-all hover:brightness-95">Cancel Reservation</button>
                </div>
            </form>
        </div>
    </div>
    </template>

    {{-- Force Reject Modal --}}
    <template x-teleport="body">
    <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="force-reject-title" @keydown.escape.window="rejectOpen = false">
        <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 id="force-reject-title" class="text-[16px] font-semibold text-[#060D26]">Force reject this reservation?</h3>
            <p class="mt-1 mb-4 text-[13.5px] text-[#5B6A8E]">This will permanently reject this reservation. The unit will be freed if applicable.</p>
            <form action="{{ route('admin.reservations.forceReject', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <label for="force-reject-note" class="mb-1.5 block text-[13px] font-medium text-[#060D26]">Rejection reason <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                <textarea id="force-reject-note" name="admin_note" rows="2" placeholder="Reason for rejection"
                    class="w-full resize-none rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-[13.5px] transition-all focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20"></textarea>
                <div class="mt-4 flex gap-2">
                    <button type="button" @click="rejectOpen = false" class="h-10 flex-1 rounded-lg border border-[#E2E4EC] text-[13.5px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">Back</button>
                    <button type="submit" class="h-10 flex-1 rounded-lg bg-[#DC2626] text-[13.5px] font-semibold text-white transition-all hover:brightness-95">Reject Reservation</button>
                </div>
            </form>
        </div>
    </div>
    </template>

</div>
@endsection
