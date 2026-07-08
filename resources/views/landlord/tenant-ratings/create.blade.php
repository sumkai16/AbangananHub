@extends('layouts.landlord')

@section('content')
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-[50px] py-8">

        {{-- Back link --}}
        <a href="{{ route('landlord.reservations.index', ['status' => 'Occupied']) }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-[#64748B] hover:text-[#1F2937] mb-6">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Reservations
        </a>

        <h1 class="text-2xl font-bold text-[#1F2937] mb-1">Rate Tenant</h1>
        <p class="text-sm text-[#64748B] mb-8">Share your experience with this tenant to help other landlords.</p>

        {{-- Tenant & reservation context card --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 mb-6">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-full bg-[#2AA7A1] text-white text-lg font-bold flex items-center justify-center shrink-0">
                    {{ strtoupper(substr($reservation->tenant->first_name ?? '?', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-base font-semibold text-[#1F2937]">
                        {{ $reservation->tenant->first_name }} {{ $reservation->tenant->last_name }}
                    </p>
                    <p class="text-[13px] text-[#64748B]">
                        {{ $reservation->property->title }} — {{ $reservation->unit->unit_label ?? 'No unit' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Rating form --}}
        <form action="{{ route('landlord.reservations.rateTenant.store', $reservation) }}" method="POST"
            class="bg-white border border-[#E2E8F0] rounded-2xl p-6" x-data="{ rating: 0, hovered: 0 }">
            @csrf

            {{-- Star picker --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-[#1F2937] mb-3">Rating</label>
                <div class="flex items-center gap-1.5">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" @click="rating = {{ $i }}" @mouseenter="hovered = {{ $i }}"
                            @mouseleave="hovered = 0" class="focus:outline-none transition-transform duration-100"
                            :class="{ 'scale-110': hovered === {{ $i }} }">
                            <svg width="32" height="32" viewBox="0 0 24 24"
                                :fill="(hovered || rating) >= {{ $i }} ? '#FBBF24' : 'none'"
                                :stroke="(hovered || rating) >= {{ $i }} ? '#FBBF24' : '#E2E8F0'" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-sm text-[#64748B]" x-show="rating > 0" x-cloak>
                        <span x-text="rating"></span>/5
                    </span>
                </div>
                <input type="hidden" name="rating" :value="rating">
                @error('rating')
                    <p class="text-[12px] text-[#EF4444] mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Comment --}}
            <div class="mb-6">
                <label for="comment" class="block text-sm font-semibold text-[#1F2937] mb-2">Comment <span
                        class="font-normal text-[#64748B]">(optional)</span></label>
                <textarea name="comment" id="comment" rows="4" maxlength="1000"
                    class="w-full border border-[#E2E8F0] rounded-xl px-4 py-3 text-sm text-[#1F2937] placeholder-[#64748B]/50 focus:ring-2 focus:ring-[#2AA7A1]/30 focus:border-[#2AA7A1] transition-colors duration-150 resize-none"
                    placeholder="How was your experience with this tenant?" x-data="{ count: 0 }"
                    x-on:input="count = $el.value.length">{{ old('comment') }}</textarea>
                <p class="text-[11px] text-[#64748B] mt-1 text-right"><span x-text="count || 0"></span>/1000</p>
                @error('comment')
                    <p class="text-[12px] text-[#EF4444] mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit --}}
            <div class="flex items-center gap-3">
                <button type="submit" x-bind:disabled="rating === 0"
                    class="px-6 py-2.5 bg-[#FF8A65] text-white text-sm font-semibold rounded-xl hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed">
                    Submit Rating
                </button>
                <a href="{{ route('landlord.reservations.index', ['status' => 'Occupied']) }}"
                    class="px-5 py-2.5 text-sm font-medium text-[#64748B] hover:text-[#1F2937] transition-colors duration-150">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection