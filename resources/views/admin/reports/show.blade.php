@extends('layouts.admin')

@section('page-title', 'Report Detail')

@section('content')
    <div class="max-w-5xl mx-auto">

        {{-- Back --}}
        <a href="{{ route('admin.reports.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#060D26] transition-colors mb-5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to reports
        </a>



        {{-- Header --}}
        <x-card flush
            class="px-5 py-4 mb-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-[16px] font-normal text-[#060D26] leading-tight">Report #{{ $report->report_id }}</h1>
                <p class="text-[12px] text-[#94A3B8] mt-0.5">Submitted {{ $report->created_at->format('M d, Y \a\t g:i A') }}
                </p>
            </div>
            <span
                class="inline-flex items-center gap-1.5 text-[12px] font-semibold px-3 py-1.5 rounded-full
                                {{ $report->isPending() ? 'bg-[#FBBF24]/[0.10] text-[#B45309] ring-1 ring-[#FBBF24]/35' : 'bg-[#22C55E]/[0.07] text-[#15803D] ring-1 ring-[#22C55E]/25' }}">
                <span
                    class="w-1.5 h-1.5 rounded-full {{ $report->isPending() ? 'bg-[#FBBF24]' : 'bg-[#22C55E]' }}"></span>
                {{ $report->report_status }}
            </span>
        </x-card>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            {{-- Reporter --}}
            <x-card>
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Reported By</p>
                <p class="text-[14px] font-semibold text-[#060D26]">
                    {{ $report->reporter ? $report->reporter->first_name . ' ' . $report->reporter->last_name : '—' }}
                </p>
                <p class="text-[12.5px] text-[#94A3B8] mt-0.5">{{ $report->reporter->email ?? '' }}</p>
            </x-card>

            {{-- Target --}}
            <x-card>
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Target</p>
                @if($report->property)
                    <p class="text-[14px] font-semibold text-[#060D26]">{{ $report->property->title }}</p>
                    <p class="text-[12.5px] text-[#94A3B8] mt-0.5">Property Listing</p>
                @elseif($report->reportedUser)
                    <p class="text-[14px] font-semibold text-[#060D26]">{{ $report->reportedUser->first_name }}
                        {{ $report->reportedUser->last_name }}
                    </p>
                    <p class="text-[12.5px] text-[#94A3B8] mt-0.5">{{ $report->reportedUser->email }} · User</p>
                @else
                    <p class="text-[13px] text-[#94A3B8]">No target recorded.</p>
                @endif
            </x-card>
        </div>

        {{-- Reason --}}
        <x-card class="mb-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Reason</p>
            <p class="text-[13.5px] text-[#060D26] leading-relaxed whitespace-pre-line">{{ $report->report_reason }}</p>
        </x-card>

        {{-- Payment dispute: the evidence in one place, and the landlord's record --}}
        @if($claim)
            @php
                $dispReservation = $claim->reservation;
                $dispWhat = $claim->claim_type === 'MoveIn' ? 'Move-in payment' : 'Rent for '.$claim->billing_period?->format('F Y');
            @endphp
            <x-card class="mb-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-3">Payment dispute</p>
                <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-[13px]">
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">What</dt><dd class="font-semibold text-[#060D26]">{{ $dispWhat }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">Amount</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($claim->amount, 2) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">Method</dt><dd class="font-semibold text-[#060D26]">{{ $claim->payment_method }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">Reference</dt><dd class="font-mono font-semibold text-[#060D26]">{{ $claim->reference_no ?? 'None (cash)' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">Paid on</dt><dd class="font-semibold text-[#060D26]">{{ $claim->paid_on->format('M d, Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#94A3B8]">Landlord rejected</dt><dd class="font-semibold text-[#060D26]">{{ $claim->decided_at?->format('M d, Y') ?? '—' }}</dd></div>
                </dl>
                @if($claim->note)<p class="mt-2 text-[12.5px] italic text-[#5B6A8E]">Tenant&rsquo;s note: &ldquo;{{ $claim->note }}&rdquo;</p>@endif
                <p class="mt-2 text-[13px] text-[#060D26]"><span class="text-[#94A3B8]">Landlord&rsquo;s reason:</span> &ldquo;{{ $claim->rejection_reason }}&rdquo;</p>

                @if($landlordRecord)
                    <div class="mt-3 rounded-xl border px-3 py-2.5 text-[12.5px] {{ $landlordRecord['disputed'] > 1 ? 'border-[#EF4444]/30 bg-[#EF4444]/[0.05] text-[#B91C1C]' : 'border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26]' }}">
                        <span class="font-bold">This landlord&rsquo;s record:</span>
                        {{ $landlordRecord['confirmed'] }} reports confirmed, {{ $landlordRecord['rejected'] }} rejected,
                        <span class="font-bold">{{ $landlordRecord['disputed'] }} sent to admin</span>{{ $landlordRecord['disputed'] > 1 ? ' — a pattern worth acting on.' : '.' }}
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap gap-2 text-[12.5px] font-bold">
                    @if($dispReservation)
                        <a href="{{ route('admin.reservations.show', $dispReservation) }}" class="rounded-lg border border-[#E2E4EC] bg-white px-3 py-2 text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Open reservation #{{ $dispReservation->reservation_id }}</a>
                        @if($dispReservation->conversation_id)
                            <a href="{{ route('admin.conversations.show', $dispReservation->conversation_id) }}" class="rounded-lg border border-[#E2E4EC] bg-white px-3 py-2 text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Read the chat</a>
                        @endif
                    @endif
                </div>
                <p class="mt-3 text-[12px] text-[#5B6A8E]">This money went straight to the landlord, so AbangananHub cannot refund it. The actions below act on the landlord&rsquo;s account or listing.</p>
            </x-card>
        @endif

        {{-- Actions --}}
        @if($report->isPending())
            <x-card>
                <h2 class="text-[14px] font-normal text-[#060D26] mb-4">Resolve this report</h2>
                <form method="POST" action="{{ route('admin.reports.resolve', $report) }}"
                    data-confirm="Resolve this report with the selected action?" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">What did you find? <span
                                class="text-[#EF4444]">*</span></label>
                        <textarea name="admin_notes" rows="3" maxlength="1000" required
                            placeholder="Describe what you found after reviewing this report"
                            class="w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-[13px] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition"></textarea>
                        @error('admin_notes')
                            <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">Action <span
                                class="text-[#EF4444]">*</span></label>
                        @php
                            $actionTakenOptions = ['none' => 'No action needed'];
                            if ($report->reported_user_id || $report->property) {
                                $actionTakenOptions['warn_user'] = 'Warn reported user (notification, no suspension)';
                                $actionTakenOptions['suspend_user'] = "Suspend reported user's account";
                            }
                            if ($report->property) {
                                $actionTakenOptions['delist_property'] = 'Delist reported property';
                            }
                        @endphp
                        <x-styled-select name="action_taken" :options="$actionTakenOptions" selected="none" required
                            class="h-11 w-full rounded-xl border border-[#E2E4EC] px-3.5 text-[13px] text-[#060D26]" />
                        @error('action_taken')
                            <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 h-11 px-6 rounded-full bg-[#FF8A66] text-[#060D26] text-[13.5px] font-semibold hover:bg-[#E96F4F] transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                        Resolve report
                    </button>
                </form>
            </x-card>
        @else
            <x-card>
                <h2 class="text-[14px] font-normal text-[#060D26] mb-3">Resolution</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Admin notes</p>
                        <p class="text-[13px] text-[#060D26] leading-relaxed">{{ $report->admin_notes ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Action taken</p>
                        <p class="text-[13px] text-[#060D26]">{{ $report->action_taken ?? 'None' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1">Resolved by</p>
                        <p class="text-[13px] text-[#060D26]">
                            {{ $report->resolver ? $report->resolver->first_name . ' ' . $report->resolver->last_name : '—' }}
                            @if($report->resolved_at)
                                <span class="text-[#94A3B8]"> — {{ $report->resolved_at->format('M d, Y \a\t g:i A') }}</span>
                            @endif
                        </p>
                    </div>
                </div>
            </x-card>
        @endif

    </div>
@endsection