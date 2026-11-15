@extends('layouts.landlord')

@section('page-title', 'Concerns')

{{--
    Concerns: what tenants reported from their tenancy chat, in one list. Built on the same pattern as My Complaints so
    the two pages feel like one product: stat cards (they filter by status), one filter bar, report-style cards, and a
    right column with a white card and a navy note. Start work / Mark fixed change the status in place through the same
    route the chat card uses (concerns.status), so the tenant is told and the chat gets its system line.
--}}
@section('content')
@php
    $warn = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z';
    $propertyOptions = ['' => 'All properties'] + $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->title])->all();
    $categoryOptions = ['' => 'All categories'] + array_combine(\App\Models\TenantConcern::CATEGORIES, \App\Models\TenantConcern::CATEGORIES);
    $query = fn (array $over) => array_filter(array_merge(['status' => $status === 'active' ? null : $status, 'property' => $propertyId, 'category' => $category], $over));
    $selectClass = 'h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26]';
    $card = 'bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)]';
    // Status chip: a tinted pill with a dot, as on My Complaints.
    $chip = [
        'Open' => ['bg-[#FBBF24]/[0.10] text-[#B45309]', 'bg-[#FBBF24]'],
        'In Progress' => ['bg-[#060D26]/[0.06] text-[#060D26]', 'bg-[#060D26]'],
        'Fixed' => ['bg-[#22C55E]/[0.07] text-[#15803D]', 'bg-[#22C55E]'],
    ];
    $stats = [
        ['active', 'Needs attention', $counts['active'], 'Open and in progress', '#060D26', '#ECEEF6', '#060D26', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
        ['Open', 'Open', $counts['Open'], 'Not started yet', '#B45309', 'rgba(251,191,36,0.10)', '#B45309', $warn],
        ['In Progress', 'In progress', $counts['In Progress'], 'Being worked on', '#060D26', '#ECEEF6', '#060D26', 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63'],
        ['Fixed', 'Fixed', $counts['Fixed'], 'Done', '#15803D', 'rgba(34,197,94,0.07)', '#059669', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
    ];
@endphp

<div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

    {{-- Header --}}
    <x-page-header title="Concerns" subtitle="What your tenants reported from their chat. Work through them here.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}" /></svg>
        </x-slot:icon>
    </x-page-header>

    {{-- Stat cards: each one is a status filter --}}
    <div class="stat-chips grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        @foreach($stats as [$key, $label, $value, $sub, $color, $iconBg, $iconStroke, $path])
            <x-stat-card :label="$label" :value="$value" :sub="$sub" :value-color="$color" :icon-bg="$iconBg"
                :href="route('landlord.concerns.index', $query(['status' => $key === 'active' ? null : $key]))"
                :aria-current="$status === $key ? 'true' : null"
                class="{{ $status === $key ? 'ring-2 ring-[#FF8A66]' : '' }}">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $iconStroke }}" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" /></svg>
                </x-slot:icon>
            </x-stat-card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 items-start">
        <div class="min-w-0">

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('landlord.concerns.index') }}" class="{{ $card }} p-4 mb-6">
                <div class="filter-row flex flex-wrap items-center gap-2.5">
                    <x-styled-select name="status" :options="$filters" :selected="$status" class="{{ $selectClass }}" />
                    <x-styled-select name="property" :options="$propertyOptions" :selected="(string) ($propertyId ?? '')" class="{{ $selectClass }}" />
                    <x-styled-select name="category" :options="$categoryOptions" :selected="(string) ($category ?? '')" class="{{ $selectClass }}" />

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13.5px] font-semibold hover:bg-[#E96F4F] transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['status', 'property', 'category']))
                        <a href="{{ route('landlord.concerns.index') }}"
                            class="h-11 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            Clear
                        </a>
                    @endif
                </div>
            </form>

            {{-- Concern cards --}}
            @if($concerns->isEmpty())
                <div class="{{ $card }} flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}" /></svg>
                    </div>
                    @if($counts['all'] === 0 && ! $propertyId && ! $category)
                        <p class="text-[15px] font-semibold text-[#060D26]">No concerns yet</p>
                        <p class="text-[13px] text-[#5B6A8E] mt-1 max-w-xs">When a tenant reports a problem from their chat, it shows up here, from report to fixed.</p>
                    @else
                        <p class="text-[15px] font-semibold text-[#060D26]">{{ $status === 'active' ? 'Nothing needs your attention' : 'Nothing here' }}</p>
                        <p class="text-[13px] text-[#5B6A8E] mt-1 max-w-xs">{{ $status === 'active' ? 'Every concern for this view is fixed.' : 'No concerns match these filters.' }}</p>
                        <a href="{{ route('landlord.concerns.index', ['status' => 'all']) }}" class="mt-5 inline-flex items-center h-10 px-5 rounded-xl border border-[#5B6A8E]/25 text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200">Show all concerns</a>
                    @endif
                </div>
            @else
                <div class="space-y-3">
                    @foreach($concerns as $concern)
                        @php
                            $running = $concern->conversation?->status === 'Open' && $concern->reservation?->rental_status === 'Occupied';
                            $tenantName = trim(($concern->tenant?->first_name ?? '').' '.($concern->tenant?->last_name ?? '')) ?: 'Tenant';
                            $propertyTitle = $concern->reservation?->property?->title;
                            $unitLabel = $concern->reservation?->unit?->unit_label;
                            $photos = $concern->photos ?? [];
                            $waitingDays = (int) $concern->created_at->diffInDays(now());
                            $stale = $concern->status === 'Open' && $waitingDays >= 3;
                        @endphp
                        <div x-data="{
                                status: @js($concern->status),
                                busy: false,
                                error: '',
                                chip: @js($chip),
                                async set(to) {
                                    this.busy = true; this.error = '';
                                    try {
                                        const res = await fetch(@js(route('concerns.status', $concern)), {
                                            method: 'PATCH',
                                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                            body: JSON.stringify({ status: to }),
                                        });
                                        if (!res.ok) throw new Error();
                                        this.status = to;
                                    } catch (e) { this.error = 'Could not update it. Try again.'; }
                                    this.busy = false;
                                },
                            }"
                            class="{{ $card }} p-5">

                            <div class="flex items-start justify-between gap-3 mb-2">
                                <span class="text-[13.5px] font-bold text-[#060D26]">{{ $concern->category }}</span>
                                <span class="inline-flex items-center gap-1.5 shrink-0 px-2.5 py-1 rounded-full text-[11px] font-semibold" :class="chip[status][0]">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="chip[status][1]"></span>
                                    <span x-text="status"></span>
                                </span>
                            </div>

                            <p class="text-[13.5px] leading-relaxed text-[#060D26] whitespace-pre-wrap break-words">{{ $concern->description }}</p>

                            @if($photos)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($photos as $photo)
                                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" aria-label="Open photo {{ $loop->iteration }}" class="h-16 w-16 overflow-hidden rounded-lg border border-[#E2E4EC] transition-colors duration-200 hover:border-[#FF8A66] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">
                                            <img src="{{ $photo['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-3 flex items-center gap-x-4 gap-y-1.5 text-[12px] text-[#5B6A8E] flex-wrap">
                                <span class="flex items-center gap-1.5">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                    {{ $tenantName }}
                                </span>
                                @if($propertyTitle)
                                    <span class="flex items-center gap-1.5">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                        {{ $propertyTitle }}@if($unitLabel) · {{ $unitLabel }}@endif
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                    {{ $concern->created_at->format('M d, Y') }}
                                    @if($stale)<span x-show="status === 'Open'" class="font-semibold text-[#B45309]">· waiting {{ $waitingDays }} days</span>@endif
                                    @if($concern->fixed_at)<span x-show="status === 'Fixed'" x-cloak>· fixed {{ $concern->fixed_at->format('M d') }}</span>@endif
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="mt-3.5 pt-3.5 border-t border-[#5B6A8E]/10 flex flex-wrap items-center gap-2">
                                <a href="{{ route('conversations.index', ['box' => 'tenancy', 'active' => $concern->conversation_id]) }}"
                                    class="inline-flex items-center gap-1.5 h-10 px-1 text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" /></svg>
                                    Open chat
                                </a>
                                <div class="ml-auto flex items-center gap-2">
                                    @if($running)
                                        <button type="button" x-show="status === 'Open'" x-cloak @click="set('In Progress')" :disabled="busy"
                                            class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 disabled:opacity-50 cursor-pointer">Start work</button>
                                        <button type="button" x-show="status !== 'Fixed'" @click="set('Fixed')" :disabled="busy"
                                            class="h-10 px-4 rounded-xl bg-[#FF8A66] text-[13px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-all duration-200 disabled:opacity-50 cursor-pointer">Mark fixed</button>
                                    @endif
                                </div>
                                <p x-show="error" x-cloak x-text="error" role="alert" class="basis-full text-[12px] font-semibold text-[#DC2626]"></p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-8">{{ $concerns->links() }}</div>
            @endif
        </div>

        {{-- Right column: where the open ones are (each line filters), then a note on what the tenant sees --}}
        <aside class="flex flex-col gap-4 lg:sticky lg:top-24">
            @if($byCategory->isNotEmpty())
                <div class="{{ $card }} p-5">
                    <h3 class="text-[13px] font-normal text-[#060D26] mb-3">Open by category</h3>
                    <ul class="-mx-2">
                        @foreach($byCategory as $name => $total)
                            @php $on = $category === $name; @endphp
                            <li>
                                <a href="{{ route('landlord.concerns.index', $query(['category' => $on ? null : $name])) }}" @if($on) aria-current="true" @endif
                                    class="flex min-h-10 items-center gap-3 rounded-lg px-2 text-[12.5px] text-[#060D26] transition-colors duration-150 hover:bg-[#F7F8FC] {{ $on ? 'bg-[#ECEEF6] font-semibold' : '' }}">
                                    <span class="min-w-0 flex-1 truncate">{{ $name }}</span>
                                    <span class="tabular-nums text-[#5B6A8E]">{{ $total }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($byProperty->isNotEmpty())
                <div class="{{ $card }} p-5">
                    <h3 class="text-[13px] font-normal text-[#060D26] mb-3">Open by property</h3>
                    <ul class="-mx-2">
                        @foreach($byProperty as $pid => $total)
                            @php $on = $propertyId === (int) $pid; @endphp
                            <li>
                                <a href="{{ route('landlord.concerns.index', $query(['property' => $on ? null : $pid])) }}" @if($on) aria-current="true" @endif
                                    class="flex min-h-10 items-center gap-3 rounded-lg px-2 text-[12.5px] text-[#060D26] transition-colors duration-150 hover:bg-[#F7F8FC] {{ $on ? 'bg-[#ECEEF6] font-semibold' : '' }}">
                                    <span class="min-w-0 flex-1 truncate">{{ $properties->firstWhere('property_id', (int) $pid)?->title ?? 'Property' }}</span>
                                    <span class="tabular-nums text-[#5B6A8E]">{{ $total }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="rounded-2xl bg-[#060D26] p-5">
                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center mb-3">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                </div>
                <p class="text-[12.5px] font-semibold text-white leading-snug">Tenants are kept in the loop</p>
                <p class="text-[11.5px] text-white/60 leading-relaxed mt-1.5">
                    When you start work or mark a concern fixed, the tenant gets a notification and a note in their chat.
                </p>
            </div>
        </aside>
    </div>
</div>
@endsection
