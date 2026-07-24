@extends('layouts.admin')

@section('page-title', 'Reservation #' . $reservation->reservation_id)

@section('content')
@php
    $statusBadge = [
        'Inquiry'                  => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
        'Under Negotiation'        => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Pending Rental Agreement' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Rental Agreement Signed'  => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
        'Occupied'                 => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Cancelled'                => 'bg-[#F7FCFC] text-[#94A3B8] border-[#E2E8F0]',
        'Rejected'                 => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
    ];
    $statusDot = [
        'Inquiry'                  => 'bg-[#94A3B8]',
        'Under Negotiation'        => 'bg-[#2AA7A1]',
        'Pending Rental Agreement' => 'bg-[#FBBF24]',
        'Rental Agreement Signed'  => 'bg-[#2AA7A1]',
        'Occupied'                 => 'bg-[#22C55E]',
        'Completed'                => 'bg-[#64748B]',
        'Cancelled'                => 'bg-[#94A3B8]',
        'Rejected'                 => 'bg-[#EF4444]',
    ];

    $rs = $reservation->rental_status;
    $isTerminal = in_array($rs, ['Occupied', ...\App\Models\Reservation::TERMINAL_STATUSES], true);

    $paymentStatusBadge = [
        'Pending'  => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        'Paid'     => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'Failed'   => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
        'Refunded' => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
    ];

    $pipeline = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement',
        'Rental Agreement Signed', 'Occupied',
    ];
    // A completed tenancy ran the whole pipeline, so it reads as finished at
    // the last step rather than falling through to array_search()'s false and
    // rendering every step grey.
    $currentPipelineIndex = $rs === 'Completed'
        ? count($pipeline) - 1
        : array_search($rs, $pipeline);
@endphp

<div class="max-w-5xl mx-auto" x-data="{ cancelOpen: false, rejectOpen: false }">

    {{-- Back + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <a href="{{ route('admin.reservations.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to reservations
        </a>

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
    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden mb-6">
        <div class="px-4 sm:px-7 py-6 border-b border-[#E2E8F0] flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2.5 mb-1">
                    <h1 class="text-[20px] font-extrabold text-[#1F2937] tracking-tight">
                        Reservation #{{ $reservation->reservation_id }}
                    </h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusBadge[$rs] ?? '' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusDot[$rs] ?? 'bg-[#94A3B8]' }}"></span>
                        {{ $rs }}
                    </span>
                </div>
                <p class="text-[13px] text-[#94A3B8]">
                    Created {{ $reservation->created_at->format('F j, Y \a\t g:i A') }}
                </p>
            </div>
            <div class="text-center bg-[#F7FCFC] border border-[#E2E8F0] rounded-2xl px-6 py-4 shrink-0">
                <p class="text-[28px] font-extrabold text-[#156F8C] leading-none">
                    {{ $reservation->duration_of_stay ?? '—' }}
                </p>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mt-1">Duration of Stay</p>
            </div>
        </div>

        {{-- Pipeline tracker (only for non-rejected/cancelled) --}}
        @if(!in_array($rs, ['Cancelled', 'Rejected']))
            <div class="px-4 sm:px-7 py-5 border-b border-[#E2E8F0]">
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-3">Rental Pipeline</p>
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
                                    {{ $isDone ? 'bg-[#2AA7A1] text-white' : 'bg-[#EEF8F8] text-[#94A3B8]' }}
                                    {{ $isCurrent ? 'ring-2 ring-[#2AA7A1]/30' : '' }}">
                                    @if($isDone && !$isCurrent)
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <p class="text-[9px] font-semibold mt-1 whitespace-nowrap
                                    {{ $isCurrent ? 'text-[#156F8C]' : ($isDone ? 'text-[#64748B]' : 'text-[#94A3B8]') }}">
                                    {{ $step === 'Pending Rental Agreement' ? 'Pending Agmt.' : ($step === 'Rental Agreement Signed' ? 'Agmt. Signed' : $step) }}
                                </p>
                            </div>
                            @if($i < count($pipeline) - 1)
                                <div class="flex-1 h-px mx-1 {{ $stepIndex < $currentPipelineIndex ? 'bg-[#2AA7A1]' : 'bg-[#EEF8F8]' }}"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Two-col layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left col --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Reservation details --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Reservation Details</h2>
                </div>
                <div class="divide-y divide-[#E2E8F0]">
                    @foreach([
                        ['Reservation Date',  $reservation->reservation_date?->format('F j, Y') ?? '—'],
                        ['Target Move-In',    $reservation->target_move_in_date?->format('F j, Y') ?? '—'],
                        ['Target Move-Out',   $reservation->target_move_out_date?->format('F j, Y') ?? '—'],
                        ['Duration of Stay',  $reservation->duration_of_stay ?? '—'],
                        ['Occupants',         $reservation->occupants_count ?? '—'],
                    ] as [$label, $value])
                        <div class="px-6 py-3.5 flex items-center justify-between">
                            <p class="text-[12px] text-[#94A3B8] font-medium">{{ $label }}</p>
                            <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Tenant's remarks --}}
            @if($reservation->remarks)
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2">Tenant's Remarks</p>
                    <p class="text-[13.5px] text-[#1F2937] leading-relaxed">{{ $reservation->remarks }}</p>
                </div>
            @endif

            {{-- Agreement info --}}
            @if($reservation->agreed_at)
                <div class="bg-[#EEF8F8] border border-[#2AA7A1]/20 rounded-3xl shadow-sm p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#156F8C] mb-3">Agreement Signed</p>
                    <div class="space-y-2">
                        <div class="flex justify-between text-[13px]">
                            <span class="text-[#156F8C]">Signed at</span>
                            <span class="font-semibold text-[#156F8C]">{{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}</span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-[#156F8C]">IP Address</span>
                            <span class="font-semibold text-[#156F8C] font-mono">{{ $reservation->agreed_ip ?? '—' }}</span>
                        </div>
                    </div>
                    @if($reservation->agreement_terms_notes)
                        <div class="mt-4 pt-4 border-t border-[#2AA7A1]/25">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-[#156F8C] mb-1.5">Negotiated Terms</p>
                            <p class="text-[13px] text-[#156F8C] leading-relaxed whitespace-pre-wrap">{{ $reservation->agreement_terms_notes }}</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Rejection reason --}}
            @if($reservation->rejection_reason)
                <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-3xl shadow-sm p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#DC2626] mb-2">Rejection Reason</p>
                    <p class="text-[13.5px] text-[#DC2626] leading-relaxed">{{ $reservation->rejection_reason }}</p>
                </div>
            @endif

            {{-- Payments --}}
            @if($reservation->payments->count())
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-[#22C55E]/[0.07] border border-[#22C55E]/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#15803D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75" />
                            </svg>
                        </div>
                        <h2 class="text-[14px] font-bold text-[#1F2937]">Payments</h2>
                    </div>
                    <div class="divide-y divide-[#E2E8F0]">
                        @foreach($reservation->payments as $payment)
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $payment->payment_type }} Payment</p>
                                    <p class="text-[12px] text-[#94A3B8] mt-0.5">
                                        {{ $payment->payment_method }}
                                        @if($payment->billing_period) · {{ $payment->billing_period }} @endif
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[14px] font-extrabold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border mt-1 {{ $paymentStatusBadge[$payment->status] ?? '' }}">
                                        {{ $payment->status }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- Right col --}}
        <div class="space-y-5">

            {{-- Tenant --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8]">Tenant</p>
                </div>
                <div class="p-5">
                    @php $tenant = $reservation->tenant; @endphp
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                            <span class="text-[#156F8C] text-[13px] font-extrabold">
                                {{ strtoupper(substr($tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($tenant->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13.5px] font-bold text-[#1F2937]">{{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: '—' }}</p>
                            <p class="text-[12px] text-[#94A3B8]">{{ $tenant->email ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-[12.5px]">
                        <div class="flex justify-between">
                            <span class="text-[#94A3B8]">Contact</span>
                            <span class="font-medium text-[#1F2937]">{{ $tenant->contact_number ?? '—' }}</span>
                        </div>
                    </div>
                    @if($tenant)
                        <a href="{{ route('admin.users.show', $tenant) }}"
                            class="mt-4 flex items-center justify-center gap-1.5 w-full h-8 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] text-[12px] font-semibold text-[#64748B] hover:bg-[#2AA7A1] hover:text-white hover:border-[#2AA7A1] transition-all">
                            View Profile
                        </a>
                    @endif
                </div>
            </div>

            {{-- Property --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8]">Property</p>
                </div>
                <div class="p-5">
                    @php
                        $property = $reservation->property;
                        $photo    = $property?->media->first();
                    @endphp
                    @if($photo)
                        <img src="{{ $photo->media_url }}" alt="" class="w-full h-32 object-cover rounded-2xl mb-3">
                    @endif
                    <p class="text-[13.5px] font-bold text-[#1F2937]">{{ $property->title ?? '—' }}</p>
                    <p class="text-[12px] text-[#94A3B8] mt-0.5">{{ $property->address ?? '—' }}</p>
                    @if($reservation->unit)
                        <div class="mt-3 pt-3 border-t border-[#E2E8F0] space-y-1.5 text-[12.5px]">
                            <div class="flex justify-between">
                                <span class="text-[#94A3B8]">Unit</span>
                                <span class="font-semibold text-[#1F2937]">{{ $reservation->unit->unit_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#94A3B8]">Rental Fee</span>
                                <span class="font-semibold text-[#1F2937]">₱{{ number_format($reservation->unit->rental_fee, 2) }}/mo</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#94A3B8]">Unit Status</span>
                                <span class="font-semibold text-[#1F2937]">{{ $reservation->unit->availability_status }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Landlord --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0]">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8]">Landlord</p>
                </div>
                <div class="p-5">
                    @php $landlord = $reservation->property?->landlord; @endphp
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                            <span class="text-[#156F8C] text-[12px] font-extrabold">
                                {{ strtoupper(substr($landlord->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($landlord->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#1F2937]">{{ trim(($landlord->first_name ?? '') . ' ' . ($landlord->last_name ?? '')) ?: '—' }}</p>
                            <p class="text-[11.5px] text-[#94A3B8]">{{ $landlord->email ?? '—' }}</p>
                        </div>
                    </div>
                    @if($landlord)
                        <a href="{{ route('admin.users.show', $landlord) }}"
                            class="flex items-center justify-center gap-1.5 w-full h-8 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] text-[12px] font-semibold text-[#64748B] hover:bg-[#2AA7A1] hover:text-white hover:border-[#2AA7A1] transition-all">
                            View Profile
                        </a>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Force Cancel Modal --}}
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="cancelOpen = false"></div>
        <div class="relative bg-white rounded-3xl shadow-xl max-w-md w-full p-7 z-10">
            <div class="w-12 h-12 rounded-2xl bg-[#FBBF24]/[0.10] border border-[#FBBF24]/25 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-[#B45309]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-[16px] font-extrabold text-[#1F2937] text-center mb-1">Force Cancel Reservation?</h3>
            <p class="text-[13px] text-[#94A3B8] text-center mb-5">
                This will cancel the reservation and free the unit. The action will be logged.
            </p>
            <form action="{{ route('admin.reservations.forceCancel', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Admin Note <span class="font-normal normal-case">(optional)</span></label>
                    <textarea name="admin_note" rows="2" placeholder="Reason for cancellation…"
                        class="w-full px-3.5 py-2.5 text-[13px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#FBBF24]/35 focus:border-[#FBBF24] resize-none transition-all"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="cancelOpen = false"
                        class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:bg-[#F7FCFC] transition-colors">
                        Back
                    </button>
                    <button type="submit"
                        class="flex-1 h-10 text-[13.5px] font-bold bg-[#FBBF24] text-white rounded-xl hover:bg-[#FBBF24] transition-colors">
                        Cancel Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Force Reject Modal --}}
    <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
        <div class="relative bg-white rounded-3xl shadow-xl max-w-md w-full p-7 z-10">
            <div class="w-12 h-12 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-[#DC2626]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <h3 class="text-[16px] font-extrabold text-[#1F2937] text-center mb-1">Force Reject Reservation?</h3>
            <p class="text-[13px] text-[#94A3B8] text-center mb-5">
                This will permanently reject this reservation. The unit will be freed if applicable.
            </p>
            <form action="{{ route('admin.reservations.forceReject', $reservation) }}" method="POST">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Rejection Reason <span class="font-normal normal-case">(optional)</span></label>
                    <textarea name="admin_note" rows="2" placeholder="Reason for rejection…"
                        class="w-full px-3.5 py-2.5 text-[13px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#EF4444]/35 focus:border-[#EF4444] resize-none transition-all"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="rejectOpen = false"
                        class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:bg-[#F7FCFC] transition-colors">
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

</div>
@endsection
