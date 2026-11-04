@extends('layouts.admin')

@section('page-title', 'Reservation #' . $reservation->reservation_id)

@section('content')
@php

    $rs = $reservation->rental_status;
    $isTerminal = in_array($rs, ['Occupied', ...\App\Models\Reservation::TERMINAL_STATUSES], true);

    $pipeline = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement',
        'Rental Agreement Signed', 'Occupied',
    ];
    // A completed tenancy ran the whole pipeline, so it reads as finished at
    // the last step rather than falling through to array_search()'s false and
    // rendering every step grey. Reserved has no step of its own: it sits after signing, before move-in.
    $currentPipelineIndex = match ($rs) {
        'Completed' => count($pipeline) - 1,
        'Reserved' => array_search('Rental Agreement Signed', $pipeline),
        default => array_search($rs, $pipeline),
    };
@endphp

<div class="max-w-5xl mx-auto" x-data="{ cancelOpen: false, rejectOpen: false }">

    {{-- Back + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <x-back-link href="{{ route('admin.reservations.index') }}" to="reservations" />

        @if(!$isTerminal)
            <div class="flex items-center gap-2">
                <button @click="rejectOpen = true"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold border border-[#EF4444]/25 text-[#DC2626] rounded-xl hover:bg-[#EF4444]/[0.07] transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Force Reject
                </button>
                <button @click="cancelOpen = true"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold border border-[#FBBF24]/35 text-[#B45309] rounded-xl hover:bg-[#FBBF24]/[0.10] transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Force Cancel
                </button>
            </div>
        @endif
    </div>

    {{-- Hero card --}}
    <x-card flush class="mb-6">
        <div class="px-4 sm:px-7 py-6 border-b border-[#E2E4EC] flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2.5 mb-1">
                    <h1 class="text-[20px] font-semibold text-[#060D26] tracking-tight">
                        Reservation #{{ $reservation->reservation_id }}
                    </h1>
                    <x-admin.status :status="$rs" />
                </div>
                <p class="text-[13px] text-[#5B6A8E]">
                    Created {{ $reservation->created_at->format('F j, Y \a\t g:i A') }}
                </p>
            </div>
            <div class="text-center bg-[#F7F8FC] border border-[#E2E4EC] rounded-2xl px-6 py-4 shrink-0">
                <p class="text-[28px] font-extrabold text-[#060D26] leading-none">
                    {{ $reservation->duration_of_stay ?? '—' }}
                </p>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mt-1">Duration of Stay</p>
            </div>
        </div>

        {{-- Pipeline tracker (only for non-rejected/cancelled) --}}
        @if(!in_array($rs, ['Cancelled', 'Rejected']))
            <div class="px-4 sm:px-7 py-5 border-b border-[#E2E4EC]">
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-3">Rental Pipeline</p>
                <div class="flex items-center gap-0 overflow-x-auto pb-1 min-w-0">
                    @foreach($pipeline as $i => $step)
                        @php
                            $stepIndex  = $i;
                            $isDone     = $currentPipelineIndex !== false && $stepIndex <= $currentPipelineIndex;
                            $isCurrent  = $currentPipelineIndex !== false && $stepIndex === $currentPipelineIndex;
                        @endphp
                        <div class="flex items-center {{ $i < count($pipeline) - 1 ? 'flex-1' : '' }}">
                            <div class="flex flex-col items-center">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0
                                    {{ $isDone ? 'bg-[#FF8A66] text-[#060D26]' : 'bg-[#ECEEF6] text-[#5B6A8E]' }}
                                    {{ $isCurrent ? 'ring-2 ring-[#FF8A66]/30' : '' }}">
                                    @if($isDone && !$isCurrent)
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <p class="text-[9px] font-semibold mt-1 whitespace-nowrap
                                    {{ $isCurrent ? 'text-[#060D26]' : ($isDone ? 'text-[#5B6A8E]' : 'text-[#5B6A8E]') }}">
                                    {{ $step === 'Pending Rental Agreement' ? 'Pending Agmt.' : ($step === 'Rental Agreement Signed' ? 'Agmt. Signed' : $step) }}
                                </p>
                            </div>
                            @if($i < count($pipeline) - 1)
                                <div class="flex-1 h-px mx-1 {{ $stepIndex < $currentPipelineIndex ? 'bg-[#FF8A66]' : 'bg-[#ECEEF6]' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-card>

    {{-- Two-col layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left col --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Reservation details --}}
            <x-card flush>
                <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center gap-3">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Reservation Details</h2>
                </div>
                <div class="divide-y divide-[#E2E4EC]">
                    @foreach([
                        ['Reservation Date',  $reservation->reservation_date?->format('F j, Y') ?? '—'],
                        ['Target Move-In',    $reservation->target_move_in_date?->format('F j, Y') ?? '—'],
                        ['Target Move-Out',   $reservation->target_move_out_date?->format('F j, Y') ?? '—'],
                        ['Duration of Stay',  $reservation->duration_of_stay ?? '—'],
                        ['Occupants',         $reservation->occupants_count ?? '—'],
                    ] as [$label, $value])
                        <div class="px-6 py-3.5 flex items-center justify-between">
                            <p class="text-[12px] text-[#5B6A8E] font-medium">{{ $label }}</p>
                            <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Tenant's remarks --}}
            @if($reservation->remarks)
                <x-card flush class="p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-2">Tenant's Remarks</p>
                    <p class="text-[13.5px] text-[#060D26] leading-relaxed">{{ $reservation->remarks }}</p>
                </x-card>
            @endif

            {{-- Agreement info --}}
            @if($reservation->agreed_at)
                <div class="bg-[#ECEEF6] border border-[#FF8A66]/20 rounded-2xl shadow-sm p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#060D26] mb-3">Agreement Signed</p>
                    <div class="space-y-2">
                        <div class="flex justify-between text-[13px]">
                            <span class="text-[#060D26]">Signed at</span>
                            <span class="font-semibold text-[#060D26]">{{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}</span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-[#060D26]">IP Address</span>
                            <span class="font-semibold text-[#060D26] font-mono">{{ $reservation->agreed_ip ?? '—' }}</span>
                        </div>
                    </div>
                    @if($reservation->agreement_terms_notes)
                        <div class="mt-4 pt-4 border-t border-[#FF8A66]/25">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-[#060D26] mb-1.5">Negotiated Terms</p>
                            <p class="text-[13px] text-[#060D26] leading-relaxed whitespace-pre-wrap">{{ $reservation->agreement_terms_notes }}</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Rejection reason --}}
            @if($reservation->rejection_reason)
                <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-2xl shadow-sm p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#DC2626] mb-2">Rejection Reason</p>
                    <p class="text-[13.5px] text-[#DC2626] leading-relaxed">{{ $reservation->rejection_reason }}</p>
                </div>
            @endif

            {{-- Payments --}}
            @if($reservation->payments->count())
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center gap-3">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Payments</h2>
                    </div>
                    <div class="divide-y divide-[#E2E4EC]">
                        @foreach($reservation->payments as $payment)
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $payment->payment_type }} Payment</p>
                                    <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                        {{ $payment->payment_method }}
                                        @if($payment->billing_period) · {{ $payment->billing_period }} @endif
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[14px] font-extrabold text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <x-admin.status :status="$payment->status" class="mt-1" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

        </div>

        {{-- Right col --}}
        <div class="space-y-5">

            {{-- Tenant --}}
            <x-card flush>
                <div class="px-5 py-4 border-b border-[#E2E4EC]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Tenant</p>
                </div>
                <div class="p-5">
                    @php $tenant = $reservation->tenant; @endphp
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-[#FF8A66]/10 flex items-center justify-center shrink-0">
                            <span class="text-[#060D26] text-[13px] font-extrabold">
                                {{ strtoupper(substr($tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($tenant->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13.5px] font-bold text-[#060D26]">{{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: '—' }}</p>
                            <p class="text-[12px] text-[#5B6A8E]">{{ $tenant->email ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-[12.5px]">
                        <div class="flex justify-between">
                            <span class="text-[#5B6A8E]">Contact</span>
                            <span class="font-medium text-[#060D26]">{{ $tenant->contact_number ?? '—' }}</span>
                        </div>
                    </div>
                    @if($tenant)
                        <a href="{{ route('admin.users.show', $tenant) }}"
                            class="mt-4 flex items-center justify-center gap-1.5 w-full h-8 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] text-[12px] font-semibold text-[#5B6A8E] hover:bg-[#FF8A66] hover:text-[#060D26] hover:border-[#FF8A66] transition-all">
                            View Profile
                        </a>
                    @endif
                </div>
            </x-card>

            {{-- Property --}}
            <x-card flush>
                <div class="px-5 py-4 border-b border-[#E2E4EC]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Property</p>
                </div>
                <div class="p-5">
                    @php
                        $property = $reservation->property;
                        $photo    = $property?->media->firstWhere('media_type', 'Image');
                    @endphp
                    @if($photo)
                        <img loading="lazy" decoding="async" src="{{ $photo->media_url }}" alt="" class="w-full h-32 object-cover rounded-2xl mb-3">
                    @endif
                    <p class="text-[13.5px] font-bold text-[#060D26]">{{ $property->title ?? '—' }}</p>
                    <p class="text-[12px] text-[#5B6A8E] mt-0.5">{{ $property->address ?? '—' }}</p>
                    @if($reservation->unit)
                        <div class="mt-3 pt-3 border-t border-[#E2E4EC] space-y-1.5 text-[12.5px]">
                            <div class="flex justify-between">
                                <span class="text-[#5B6A8E]">Unit</span>
                                <span class="font-semibold text-[#060D26]">{{ $reservation->unit->unit_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#5B6A8E]">Rental Fee</span>
                                <span class="font-semibold text-[#060D26]">₱{{ number_format($reservation->unit->rental_fee, 2) }}/mo</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#5B6A8E]">Unit Status</span>
                                <span class="font-semibold text-[#060D26]">{{ $reservation->unit->availability_status }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Landlord --}}
            <x-card flush>
                <div class="px-5 py-4 border-b border-[#E2E4EC]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Landlord</p>
                </div>
                <div class="p-5">
                    @php $landlord = $reservation->property?->landlord; @endphp
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-[#ECEEF6] flex items-center justify-center shrink-0">
                            <span class="text-[#060D26] text-[12px] font-extrabold">
                                {{ strtoupper(substr($landlord->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($landlord->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#060D26]">{{ trim(($landlord->first_name ?? '') . ' ' . ($landlord->last_name ?? '')) ?: '—' }}</p>
                            <p class="text-[11.5px] text-[#5B6A8E]">{{ $landlord->email ?? '—' }}</p>
                        </div>
                    </div>
                    @if($landlord)
                        <a href="{{ route('admin.users.show', $landlord) }}"
                            class="flex items-center justify-center gap-1.5 w-full h-8 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] text-[12px] font-semibold text-[#5B6A8E] hover:bg-[#FF8A66] hover:text-[#060D26] hover:border-[#FF8A66] transition-all">
                            View Profile
                        </a>
                    @endif
                </div>
            </x-card>

        </div>
    </div>

    {{-- Force Cancel Modal --}}
    <template x-teleport="body">
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="cancelOpen = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-7 z-10">
            <div class="w-12 h-12 rounded-2xl bg-[#FBBF24]/[0.10] border border-[#FBBF24]/25 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-[#B45309]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-[16px] font-semibold text-[#060D26] text-center mb-1">Force Cancel Reservation?</h3>
            <p class="text-[13px] text-[#5B6A8E] text-center mb-5">
                This will cancel the reservation and free the unit. The action will be logged.
            </p>
            <form action="{{ route('admin.reservations.forceCancel', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-1.5">Admin Note <span class="font-normal normal-case">(optional)</span></label>
                    <textarea name="admin_note" rows="2" placeholder="Reason for cancellation…"
                        class="w-full px-3.5 py-2.5 text-[13px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] focus:outline-none focus:ring-2 focus:ring-[#FBBF24]/35 focus:border-[#FBBF24] resize-none transition-all"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="cancelOpen = false"
                        class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:bg-[#F7F8FC] transition-colors">
                        Back
                    </button>
                    <button type="submit"
                        class="flex-1 h-10 text-[13.5px] font-bold bg-[#FBBF24] text-[#060D26] rounded-xl hover:bg-[#FBBF24] transition-colors">
                        Cancel Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>

    {{-- Force Reject Modal --}}
    <template x-teleport="body">
    <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-7 z-10">
            <div class="w-12 h-12 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-[#DC2626]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <h3 class="text-[16px] font-semibold text-[#060D26] text-center mb-1">Force Reject Reservation?</h3>
            <p class="text-[13px] text-[#5B6A8E] text-center mb-5">
                This will permanently reject this reservation. The unit will be freed if applicable.
            </p>
            <form action="{{ route('admin.reservations.forceReject', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-1.5">Rejection Reason <span class="font-normal normal-case">(optional)</span></label>
                    <textarea name="admin_note" rows="2" placeholder="Reason for rejection…"
                        class="w-full px-3.5 py-2.5 text-[13px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] focus:outline-none focus:ring-2 focus:ring-[#EF4444]/35 focus:border-[#EF4444] resize-none transition-all"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="rejectOpen = false"
                        class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:bg-[#F7F8FC] transition-colors">
                        Back
                    </button>
                    <button type="submit"
                        class="flex-1 h-10 text-[13.5px] font-bold bg-[#EF4444] text-white rounded-xl hover:bg-[#EF4444] transition-colors">
                        Reject Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>

</div>
@endsection
