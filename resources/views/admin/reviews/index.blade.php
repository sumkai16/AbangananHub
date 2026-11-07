@extends('layouts.admin')

@section('page-title', 'Reviews')

@section('content')
@php
    $ratingOptions = ['all' => 'All ratings'];
    foreach ([5, 4, 3, 2, 1] as $s) {
        $ratingOptions[(string) $s] = $s . ' star' . ($s > 1 ? 's' : '');
    }
    $visibilityOptions = [
        'all' => 'All reviews ('.$counts['all'].')',
        'visible' => 'Visible ('.$counts['visible'].')',
        'hidden' => 'Hidden ('.$counts['hidden'].')',
    ];
    $sortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'highest' => 'Highest rated', 'lowest' => 'Lowest rated'];

    $keep = ['search' => $search, 'rating' => $rating !== 'all' ? $rating : null, 'sort' => $sort !== 'newest' ? $sort : null];
    $cellHref = fn (array $params) => route('admin.reviews.index', array_filter(array_merge($keep, $params)));
    $summary = [
        ['label' => 'Total reviews', 'value' => number_format($counts['all']), 'note' => $counts['average'] ? number_format($counts['average'], 1).' average rating' : 'No ratings yet', 'href' => $cellHref([]), 'active' => $visibility === 'all'],
        ['label' => 'Visible', 'value' => number_format($counts['visible']), 'note' => 'Shown on listings', 'dot' => '#22C55E', 'href' => $visibility === 'visible' ? $cellHref([]) : $cellHref(['visibility' => 'visible']), 'active' => $visibility === 'visible'],
        ['label' => 'Hidden', 'value' => number_format($counts['hidden']), 'note' => $counts['hidden'] > 0 ? 'Hidden by an admin' : 'Nothing hidden', 'dot' => '#FBBF24', 'href' => $visibility === 'hidden' ? $cellHref([]) : $cellHref(['visibility' => 'hidden']), 'active' => $visibility === 'hidden'],
        ['label' => 'Low rated', 'value' => number_format($counts['low']), 'note' => '1 or 2 stars', 'dot' => '#EF4444'],
    ];
@endphp

<div class="max-w-[1600px] mx-auto" x-data="{
        view: (() => { try { return localStorage.getItem('adminReviewsView') || 'table'; } catch (e) { return 'table'; } })(),
        setView(v) { this.view = v; try { localStorage.setItem('adminReviewsView', v); } catch (e) {} }
    }">

    <x-page-header icon-path="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" title="Reviews" subtitle="Tenant reviews. Hide anything inappropriate or fake." />

    <x-stat-strip :cells="$summary" class="mb-4" />

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by tenant, property, or review text…" aria-label="Search reviews"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select autosubmit name="visibility" :options="$visibilityOptions" :selected="$visibility"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="rating" :options="$ratingOptions" :selected="(string) $rating"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                <x-styled-select autosubmit name="sort" :options="$sortOptions" :selected="$sort"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                @if($search || $rating !== 'all' || $visibility !== 'all' || $sort !== 'newest')
                    <a href="{{ route('admin.reviews.index') }}"
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
    @if($reviews->isEmpty())
        <x-card flush><x-admin.empty title="No reviews found" :note="($search || $rating !== 'all' || $visibility !== 'all') ? 'Try a different search or filter.' : null" /></x-card>
    @else
        <div :class="view === 'grid' ? 'grid grid-cols-1 gap-3 lg:grid-cols-2 2xl:grid-cols-3' : 'space-y-3'">
            @foreach($reviews as $review)
                <x-card class="flex flex-col sm:flex-row gap-4 sm:gap-5 {{ $review->is_hidden ? 'opacity-60' : '' }}"
                    x-bind:class="view === 'grid' ? 'sm:!flex-col' : ''"
                    x-data="{ confirmOpen: false }">

                    {{-- Rating block --}}
                    <div class="shrink-0 text-center w-14" :class="view === 'grid' ? 'flex items-center gap-2 w-auto text-left' : ''">
                        <p class="text-[28px] font-extrabold text-[#060D26] leading-none">{{ $review->rating }}</p>
                        <div class="flex justify-center gap-0.5 mt-1">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-2.5 h-2.5" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E4EC' }}" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endfor
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                            <div>
                                <p class="text-[13.5px] font-bold text-[#060D26]">
                                    {{ trim(($review->tenant->first_name ?? '') . ' ' . ($review->tenant->last_name ?? '')) ?: '—' }}
                                    <span class="text-[#5B6A8E] font-normal">reviewed</span>
                                    {{ $review->property->title ?? '—' }}
                                </p>
                                <p class="text-[11.5px] text-[#5B6A8E] mt-0.5">
                                    Landlord: {{ trim(($review->landlord->first_name ?? '') . ' ' . ($review->landlord->last_name ?? '')) ?: '—' }}
                                    · {{ $review->created_at->format('M d, Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($review->is_hidden)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#FBBF24]/15 text-[#060D26] text-[11px] font-semibold rounded-md">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        Hidden
                                    </span>
                                @endif
                                <button @click="confirmOpen = true"
                                    class="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-semibold border rounded-xl transition-colors shrink-0
                                        {{ $review->is_hidden
                                            ? 'border-[#22C55E]/30 text-[#15803D] hover:bg-[#22C55E]/5'
                                            : 'border-[#FBBF24]/50 text-[#060D26] hover:bg-[#FBBF24]/5' }}">
                                    @if($review->is_hidden)
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Unhide
                                    @else
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        Hide
                                    @endif
                                </button>
                            </div>
                        </div>

                        @if($review->review_comment)
                            <p class="text-[13px] text-[#060D26] leading-relaxed">{{ $review->review_comment }}</p>
                        @else
                            <p class="text-[13px] text-[#5B6A8E] italic">No comment.</p>
                        @endif

                        {{-- Landlord reply --}}
                        @if($review->landlord_reply)
                            <div class="mt-3 pl-4 border-l-2 border-[#FF8A66]/30">
                                <p class="text-[11.5px] font-semibold text-[#060D26] mb-0.5">Landlord reply</p>
                                <p class="text-[13px] text-[#060D26] leading-relaxed">{{ $review->landlord_reply }}</p>
                                @if($review->landlord_replied_at)
                                    <p class="text-[11px] text-[#5B6A8E] mt-1">{{ $review->landlord_replied_at->format('M d, Y') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Toggle confirm modal --}}
                    <template x-teleport="body">
                    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-black/40" @click="confirmOpen = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-7 z-10">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-4
                                {{ $review->is_hidden ? 'bg-[#22C55E]/10 border border-[#22C55E]/20' : 'bg-[#FBBF24]/10 border border-[#FBBF24]/30' }}">
                                @if($review->is_hidden)
                                    <svg class="w-6 h-6 text-[#15803D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @else
                                    <svg class="w-6 h-6 text-[#FBBF24]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                @endif
                            </div>
                            <h3 class="text-[16px] font-semibold text-[#060D26] text-center mb-1">
                                {{ $review->is_hidden ? 'Unhide Review?' : 'Hide Review?' }}
                            </h3>
                            <p class="text-[13px] text-[#5B6A8E] text-center mb-6">
                                @if($review->is_hidden)
                                    This will make the {{ $review->rating }}-star review by
                                    <strong class="text-[#060D26]">{{ $review->tenant->first_name ?? 'this tenant' }}</strong>
                                    visible again on the property listing.
                                @else
                                    This will hide the {{ $review->rating }}-star review by
                                    <strong class="text-[#060D26]">{{ $review->tenant->first_name ?? 'this tenant' }}</strong>
                                    from the public listing. It can be unhidden later.
                                @endif
                            </p>
                            <div class="flex gap-3">
                                <button @click="confirmOpen = false"
                                    class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:brightness-95 transition-colors">
                                    Cancel
                                </button>
                                <form action="{{ route('admin.reviews.toggleHidden', $review) }}" method="POST" class="flex-1">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        class="w-full h-10 text-[13.5px] font-bold text-[#060D26] rounded-xl hover:brightness-95 transition-colors
                                            {{ $review->is_hidden ? 'bg-[#15803D]' : 'bg-[#FBBF24]' }}">
                                        {{ $review->is_hidden ? 'Unhide' : 'Hide' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    </template>

                </x-card>
            @endforeach
        </div>

        @if($reviews->hasPages())
            <div class="mt-5">{{ $reviews->links() }}</div>
        @endif
    @endif

</div>
@endsection