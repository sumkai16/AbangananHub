@extends('layouts.admin')

@section('page-title', 'Landlord Payouts')

@section('content')
@php
    $needsCount = $failed->count() + $toApprove->count();
    $historyBy = [
        'processing' => $payouts->where('status', 'Processing')->values(),
        'failed' => $payouts->where('status', 'Failed')->values(),
        'sent' => $payouts->where('status', 'Sent')->values(),
    ];
    $tabs = [
        'owed' => ['Owed', $pending->count()],
        'processing' => ['Processing', $historyBy['processing']->count()],
        'failed' => ['Failed', $historyBy['failed']->count()],
        'sent' => ['Sent', $historyBy['sent']->count()],
    ];
@endphp
<div class="admin-compact admin-fill">

    <x-admin.header title="Payouts"
        subtitle="Money the platform owes landlords. {{ $automatic ? 'It is sent to their GCash automatically; PayMongo charges about ₱10 per transfer (one free a week).' : 'Automatic payouts are off: pay landlords by hand.' }}">
        @if ($nextRun)
            <x-slot:actions>
                <p class="inline-flex h-8 items-center gap-2 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26]">
                    <span class="h-2 w-2 rounded-full bg-[#15803D]" aria-hidden="true"></span>
                    Automatic payouts on · next run {{ $nextRun->format('g:i A') }}@if ($summary['readyCount'] > 0) · up to ₱{{ number_format($summary['fees']) }} in fees @endif
                </p>
            </x-slot:actions>
        @endif
    </x-admin.header>

    @if (! $automatic)
        <div class="mb-6 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4 text-[13px] leading-6 text-[#B45309]">
            <p class="font-semibold">Manual mode: automatic payouts are switched off.</p>
            <p>Nothing is sent to PayMongo. Send each landlord's GCash by hand from your own GCash or the PayMongo dashboard, then press <strong>Mark Paid Out</strong> and type the reference. Turn automatic payouts back on in <a href="{{ route('admin.settings.edit') }}" class="underline font-semibold">System Settings</a> once PayMongo can send to GCash.</p>
        </div>
    @endif

    @if ($automatic && ! empty($configMissing))
        <div class="mb-6 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4 text-[13px] leading-6 text-[#B45309]">
            <p class="font-semibold">Automatic payouts are off.</p>
            <p>Set {{ implode(', ', $configMissing) }} in <code>.env</code> to turn them on. Until then money stays owed and you can send it by hand below, then press Mark Paid Out.</p>
        </div>
    @endif

    @include('admin.payouts.partials.summary')

    <div class="flex flex-1 flex-col" x-data="{ tab: 'owed', q: '', needs: false, open: null }" x-on:keydown.escape.window="needs = false">

        {{-- Tabs, the Needs you button and search --}}
        <div class="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-2 border-b border-[#E2E4EC]" role="tablist">
            <div class="flex flex-wrap gap-x-6">
                @foreach ($tabs as $key => [$label, $count])
                    <button type="button" role="tab" x-on:click="tab = '{{ $key }}'" x-bind:aria-selected="tab === '{{ $key }}'"
                        x-bind:class="tab === '{{ $key }}' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#94A3B8] hover:text-[#060D26]'"
                        class="-mb-px border-b-2 pb-3 text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer">
                        {{ $label }} <span class="ml-1 tabular-nums" x-bind:class="tab === '{{ $key }}' ? 'text-[#156F8C]' : ''">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <div class="relative mb-2 flex flex-wrap items-center gap-2.5" x-on:click.outside="needs = false">
                @if ($needsCount > 0)
                    <button type="button" x-on:click="needs = ! needs" x-bind:aria-expanded="needs" aria-controls="needs-panel"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-[#FF8A66] bg-[#FF8A66]/[0.14] px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#FF8A66]/[0.26] cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        Needs you
                        <span class="inline-grid h-5 min-w-5 place-items-center rounded-full bg-[#060D26] px-1.5 text-[11.5px] font-bold tabular-nums text-white">{{ $needsCount }}</span>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="transition-transform duration-200" x-bind:class="needs && 'rotate-180'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                @else
                    <span class="text-[12.5px] text-[#5B6A8E]">Nothing needs you right now.</span>
                @endif
                <div class="relative w-full sm:w-60" x-show="tab === 'owed'">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
                    <input type="search" x-model="q" placeholder="Search landlords" aria-label="Search landlords"
                        class="h-9 w-full rounded-lg border border-[#E2E4EC] bg-white pl-8 pr-3 text-[13px] text-[#060D26] placeholder-[#5B6A8E] transition-colors duration-200 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">
                </div>

                @if ($needsCount > 0)
                    @include('admin.payouts.partials.needs-panel')
                @endif
            </div>
        </div>

        <div class="flex flex-1 flex-col" x-show="tab === 'owed'" role="tabpanel">
            @include('admin.payouts.partials.owed')
        </div>
        @foreach ([
            'processing' => ['Nothing is with PayMongo', 'Payouts that were sent and are still going through show here.'],
            'failed' => ['No failed payouts', 'A payout PayMongo could not send shows here, with a Retry button.'],
            'sent' => ['No payouts sent yet', 'Money that has reached a landlord shows here.'],
        ] as $key => $empty)
            <div class="flex flex-1 flex-col" x-show="tab === '{{ $key }}'" x-cloak role="tabpanel">
                @include('admin.payouts.partials.history', ['rows' => $historyBy[$key], 'empty' => $empty])
            </div>
        @endforeach
    </div>

    @include('admin.payouts.partials.modal')
</div>
@endsection
