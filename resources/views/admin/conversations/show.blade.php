@extends('layouts.admin')

@section('page-title', 'Conversation')

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Back + header --}}
    <div class="flex items-start gap-4 mb-6">
        <a href="{{ route('admin.conversations.index') }}"
            class="mt-0.5 inline-flex items-center gap-1.5 h-9 px-3.5 text-[13px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] hover:bg-[#F7FCFC] transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-xl font-extrabold text-[#1F2937] tracking-tight">Conversation Thread</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border
                    {{ ($conversation->status ?? 'Open') === 'Resolved' ? 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]' : 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25' }}">
                    {{ $conversation->status ?? 'Open' }}
                </span>
                <span class="text-[12px] text-[#94A3B8] font-medium">Read-only · Admin view</span>
            </div>
            @if($conversation->property)
                <p class="text-[13px] text-[#64748B] mt-1">
                    Re: <span class="font-semibold text-[#1F2937]">{{ $conversation->property->title }}</span>
                    @if($conversation->unit) · Unit {{ $conversation->unit->unit_number }} @endif
                </p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Left: message thread --}}
        <div class="lg:col-span-2">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
                    <h2 class="text-[13.5px] font-bold text-[#1F2937]">Messages</h2>
                    <span class="text-[12px] text-[#94A3B8]">{{ $conversation->messages->count() }} message{{ $conversation->messages->count() !== 1 ? 's' : '' }}</span>
                </div>

                {{-- Same stepper the participants see, from the same partial —
                     an admin arbitrating a dispute needs the stage the parties
                     are looking at, not a second reading of it. --}}
                @if ($reservation)
                    <div class="px-6 pt-4 pb-3 border-b border-[#E2E8F0] bg-[#F7FCFC]">
                        @include('conversations.partials._stage-stepper', ['reservation' => $reservation])
                    </div>
                @endif

                @if($conversation->messages->isEmpty())
                    <div class="p-12 text-center">
                        <svg class="w-10 h-10 text-[#94A3B8] mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                        <p class="text-[13px] text-[#94A3B8]">No messages in this conversation yet.</p>
                    </div>
                @else
                    <div class="p-5 space-y-4 max-h-[600px] overflow-y-auto">
                        @php
                            $tenantId   = $conversation->tenant_id ?? null;
                            $landlordId = $conversation->landlord_id ?? null;
                        @endphp
                        @foreach($conversation->messages as $msg)
                            @php
                                $isTenant   = $msg->sender_id === $tenantId;
                                $senderName = trim(($msg->sender->first_name ?? '') . ' ' . ($msg->sender->last_name ?? '')) ?: 'Unknown';
                                $roleLabel  = $isTenant ? 'Tenant' : 'Landlord';
                            @endphp
                            <div class="flex {{ $isTenant ? 'flex-row' : 'flex-row-reverse' }} items-end gap-2.5">
                                {{-- Avatar --}}
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-[12px] font-bold
                                    {{ $isTenant ? 'bg-[#2AA7A1]/10 text-[#156F8C]' : 'bg-[#22C55E]/[0.07] text-[#15803D]' }}">
                                    {{ strtoupper(substr($msg->sender->first_name ?? '?', 0, 1)) }}
                                </div>
                                {{-- Bubble --}}
                                <div class="max-w-[75%]">
                                    <p class="text-[10.5px] font-semibold {{ $isTenant ? 'text-[#156F8C]' : 'text-[#15803D] text-right' }} mb-1">
                                        {{ $senderName }} · {{ $roleLabel }}
                                    </p>
                                    <div class="px-4 py-2.5 rounded-2xl text-[13px] leading-relaxed
                                        {{ $isTenant
                                            ? 'bg-[#2AA7A1]/10 text-[#1F2937] rounded-bl-sm border border-[#2AA7A1]/10'
                                            : 'bg-[#EEF8F8] text-[#1F2937] rounded-br-sm border border-[#E2E8F0]' }}">
                                        {{ $msg->message }}
                                    </div>
                                    <p class="text-[10.5px] text-[#94A3B8] mt-1 {{ $isTenant ? '' : 'text-right' }}">
                                        {{ $msg->sent_at?->format('M d, Y · g:i A') ?? $msg->created_at->format('M d, Y · g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="px-6 py-3 border-t border-[#E2E8F0] bg-[#FBBF24]/[0.10]">
                    <p class="text-[11px] text-[#B45309] font-medium text-center">
                        Admin view only — you cannot send messages in this conversation.
                    </p>
                </div>
            </div>
        </div>

        {{-- Right: participants + property --}}
        <div class="space-y-4">

            {{-- Move-in & escrow — read-only mirror of what the two parties act
                 on in their own thread. This is the state an admin actually
                 needs when a move-in dispute lands in the queue. --}}
            @if ($reservation)
                @php
                    $heldPayment = $reservation->payments->firstWhere('status', 'Held');
                    $releasedPayment = $reservation->payments->firstWhere('status', 'Released');
                    $onTurnoverClock = $reservation->isTurnoverClock();
                    $deadlineAt = $onTurnoverClock
                        ? ($reservation->move_in_deadline_at ?? $reservation->computeTurnoverDeadline())
                        : $reservation->move_in_deadline_at;
                @endphp
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                    <p class="text-[10.5px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2.5">Move-in &amp; escrow</p>

                    @if ($reservation->move_in_disputed_at)
                        <div class="mb-3 rounded-xl bg-[#FBBF24]/[0.10] px-3 py-2.5">
                            <p class="text-[12px] font-bold text-[#B45309]">Disputed — awaiting your review</p>
                            <p class="mt-0.5 text-[11.5px] text-[#B45309]">
                                Reported {{ $reservation->move_in_disputed_at->diffForHumans() }}. The countdown is paused.
                            </p>
                            @if ($reservation->move_in_dispute_reason)
                                <p class="mt-1.5 text-[11.5px] text-[#1F2937] italic">
                                    "{{ $reservation->move_in_dispute_reason }}"</p>
                            @endif
                        </div>
                    @endif

                    <dl class="space-y-2 text-[12.5px]">
                        <div class="flex justify-between gap-3">
                            <dt class="text-[#94A3B8] shrink-0">Escrow</dt>
                            <dd class="font-semibold text-right {{ $heldPayment ? 'text-[#156F8C]' : ($releasedPayment ? 'text-[#15803D]' : 'text-[#64748B]') }}">
                                @if ($heldPayment)
                                    Held &middot; &#8369;{{ number_format($heldPayment->amount, 2) }}
                                @elseif ($releasedPayment)
                                    Released &middot; &#8369;{{ number_format($releasedPayment->amount, 2) }}
                                @else
                                    Not funded
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[#94A3B8] shrink-0">Clock</dt>
                            <dd class="font-semibold text-[#1F2937] text-right">
                                {{ $onTurnoverClock ? 'Key turnover (landlord)' : 'Move-in confirmation (tenant)' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[#94A3B8] shrink-0">Deadline</dt>
                            <dd class="font-semibold text-[#1F2937] text-right">
                                {{ $deadlineAt?->format('M d, Y') ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[#94A3B8] shrink-0">Handover</dt>
                            <dd class="font-semibold text-right {{ $reservation->hasConfirmedHandover() ? 'text-[#15803D]' : 'text-[#64748B]' }}">
                                @if ($reservation->hasConfirmedHandover())
                                    {{ $reservation->handover_at->format('M d, g:i A') }}
                                @elseif ($reservation->hasProposedHandover())
                                    {{ $reservation->handover_at->format('M d, g:i A') }} (proposed)
                                @else
                                    Not scheduled
                                @endif
                            </dd>
                        </div>
                        @if ($reservation->keys_turned_over_at)
                            <div class="flex justify-between gap-3">
                                <dt class="text-[#94A3B8] shrink-0">Keys marked</dt>
                                <dd class="font-semibold text-[#1F2937] text-right">
                                    {{ $reservation->keys_turned_over_at->format('M d, Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Tenant card --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2.5">Tenant</p>
                @if($conversation->tenant)
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-9 h-9 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center text-[13px] font-bold text-[#156F8C]">
                            {{ strtoupper(substr($conversation->tenant->first_name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#1F2937]">
                                {{ trim($conversation->tenant->first_name . ' ' . $conversation->tenant->last_name) }}
                            </p>
                            <p class="text-[11px] text-[#94A3B8]">{{ $conversation->tenant->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.users.show', $conversation->tenant) }}"
                        class="block w-full text-center py-2 text-[12px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:bg-[#F7FCFC] transition-colors">
                        View Profile
                    </a>
                @else
                    <p class="text-[13px] text-[#94A3B8] italic">No tenant linked.</p>
                @endif
            </div>

            {{-- Landlord card --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2.5">Landlord</p>
                @if($conversation->landlord)
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-9 h-9 rounded-full bg-[#22C55E]/[0.07] flex items-center justify-center text-[13px] font-bold text-[#15803D]">
                            {{ strtoupper(substr($conversation->landlord->first_name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#1F2937]">
                                {{ trim($conversation->landlord->first_name . ' ' . $conversation->landlord->last_name) }}
                            </p>
                            <p class="text-[11px] text-[#94A3B8]">{{ $conversation->landlord->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.users.show', $conversation->landlord) }}"
                        class="block w-full text-center py-2 text-[12px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:bg-[#F7FCFC] transition-colors">
                        View Profile
                    </a>
                @else
                    <p class="text-[13px] text-[#94A3B8] italic">No landlord linked.</p>
                @endif
            </div>

            {{-- Property card --}}
            @if($conversation->property)
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                    <p class="text-[10.5px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2.5">Property</p>
                    <p class="text-[13.5px] font-bold text-[#1F2937] mb-0.5">{{ $conversation->property->title }}</p>
                    <p class="text-[12px] text-[#94A3B8] mb-1">{{ $conversation->property->address ?? '' }}</p>
                    @if($conversation->unit)
                        <p class="text-[12px] font-semibold text-[#156F8C]">Unit {{ $conversation->unit->unit_number }}</p>
                    @endif
                </div>
            @endif

            {{-- Meta --}}
            <div class="bg-[#F7FCFC] border border-[#E2E8F0] rounded-2xl p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2.5">Details</p>
                <div class="space-y-2 text-[12.5px]">
                    <div class="flex justify-between">
                        <span class="text-[#94A3B8]">Started</span>
                        <span class="font-semibold text-[#1F2937]">{{ $conversation->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#94A3B8]">Last activity</span>
                        <span class="font-semibold text-[#1F2937]">{{ $conversation->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#94A3B8]">Status</span>
                        <span class="font-bold {{ ($conversation->status ?? 'Open') === 'Resolved' ? 'text-[#64748B]' : 'text-[#156F8C]' }}">
                            {{ $conversation->status ?? 'Open' }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
