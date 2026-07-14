@extends('layouts.landlord')

@section('page-title', 'Reviews')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-[20px] font-bold text-[#156F8C]">Reviews</h1>
            <p class="text-[13px] text-[#64748B] mt-1">What tenants are saying about your properties.</p>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <p class="text-[12px] text-[#64748B]">Total reviews</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <p class="text-[12px] text-[#64748B]">Average rating</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ number_format($stats['avg'], 1) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <p class="text-[12px] text-[#64748B]">Awaiting reply</p>
                <p class="text-[22px] font-bold text-[#1F2937]">{{ $stats['unreplied'] }}</p>
            </div>
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('landlord.reviews.index') }}"
            class="flex flex-col sm:flex-row sm:items-center gap-2 mb-6">
            <select name="property"
                class="w-full sm:w-56 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
                <option value="">All properties</option>
                @foreach($properties as $property)
                    <option value="{{ $property->property_id }}" @selected(request('property') == $property->property_id)>
                        {{ $property->title }}
                    </option>
                @endforeach
            </select>

            <select name="rating"
                class="w-full sm:w-40 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
                <option value="">All ratings</option>
                @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} {{ Str::plural('star', $i) }}</option>
                @endfor
            </select>

            <select name="status"
                class="w-full sm:w-40 pl-4 pr-8 py-2.5 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:border-[#2AA7A1]">
                <option value="">All statuses</option>
                <option value="replied" @selected(request('status') === 'replied')>Replied</option>
                <option value="unreplied" @selected(request('status') === 'unreplied')>Unreplied</option>
            </select>

            <button type="submit"
                class="bg-[#156F8C] text-white rounded-xl px-4 py-2.5 text-[13px] font-semibold hover:brightness-95 transition">
                Filter
            </button>
        </form>

        {{-- Review cards --}}
        @if($reviews->isEmpty())
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-12 flex flex-col items-center text-center">
                <svg class="w-10 h-10 text-[#64748B] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                </svg>
                <p class="text-[14px] font-semibold text-[#1F2937]">No reviews yet</p>
                <p class="text-[13px] text-[#64748B] mt-1">Reviews from tenants will appear here.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($reviews as $review)
                    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5">
                        {{-- Top row --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                    {{ strtoupper(substr($review->tenant->first_name ?? '', 0, 1) . substr($review->tenant->last_name ?? '', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-[13px] font-semibold text-[#1F2937]">
                                        {{ $review->tenant->first_name }} {{ $review->tenant->last_name }}
                                    </p>
                                    <p class="text-[12px] text-[#64748B]">{{ $review->property->title ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-[#FBBF24]' : 'text-[#E2E8F0]' }}"
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
                        <p class="text-[13px] text-[#1F2937] mt-3">{{ $review->comment }}</p>

                        {{-- Badge row --}}
                        <div class="flex items-center justify-between mt-3">
                            @if($review->landlord_reply)
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-[#F0FDF4] text-[#166534] text-[11px] font-semibold">
                                    Replied
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-full bg-[#FFFBEB] text-[#92400E] text-[11px] font-semibold">
                                    Awaiting reply
                                </span>
                                @if($review->property)
                                    <a href="{{ route('properties.show', $review->property->property_id) }}"
                                        class="text-[13px] text-[#156F8C] font-semibold hover:brightness-95 transition">
                                        Reply
                                    </a>
                                @endif
                            @endif
                        </div>

                        {{-- Landlord reply --}}
                        @if($review->landlord_reply)
                            <p class="text-[12px] text-[#64748B] italic border-t border-[#E2E8F0] pt-3 mt-3">
                                Your reply: {{ $review->landlord_reply }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
@endsection
