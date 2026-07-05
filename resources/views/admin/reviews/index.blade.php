@extends('layouts.admin')

@section('page-title', 'Reviews')

@section('content')
<div class="max-w-[1400px]">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Reviews</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">Moderate tenant reviews. Remove fake or inappropriate content.</p>
        </div>
        <span class="text-[13px] font-semibold text-gray-400">{{ number_format($counts['all']) }} total</span>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-5 gap-3 mb-6">
        @foreach([5 => 'emerald', 4 => 'blue', 3 => 'amber', 2 => 'orange', 1 => 'red'] as $stars => $color)
            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm text-center">
                <div class="flex items-center justify-center gap-0.5 mb-1">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-3 h-3 {{ $i <= $stars ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                    @endfor
                </div>
                <p class="text-[20px] font-extrabold text-[#1A1A2E]">{{ $counts[$stars] }}</p>
                <p class="text-[10px] text-gray-400">{{ $stars }}-star</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.reviews.index') }}"
        class="bg-white border border-gray-100 rounded-2xl p-4 mb-5 shadow-sm flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" width="15" height="15" fill="none"
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
            <a href="{{ route('admin.reviews.index') }}"
                class="h-10 px-4 text-[13.5px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:text-[#1A1A2E] transition-colors flex items-center">
                Clear
            </a>
        @endif
    </form>

    {{-- List --}}
    @if($reviews->isEmpty())
        <div class="bg-white border border-gray-100 rounded-3xl p-16 text-center shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1A1A2E]">No reviews found</p>
            <p class="text-[13px] text-gray-400 mt-1">{{ $search ? 'Try adjusting your search.' : 'No reviews yet.' }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($reviews as $review)
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 flex gap-5" x-data="{ deleteOpen: false }">

                    {{-- Rating block --}}
                    <div class="shrink-0 text-center w-14">
                        <p class="text-[28px] font-extrabold text-[#1A1A2E] leading-none">{{ $review->rating }}</p>
                        <div class="flex justify-center gap-0.5 mt-1">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-2.5 h-2.5 {{ $i <= $review->rating ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endfor
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                            <div>
                                <p class="text-[13.5px] font-bold text-[#1A1A2E]">
                                    {{ trim(($review->tenant->first_name ?? '') . ' ' . ($review->tenant->last_name ?? '')) ?: '—' }}
                                    <span class="text-gray-400 font-normal">reviewed</span>
                                    {{ $review->property->title ?? '—' }}
                                </p>
                                <p class="text-[11.5px] text-gray-400 mt-0.5">
                                    Landlord: {{ trim(($review->landlord->first_name ?? '') . ' ' . ($review->landlord->last_name ?? '')) ?: '—' }}
                                    · {{ $review->created_at->format('M d, Y') }}
                                </p>
                            </div>
                            <button @click="deleteOpen = true"
                                class="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-semibold border border-red-200 text-red-500 rounded-xl hover:bg-red-50 transition-colors shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                Remove
                            </button>
                        </div>
                        @if($review->review_comment)
                            <p class="text-[13px] text-gray-600 leading-relaxed">{{ $review->review_comment }}</p>
                        @else
                            <p class="text-[13px] text-gray-300 italic">No comment.</p>
                        @endif
                    </div>

                    {{-- Delete modal --}}
                    <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false"></div>
                        <div class="relative bg-white rounded-3xl shadow-xl max-w-sm w-full p-7 z-10">
                            <div class="w-12 h-12 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                                </svg>
                            </div>
                            <h3 class="text-[16px] font-extrabold text-[#1A1A2E] text-center mb-1">Remove Review?</h3>
                            <p class="text-[13px] text-gray-400 text-center mb-6">
                                This will permanently delete this {{ $review->rating }}-star review by
                                <strong class="text-[#1A1A2E]">{{ $review->tenant->first_name ?? 'this tenant' }}</strong>.
                                This cannot be undone.
                            </p>
                            <div class="flex gap-3">
                                <button @click="deleteOpen = false"
                                    class="flex-1 h-10 text-[13.5px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="flex-1">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="w-full h-10 text-[13.5px] font-bold bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors">
                                        Remove
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
