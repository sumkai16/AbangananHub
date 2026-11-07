@extends('layouts.admin')

@section('page-title', 'Conversations')

@section('content')
@php
    $statusOptions = ['all' => 'All statuses ('.$counts['all'].')', 'Open' => 'Open ('.$counts['Open'].')', 'Resolved' => 'Resolved ('.$counts['Resolved'].')'];
    $sortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first'];
    $cellHref = fn (array $params) => route('admin.conversations.index', array_filter(array_merge(['search' => $search, 'sort' => $sort !== 'newest' ? $sort : null], $params)));
    $summary = [
        ['label' => 'Total conversations', 'value' => number_format($counts['all']), 'note' => 'Tenant and landlord threads', 'href' => $cellHref([]), 'active' => $status === 'all'],
        ['label' => 'Open', 'value' => number_format($counts['Open']), 'note' => 'Still going', 'dot' => '#FBBF24', 'href' => $status === 'Open' ? $cellHref([]) : $cellHref(['status' => 'Open']), 'active' => $status === 'Open'],
        ['label' => 'Resolved', 'value' => number_format($counts['Resolved']), 'note' => 'Closed', 'dot' => '#94A3B8', 'href' => $status === 'Resolved' ? $cellHref([]) : $cellHref(['status' => 'Resolved']), 'active' => $status === 'Resolved'],
    ];
@endphp

<div class="max-w-[1600px] mx-auto" x-data="{
        view: (() => { try { return localStorage.getItem('adminConversationsView') || 'table'; } catch (e) { return 'table'; } })(),
        setView(v) { this.view = v; try { localStorage.setItem('adminConversationsView', v); } catch (e) {} }
    }">

    <x-page-header icon-path="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" title="Conversations" subtitle="Tenant and landlord threads, for dispute support." />

    <x-stat-strip :cells="$summary" class="mb-4" />

    <form data-live method="GET" action="{{ route('admin.conversations.index') }}" class="mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by tenant, landlord, or property…" aria-label="Search conversations"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="status" :options="$statusOptions" :selected="$status"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="sort" :options="$sortOptions" :selected="$sort"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                @if($search || $status !== 'all' || $sort !== 'newest')
                    <a href="{{ route('admin.conversations.index') }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                @endif

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-10 p-1 rounded-xl border border-[#5B6A8E]/25 bg-white">
                    <button type="button" x-on:click="setView('grid')" aria-label="Grid view"
                        :class="view === 'grid' ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-8 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    </button>
                    <button type="button" x-on:click="setView('table')" aria-label="List view"
                        :class="view === 'table' ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-8 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- List --}}
    @if($conversations->isEmpty())
        <x-card flush><x-admin.empty title="No conversations found" :note="($search || $status !== 'all') ? 'Try a different search or filter.' : null" /></x-card>
    @else
        <x-card flush class="divide-y divide-[#E2E4EC]" x-show="view === 'table'">
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

        {{-- Grid view --}}
        <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach($conversations as $conv)
                <a href="{{ route('admin.conversations.show', $conv) }}" class="group block rounded-xl border border-[#E2E4EC] bg-white p-4 transition-shadow duration-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 truncate text-[13px] font-semibold text-[#060D26]">{{ $conv->property->title ?? '—' }}</p>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-bold {{ $conv->status === 'Resolved' ? 'bg-[#E2E4EC] text-[#5B6A8E]' : 'bg-[#FBBF24]/15 text-[#B45309]' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $conv->status === 'Resolved' ? 'bg-[#94A3B8]' : 'bg-[#FBBF24]' }}"></span>{{ $conv->status ?? 'Open' }}
                        </span>
                    </div>
                    <p class="mt-2 truncate text-[12.5px] text-[#060D26]">{{ trim(($conv->tenant->first_name ?? '') . ' ' . ($conv->tenant->last_name ?? '')) ?: '—' }}
                        <span class="text-[#5B6A8E]">with</span> {{ trim(($conv->landlord->first_name ?? '') . ' ' . ($conv->landlord->last_name ?? '')) ?: '—' }}</p>
                    @if($conv->latestMessage)
                        <p class="mt-2 line-clamp-2 text-[12.5px] text-[#5B6A8E]">{{ $conv->latestMessage->message }}</p>
                        <p class="mt-1 text-[11px] text-[#5B6A8E]">{{ $conv->latestMessage->sent_at?->diffForHumans() }}</p>
                    @else
                        <p class="mt-2 text-[12px] italic text-[#5B6A8E]">No messages</p>
                    @endif
                </a>
            @endforeach
        </div>
        @if($conversations->hasPages())
            <div class="mt-4 bg-white border border-[#E2E4EC] rounded-2xl px-6 py-3 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">{{ $conversations->links() }}</div>
        @endif
    @endif

</div>
@endsection
