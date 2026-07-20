@extends('layouts.admin')

@section('page-title', 'Conversations')

@section('content')
<div class="max-w-7xl">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Conversations</h1>
            <p class="text-sm text-[#64748B] mt-1">Read-only access to all tenant–landlord conversations for dispute support.</p>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        @php
            $convStats = [
                ['label' => 'Total', 'value' => $counts['all'], 'accent' => 'text-[#156F8C]', 'dot' => 'bg-[#156F8C]', 'sub' => null],
                ['label' => 'Open', 'value' => $counts['Open'], 'accent' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]', 'sub' => 'Active threads'],
                ['label' => 'Resolved', 'value' => $counts['Resolved'], 'accent' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]', 'sub' => null],
            ];
        @endphp
        @foreach ($convStats as $stat)
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-4 py-3.5 shadow-lg">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $stat['dot'] }}"></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">{{ $stat['label'] }}</p>
                </div>
                <p class="text-2xl font-bold {{ $stat['accent'] }} mt-1">{{ number_format($stat['value']) }}</p>
                @if($stat['sub'])
                    <p class="text-[11px] text-[#94A3B8] mt-0.5">{{ $stat['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.conversations.index') }}"
        class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-4 mb-5 shadow-lg flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant, landlord, or property…" aria-label="Search by tenant, landlord, or property"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC]/50 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
        </div>
        <select name="status"
            class="h-10 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC]/50 px-3 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
            <option value="all"     {{ $status === 'all'     ? 'selected' : '' }}>All statuses</option>
            <option value="Open"    {{ $status === 'Open'    ? 'selected' : '' }}>Open</option>
            <option value="Resolved"{{ $status === 'Resolved'? 'selected' : '' }}>Resolved</option>
        </select>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-all duration-200 shadow-sm cursor-pointer">
            Filter
        </button>
        @if($search || $status !== 'all')
            <a href="{{ route('admin.conversations.index') }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] transition-all duration-200 flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- List --}}
    @if($conversations->isEmpty())
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-white/30 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No conversations found</p>
            <p class="text-[13px] text-[#64748B] mt-1">{{ $search ? 'Try adjusting your search.' : 'None yet.' }}</p>
        </div>
    @else
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden divide-y divide-[#E2E8F0]">
            @foreach($conversations as $conv)
                <a href="{{ route('admin.conversations.show', $conv) }}"
                    class="flex flex-wrap sm:flex-nowrap items-center gap-4 px-6 py-4 hover:bg-[#F7FCFC]/70 transition-all duration-200 group">
                    <div class="min-w-0 flex-1 basis-40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Tenant</p>
                        <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">
                            {{ trim(($conv->tenant->first_name ?? '') . ' ' . ($conv->tenant->last_name ?? '')) ?: '—' }}
                        </p>
                        <p class="text-[12px] text-[#64748B] truncate">{{ $conv->tenant->email ?? '—' }}</p>
                    </div>

                    <div class="min-w-0 flex-1 basis-32">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Landlord</p>
                        <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">
                            {{ trim(($conv->landlord->first_name ?? '') . ' ' . ($conv->landlord->last_name ?? '')) ?: '—' }}
                        </p>
                    </div>

                    <div class="min-w-0 flex-1 basis-40">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Property</p>
                        <p class="text-[13.5px] text-[#1F2937] truncate">{{ $conv->property->title ?? '—' }}</p>
                    </div>

                    <div class="min-w-0 flex-1 basis-52">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-[#94A3B8]">Last Message</p>
                        @if($conv->latestMessage)
                            <p class="text-[12.5px] text-[#64748B] truncate">{{ $conv->latestMessage->message }}</p>
                            <p class="text-[11px] text-[#94A3B8] mt-0.5">{{ $conv->latestMessage->sent_at?->diffForHumans() }}</p>
                        @else
                            <span class="text-[12px] text-[#94A3B8] italic">No messages</span>
                        @endif
                    </div>

                    <div class="shrink-0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold
                            {{ $conv->status === 'Resolved' ? 'bg-[#E2E8F0] text-[#64748B]' : 'bg-[#FBBF24]/15 text-[#B45309]' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $conv->status === 'Resolved' ? 'bg-[#94A3B8]' : 'bg-[#FBBF24]' }}"></span>
                            {{ $conv->status ?? 'Open' }}
                        </span>
                    </div>

                    <svg class="w-4 h-4 text-[#94A3B8] group-hover:text-[#2AA7A1] group-hover:translate-x-0.5 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endforeach
        </div>
        @if($conversations->hasPages())
            <div class="mt-4 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-6 py-3 shadow-lg">{{ $conversations->links() }}</div>
        @endif
    @endif

</div>
@endsection
