@extends('layouts.landlord')

@section('page-title', 'Reviews')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <div class="flex items-center gap-3.5 mb-5">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Reviews</h1>
                <p class="text-sm text-[#64748B] mt-0.5">What tenants are saying about your properties.</p>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Reviews</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $stats['total'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Across all properties</p>
            </div>

            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Average Rating</span>
                    <div class="w-8 h-8 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#F59E0B">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ number_format($stats['avg'], 1) }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Out of 5.0 stars</p>
            </div>

            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Awaiting Reply</span>
                    <div class="w-8 h-8 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#B45309]">{{ $stats['unreplied'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Needs your response</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 items-start">
            <div class="min-w-0">
                {{-- Filter bar --}}
                <form method="GET" action="{{ route('landlord.reviews.index') }}"
                    class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4 mb-6">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative">
                            <select name="property"
                                class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer max-w-[200px]">
                                <option value="">All Properties</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->property_id }}" @selected(request('property') == $property->property_id)>
                                        {{ $property->title }}
                                    </option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>

                        <div class="relative">
                            <select name="rating"
                                class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                                <option value="">All Ratings</option>
                                @for($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} {{ Str::plural('star', $i) }}</option>
                                @endfor
                            </select>
                            <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>

                        <div class="relative">
                            <select name="status"
                                class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                                <option value="">All Statuses</option>
                                <option value="replied" @selected(request('status') === 'replied')>Replied</option>
                                <option value="unreplied" @selected(request('status') === 'unreplied')>Unreplied</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>

                        <button type="submit"
                            class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            Filter
                        </button>

                        @if(request()->hasAny(['property', 'rating', 'status']))
                            <a href="{{ route('landlord.reviews.index') }}"
                                class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Clear
                            </a>
                        @endif
                    </div>
                </form>

                {{-- Review cards --}}
                @if($reviews->isEmpty())
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] flex flex-col items-center justify-center py-16 px-6 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <p class="text-[15px] font-semibold text-[#1F2937]">No reviews yet</p>
                        <p class="text-[13px] text-[#64748B] mt-1 max-w-xs">Once tenants complete a stay, their reviews and ratings for your properties will show up here.</p>
                        <a href="{{ route('landlord.properties.index') }}"
                            class="mt-5 inline-flex items-center gap-1.5 h-10 px-5 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 transition-all duration-200">
                            View your properties
                        </a>
                    </div>
                @else
                    <div class="space-y-4" x-data="{ replyOpenId: null }">
                        @foreach($reviews as $review)
                            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                                {{-- Top row --}}
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[13px] font-bold text-[#156F8C] shrink-0">
                                            {{ strtoupper(substr($review->tenant->first_name ?? '', 0, 1) . substr($review->tenant->last_name ?? '', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-[13.5px] font-bold text-[#1F2937]">
                                                {{ $review->tenant->first_name }} {{ $review->tenant->last_name }}
                                            </p>
                                            <p class="text-[12px] text-[#64748B] flex items-center gap-1">
                                                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                                </svg>
                                                {{ $review->property->title ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-0.5">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-[#B45309]' : 'text-[#E2E8F0]' }}"
                                                    viewBox="0 0 24 24" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            @endfor
                                        </div>
                                        <span class="text-[12px] text-[#64748B]">{{ $review->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>

                                {{-- Comment --}}
                                <p class="text-[13.5px] text-[#1F2937] mt-3 leading-relaxed">{{ $review->review_comment }}</p>

                                {{-- Badge row --}}
                                <div class="flex items-center justify-between mt-3">
                                    @if($review->landlord_reply)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#22C55E]/[0.07] text-[#15803D] text-[11px] font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
                                            Replied
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#FBBF24]/[0.10] text-[#B45309] text-[11px] font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#FBBF24]"></span>
                                            Awaiting reply
                                        </span>
                                        <button type="button"
                                            @click="replyOpenId = replyOpenId === {{ $review->review_id }} ? null : {{ $review->review_id }}"
                                            class="text-[12.5px] font-semibold text-[#2AA7A1] hover:text-[#156F8C] transition-colors duration-150">
                                            <span x-text="replyOpenId === {{ $review->review_id }} ? 'Cancel' : 'Reply'"></span>
                                        </button>
                                    @endif
                                </div>

                                {{-- Inline reply form --}}
                                @if(!$review->landlord_reply)
                                    <div x-show="replyOpenId === {{ $review->review_id }}" x-cloak x-transition
                                        class="mt-3 pt-3 border-t border-[#64748B]/10">
                                        <form method="POST" action="{{ route('landlord.reviews.reply', $review) }}" class="space-y-2.5">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="landlord_reply" rows="2" maxlength="1000" required
                                                placeholder="Write a public reply to this review..."
                                                class="w-full rounded-xl border border-[#64748B]/25 px-3.5 py-2.5 text-[13px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition"></textarea>
                                            <button type="submit"
                                                class="h-9 px-4 rounded-full bg-[#2AA7A1] text-white text-[12.5px] font-semibold hover:brightness-95 transition-all duration-200">
                                                Submit Reply
                                            </button>
                                        </form>
                                    </div>
                                @endif

                                {{-- Landlord reply --}}
                                @if($review->landlord_reply)
                                    <div class="mt-3 pt-3 border-t border-[#64748B]/10 pl-3 border-l-2 border-l-[#2AA7A1]">
                                        <p class="text-[11px] font-semibold text-[#156F8C] uppercase tracking-wide">Your reply</p>
                                        <p class="text-[12.5px] text-[#64748B] mt-0.5 leading-relaxed">{{ $review->landlord_reply }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $reviews->links() }}
                    </div>
                @endif
            </div>

            {{-- Sidebar: reputation tips --}}
            <aside class="flex flex-col gap-4 lg:sticky lg:top-24">
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h3 class="text-[13px] font-bold text-[#1F2937] mb-3.5">Build your reputation</h3>
                    <ul class="flex flex-col gap-3">
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#F59E0B"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">Reply to every review — a thoughtful response shows future tenants you're engaged</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#F59E0B"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">Keep responses professional, even for critical feedback</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0 mt-0.5">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#F59E0B"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            </span>
                            <span class="text-[12.5px] text-[#64748B] leading-relaxed">A strong rating increases visibility in property search</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-[#1F2937] p-5">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center mb-3">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="#F59E0B"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    </div>
                    <p class="text-[12.5px] font-semibold text-white leading-snug">Replies are public</p>
                    <p class="text-[11.5px] text-white/60 leading-relaxed mt-1.5">
                        Your reply appears directly beneath the tenant's review on your listing.
                    </p>
                </div>
            </aside>
        </div>
    </div>
@endsection
