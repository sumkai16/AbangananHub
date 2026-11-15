@extends('layouts.landlord')

{{--
    Concerns: what tenants reported from their tenancy chat, in one list. Filter by status (tabs), property and category;
    Start work / Mark fixed change the status in place through the same route the chat card uses (concerns.status), so
    the tenant is told and the chat gets its system line. "Open chat" jumps to the conversation.
--}}
@section('content')
@php
    $icon = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z';
    $propertyOptions = ['' => 'All properties'] + $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->title])->all();
    $categoryOptions = ['' => 'All categories'] + array_combine(\App\Models\TenantConcern::CATEGORIES, \App\Models\TenantConcern::CATEGORIES);
    $query = fn (array $over) => array_filter(array_merge(['status' => $status === 'active' ? null : $status, 'property' => $propertyId, 'category' => $category], $over));
    $selectClass = 'h-10 w-full pl-3.5 pr-9 rounded-xl border border-[#E2E4EC] bg-white text-[13.5px] font-medium text-[#060D26] hover:border-[#5B6A8E]/40 transition-colors duration-200';
    $pill = [
        'Open' => 'bg-[#FBBF24]/20 text-[#7A4B00]',
        'In Progress' => 'bg-[#ECEEF6] text-[#060D26]',
        'Fixed' => 'bg-[#22C55E]/10 text-[#15803D]',
    ];
@endphp

<div class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-16">
    <x-page-header title="Concerns" subtitle="What your tenants reported from their chat. Work through them here." :icon-path="$icon" />

    {{-- Status tabs --}}
    <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-[#E2E4EC]" aria-label="Concern status">
        @foreach($filters as $key => $label)
            <a href="{{ route('landlord.concerns.index', $query(['status' => $key === 'active' ? null : $key])) }}" data-page-swap="nav" @if($status === $key) aria-current="page" @endif
                class="-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-[13.5px] font-semibold transition-colors duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 {{ $status === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">
                {{ $label }}
                <span class="text-[12px] tabular-nums {{ $status === $key ? 'text-[#060D26]' : 'text-[#94A3B8]' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Filters --}}
    <form method="GET" action="{{ route('landlord.concerns.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
        @if($status !== 'active')<input type="hidden" name="status" value="{{ $status }}">@endif
        <div>
            <label class="sr-only" for="concern-property">Property</label>
            <x-styled-select name="property" id="concern-property" autosubmit :options="$propertyOptions" :selected="(string) ($propertyId ?? '')" class="{{ $selectClass }}" />
        </div>
        <div>
            <label class="sr-only" for="concern-category">Category</label>
            <x-styled-select name="category" id="concern-category" autosubmit :options="$categoryOptions" :selected="(string) ($category ?? '')" class="{{ $selectClass }}" />
        </div>
    </form>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
        <div class="min-w-0">
    @if($concerns->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#E2E4EC] bg-white px-6 py-14 text-center">
            @if($counts['all'] === 0 && ! $propertyId && ! $category)
                <p class="text-[15px] font-semibold text-[#060D26]">No concerns yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-[#5B6A8E]">When a tenant reports a problem from their chat, it shows up here.</p>
            @else
                <p class="text-[15px] font-semibold text-[#060D26]">{{ $status === 'active' ? 'Nothing needs your attention' : 'Nothing here' }}</p>
                <p class="mx-auto mt-1 max-w-sm text-[13px] text-[#5B6A8E]">{{ $status === 'active' ? 'Every concern for this view is fixed.' : 'No concerns match this view.' }}</p>
                <a href="{{ route('landlord.concerns.index', ['status' => 'all']) }}" data-page-swap="nav" class="mt-4 inline-flex h-10 items-center rounded-full border border-[#060D26]/35 bg-white px-5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6]">Show all concerns</a>
            @endif
        </div>
    @else
        <ul class="grid grid-cols-1 gap-4 2xl:grid-cols-2">
            @foreach($concerns as $concern)
                @php
                    $running = $concern->conversation?->status === 'Open' && $concern->reservation?->rental_status === 'Occupied';
                    $tenantName = trim(($concern->tenant?->first_name ?? '').' '.($concern->tenant?->last_name ?? '')) ?: 'Tenant';
                    $initial = mb_strtoupper(mb_substr($tenantName, 0, 1));
                    $where = trim(($concern->reservation?->property?->title ?? '').' · '.($concern->reservation?->unit?->unit_label ?? ''), ' ·');
                    $photos = $concern->photos ?? [];
                    // Open for 3+ days: say how long, so the oldest ones stand out.
                    $waitingDays = (int) $concern->created_at->diffInDays(now());
                    $stale = $concern->status === 'Open' && $waitingDays >= 3;
                @endphp
                <li x-data="{
                        status: @js($concern->status),
                        busy: false,
                        error: '',
                        pill: @js($pill),
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
                    class="flex flex-col overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white transition-shadow duration-200 hover:shadow-[0_4px_16px_rgba(6,13,38,0.06)]">

                    {{-- Who --}}
                    <div class="flex items-center gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#060D26] text-[14px] font-semibold text-white" aria-hidden="true">{{ $initial }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14.5px] font-semibold text-[#060D26]">{{ $tenantName }}</p>
                            @if($where)<p class="truncate text-[12.5px] text-[#5B6A8E]">{{ $where }}</p>@endif
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[12px] font-semibold" :class="pill[status]" x-text="status"></span>
                    </div>

                    {{-- What --}}
                    <div class="flex-1 px-4 pt-4 sm:px-5">
                        <p class="text-[12.5px] text-[#5B6A8E]">
                            <span class="font-semibold text-[#060D26]">{{ $concern->category }}</span>
                            · {{ $concern->created_at->diffForHumans() }}
                            @if($stale)<span x-show="status === 'Open'" class="font-semibold text-[#B45309]"> · waiting {{ $waitingDays }} days</span>@endif
                            @if($concern->fixed_at)<span x-show="status === 'Fixed'" x-cloak> · fixed {{ $concern->fixed_at->format('M j') }}</span>@endif
                        </p>
                        <p class="mt-1.5 text-[14.5px] leading-relaxed text-[#060D26] whitespace-pre-wrap break-words">{{ $concern->description }}</p>

                        @if($photos)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($photos as $photo)
                                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" aria-label="Open photo {{ $loop->iteration }}" class="h-[72px] w-[72px] overflow-hidden rounded-xl border border-[#E2E4EC] transition-colors duration-200 hover:border-[#FF8A66] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">
                                        <img src="{{ $photo['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Do something about it --}}
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-3 sm:px-5">
                        <a href="{{ route('conversations.index', ['box' => 'tenancy', 'active' => $concern->conversation_id]) }}"
                            class="inline-flex h-10 items-center gap-1.5 text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" /></svg>
                            Open chat
                        </a>
                        <div class="ml-auto flex items-center gap-2">
                            @if($running)
                                <button type="button" x-show="status === 'Open'" x-cloak @click="set('In Progress')" :disabled="busy"
                                    class="h-10 rounded-full border border-[#060D26]/35 bg-white px-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 disabled:opacity-50 cursor-pointer">Start work</button>
                                <button type="button" x-show="status !== 'Fixed'" @click="set('Fixed')" :disabled="busy"
                                    class="h-10 rounded-full bg-[#FF8A66] px-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 disabled:opacity-50 cursor-pointer">Mark fixed</button>
                                <span x-show="status === 'Fixed'" x-cloak class="inline-flex h-10 items-center gap-1.5 text-[13px] font-semibold text-[#15803D]">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    Fixed
                                </span>
                            @endif
                        </div>
                        <p x-show="error" x-cloak x-text="error" role="alert" class="basis-full text-[12.5px] font-semibold text-[#DC2626]"></p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $concerns->links() }}</div>
    @endif
        </div>

        {{-- Where the open ones are. Each line filters the list; the active one shows again to clear it. --}}
        @if($byCategory->isNotEmpty() || $byProperty->isNotEmpty())
        <aside class="grid gap-5 xl:sticky xl:top-6" aria-label="Where the open concerns are">
            @if($byCategory->isNotEmpty())
                <section class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Open by category</h2>
                    <ul class="mt-2 -mx-2">
                        @foreach($byCategory as $name => $total)
                            @php $on = $category === $name; @endphp
                            <li>
                                <a href="{{ route('landlord.concerns.index', $query(['category' => $on ? null : $name])) }}" data-page-swap="nav" @if($on) aria-current="true" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-2 text-[13.5px] transition-colors duration-150 hover:bg-[#F7F8FC] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 {{ $on ? 'bg-[#ECEEF6] font-semibold' : 'font-medium' }} text-[#060D26]">
                                    <span class="min-w-0 flex-1 truncate">{{ $name }}</span>
                                    <span class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $total }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
            @if($byProperty->isNotEmpty())
                <section class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Open by property</h2>
                    <ul class="mt-2 -mx-2">
                        @foreach($byProperty as $pid => $total)
                            @php $on = $propertyId === (int) $pid; @endphp
                            <li>
                                <a href="{{ route('landlord.concerns.index', $query(['property' => $on ? null : $pid])) }}" data-page-swap="nav" @if($on) aria-current="true" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-2 text-[13.5px] transition-colors duration-150 hover:bg-[#F7F8FC] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 {{ $on ? 'bg-[#ECEEF6] font-semibold' : 'font-medium' }} text-[#060D26]">
                                    <span class="min-w-0 flex-1 truncate">{{ $properties->firstWhere('property_id', (int) $pid)?->title ?? 'Property' }}</span>
                                    <span class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ $total }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
        @endif
    </div>
</div>
@endsection
