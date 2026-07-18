@extends('layouts.admin')

@section('page-title', 'Reviews')

@section('content')
<div class="max-w-[1400px]">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">Reviews</h1>
            <p class="text-[13.5px] text-[#64748B] mt-1">Moderate tenant reviews. Flag inappropriate or fake content.</p>
        </div>
        <span class="text-[13px] font-semibold text-[#64748B]">{{ number_format($counts['all']) }} total</span>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-6">
        @foreach([5, 4, 3, 2, 1] as $stars)
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-4 shadow-lg text-center">
                <div class="flex items-center justify-center gap-0.5 mb-1">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-3 h-3" fill="{{ $i <= $stars ? '#FBBF24' : '#E2E8F0' }}" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                    @endfor
                </div>
                <p class="text-[20px] font-extrabold text-[#1F2937]">{{ $counts[$stars] }}</p>
                <p class="text-[10px] text-[#64748B]">{{ $stars }}-star</p>
            </div>
        @endforeach
    </div>

    {{-- Visibility tabs --}}
    <div class="flex items-center gap-1 mb-5">
        @foreach(['all' => 'All', 'visible' => 'Visible', 'hidden' => 'Hidden'] as $key => $label)
            @php
                $isActive = $visibility === $key;
                $count = $counts[$key];
                $params = array_merge(request()->except(['visibility', 'page']), $key !== 'all' ? ['visibility' => $key] : []);
            @endphp
            <a href="{{ route('admin.reviews.index', $params) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[13px] font-semibold transition-colors
                    {{ $isActive ? 'bg-[#156F8C] text-white' : 'bg-white text-[#1F2937] border border-[#E2E8F0] hover:brightness-95' }}">
                {{ $label }}
                <span class="text-[11px] {{ $isActive ? 'text-white/70' : 'text-[#64748B]' }}">({{ $count }})</span>
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.reviews.index') }}"
        class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-4 mb-5 shadow-lg flex flex-col sm:flex-row gap-3">
        @if($visibility !== 'all')
            <input type="hidden" name="visibility" value="{{ $visibility }}">
        @endif
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="15" height="15" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                placeholder="Search by tenant, property, or review text…"
                class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
        </div>
        <select name="rating"
            class="h-10 text-[13.5px] rounded-xl border border-gray-200 bg-gray-50/50 px-3 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
            <option value="all" {{ $rating === 'all' ? 'selected' : '' }}>All ratings</option>
            @foreach([1,2,3,4,5] as $s)
                <option value="{{ $s }}" {{ $rating == $s ? 'selected' : '' }}>{{ $s }} star{{ $s > 1 ? 's' : '' }}</option>
            @endforeach
        </select>
        <button type="submit"
            class="h-10 px-5 text-[13.5px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-colors shadow-sm">
            Filter
        </button>
        @if($search || $rating !== 'all')
            <a href="{{ route('admin.reviews.index', $visibility !== 'all' ? ['visibility' => $visibility] : []) }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- Success flash --}}
    @if(session('success'))
        <div class="mb-5 px-4 py-3 bg-[#22C55E]/10 border border-[#22C55E]/30 text-[#1F2937] text-[13px] font-semibold rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- List --}}
    @if($reviews->isEmpty())
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No reviews found</p>
            <p class="text-[13px] text-[#64748B] mt-1">{{ $search ? 'Try adjusting your search.' : 'No reviews yet.' }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($reviews as $review)
                <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 flex flex-col sm:flex-row gap-4 sm:gap-5 {{ $review->is_hidden ? 'opacity-60' : '' }}"
                    x-data="{ confirmOpen: false }">

                    {{-- Rating block --}}
                    <div class="shrink-0 text-center w-14">
                        <p class="text-[28px] font-extrabold text-[#1F2937] leading-none">{{ $review->rating }}</p>
                        <div class="flex justify-center gap-0.5 mt-1">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-2.5 h-2.5" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E8F0' }}" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endfor
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                            <div>
                                <p class="text-[13.5px] font-bold text-[#1F2937]">
                                    {{ trim(($review->tenant->first_name ?? '') . ' ' . ($review->tenant->last_name ?? '')) ?: '—' }}
                                    <span class="text-[#64748B] font-normal">reviewed</span>
                                    {{ $review->property->title ?? '—' }}
                                </p>
                                <p class="text-[11.5px] text-[#64748B] mt-0.5">
                                    Landlord: {{ trim(($review->landlord->first_name ?? '') . ' ' . ($review->landlord->last_name ?? '')) ?: '—' }}
                                    · {{ $review->created_at->format('M d, Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($review->is_hidden)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#FBBF24]/15 text-[#1F2937] text-[11px] font-semibold rounded-full">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        Hidden
                                    </span>
                                @endif
                                <button @click="confirmOpen = true"
                                    class="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-semibold border rounded-xl transition-colors shrink-0
                                        {{ $review->is_hidden
                                            ? 'border-[#22C55E]/30 text-[#22C55E] hover:bg-[#22C55E]/5'
                                            : 'border-[#FBBF24]/50 text-[#1F2937] hover:bg-[#FBBF24]/5' }}">
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
                            <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $review->review_comment }}</p>
                        @else
                            <p class="text-[13px] text-[#64748B] italic">No comment.</p>
                        @endif

                        {{-- Landlord reply --}}
                        @if($review->landlord_reply)
                            <div class="mt-3 pl-4 border-l-2 border-[#2AA7A1]/30">
                                <p class="text-[11.5px] font-semibold text-[#156F8C] mb-0.5">Landlord reply</p>
                                <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $review->landlord_reply }}</p>
                                @if($review->landlord_replied_at)
                                    <p class="text-[11px] text-[#64748B] mt-1">{{ $review->landlord_replied_at->format('M d, Y') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Toggle confirm modal --}}
                    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-black/40" @click="confirmOpen = false"></div>
                        <div class="relative bg-white rounded-3xl shadow-xl max-w-sm w-full p-7 z-10">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-4
                                {{ $review->is_hidden ? 'bg-[#22C55E]/10 border border-[#22C55E]/20' : 'bg-[#FBBF24]/10 border border-[#FBBF24]/30' }}">
                                @if($review->is_hidden)
                                    <svg class="w-6 h-6 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @else
                                    <svg class="w-6 h-6 text-[#FBBF24]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                @endif
                            </div>
                            <h3 class="text-[16px] font-extrabold text-[#1F2937] text-center mb-1">
                                {{ $review->is_hidden ? 'Unhide Review?' : 'Hide Review?' }}
                            </h3>
                            <p class="text-[13px] text-[#64748B] text-center mb-6">
                                @if($review->is_hidden)
                                    This will make the {{ $review->rating }}-star review by
                                    <strong class="text-[#1F2937]">{{ $review->tenant->first_name ?? 'this tenant' }}</strong>
                                    visible again on the property listing.
                                @else
                                    This will hide the {{ $review->rating }}-star review by
                                    <strong class="text-[#1F2937]">{{ $review->tenant->first_name ?? 'this tenant' }}</strong>
                                    from the public listing. It can be unhidden later.
                                @endif
                            </p>
                            <div class="flex gap-3">
                                <button @click="confirmOpen = false"
                                    class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:brightness-95 transition-colors">
                                    Cancel
                                </button>
                                <form action="{{ route('admin.reviews.toggleHidden', $review) }}" method="POST" class="flex-1">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        class="w-full h-10 text-[13.5px] font-bold text-white rounded-xl hover:brightness-95 transition-colors
                                            {{ $review->is_hidden ? 'bg-[#22C55E]' : 'bg-[#FBBF24]' }}">
                                        {{ $review->is_hidden ? 'Unhide' : 'Hide' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        @if($reviews->hasPages())
            <div class="mt-5">{{ $reviews->links() }}</div>
        @endif
    @endif

</div>
@endsection