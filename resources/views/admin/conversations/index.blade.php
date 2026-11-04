@extends('layouts.admin')

@section('page-title', 'Conversations')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" title="Conversations" subtitle="Tenant and landlord threads, for dispute support." />

    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'All', 'value' => number_format($counts['all']), 'href' => route('admin.conversations.index', array_filter(['search' => $search])), 'active' => $status === 'all'],
        ['label' => 'Open', 'value' => number_format($counts['Open']), 'href' => route('admin.conversations.index', array_filter(['status' => 'Open', 'search' => $search])), 'active' => $status === 'Open'],
        ['label' => 'Resolved', 'value' => number_format($counts['Resolved']), 'href' => route('admin.conversations.index', array_filter(['status' => 'Resolved', 'search' => $search])), 'active' => $status === 'Resolved'],
    ]" />

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.conversations.index') }}"
        class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant, landlord, or property…" aria-label="Search by tenant, landlord, or property"
                x-on:input.debounce.400ms="$el.form.requestSubmit()"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
        </div>
        <x-styled-select name="status" :options="['all' => 'All statuses', 'Open' => 'Open', 'Resolved' => 'Resolved']"
            :selected="$status"
            class="h-10 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white px-3" />
        <button type="submit" class="h-10 rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Filter</button>
        @if($search || $status !== 'all')
            <a href="{{ route('admin.conversations.index') }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:text-[#060D26] transition-all duration-200 flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- List --}}
    @if($conversations->isEmpty())
        <x-card flush><x-admin.empty title="No conversations found" :note="$search ? 'Try a different search.' : null" /></x-card>
    @else
        <x-card flush class="divide-y divide-[#E2E4EC]">
            @foreach($conversations as $conv)
                <a href="{{ route('admin.conversations.show', $conv) }}"
                    class="flex flex-wrap sm:flex-nowrap items-center gap-4 px-6 py-4 hover:bg-[#F7F8FC]/70 transition-all duration-200 group">
                    <div class="min-w-0 flex-1 basis-40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Tenant</p>
                        <p class="text-[13.5px] font-semibold text-[#060D26] truncate">
                            {{ trim(($conv->tenant->first_name ?? '') . ' ' . ($conv->tenant->last_name ?? '')) ?: '—' }}
                        </p>
                        <p class="text-[12px] text-[#5B6A8E] truncate">{{ $conv->tenant->email ?? '—' }}</p>
                    </div>

                    <div class="min-w-0 flex-1 basis-32">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Landlord</p>
                        <p class="text-[13.5px] font-semibold text-[#060D26] truncate">
                            {{ trim(($conv->landlord->first_name ?? '') . ' ' . ($conv->landlord->last_name ?? '')) ?: '—' }}
                        </p>
                    </div>

                    <div class="min-w-0 flex-1 basis-40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Property</p>
                        <p class="text-[13.5px] text-[#060D26] truncate">{{ $conv->property->title ?? '—' }}</p>
                    </div>

                    <div class="min-w-0 flex-1 basis-52">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Last Message</p>
                        @if($conv->latestMessage)
                            <p class="text-[12.5px] text-[#5B6A8E] truncate">{{ $conv->latestMessage->message }}</p>
                            <p class="text-[11px] text-[#5B6A8E] mt-0.5">{{ $conv->latestMessage->sent_at?->diffForHumans() }}</p>
                        @else
                            <span class="text-[12px] text-[#5B6A8E] italic">No messages</span>
                        @endif
                    </div>

                    <div class="shrink-0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold
                            {{ $conv->status === 'Resolved' ? 'bg-[#E2E4EC] text-[#5B6A8E]' : 'bg-[#FBBF24]/15 text-[#B45309]' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $conv->status === 'Resolved' ? 'bg-[#94A3B8]' : 'bg-[#FBBF24]' }}"></span>
                            {{ $conv->status ?? 'Open' }}
                        </span>
                    </div>

                    <svg class="w-4 h-4 text-[#5B6A8E] group-hover:text-[#B35A3D] group-hover:translate-x-0.5 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endforeach
        </x-card>
        @if($conversations->hasPages())
            <div class="mt-4 bg-white border border-[#E2E4EC] rounded-2xl px-6 py-3 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">{{ $conversations->links() }}</div>
        @endif
    @endif

</div>
@endsection
